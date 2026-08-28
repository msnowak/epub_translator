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
    public function __construct(
        private ElementSanitizer $sanitizer,
        private string $apiOrigin,
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

        $base = $this->sanitizer->baseFor($chapterHref);

        // Jedno przejscie po migawce elementow. localName widzi element tak
        // samo w przestrzeni nazw XHTML i bez niej, wiec XPath z prefiksami
        // przestal byc potrzebny.
        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            if ($this->sanitizer->isRemovable($element)) {
                $element->parentNode?->removeChild($element);

                continue;
            }

            $this->sanitizer->sanitize($element, $projectId, $base);
        }

        $this->injectPolicy($document);
    }

    /**
     * The chapter reaches the browser through srcdoc, so the response header
     * the preview endpoint sets never applies to it - a srcdoc document
     * inherits the parent's policy and carries none of its own. A meta tag
     * does apply, which makes this the only way the policy binds at all.
     */
    private function injectPolicy(\DOMDocument $document): void
    {
        $head = $document->getElementsByTagName('head')->item(0);

        if (!$head instanceof \DOMElement) {
            return;
        }

        // createElementNS, nie createElement: dokument jedzie przez loadXML,
        // wiec element bez przestrzeni nazw wyszedlby jako <meta xmlns="">.
        $meta = $document->createElementNS($head->namespaceURI, 'meta');
        $meta->setAttribute('http-equiv', 'Content-Security-Policy');
        $meta->setAttribute('content', $this->policy());

        // Polityka obowiazuje dla tresci sparsowanej PO niej, wiec idzie na
        // sam poczatek glowy dokumentu.
        $head->insertBefore($meta, $head->firstChild);
    }

    /**
     * No 'self': a srcdoc document with allow-same-origin inherits the parent
     * origin (the SPA), while the frontend rewrites every asset path onto the
     * API origin. 'self' would block the book's own images. Neither sandbox
     * nor frame-ancestors appear - a meta tag cannot carry them, and the
     * iframe's sandbox attribute covers the first one anyway.
     */
    private function policy(): string
    {
        return implode('; ', [
            "default-src 'none'",
            \sprintf('img-src %s data:', $this->apiOrigin),
            \sprintf("style-src %s 'unsafe-inline'", $this->apiOrigin),
            \sprintf('font-src %s data:', $this->apiOrigin),
            "base-uri 'none'",
            "form-action 'none'",
        ]);
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
}
