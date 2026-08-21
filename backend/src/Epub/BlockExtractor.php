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

    public function __construct(
        private XhtmlDocument $xhtml,
    ) {
    }

    /**
     * @return list<Block>
     */
    public function extract(string $xhtml): array
    {
        $document = $this->xhtml->load($xhtml);
        $blocks = [];

        foreach ($this->elements($document) as $index => $element) {
            $blocks[] = new Block($index, $this->xhtml->innerHtml($element));
        }

        return $blocks;
    }

    /**
     * Blocks in the same order extract() uses to assign nodeIndex. One place
     * decides what counts as a block - otherwise writing a translation back
     * would land in a different node than the one it was read from.
     *
     * @return list<\DOMElement>
     */
    public function elements(\DOMDocument $document): array
    {
        $elements = [];

        foreach ($this->candidates($document) as $element) {
            if ($this->containsNestedBlock($element)) {
                continue;
            }

            if ('' === trim(strip_tags($this->xhtml->innerHtml($element)))) {
                continue;
            }

            $elements[] = $element;
        }

        return $elements;
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
}
