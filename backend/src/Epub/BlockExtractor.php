<?php

declare(strict_types=1);

namespace App\Epub;

/**
 * Picks the deepest block-level elements that contain text. A list item holding
 * a paragraph is not a segment - the paragraph is. Numbering follows document
 * order and is the stable address used to write the translation back later.
 */
final readonly class BlockExtractor
{
    private const array BLOCK_TAGS = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'li', 'blockquote', 'td', 'th', 'dt', 'dd', 'figcaption',
    ];

    /**
     * @return list<Block>
     */
    public function extract(string $xhtml): array
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

        $blocks = [];
        $index = 0;

        foreach ($this->candidates($document) as $element) {
            if ($this->containsNestedBlock($element)) {
                continue;
            }

            $innerHtml = $this->innerHtml($element);

            if ('' === trim(strip_tags($innerHtml))) {
                continue;
            }

            $blocks[] = new Block($index, $innerHtml);
            ++$index;
        }

        return $blocks;
    }

    /**
     * @return list<\DOMElement>
     */
    private function candidates(\DOMDocument $document): array
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        $expression = implode(' | ', array_map(
            static fn (string $tag): string => \sprintf('//xhtml:%s | //%s', $tag, $tag),
            self::BLOCK_TAGS,
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

    private function containsNestedBlock(\DOMElement $element): bool
    {
        foreach ($element->getElementsByTagName('*') as $descendant) {
            if (\in_array(strtolower($descendant->nodeName), self::BLOCK_TAGS, true)) {
                return true;
            }
        }

        return false;
    }

    private function innerHtml(\DOMElement $element): string
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
}
