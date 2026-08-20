<?php

declare(strict_types=1);

namespace App\Tests\Epub;

use App\Epub\SegmentSplitter;
use PHPUnit\Framework\TestCase;

final class SegmentSplitterTest extends TestCase
{
    public function testShortTextIsNotSplit(): void
    {
        self::assertSame(['Krótkie zdanie.'], (new SegmentSplitter())->split('Krótkie zdanie.', 100));
    }

    public function testSplitsOnSentenceBoundaries(): void
    {
        $text = 'Pierwsze zdanie. Drugie zdanie. Trzecie zdanie.';

        $parts = (new SegmentSplitter())->split($text, 30);

        self::assertGreaterThan(1, \count($parts));

        foreach ($parts as $part) {
            self::assertNotSame('', trim($part));
        }

        self::assertSame($text, implode(' ', $parts));
    }

    public function testSentenceLongerThanLimitIsKeptWhole(): void
    {
        $text = 'To jest jedno bardzo długie zdanie bez żadnej kropki w środku';

        self::assertSame([$text], (new SegmentSplitter())->split($text, 10));
    }
}
