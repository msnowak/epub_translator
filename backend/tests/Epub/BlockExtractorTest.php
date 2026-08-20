<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\BlockExtractor;
use PHPUnit\Framework\TestCase;

final class BlockExtractorTest extends TestCase
{
    public function testExtractsParagraphsInDocumentOrder(): void
    {
        $blocks = (new BlockExtractor())->extract($this->document('<p>One</p><p>Two</p>'));

        self::assertCount(2, $blocks);
        self::assertSame(0, $blocks[0]->nodeIndex);
        self::assertSame('One', $blocks[0]->innerHtml);
        self::assertSame(1, $blocks[1]->nodeIndex);
        self::assertSame('Two', $blocks[1]->innerHtml);
    }

    public function testTakesDeepestBlockWhenBlocksAreNested(): void
    {
        $blocks = (new BlockExtractor())->extract($this->document('<li><p>Inner</p></li>'));

        self::assertCount(1, $blocks);
        self::assertSame('Inner', $blocks[0]->innerHtml);
    }

    public function testKeepsInlineMarkupInsideABlock(): void
    {
        $blocks = (new BlockExtractor())->extract($this->document('<p>To jest <em>ważne</em></p>'));

        self::assertCount(1, $blocks);
        self::assertSame('To jest <em>ważne</em>', $blocks[0]->innerHtml);
    }

    public function testSkipsBlocksWithoutText(): void
    {
        $blocks = (new BlockExtractor())->extract($this->document('<p><img src="a.png"/></p><p>Text</p>'));

        self::assertCount(1, $blocks);
        self::assertSame('Text', $blocks[0]->innerHtml);
    }

    public function testCoversHeadingsListsAndTableCells(): void
    {
        $blocks = (new BlockExtractor())->extract($this->document(
            '<h1>Title</h1><ul><li>Item</li></ul><table><tr><td>Cell</td></tr></table>',
        ));

        self::assertSame(['Title', 'Item', 'Cell'], array_map(static fn ($block) => $block->innerHtml, $blocks));
    }

    private function document(string $bodyHtml): string
    {
        return \sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><html xmlns="http://www.w3.org/1999/xhtml"><body>%s</body></html>',
            $bodyHtml,
        );
    }
}
