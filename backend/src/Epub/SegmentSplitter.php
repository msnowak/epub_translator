<?php

declare(strict_types=1);

namespace App\Epub;

/**
 * Splits an over-long paragraph on sentence boundaries. A single paragraph can
 * exceed a small local model's context window; the parts are rejoined with a
 * single space when the translation is written back.
 */
final readonly class SegmentSplitter
{
    /**
     * @return list<string>
     */
    public function split(string $text, int $maxChars): array
    {
        if ($maxChars <= 0 || mb_strlen($text) <= $maxChars) {
            return [$text];
        }

        $sentences = preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (false === $sentences) {
            return [$text];
        }

        $parts = [];
        $current = '';

        foreach ($sentences as $sentence) {
            $candidate = '' === $current ? $sentence : $current.' '.$sentence;

            if (mb_strlen($candidate) > $maxChars && '' !== $current) {
                $parts[] = $current;
                $current = $sentence;

                continue;
            }

            $current = $candidate;
        }

        // Lista zdan jest niepusta, wiec petla wyzej zawsze cos zostawia.
        $parts[] = $current;

        return $parts;
    }
}
