<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\InlineTokenizer;
use PHPUnit\Framework\TestCase;

final class InlineTokenizerTest extends TestCase
{
    public function testPlainTextHasNoPlaceholders(): void
    {
        $result = (new InlineTokenizer())->tokenize('Zwykły akapit.');

        self::assertSame('Zwykły akapit.', $result->text);
        self::assertSame([], $result->placeholders);
    }

    public function testWrappingTagBecomesPairedToken(): void
    {
        $result = (new InlineTokenizer())->tokenize('To jest <em>ważne</em> słowo.');

        self::assertSame('To jest [1]ważne[/1] słowo.', $result->text);
        self::assertSame(['1' => '<em>'], $result->placeholders);
    }

    public function testAttributesArePreserved(): void
    {
        $result = (new InlineTokenizer())->tokenize('Patrz <a href="notes.xhtml#n1">przypis</a>.');

        self::assertSame('Patrz [1]przypis[/1].', $result->text);
        self::assertSame(['1' => '<a href="notes.xhtml#n1">'], $result->placeholders);
    }

    public function testVoidTagBecomesSelfClosingToken(): void
    {
        $result = (new InlineTokenizer())->tokenize('Pierwsza<br/>druga');

        self::assertSame('Pierwsza[1/]druga', $result->text);
        self::assertSame(['1' => '<br/>'], $result->placeholders);
    }

    public function testNestedTagsAreNumberedInDocumentOrder(): void
    {
        $result = (new InlineTokenizer())->tokenize('<em>bardzo <strong>mocno</strong></em> tak');

        self::assertSame('[1]bardzo [2]mocno[/2][/1] tak', $result->text);
        self::assertSame(['1' => '<em>', '2' => '<strong>'], $result->placeholders);
    }

    public function testRoundTripRestoresOriginalMarkup(): void
    {
        $tokenizer = new InlineTokenizer();

        $samples = [
            'Zwykły akapit.',
            'To jest <em>ważne</em> słowo.',
            'Patrz <a href="notes.xhtml#n1">przypis</a>.',
            '<em>bardzo <strong>mocno</strong></em> tak',
            'Pierwsza<br/>druga',
        ];

        foreach ($samples as $sample) {
            $tokenized = $tokenizer->tokenize($sample);

            self::assertSame(
                $sample,
                $tokenizer->detokenize($tokenized->text, $tokenized->placeholders),
                \sprintf('Round trip failed for: %s', $sample),
            );
        }
    }

    public function testDetokenizeEscapesBareTextButNotTokens(): void
    {
        $tokenizer = new InlineTokenizer();

        self::assertSame('Kot &amp; pies', $tokenizer->detokenize('Kot & pies', []));
    }
}
