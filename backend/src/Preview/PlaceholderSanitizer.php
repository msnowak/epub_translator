<?php

declare(strict_types=1);

namespace App\Preview;

use App\Epub\InlineTokenizer;

/**
 * Prepares the inline markup behind [1]…[/1] for the editor's live preview.
 * The editor swaps one node at a time and cannot run the chapter through
 * PreviewDecorator for every keystroke, so the markup it will paste is
 * sanitized here instead - by the very same rules, on the very same objects.
 */
final readonly class PlaceholderSanitizer
{
    public function __construct(
        private ElementSanitizer $sanitizer,
        private InlineTokenizer $tokenizer,
    ) {
    }

    /**
     * @param array<array-key, string> $placeholders
     *
     * @return array<string, string>
     */
    public function sanitize(array $placeholders, string $projectId, string $chapterHref): array
    {
        if ([] === $placeholders) {
            // Wiekszosc akapitow nie ma zadnego znacznika i nie ma za co
            // placic parsowaniem.
            return [];
        }

        $base = $this->sanitizer->baseFor($chapterHref);
        $safe = [];

        foreach ($placeholders as $number => $markup) {
            $element = $this->parse($markup);

            if (null === $element || $this->sanitizer->isRemovable($element)) {
                // Zeton bez znacznika zostaje w podgladzie doslownie, tak samo
                // jak zeton nieznany w detokenize().
                continue;
            }

            $this->sanitizer->sanitize($element, $projectId, $base);
            $safe[(string) $number] = $this->tokenizer->openingMarkup($element);
        }

        return $safe;
    }

    private function parse(string $markup): ?\DOMElement
    {
        if (1 !== preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', $markup, $matches)) {
            return null;
        }

        // Sam znacznik otwierajacy nie jest poprawnym XML-em. Element pusty
        // przychodzi juz domkniety ("<br/>"), reszte domykamy tutaj.
        $fragment = str_ends_with(rtrim($markup), '/>')
            ? $markup
            : $markup.'</'.$matches[1].'>';

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            // Ten sam <root> co w InlineTokenizer::parse(): nieznany prefiks
            // (epub:type z przypisow EPUB 3) jest dla libxml ostrzezeniem,
            // nie bledem, wiec nazwa atrybutu zostaje w calosci.
            $document->loadXML('<root>'.$fragment.'</root>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document->documentElement?->firstElementChild;
    }
}
