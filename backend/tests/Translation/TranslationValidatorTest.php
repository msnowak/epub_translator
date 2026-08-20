<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Translation\TranslationRejectedException;
use App\Translation\TranslationValidator;
use PHPUnit\Framework\TestCase;

final class TranslationValidatorTest extends TestCase
{
    public function testAcceptsPlainTranslation(): void
    {
        (new TranslationValidator())->validate('This is a paragraph.', 'To jest akapit.');

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsTranslationKeepingAllTokens(): void
    {
        (new TranslationValidator())->validate(
            'This is [1]important[/1] and[2/]new.',
            'To jest [1]ważne[/1] i[2/]nowe.',
        );

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsReorderedTokensThatStayNested(): void
    {
        // Szyk zdania w polskim bywa inny niz w angielskim - walidator pilnuje
        // kompletu i poprawnego zagniezdzenia zetonow, nie ich pozycji w tekscie.
        (new TranslationValidator())->validate(
            '[1]Very[/1] good [2]idea[/2].',
            '[2]Pomysł[/2] jest [1]bardzo[/1] dobry.',
        );

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsEmptyTranslation(): void
    {
        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate('This is a paragraph.', '   ');
    }

    public function testRejectsMissingToken(): void
    {
        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate('This is [1]important[/1].', 'To jest ważne.');
    }

    public function testRejectsInventedToken(): void
    {
        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate('This is important.', 'To jest [1]ważne[/1].');
    }

    public function testRejectsUnclosedToken(): void
    {
        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate('This is [1]important[/1].', 'To jest [1]ważne.');
    }

    public function testRejectsCrossedNesting(): void
    {
        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate(
            '[1]very [2]strongly[/2][/1] so',
            '[1]bardzo [2]mocno[/1][/2] tak',
        );
    }

    public function testRejectsVoidTokenTurnedIntoPair(): void
    {
        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate('Line[1/]break', 'Wiersz[1]łamanie[/1]');
    }

    public function testRejectsUntouchedLongText(): void
    {
        $text = 'This paragraph is definitely longer than forty characters.';

        $this->expectException(TranslationRejectedException::class);

        (new TranslationValidator())->validate($text, $text);
    }

    public function testAcceptsUntouchedShortText(): void
    {
        (new TranslationValidator())->validate('OK', 'OK');

        $this->expectNotToPerformAssertions();
    }
}
