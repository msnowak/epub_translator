<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * The only barrier between a small local model and the database. Markup lives
 * in numbered tokens (see App\Epub\InlineTokenizer), so a lost token means a
 * paragraph that can no longer be written back into the EPUB.
 */
final readonly class TranslationValidator
{
    private const string TOKEN_PATTERN = '/\[(\/?)(\d+)(\/?)\]/';

    /**
     * Ponizej tego progu identyczne wejscie i wyjscie sa wiarygodne: nazwa
     * wlasna, data albo "OK" brzmia tak samo w obu jezykach.
     */
    private const int ECHO_THRESHOLD = 40;

    /**
     * @throws TranslationRejectedException
     */
    public function validate(string $source, string $translation): void
    {
        $trimmed = trim($translation);

        if ('' === $trimmed) {
            throw new TranslationRejectedException('The engine returned an empty translation.');
        }

        $sourceTokens = $this->tokenKinds($source);
        $translationTokens = $this->tokenKinds($translation);

        foreach ($sourceTokens as $number => $kind) {
            if (!isset($translationTokens[$number])) {
                throw new TranslationRejectedException(\sprintf('The translation dropped token %s.', $number));
            }

            if ($translationTokens[$number] !== $kind) {
                throw new TranslationRejectedException(\sprintf('The translation changed token %s from %s to %s.', $number, $kind, $translationTokens[$number]));
            }
        }

        foreach ($translationTokens as $number => $kind) {
            if (!isset($sourceTokens[$number])) {
                throw new TranslationRejectedException(\sprintf('The translation invented token %s.', $number));
            }
        }

        $this->assertWellNested($translation);

        if (mb_strlen(trim($source)) > self::ECHO_THRESHOLD && $trimmed === trim($source)) {
            throw new TranslationRejectedException('The engine echoed the source text back unchanged.');
        }
    }

    /**
     * Klucze sa numerami zetonow, wiec PHP koeruuje je do int - stad int|string.
     *
     * @return array<int|string, string> numer zetonu => "void" albo "paired"
     */
    private function tokenKinds(string $text): array
    {
        preg_match_all(self::TOKEN_PATTERN, $text, $matches, PREG_SET_ORDER);

        $kinds = [];

        foreach ($matches as $match) {
            $kinds[$match[2]] = '' === $match[3] ? 'paired' : 'void';
        }

        return $kinds;
    }

    /**
     * @throws TranslationRejectedException
     */
    private function assertWellNested(string $text): void
    {
        preg_match_all(self::TOKEN_PATTERN, $text, $matches, PREG_SET_ORDER);

        $stack = [];

        foreach ($matches as $match) {
            $closing = '' !== $match[1];
            $void = '' !== $match[3];
            $number = $match[2];

            if ($void) {
                continue;
            }

            if (!$closing) {
                $stack[] = $number;

                continue;
            }

            if ([] === $stack || array_pop($stack) !== $number) {
                throw new TranslationRejectedException(\sprintf('Token %s closes out of order.', $number));
            }
        }

        if ([] !== $stack) {
            throw new TranslationRejectedException(\sprintf('Token %s was never closed.', end($stack)));
        }
    }
}
