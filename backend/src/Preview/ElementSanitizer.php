<?php

declare(strict_types=1);

namespace App\Preview;

/**
 * The per-element half of the preview's trust boundary. It lives on its own
 * because two callers need exactly these rules: PreviewDecorator, which walks a
 * whole chapter, and PlaceholderSanitizer, which prepares the inline markup the
 * editor pastes into that chapter one paragraph at a time. Two copies of a
 * security rule drift, and the copy that drifts is the one that leaks.
 */
final readonly class ElementSanitizer
{
    public const array URL_ATTRIBUTES = ['src', 'href', 'poster'];

    /**
     * Elements that can load and run something of their own. A script is the
     * obvious one; an iframe, object or embed pulls in a document just as
     * effectively, and all of them would be served from our own origin.
     */
    public const array EXECUTABLE_TAGS = ['script', 'iframe', 'object', 'embed'];

    public function __construct(
        private AssetUrlRewriter $rewriter,
    ) {
    }

    /** True for elements that must not survive in the preview at all. */
    public function isRemovable(\DOMElement $element): bool
    {
        $name = strtolower((string) $element->localName);

        if (\in_array($name, self::EXECUTABLE_TAGS, true)) {
            return true;
        }

        return 'meta' === $name
            && 'refresh' === strtolower(trim($element->getAttribute('http-equiv')));
    }

    public function sanitize(\DOMElement $element, string $projectId, string $base): void
    {
        $this->stripExecutableAttributes($element);
        $this->detachLink($element);
        $this->rewriteUrls($element, $projectId, $base);
    }

    private function stripExecutableAttributes(\DOMElement $element): void
    {
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

    /**
     * Odsylacz w ksiazce nie jest zasobem podgladu: prowadzilby z tlumaczenia
     * do surowego pliku rozdzialu, ktory podpisalibysmy wlasna reka. Wartosc
     * zostaje dla edytora w data-epub-href, ale przegladarka nie ma juz dokad
     * pojsc. Wyjatkiem sa kotwice "#fragment" - te nie wychodza poza dokument,
     * wiec zostaja zywe, inaczej przypisy przestaja dzialac.
     */
    private function detachLink(\DOMElement $element): void
    {
        if ('a' !== strtolower((string) $element->localName)) {
            return;
        }

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if ('href' !== strtolower((string) $attribute->localName)) {
                continue;
            }

            if (str_starts_with(trim($attribute->value), '#')) {
                continue;
            }

            $element->setAttribute('data-epub-href', $attribute->value);
            $element->removeAttributeNode($attribute);
        }
    }

    private function rewriteUrls(\DOMElement $element, string $projectId, string $base): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            if (!\in_array(strtolower((string) $attribute->localName), self::URL_ATTRIBUTES, true)) {
                continue;
            }

            $rewritten = $this->rewriter->rewrite($attribute->value, $projectId, $base);

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
