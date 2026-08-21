<?php

declare(strict_types=1);

namespace App\Epub;

/**
 * All DOM mechanics in one place, so BlockExtractor, ChapterComposer and the
 * preview decorator agree on how a chapter is parsed and serialised.
 */
final readonly class XhtmlDocument
{
    /**
     * @throws InvalidEpubException
     */
    public function load(string $xhtml): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($xhtml)) {
                throw new InvalidEpubException('Could not parse the chapter document.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (null === $document->encoding) {
            // A chapter without an explicit XML encoding declaration leaves
            // DOMDocument::$encoding unset, and saveXML() then escapes every
            // non-ASCII character as a numeric entity instead of emitting the
            // raw UTF-8 bytes. Every chapter we read is UTF-8, so pin it.
            $document->encoding = 'UTF-8';
        }

        return $document;
    }

    public function innerHtml(\DOMElement $element): string
    {
        $document = $element->ownerDocument;

        if (null === $document) {
            throw new InvalidEpubException('The block element is detached from its document.');
        }

        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $document->saveXML($child);
        }

        return trim($html);
    }

    public function replaceInnerHtml(\DOMElement $element, string $html): void
    {
        $document = $element->ownerDocument;

        if (null === $document) {
            throw new InvalidEpubException('The block element is detached from its document.');
        }

        while (null !== $element->firstChild) {
            $element->removeChild($element->firstChild);
        }

        if ('' === trim($html)) {
            return;
        }

        foreach ($this->parseFragment($document, $element->namespaceURI, $html) as $child) {
            $element->appendChild($child);
        }
    }

    public function save(\DOMDocument $document): string
    {
        return (string) $document->saveXML();
    }

    /**
     * Fragment jedzie do parsera w opakowaniu dziedziczacym przestrzen nazw
     * elementu docelowego. Bez tego <em> z detokenize() wyladowaloby poza
     * przestrzenia XHTML i zserializowalo sie jako <em xmlns="">.
     *
     * @return list<\DOMNode>
     */
    private function parseFragment(\DOMDocument $document, ?string $namespace, string $html): array
    {
        $wrapper = null === $namespace
            ? '<wrapper>'.$html.'</wrapper>'
            : \sprintf('<wrapper xmlns="%s">%s</wrapper>', $namespace, $html);

        $fragment = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $parsed = $fragment->loadXML('<?xml version="1.0" encoding="utf-8"?>'.$wrapper);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$parsed || null === $fragment->documentElement) {
            // Niedomkniety znacznik z recznej poprawki albo od modelu. Tekst
            // jest wazniejszy niz formatowanie - rozdzial ma sie otworzyc.
            return [$document->createTextNode(strip_tags($html))];
        }

        $children = [];

        foreach ($fragment->documentElement->childNodes as $child) {
            $children[] = $document->importNode($child, true);
        }

        return $children;
    }
}
