<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Preview\AssetUrlRewriter;
use App\Preview\AssetUrlSigner;
use App\Preview\ElementSanitizer;
use PHPUnit\Framework\TestCase;

final class ElementSanitizerTest extends TestCase
{
    private const string PROJECT = '01920000-0000-7000-8000-000000000000';

    public function testRecognisesWhatMustNotSurviveInThePreview(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertTrue($sanitizer->isRemovable($this->element('<script>alert(1)</script>')));
        self::assertTrue($sanitizer->isRemovable($this->element('<iframe src="x"></iframe>')));
        self::assertTrue($sanitizer->isRemovable($this->element('<meta http-equiv="refresh" content="0;url=x"/>')));
        self::assertFalse($sanitizer->isRemovable($this->element('<em>x</em>')));
        self::assertFalse($sanitizer->isRemovable($this->element('<meta charset="utf-8"/>')));
    }

    public function testStripsHandlersDetachesLinksAndSignsAssets(): void
    {
        $element = $this->element('<a href="ch2.xhtml" onclick="steal()">x</a>');

        $this->sanitizer()->sanitize($element, self::PROJECT, 'OEBPS');

        self::assertFalse($element->hasAttribute('onclick'));
        self::assertFalse($element->hasAttribute('href'));
        self::assertSame('ch2.xhtml', $element->getAttribute('data-epub-href'));

        $image = $this->element('<img src="images/a.png"/>');

        $this->sanitizer()->sanitize($image, self::PROJECT, 'OEBPS');

        self::assertStringStartsWith(
            '/api/projects/'.self::PROJECT.'/assets/OEBPS/images/a.png?t=',
            $image->getAttribute('src'),
        );
    }

    public function testKeepsAnAnchorLive(): void
    {
        $element = $this->element('<a href="#note-1">1</a>');

        $this->sanitizer()->sanitize($element, self::PROJECT, 'OEBPS');

        self::assertSame('#note-1', $element->getAttribute('href'));
        self::assertFalse($element->hasAttribute('data-epub-href'));
    }

    private function sanitizer(): ElementSanitizer
    {
        return new ElementSanitizer(new AssetUrlRewriter(new AssetUrlSigner('sekret')));
    }

    private function element(string $markup): \DOMElement
    {
        $document = new \DOMDocument();
        $document->loadXML('<root>'.$markup.'</root>');

        $element = $document->documentElement?->firstElementChild;

        if (!$element instanceof \DOMElement) {
            self::fail('Nie udało się zbudować elementu z: '.$markup);
        }

        return $element;
    }
}
