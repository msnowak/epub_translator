<?php

declare(strict_types=1);

namespace App\Tests\Preview;

use App\Preview\AssetUrlSigner;
use PHPUnit\Framework\TestCase;

final class AssetUrlSignerTest extends TestCase
{
    private const string PROJECT = '01920000-0000-7000-8000-000000000000';

    public function testAcceptsItsOwnSignature(): void
    {
        $signer = new AssetUrlSigner('sekret');
        $token = $signer->sign(self::PROJECT, 'OEBPS/images/cover.png');

        self::assertTrue($signer->isValid(self::PROJECT, 'OEBPS/images/cover.png', $token));
    }

    public function testRejectsASignatureForAnotherPath(): void
    {
        $signer = new AssetUrlSigner('sekret');
        $token = $signer->sign(self::PROJECT, 'OEBPS/images/cover.png');

        self::assertFalse($signer->isValid(self::PROJECT, 'OEBPS/images/secret.png', $token));
    }

    public function testRejectsASignatureForAnotherProject(): void
    {
        $signer = new AssetUrlSigner('sekret');
        $token = $signer->sign(self::PROJECT, 'OEBPS/images/cover.png');

        self::assertFalse($signer->isValid('01920000-0000-7000-8000-000000000001', 'OEBPS/images/cover.png', $token));
    }

    public function testRejectsASignatureFromAnotherSecret(): void
    {
        $token = (new AssetUrlSigner('sekret'))->sign(self::PROJECT, 'OEBPS/images/cover.png');

        self::assertFalse((new AssetUrlSigner('inny-sekret'))->isValid(self::PROJECT, 'OEBPS/images/cover.png', $token));
    }

    public function testRejectsGarbage(): void
    {
        $signer = new AssetUrlSigner('sekret');

        self::assertFalse($signer->isValid(self::PROJECT, 'OEBPS/images/cover.png', 'nonsens'));
        self::assertFalse($signer->isValid(self::PROJECT, 'OEBPS/images/cover.png', ''));
        self::assertFalse($signer->isValid(self::PROJECT, 'OEBPS/images/cover.png', '999.deadbeef'));
    }

    public function testRejectsAnExpiredSignature(): void
    {
        // Podpis wystawia sam signer, wiec skrot jest prawdziwy i odrzucic
        // token moze wylacznie zegar - recznie sklejony digest przechodzilby
        // ten test takze bez sprawdzania waznosci.
        $signer = new AssetUrlSigner('sekret', ttlSeconds: -10);
        $token = $signer->sign(self::PROJECT, 'OEBPS/images/cover.png');

        self::assertFalse($signer->isValid(self::PROJECT, 'OEBPS/images/cover.png', $token));
    }

    public function testSignatureSurvivesItsWholeLifetime(): void
    {
        $signer = new AssetUrlSigner('sekret', ttlSeconds: 3600);
        $token = $signer->sign(self::PROJECT, 'OEBPS/images/cover.png');

        [$expiresAt] = explode('.', $token, 2);

        self::assertGreaterThan(time() + 3500, (int) $expiresAt);
    }

    public function testRejectsATokenWithATamperedExpiry(): void
    {
        $signer = new AssetUrlSigner('sekret');
        $token = $signer->sign(self::PROJECT, 'OEBPS/images/cover.png');

        [, $digest] = explode('.', $token, 2);
        $tamperedToken = (time() + 999_999).'.'.$digest;

        self::assertFalse($signer->isValid(self::PROJECT, 'OEBPS/images/cover.png', $tamperedToken));
    }

    public function testRejectsATokenMintedForADifferentPairThatWouldCollideUnderNaiveConcatenation(): void
    {
        $signer = new AssetUrlSigner('sekret');

        // Naively joined with '|', both pairs render as the same string:
        // "project|evil|path|<expiresAt>". A length-prefixed payload must
        // keep them distinct.
        $token = $signer->sign('project|evil', 'path');

        self::assertFalse($signer->isValid('project', 'evil|path', $token));
    }
}
