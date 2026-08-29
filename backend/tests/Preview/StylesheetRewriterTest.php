<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Preview\AssetUrlRewriter;
use App\Preview\AssetUrlSigner;
use App\Preview\StylesheetRewriter;
use PHPUnit\Framework\TestCase;

final class StylesheetRewriterTest extends TestCase
{
    private const string PROJECT = '01920000-0000-7000-8000-000000000000';

    public function testRewritesAUrlInAStylesheetAtTheRootOfTheZip(): void
    {
        $css = '@font-face { src: url(OEBPS/font.ttf); }';

        $rewritten = $this->rewriter()->rewrite($css, self::PROJECT, 'page_styles.css');

        self::assertStringContainsString('/api/projects/'.self::PROJECT.'/assets/OEBPS/font.ttf?t=', $rewritten);
    }

    public function testResolvesAUrlRelativeToTheStylesheetNotTheChapterOrRoot(): void
    {
        $css = "@font-face { src: url('../fonts/f.ttf'); }";

        $rewritten = $this->rewriter()->rewrite($css, self::PROJECT, 'OEBPS/styles/main.css');

        // "OEBPS/styles/" + "../fonts/f.ttf" = "OEBPS/fonts/f.ttf" - to jest
        // sedno bledu, ktory ta klasa naprawia: adres jest wzgledem arkusza,
        // nie wzgledem rozdzialu ani korzenia.
        self::assertStringContainsString('/assets/OEBPS/fonts/f.ttf?t=', $rewritten);
    }

    public function testTreatsDoubleAndSingleQuotesTheSameAsNoQuotes(): void
    {
        $rewriter = $this->rewriter();
        $base = 'page_styles.css';

        $bare = $rewriter->rewrite('url(a.ttf)', self::PROJECT, $base);
        $double = $rewriter->rewrite('url("a.ttf")', self::PROJECT, $base);
        $single = $rewriter->rewrite("url('a.ttf')", self::PROJECT, $base);

        $expectedPrefix = '/api/projects/'.self::PROJECT.'/assets/a.ttf?t=';

        self::assertStringContainsString($expectedPrefix, $bare);
        self::assertStringContainsString($expectedPrefix, $double);
        self::assertStringContainsString($expectedPrefix, $single);
    }

    public function testToleratesWhitespaceInsideTheParentheses(): void
    {
        $rewritten = $this->rewriter()->rewrite('url(  a.ttf  )', self::PROJECT, 'page_styles.css');

        self::assertStringContainsString('/assets/a.ttf?t=', $rewritten);
    }

    public function testLeavesADataUriUntouched(): void
    {
        $css = 'src: url(data:font/ttf;base64,AAAA);';

        self::assertSame($css, $this->rewriter()->rewrite($css, self::PROJECT, 'page_styles.css'));
    }

    public function testLeavesAbsoluteUrlsUntouched(): void
    {
        $absolute = 'src: url(https://example.com/f.ttf);';
        $protocolRelative = 'src: url(//example.com/f.ttf);';

        $rewriter = $this->rewriter();

        self::assertSame($absolute, $rewriter->rewrite($absolute, self::PROJECT, 'page_styles.css'));
        self::assertSame(
            $protocolRelative,
            $rewriter->rewrite($protocolRelative, self::PROJECT, 'page_styles.css'),
        );
    }

    public function testRewritesEveryUrlInAStylesheetWithSeveralFontFaceRules(): void
    {
        $css = <<<'CSS'
            @font-face { font-family: "A"; src: url(OEBPS/fonts/a.ttf); }
            @font-face { font-family: "B"; src: url(OEBPS/fonts/b.ttf); }
            @font-face { font-family: "C"; src: url(OEBPS/fonts/c.ttf); }
            @font-face { font-family: "D"; src: url(OEBPS/fonts/d.ttf); }
            CSS;

        $rewritten = $this->rewriter()->rewrite($css, self::PROJECT, 'page_styles.css');

        foreach (['a', 'b', 'c', 'd'] as $letter) {
            self::assertStringContainsString(
                '/assets/OEBPS/fonts/'.$letter.'.ttf?t=',
                $rewritten,
            );
        }
    }

    public function testReturnsAStylesheetWithoutAnyUrlUnchanged(): void
    {
        $css = 'body { color: red; font-weight: bold; }';

        self::assertSame($css, $this->rewriter()->rewrite($css, self::PROJECT, 'page_styles.css'));
    }

    private function rewriter(): StylesheetRewriter
    {
        return new StylesheetRewriter(new AssetUrlRewriter(new AssetUrlSigner('sekret')));
    }
}
