<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Epub\InlineTokenizer;
use App\Preview\AssetUrlSigner;
use App\Preview\ElementSanitizer;
use App\Preview\PlaceholderSanitizer;
use PHPUnit\Framework\TestCase;

final class PlaceholderSanitizerTest extends TestCase
{
    private const string PROJECT = '01920000-0000-7000-8000-000000000000';

    public function testKeepsPlainInlineMarkupAsItIs(): void
    {
        $safe = $this->sanitize(['1' => '<em>', '2' => '<strong class="x">']);

        self::assertSame('<em>', $this->token($safe, '1'));
        self::assertSame('<strong class="x">', $this->token($safe, '2'));
    }

    public function testDetachesABookLinkAndKeepsAnAnchorLive(): void
    {
        $safe = $this->sanitize(['1' => '<a href="ch2.xhtml">', '2' => '<a href="#note-1">']);

        self::assertSame('<a data-epub-href="ch2.xhtml">', $this->token($safe, '1'));
        self::assertSame('<a href="#note-1">', $this->token($safe, '2'));
    }

    public function testSignsAnInlineImage(): void
    {
        $safe = $this->sanitize(['1' => '<img src="images/a.png"/>']);

        self::assertStringStartsWith(
            '<img src="/api/projects/'.self::PROJECT.'/assets/OEBPS/images/a.png?t=',
            $this->token($safe, '1'),
        );
        // Element pusty musi zostac pusty - inaczej podglad dostanie <img> bez
        // domkniecia i zjadloby reszte akapitu.
        self::assertStringEndsWith('/>', $this->token($safe, '1'));
    }

    public function testDropsHandlersAndJavascriptUrls(): void
    {
        $safe = $this->sanitize([
            '1' => '<span onclick="steal()">',
            '2' => '<a href="javascript:steal()">',
        ]);

        self::assertSame('<span>', $this->token($safe, '1'));
        self::assertSame('<a>', $this->token($safe, '2'));
    }

    public function testOmitsExecutableMarkupEntirely(): void
    {
        $safe = $this->sanitize(['1' => '<script>', '2' => '<em>']);

        // Pominiety zeton zostaje w podgladzie doslownie jako "[1]" - dokladnie
        // tak, jak detokenize() traktuje zeton nieznany.
        self::assertArrayNotHasKey('1', $safe);
        self::assertArrayHasKey('2', $safe);
    }

    public function testKeepsAnEpubNamespacedAttribute(): void
    {
        $safe = $this->sanitize(['1' => '<a epub:type="noteref" href="notes.xhtml#n1">']);

        self::assertSame('<a epub:type="noteref" data-epub-href="notes.xhtml#n1">', $this->token($safe, '1'));
    }

    public function testCostsNothingWhenThereAreNoTokens(): void
    {
        self::assertSame([], $this->sanitize([]));
    }

    /**
     * @param array<array-key, string> $placeholders
     *
     * @return array<string, string>
     */
    private function sanitize(array $placeholders): array
    {
        $sanitizer = new PlaceholderSanitizer(
            new ElementSanitizer(new AssetUrlSigner('sekret')),
            new InlineTokenizer(),
        );

        return $sanitizer->sanitize($placeholders, self::PROJECT, 'OEBPS/ch1.xhtml');
    }

    /**
     * PHPStan level 8 cannot know a general array<string, string> holds a
     * given key just because the test built it that way - narrow with plain
     * PHP the same way the rest of the suite does, not with a PHPUnit
     * assertion, since there is no phpstan-phpunit extension to read it.
     *
     * @param array<string, string> $safe
     */
    private function token(array $safe, string $number): string
    {
        if (!\array_key_exists($number, $safe)) {
            self::fail(\sprintf('Brak zetonu "%s" w bezpiecznej mapie.', $number));
        }

        return $safe[$number];
    }
}
