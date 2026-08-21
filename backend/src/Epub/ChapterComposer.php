<?php

declare(strict_types=1);

namespace App\Epub;

use App\Entity\Segment;
use App\Entity\SegmentStatus;

/**
 * Puts translations back into a chapter. The one place that does it: the
 * editor preview and the EPUB export both come through here, so what a reader
 * sees on screen and what lands in the downloaded file cannot drift apart.
 */
final readonly class ChapterComposer
{
    public function __construct(
        private BlockExtractor $blockExtractor,
        private InlineTokenizer $tokenizer,
        private XhtmlDocument $xhtml,
    ) {
    }

    /**
     * @param list<Segment> $segments segmenty rozdzialu w dowolnej kolejnosci
     */
    public function compose(\DOMDocument $document, array $segments): void
    {
        $elements = $this->blockExtractor->elements($document);

        foreach ($this->groupByNodeIndex($segments) as $nodeIndex => $group) {
            $element = $elements[$nodeIndex] ?? null;

            if (null === $element) {
                // Segment wskazuje blok, ktorego w dokumencie nie ma - plik
                // podmieniono po sparsowaniu. Oryginal zostaje nietkniety.
                continue;
            }

            $translation = $this->translationFor($group);

            if (null === $translation) {
                continue;
            }

            $this->xhtml->replaceInnerHtml($element, $translation);
        }
    }

    /**
     * @param list<Segment> $segments
     *
     * @return array<int, list<Segment>> nodeIndex => podsegmenty posortowane po subIndex
     */
    private function groupByNodeIndex(array $segments): array
    {
        $groups = [];

        foreach ($segments as $segment) {
            $groups[$segment->getNodeIndex()][] = $segment;
        }

        foreach ($groups as $nodeIndex => $group) {
            usort($group, static fn (Segment $a, Segment $b): int => $a->getSubIndex() <=> $b->getSubIndex());
            $groups[$nodeIndex] = $group;
        }

        return $groups;
    }

    /**
     * @param list<Segment> $group
     */
    private function translationFor(array $group): ?string
    {
        $parts = [];

        foreach ($group as $segment) {
            $text = $segment->getTranslatedText();

            if (!$this->isUsable($segment) || null === $text) {
                // Wystarczy jeden brakujacy podsegment, zeby caly akapit
                // zostal w oryginale - polowa po polsku bylaby gorsza
                // niz calosc po angielsku.
                return null;
            }

            $parts[] = $text;
        }

        if ([] === $parts) {
            return null;
        }

        // Kazdy podsegment niesie ta sama, pelna mape zetonow: tokenizacja
        // dzieje sie na calym bloku, dopiero potem tnie go SegmentSplitter.
        return $this->tokenizer->detokenize(implode(' ', $parts), $group[0]->getPlaceholders());
    }

    private function isUsable(Segment $segment): bool
    {
        return \in_array($segment->getStatus(), [SegmentStatus::Translated, SegmentStatus::Edited], true);
    }
}
