<?php

declare(strict_types=1);

namespace App\Epub;

/**
 * Replaces inline markup with numbered tokens so a language model translates
 * plain text and cannot corrupt the HTML. Small local models routinely mangle
 * tags; a missing token is detectable and retryable, broken markup is not.
 */
final readonly class InlineTokenizer
{
    private const array VOID_TAGS = ['br', 'img', 'hr', 'wbr'];

    public function tokenize(string $innerHtml): TokenizedText
    {
        $fragment = $this->parse($innerHtml);

        $text = '';
        $placeholders = [];
        $counter = 0;

        $this->walk($fragment, $text, $placeholders, $counter);

        return new TokenizedText($text, $placeholders);
    }

    /**
     * @param array<array-key, string> $placeholders
     */
    public function detokenize(string $text, array $placeholders): string
    {
        $result = '';
        $offset = 0;

        while (preg_match('/\[(\/?)(\d+)(\/?)\]/', $text, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            $whole = $matches[0];

            $result .= $this->escape(substr($text, $offset, $whole[1] - $offset));

            $number = $matches[2][0];
            $opening = $placeholders[$number] ?? null;

            if (null === $opening) {
                // Nieznany zeton zostaje doslownie - lepiej zostawic slad
                // w tekscie niz po cichu zjesc fragment tresci.
                $result .= $whole[0];
            } elseif ('' !== $matches[1][0]) {
                $result .= \sprintf('</%s>', $this->tagName($opening));
            } else {
                $result .= $opening;
            }

            $offset = $whole[1] + \strlen($whole[0]);
        }

        return $result.$this->escape(substr($text, $offset));
    }

    /**
     * PHP zamienia numeryczne klucze tekstowe na calkowite, wiec mapa zetonow
     * ma klucze typu array-key, mimo ze numery generujemy jako tekst.
     *
     * @param array<array-key, string> $placeholders
     */
    private function walk(\DOMNode $node, string &$text, array &$placeholders, int &$counter): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text .= $child->nodeValue ?? '';

                continue;
            }

            if (!$child instanceof \DOMElement) {
                continue;
            }

            $number = (string) ++$counter;
            $placeholders[$number] = $this->openingMarkup($child);

            if (\in_array(strtolower($child->nodeName), self::VOID_TAGS, true)) {
                $text .= \sprintf('[%s/]', $number);

                continue;
            }

            $text .= \sprintf('[%s]', $number);
            $this->walk($child, $text, $placeholders, $counter);
            $text .= \sprintf('[/%s]', $number);
        }
    }

    private function openingMarkup(\DOMElement $element): string
    {
        $name = strtolower($element->nodeName);
        $markup = '<'.$name;

        foreach ($element->attributes as $attribute) {
            $markup .= \sprintf(' %s="%s"', $attribute->name, htmlspecialchars($attribute->value, ENT_QUOTES | ENT_XML1));
        }

        return $markup.(\in_array($name, self::VOID_TAGS, true) ? '/>' : '>');
    }

    private function tagName(string $openingMarkup): string
    {
        preg_match('/^<([a-z0-9]+)/i', $openingMarkup, $matches);

        return $matches[1] ?? 'span';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_XML1);
    }

    private function parse(string $innerHtml): \DOMElement
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadXML('<root>'.$innerHtml.'</root>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;

        if (!$root instanceof \DOMElement) {
            throw new InvalidEpubException('Could not parse the paragraph markup.');
        }

        return $root;
    }
}
