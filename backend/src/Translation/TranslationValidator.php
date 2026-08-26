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
     * Integralnosc danych: pusty tekst, komplet zetonow tego samego rodzaju,
     * poprawne zagniezdzenie. Dotyczy kazdego zapisu bez wzgledu na to, kto go
     * wykonal - taki akapit nie da sie zlozyc z powrotem do EPUB-a.
     *
     * @throws TranslationRejectedException
     */
    public function validate(string $source, string $translation): void
    {
        $trimmed = trim($translation);

        if ('' === $trimmed) {
            throw TranslationRejectedException::emptyTranslation();
        }

        $sourceTokens = $this->tokenKinds($source);
        $translationTokens = $this->tokenKinds($translation);

        foreach ($sourceTokens as $number => $kind) {
            if (!isset($translationTokens[$number])) {
                throw TranslationRejectedException::tokenIntegrity(\sprintf('The translation dropped token %s.', $number));
            }

            if ($translationTokens[$number] !== $kind) {
                throw TranslationRejectedException::tokenIntegrity(\sprintf('The translation changed token %s from %s to %s.', $number, $kind, $translationTokens[$number]));
            }
        }

        foreach ($translationTokens as $number => $kind) {
            if (!isset($sourceTokens[$number])) {
                throw TranslationRejectedException::tokenIntegrity(\sprintf('The translation invented token %s.', $number));
            }
        }

        $this->assertWellNested($translation);
    }

    /**
     * Wiarygodnosc odpowiedzi modelu: lapie silnik oddajacy wejscie zamiast
     * tlumaczenia, czesta awaria malych modeli lokalnych. Dotyczy wylacznie
     * sciezki silnika - dla czlowieka URL, nazwa wlasna czy fragment kodu
     * identyczny ze zrodlem w tlumaczeniu to poprawny wynik, nie awaria, wiec
     * UpdateSegmentProcessor nigdy nie wola tej metody.
     *
     * @throws TranslationRejectedException
     */
    public function assertNotEchoed(string $source, string $translation): void
    {
        if (mb_strlen(trim($source)) > self::ECHO_THRESHOLD && trim($translation) === trim($source)) {
            throw TranslationRejectedException::echoedSource();
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
                throw TranslationRejectedException::tokenIntegrity(\sprintf('Token %s closes out of order.', $number));
            }
        }

        if ([] !== $stack) {
            throw TranslationRejectedException::tokenIntegrity(\sprintf('Token %s was never closed.', end($stack)));
        }
    }
}
