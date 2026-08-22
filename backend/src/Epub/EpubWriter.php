<?php

declare(strict_types=1);

namespace App\Epub;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Writes a translated copy of a book: the original archive is copied and only
 * the entries handed over are replaced. Everything else - images, fonts,
 * stylesheets, navigation - stays exactly as the publisher stored it, keeps its
 * compression, and never passes through PHP memory.
 */
final readonly class EpubWriter
{
    private const string MIMETYPE_ENTRY = 'mimetype';

    public function __construct(
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @param array<string, string> $documents entry name => new contents; every
     *                                         name must already exist in the source
     *
     * @throws InvalidEpubException
     */
    public function write(string $sourcePath, array $documents, string $targetPath): void
    {
        try {
            $this->filesystem->copy($sourcePath, $targetPath, true);
        } catch (IOException $exception) {
            throw new InvalidEpubException('Could not copy the source archive.', 0, $exception);
        }

        $zip = new \ZipArchive();

        if (true !== $zip->open($targetPath)) {
            throw new InvalidEpubException('Could not open the copied archive for writing.');
        }

        foreach ($documents as $entry => $contents) {
            if (false === $zip->locateName($entry)) {
                $zip->close();

                // addFromString stworzyloby brakujacy wpis, czyli dolozyloby
                // do ksiazki plik zamiast podmienic rozdzial.
                throw new InvalidEpubException(\sprintf('The archive has no entry "%s" to replace.', $entry));
            }

            $zip->addFromString($entry, $contents);
        }

        if (false !== $zip->locateName(self::MIMETYPE_ENTRY)) {
            // OCF chce "mimetype" bez kompresji. Kopia nie rusza kolejnosci
            // wpisow, ale ksiazka moze przyjsc z tym wpisem spakowanym -
            // wtedy poprawiamy to, co da sie poprawic bez przebudowy.
            $zip->setCompressionName(self::MIMETYPE_ENTRY, \ZipArchive::CM_STORE);
        }

        if (true !== $zip->close()) {
            throw new InvalidEpubException('Could not finish writing the archive.');
        }
    }
}
