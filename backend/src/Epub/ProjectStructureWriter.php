<?php

declare(strict_types=1);

namespace App\Epub;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Turns an EPUB file into the chapter and segment rows the translation engine
 * will work through. Flushes in batches so a large book does not build one
 * enormous unit of work.
 */
final readonly class ProjectStructureWriter
{
    private const int BATCH_SIZE = 200;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EpubReader $reader,
        private BlockExtractor $extractor,
        private InlineTokenizer $tokenizer,
        private SegmentSplitter $splitter,
        #[Autowire('%env(int:MAX_SEGMENT_CHARS)%')]
        private int $maxSegmentChars,
    ) {
    }

    public function write(Project $project, string $epubPath): int
    {
        $package = $this->reader->open($epubPath);
        $created = 0;

        try {
            foreach ($package->spineHrefs() as $spineOrder => $href) {
                $chapter = new Chapter($project, $spineOrder, $href);
                $this->entityManager->persist($chapter);

                $position = 0;

                foreach ($this->extractor->extract($package->read($href)) as $block) {
                    $tokenized = $this->tokenizer->tokenize($block->innerHtml);
                    $parts = $this->splitter->split($tokenized->text, $this->maxSegmentChars);

                    foreach ($parts as $subIndex => $part) {
                        $this->entityManager->persist(new Segment(
                            $chapter,
                            $position,
                            $block->nodeIndex,
                            $subIndex,
                            $part,
                            $tokenized->placeholders,
                        ));

                        ++$position;
                        ++$created;

                        if (0 === $created % self::BATCH_SIZE) {
                            $this->entityManager->flush();
                        }
                    }
                }
            }

            $this->entityManager->flush();
        } finally {
            $package->close();
        }

        return $created;
    }
}
