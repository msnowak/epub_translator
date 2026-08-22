<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\Chapter;
use App\Entity\Project;
use App\Entity\Segment;
use App\Epub\ChapterComposer;
use App\Epub\EpubPackage;
use App\Epub\EpubReader;
use App\Epub\EpubWriter;
use App\Epub\InvalidEpubException;
use App\Epub\OpfMetadataWriter;
use App\Epub\XhtmlDocument;
use App\Repository\ChapterRepository;
use App\Repository\SegmentRepository;
use App\Storage\ProjectStorage;

/**
 * Builds the downloadable book. Chapter content comes from ChapterComposer -
 * the very object the editor preview uses - so what a reader sees on screen and
 * what lands in the file cannot drift apart. This class only decides which
 * chapters to compose, refreshes the package metadata and hands the result to
 * EpubWriter. The file is built per request and never cached, so it always
 * carries the newest manual corrections.
 */
final readonly class TranslatedEpubBuilder
{
    public function __construct(
        private EpubReader $reader,
        private ProjectStorage $storage,
        private ChapterRepository $chapters,
        private SegmentRepository $segments,
        private ChapterComposer $composer,
        private OpfMetadataWriter $metadata,
        private EpubWriter $writer,
        private XhtmlDocument $xhtml,
    ) {
    }

    /**
     * @return string path to a freshly built file the caller owns and must delete
     *
     * @throws InvalidEpubException
     */
    public function build(Project $project): string
    {
        $source = $this->storage->path($project);
        $package = $this->reader->open($source);

        try {
            $documents = [];

            foreach ($this->chapters->findForProjectInSpineOrder($project) as $chapter) {
                $documents[$chapter->getHref()] = $this->compose($package, $chapter);
            }

            $opfHref = $package->opfHref();
            $documents[$opfHref] = $this->metadata->update(
                $package->read($opfHref),
                $project->getTargetLanguage(),
                $project->getTitle(),
            );
        } finally {
            // Wykona sie takze wtedy, gdy rozdzial nie da sie odczytac.
            $package->close();
        }

        $target = $this->temporaryPath();
        $this->writer->write($source, $documents, $target);

        return $target;
    }

    private function compose(EpubPackage $package, Chapter $chapter): string
    {
        /** @var list<Segment> $segments */
        $segments = $this->segments->findBy(['chapter' => $chapter], ['position' => 'ASC']);

        $document = $this->xhtml->load($package->read($chapter->getHref()));
        // Jedno przejscie po dokumencie. Zwrocona lista blokow jest potrzebna
        // podgladowi do znakowania; eksport niczego nie znakuje, wiec ja
        // ignoruje - i pod zadnym pozorem nie wyznacza jej ponownie.
        $this->composer->compose($document, $segments);

        return $this->xhtml->save($document);
    }

    /**
     * @throws InvalidEpubException
     */
    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epub-export-');

        if (false === $path) {
            throw new InvalidEpubException('Could not create a temporary file for the export.');
        }

        return $path;
    }
}
