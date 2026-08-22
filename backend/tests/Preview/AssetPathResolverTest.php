<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Preview\AssetPathResolver;
use PHPUnit\Framework\TestCase;

final class AssetPathResolverTest extends TestCase
{
    private const array MANIFEST = [
        'OEBPS/ch1.xhtml',
        'OEBPS/images/cover.png',
        'OEBPS/styles/main.css',
    ];

    public function testResolvesAPathFromTheManifest(): void
    {
        self::assertSame(
            'OEBPS/images/cover.png',
            (new AssetPathResolver())->resolve('OEBPS/images/cover.png', self::MANIFEST),
        );
    }

    public function testNormalisesRedundantSegments(): void
    {
        self::assertSame(
            'OEBPS/images/cover.png',
            (new AssetPathResolver())->resolve('OEBPS/styles/../images/./cover.png', self::MANIFEST),
        );
    }

    public function testStripsALeadingSlash(): void
    {
        self::assertSame(
            'OEBPS/images/cover.png',
            (new AssetPathResolver())->resolve('/OEBPS/images/cover.png', self::MANIFEST),
        );
    }

    public function testRejectsAPathOutsideTheManifest(): void
    {
        self::assertNull((new AssetPathResolver())->resolve('OEBPS/images/secret.png', self::MANIFEST));
    }

    public function testRejectsTraversalAboveTheRoot(): void
    {
        $resolver = new AssetPathResolver();

        self::assertNull($resolver->resolve('../../etc/passwd', self::MANIFEST));
        self::assertNull($resolver->resolve('OEBPS/../../etc/passwd', self::MANIFEST));
    }

    public function testRejectsAnEmptyPath(): void
    {
        $resolver = new AssetPathResolver();

        self::assertNull($resolver->resolve('', self::MANIFEST));
        self::assertNull($resolver->resolve('/', self::MANIFEST));
    }

    public function testRejectsABackslashPath(): void
    {
        // Windowsowy separator nie moze byc furtka omijajaca normalizacje.
        self::assertNull((new AssetPathResolver())->resolve('OEBPS\\..\\..\\etc\\passwd', self::MANIFEST));
    }
}
