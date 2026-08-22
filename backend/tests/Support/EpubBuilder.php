<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Builds minimal but structurally valid EPUB files at test time, so the repo
 * carries no binary fixtures and each test states exactly what it exercises.
 */
final class EpubBuilder
{
    private string $title = 'Test Book';
    private string $language = 'en';

    /** @var array<string, string> */
    private array $chapters = [];

    /** @var array<string, string> */
    private array $images = [];

    /** @var array<string, string> */
    private array $chapterManifestHrefs = [];

    /** @var array<string, string> */
    private array $imageManifestHrefs = [];

    private bool $withContainerXml = true;
    private bool $corrupted = false;

    public static function create(): self
    {
        return new self();
    }

    public function withTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function withLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @param string|null $manifestHref how the OPF declares the file, when that
     *                                  differs from the zip entry name - a book
     *                                  written to spec percent-encodes it
     */
    public function withChapter(string $href, string $bodyHtml, ?string $manifestHref = null): self
    {
        $this->chapters[$href] = $bodyHtml;
        $this->chapterManifestHrefs[$href] = $manifestHref ?? $href;

        return $this;
    }

    public function withImage(string $href, string $binaryContent, ?string $manifestHref = null): self
    {
        $this->images[$href] = $binaryContent;
        $this->imageManifestHrefs[$href] = $manifestHref ?? $href;

        return $this;
    }

    public function withoutContainerXml(): self
    {
        $this->withContainerXml = false;

        return $this;
    }

    public function corrupted(): self
    {
        $this->corrupted = true;

        return $this;
    }

    public function build(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'epub').'.epub';

        if ($this->corrupted) {
            file_put_contents($path, 'this is not a zip archive');

            return $path;
        }

        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException('Could not create the test EPUB archive.');
        }

        $zip->addFromString('mimetype', 'application/epub+zip');

        if ($this->withContainerXml) {
            $zip->addFromString('META-INF/container.xml', <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
                    <rootfiles>
                        <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
                    </rootfiles>
                </container>
                XML);
        }

        $manifest = '';
        $spine = '';
        $index = 0;

        foreach ($this->chapters as $href => $bodyHtml) {
            $id = 'chapter'.$index;
            $manifest .= \sprintf(
                '<item id="%s" href="%s" media-type="application/xhtml+xml"/>',
                $id,
                htmlspecialchars($this->chapterManifestHrefs[$href], ENT_XML1),
            );
            $spine .= \sprintf('<itemref idref="%s"/>', $id);
            $zip->addFromString('OEBPS/'.$href, $this->chapterDocument($bodyHtml));
            ++$index;
        }

        foreach ($this->images as $href => $content) {
            $manifest .= \sprintf(
                '<item id="image%d" href="%s" media-type="image/png"/>',
                $index,
                htmlspecialchars($this->imageManifestHrefs[$href], ENT_XML1),
            );
            $zip->addFromString('OEBPS/'.$href, $content);
            ++$index;
        }

        $zip->addFromString('OEBPS/content.opf', \sprintf(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <package xmlns="http://www.idpf.org/2007/opf" version="3.0" unique-identifier="bookid">
                    <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                        <dc:identifier id="bookid">urn:uuid:test</dc:identifier>
                        <dc:title>%s</dc:title>
                        <dc:language>%s</dc:language>
                    </metadata>
                    <manifest>%s</manifest>
                    <spine>%s</spine>
                </package>
                XML,
            htmlspecialchars($this->title, ENT_XML1),
            htmlspecialchars($this->language, ENT_XML1),
            $manifest,
            $spine,
        ));

        $zip->close();

        return $path;
    }

    private function chapterDocument(string $bodyHtml): string
    {
        return \sprintf(
            <<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <html xmlns="http://www.w3.org/1999/xhtml">
                    <head><title>Chapter</title></head>
                    <body>%s</body>
                </html>
                XML,
            $bodyHtml,
        );
    }
}
