<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Epub\BlockExtractor;
use App\Epub\XhtmlDocument;
use App\Preview\AssetUrlSigner;
use App\Preview\PreviewDecorator;
use PHPUnit\Framework\TestCase;

final class PreviewDecoratorTest extends TestCase
{
    private const string PROJECT = '01920000-0000-7000-8000-000000000000';

    public function testMarksBlocksWithTheirSegmentId(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>One.</p><p>Two.</p>'));

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/ch1.xhtml', [
            0 => 'seg-one',
            1 => 'seg-two',
        ]);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('data-segment-id="seg-one"', $saved);
        self::assertStringContainsString('data-segment-id="seg-two"', $saved);
    }

    public function testRewritesRelativeAssetPathsAgainstTheChapter(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><img src="images/cover.png"/></p>'));

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/ch1.xhtml', []);

        $saved = $xhtml->save($document);

        // Rozdzial lezy w OEBPS/, wiec images/ to OEBPS/images/.
        self::assertStringContainsString('/api/projects/'.self::PROJECT.'/assets/OEBPS/images/cover.png?t=', $saved);
    }

    public function testRewritesStylesheetLinks(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load(
            '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml">'
            .'<head><link rel="stylesheet" href="../styles/main.css"/></head>'
            .'<body><p>Text.</p></body></html>',
        );

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/text/ch1.xhtml', []);

        self::assertStringContainsString('/assets/OEBPS/styles/main.css?t=', $xhtml->save($document));
    }

    public function testLeavesAbsoluteUrlsAndAnchorsAlone(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter(
            '<p><a href="https://example.com/a">link</a><a href="#footnote">przypis</a></p>',
        ));

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/ch1.xhtml', []);

        $saved = $xhtml->save($document);

        self::assertStringContainsString('https://example.com/a', $saved);
        self::assertStringContainsString('"#footnote"', $saved);
    }

    public function testStripsScriptElements(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p>Text.</p><script>alert(1)</script>'));

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/ch1.xhtml', []);

        $saved = $xhtml->save($document);

        self::assertStringNotContainsString('alert(1)', $saved);
        self::assertStringNotContainsString('<script', $saved);
    }

    public function testStripsInlineEventHandlers(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p onclick="steal()">Text.</p>'));

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/ch1.xhtml', []);

        self::assertStringNotContainsString('onclick', $xhtml->save($document));
    }

    public function testStripsJavascriptUrls(): void
    {
        $xhtml = new XhtmlDocument();
        $document = $xhtml->load($this->chapter('<p><a href="javascript:steal()">klik</a></p>'));

        $this->decorator()->decorate($document, self::PROJECT, 'OEBPS/ch1.xhtml', []);

        self::assertStringNotContainsString('javascript:', $xhtml->save($document));
    }

    private function decorator(): PreviewDecorator
    {
        $xhtml = new XhtmlDocument();

        return new PreviewDecorator(new BlockExtractor($xhtml), new AssetUrlSigner('sekret'));
    }

    private function chapter(string $body): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<html xmlns="http://www.w3.org/1999/xhtml"><head><title>T</title></head>'
            .'<body>'.$body.'</body></html>';
    }
}
