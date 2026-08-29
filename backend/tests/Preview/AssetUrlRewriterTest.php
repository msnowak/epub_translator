<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Preview\AssetUrlRewriter;
use App\Preview\AssetUrlSigner;
use PHPUnit\Framework\TestCase;

final class AssetUrlRewriterTest extends TestCase
{
    // Przeniesiony z ElementSanitizerTest razem z baseFor(): metoda przeszla
    // do tej klasy, wiec jej test tez. Same assertions as before the move.
    public function testResolvesTheBaseAgainstTheCarryingFile(): void
    {
        $rewriter = $this->rewriter();

        self::assertSame('OEBPS', $rewriter->baseFor('OEBPS/ch1.xhtml'));
        self::assertSame('', $rewriter->baseFor('ch1.xhtml'));
    }

    private function rewriter(): AssetUrlRewriter
    {
        return new AssetUrlRewriter(new AssetUrlSigner('sekret'));
    }
}
