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
            // Kopia juz lezy na dysku, ale nie da sie jej otworzyc jako
            // archiwum - po throw targetPath ma nie istniec, wiec sprzatamy.
            $this->discardTarget($targetPath);

            throw new InvalidEpubException('Could not open the copied archive for writing.');
        }

        foreach ($documents as $entry => $contents) {
            if (false === $zip->locateName($entry)) {
                // Ta petla mogla juz podmienic wczesniejsze wpisy przez
                // addFromString. Ten build ext-zip nie ma discard(), wiec
                // unchangeAll() cofa te podmiany, zanim close() zdazyloby
                // je zapisac na dysk - eksport ma byc wszystko albo nic.
                $zip->unchangeAll();
                $zip->close();
                $this->discardTarget($targetPath);

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
            // Nieudane zamkniecie moglo mimo to czesciowo zapisac dane na
            // dysk - eksport ma byc wszystko albo nic, wiec nie zostawiamy
            // polksiazki pod docelowa sciezka.
            $this->discardTarget($targetPath);

            throw new InvalidEpubException('Could not finish writing the archive.');
        }
    }

    /**
     * Best-effort cleanup of a target that must not survive a failed write.
     * Swallows a failed delete on purpose: the caller is already about to
     * receive the real InvalidEpubException, and a stray IOException from
     * remove() must never replace it and hide what actually went wrong.
     */
    private function discardTarget(string $targetPath): void
    {
        try {
            $this->filesystem->remove($targetPath);
        } catch (IOException) {
            // celowo pomijamy - prawdziwy blad juz leci do wywolujacego,
            // a nieudane sprzatanie nie moze go zaslonic.
        }
    }
}
