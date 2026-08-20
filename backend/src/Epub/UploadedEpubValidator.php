<?php

declare(strict_types=1);

namespace App\Epub;

final readonly class UploadedEpubValidator
{
    private const string CONTAINER_PATH = 'META-INF/container.xml';

    public function validate(string $path): void
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw new InvalidEpubException('The uploaded file is not a readable ZIP archive.');
        }

        try {
            if (false === $zip->locateName(self::CONTAINER_PATH)) {
                throw new InvalidEpubException('The archive has no META-INF/container.xml, so it is not an EPUB.');
            }
        } finally {
            $zip->close();
        }
    }
}
