<?php

declare(strict_types=1);

namespace App\Epub;

final class EpubPackage
{
    /**
     * @param list<string> $spineHrefs
     * @param list<string> $manifestHrefs
     */
    public function __construct(
        private readonly \ZipArchive $zip,
        private readonly array $spineHrefs,
        private readonly array $manifestHrefs,
        private readonly ?string $title,
        private readonly ?string $language,
    ) {
    }

    /**
     * @return list<string>
     */
    public function spineHrefs(): array
    {
        return $this->spineHrefs;
    }

    /**
     * @return list<string>
     */
    public function manifestHrefs(): array
    {
        return $this->manifestHrefs;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function language(): ?string
    {
        return $this->language;
    }

    public function read(string $href): string
    {
        $contents = $this->zip->getFromName($href);

        if (false === $contents) {
            throw new InvalidEpubException(\sprintf('The archive has no entry "%s".', $href));
        }

        return $contents;
    }

    public function close(): void
    {
        $this->zip->close();
    }
}
