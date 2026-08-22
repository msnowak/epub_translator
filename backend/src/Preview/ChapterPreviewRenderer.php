<?php

declare(strict_types=1);

namespace App\Preview;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Epub\ChapterComposer;
use App\Epub\EpubReader;
use App\Epub\XhtmlDocument;
use App\Repository\SegmentRepository;
use App\Storage\ProjectStorage;

/**
 * Reads a chapter out of the book, puts the translations in and decorates the
 * result for the editor. Composition is shared with the export, so the preview
 * and the downloaded file cannot disagree about what the chapter says.
 */
final readonly class ChapterPreviewRenderer
{
    public function __construct(
        private EpubReader $reader,
        private ProjectStorage $storage,
        private SegmentRepository $segments,
        private ChapterComposer $composer,
        private PreviewDecorator $decorator,
        private XhtmlDocument $xhtml,
    ) {
    }

    public function render(Project $project, Chapter $chapter): string
    {
        $package = $this->reader->open($this->storage->path($project));

        try {
            $source = $package->read($chapter->getHref());
        } finally {
            $package->close();
        }

        /** @var list<Segment> $segments */
        $segments = $this->segments->findBy(['chapter' => $chapter], ['position' => 'ASC']);

        $document = $this->xhtml->load($source);
        // Bloki z tego samego przejscia, ktore skladalo tlumaczenia: to ono
        // wyznacza nodeIndex, a po podmianie tresci blok bez tekstu wypadlby
        // z ponownego wyliczenia i przesunal identyfikatory kolejnych akapitow.
        $blocks = $this->composer->compose($document, $segments);

        $this->decorator->decorate(
            $document,
            $blocks,
            (string) $project->getId(),
            $chapter->getHref(),
            $this->segmentIdsByNodeIndex($segments),
        );

        return $this->xhtml->save($document);
    }

    /**
     * @param list<Segment> $segments
     *
     * @return array<int, string>
     */
    private function segmentIdsByNodeIndex(array $segments): array
    {
        $ids = [];

        foreach ($segments as $segment) {
            // Podzielony akapit ma kilka segmentow na jednym bloku; edytor
            // adresuje blok pierwszym z nich, bo to on wyznacza poczatek akapitu.
            if (0 === $segment->getSubIndex()) {
                $ids[$segment->getNodeIndex()] = (string) $segment->getId();
            }
        }

        return $ids;
    }
}
