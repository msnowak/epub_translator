<?php

declare(strict_types=1);

namespace App\Preview;

/**
 * Turns a composed chapter into something an iframe can display: blocks get an
 * id the editor can address, asset paths get rewritten and signed, and
 * anything executable is removed. The book can come from anywhere and the JWT
 * lives in the same browser tab, so this is a real trust boundary: nothing
 * here may rely on a sandbox attribute a frontend has not written yet.
 */
final readonly class PreviewDecorator
{
    private const array URL_ATTRIBUTES = ['src', 'href', 'poster'];

    /**
     * Elements that can load and run something of their own. A script is the
     * obvious one; an iframe, object or embed pulls in a document just as
     * effectively, and all of them would be served from our own origin.
     */
    private const array EXECUTABLE_TAGS = ['script', 'iframe', 'object', 'embed'];

    public function __construct(
        private AssetUrlSigner $signer,
    ) {
    }

    /**
     * @param list<\DOMElement>  $blocks                blocks in the very order that assigned nodeIndex
     * @param array<int, string> $segmentIdsByNodeIndex
     */
    public function decorate(
        \DOMDocument $document,
        array $blocks,
        string $projectId,
        string $chapterHref,
        array $segmentIdsByNodeIndex,
    ): void {
        $this->markBlocks($blocks, $segmentIdsByNodeIndex);
        $this->removeScripts($document);
        $this->detachLinks($document);
        $this->rewriteUrls($document, $projectId, $chapterHref);
    }

    /**
     * @param list<\DOMElement>  $blocks
     * @param array<int, string> $segmentIdsByNodeIndex
     */
    private function markBlocks(array $blocks, array $segmentIdsByNodeIndex): void
    {
        // Bloki przychodza z zewnatrz, z tego samego przejscia, ktore
        // skladalo tlumaczenia. Drugie przejscie po zmienionym dokumencie
        // moglo pominac blok, ktory po zlozeniu nie ma juz tekstu, i przesunac
        // wszystkie kolejne identyfikatory o jeden.
        foreach ($blocks as $nodeIndex => $element) {
            $segmentId = $segmentIdsByNodeIndex[$nodeIndex] ?? null;

            if (null === $segmentId) {
                continue;
            }

            $element->setAttribute('data-segment-id', $segmentId);
        }
    }

    private function removeScripts(\DOMDocument $document): void
    {
        foreach ($this->executableElements($document) as $element) {
            $element->parentNode?->removeChild($element);
        }

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            if ($this->isMetaRefresh($element)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                // localName, a nie name: dla "xlink:href" oba zwracaja "href",
                // ale usuwac trzeba przez wezel atrybutu - removeAttribute()
                // szuka nazwy kwalifikowanej i po cichu nie robi nic.
                $name = strtolower((string) $attribute->localName);

                if (str_starts_with($name, 'on')) {
                    $element->removeAttributeNode($attribute);

                    continue;
                }

                if (\in_array($name, self::URL_ATTRIBUTES, true)
                    && str_starts_with(strtolower(trim($attribute->value)), 'javascript:')
                ) {
                    $element->removeAttributeNode($attribute);
                }
            }
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function executableElements(\DOMDocument $document): array
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        $expression = implode(' | ', array_map(
            static fn (string $tag): string => \sprintf('//xhtml:%s | //%s', $tag, $tag),
            self::EXECUTABLE_TAGS,
        ));

        $nodes = $xpath->query($expression);
        $elements = [];

        if (false !== $nodes) {
            foreach ($nodes as $node) {
                if ($node instanceof \DOMElement) {
                    $elements[] = $node;
                }
            }
        }

        return $elements;
    }

    private function isMetaRefresh(\DOMElement $element): bool
    {
        return 'meta' === strtolower((string) $element->localName)
            && 'refresh' === strtolower(trim($element->getAttribute('http-equiv')));
    }

    /**
     * Odsylacz w ksiazce nie jest zasobem podgladu: prowadzilby z tlumaczenia
     * do surowego pliku rozdzialu, ktory podpisalibysmy wlasna reka. Wartosc
     * zostaje dla edytora w data-epub-href, ale przegladarka nie ma juz dokad
     * pojsc.
     */
    private function detachLinks(\DOMDocument $document): void
    {
        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            if ('a' !== strtolower((string) $element->localName)) {
                continue;
            }

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if ('href' !== strtolower((string) $attribute->localName)) {
                    continue;
                }

                $element->setAttribute('data-epub-href', $attribute->value);
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private function rewriteUrls(\DOMDocument $document, string $projectId, string $chapterHref): void
    {
        $base = \dirname($chapterHref);
        $base = '.' === $base ? '' : $base;

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if (!\in_array(strtolower((string) $attribute->localName), self::URL_ATTRIBUTES, true)) {
                    continue;
                }

                $rewritten = $this->rewrite($attribute->value, $projectId, $base);

                if (null === $rewritten) {
                    continue;
                }

                // Zapis przez setAttributeNS z nazwa kwalifikowana atrybutu:
                // trafia takze w xlink:href okladek z EPUB 2, a w odroznieniu
                // od DOMAttr::$value poprawnie escape'uje wartosc.
                $element->setAttributeNS($attribute->namespaceURI, $attribute->nodeName, $rewritten);
            }
        }
    }

    private function rewrite(string $value, string $projectId, string $base): ?string
    {
        $trimmed = trim($value);

        if ('' === $trimmed
            || str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, 'data:')
            || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $trimmed)
        ) {
            // Kotwice i adresy bezwzgledne nie wskazuja na wnetrze ksiazki.
            return null;
        }

        [$path, $fragment] = $this->splitFragment($trimmed);
        $resolved = $this->resolveAgainst($base, $path);

        return \sprintf(
            '/api/projects/%s/assets/%s?t=%s',
            $projectId,
            $this->encodePath($resolved),
            $this->signer->sign($projectId, $resolved),
        ).$fragment;
    }

    /**
     * @return array{string, string}
     */
    private function splitFragment(string $value): array
    {
        $hash = strpos($value, '#');

        return false === $hash
            ? [$value, '']
            : [substr($value, 0, $hash), substr($value, $hash)];
    }

    // Adresy w ksiazce sa wzgledem pliku rozdzialu, a nie korzenia zipa -
    // bez tego "images/cover.png" z OEBPS/ch1.xhtml szukaloby obrazu
    // w korzeniu i nie znalazloby go.
    private function resolveAgainst(string $base, string $path): string
    {
        $segments = '' === $base ? [] : explode('/', $base);

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            // Router Symfony dekoduje cala sciezke przed dopasowaniem trasy,
            // wiec kontroler widzi nazwe pliku w postaci zdekodowanej.
            // Podpisujemy dokladnie ta postac - inaczej ksiazka z poprawnie
            // zakodowana spacja dostawalaby 403. Dekodujemy segment po
            // segmencie, zeby "%2F" nie stalo sie separatorem sciezki.
            $segment = rawurldecode($segment);

            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function encodePath(string $path): string
    {
        // Kodujemy segmenty, nie ukosniki: sciezka ma zostac sciezka.
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
