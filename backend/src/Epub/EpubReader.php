<?php

declare(strict_types=1);

namespace App\Epub;

final readonly class EpubReader
{
    private const string CONTAINER_PATH = 'META-INF/container.xml';
    private const string CONTAINER_NS = 'urn:oasis:names:tc:opendocument:xmlns:container';
    private const string OPF_NS = 'http://www.idpf.org/2007/opf';
    private const string DC_NS = 'http://purl.org/dc/elements/1.1/';

    public function open(string $path): EpubPackage
    {
        $zip = new \ZipArchive();

        if (true !== $zip->open($path, \ZipArchive::RDONLY)) {
            throw new InvalidEpubException('The file is not a readable ZIP archive.');
        }

        try {
            $opfPath = $this->locateOpf($zip);
            $opf = $this->loadXml($this->entry($zip, $opfPath), 'the OPF package document');

            $manifest = $this->readManifest($opf, $this->directoryOf($opfPath));
            $spine = $this->readSpine($opf, $manifest);

            if ([] === $spine) {
                throw new InvalidEpubException('The OPF spine is empty, so there is nothing to translate.');
            }

            return new EpubPackage(
                $zip,
                $opfPath,
                $spine,
                array_values($manifest),
                $this->metadataValue($opf, 'title'),
                $this->metadataValue($opf, 'language'),
            );
        } catch (InvalidEpubException $exception) {
            $zip->close();

            throw $exception;
        }
    }

    private function locateOpf(\ZipArchive $zip): string
    {
        $container = $this->loadXml($this->entry($zip, self::CONTAINER_PATH), 'META-INF/container.xml');
        $rootfiles = $container->getElementsByTagNameNS(self::CONTAINER_NS, 'rootfile');
        $rootfile = $rootfiles->item(0);

        if (!$rootfile instanceof \DOMElement || '' === $rootfile->getAttribute('full-path')) {
            throw new InvalidEpubException('META-INF/container.xml does not point at a package document.');
        }

        $declared = $rootfile->getAttribute('full-path');
        $decoded = $this->decode($declared);

        // Rozstrzyga archiwum, nie specyfikacja: ksiazka zgodna z nia trzyma
        // wpis pod nazwa zdekodowana, a naiwny generator - pod doslownym
        // "%20", ktore deklaruje tak samo. Dekodujemy wiec, a gdy takiego wpisu
        // nie ma, zostajemy przy postaci doslownej, zeby nie zepsuc ksiazek,
        // ktore dzialaly do tej pory.
        return false === $zip->locateName($decoded) ? $declared : $decoded;
    }

    /**
     * @return array<string, string> id => href relative to the archive root
     */
    private function readManifest(\DOMDocument $opf, string $opfDirectory): array
    {
        $manifest = [];

        foreach ($opf->getElementsByTagNameNS(self::OPF_NS, 'item') as $item) {
            $id = $item->getAttribute('id');
            $href = $item->getAttribute('href');

            if ('' === $id || '' === $href) {
                continue;
            }

            $manifest[$id] = $this->resolve($opfDirectory, $this->decode($href));
        }

        if ([] === $manifest) {
            throw new InvalidEpubException('The OPF manifest is empty.');
        }

        return $manifest;
    }

    /**
     * @param array<string, string> $manifest
     *
     * @return list<string>
     */
    private function readSpine(\DOMDocument $opf, array $manifest): array
    {
        $spine = [];

        foreach ($opf->getElementsByTagNameNS(self::OPF_NS, 'itemref') as $itemref) {
            $idref = $itemref->getAttribute('idref');

            if (isset($manifest[$idref])) {
                $spine[] = $manifest[$idref];
            }
        }

        return $spine;
    }

    private function metadataValue(\DOMDocument $opf, string $name): ?string
    {
        $nodes = $opf->getElementsByTagNameNS(self::DC_NS, $name);
        $node = $nodes->item(0);

        return $node instanceof \DOMElement ? $node->textContent : null;
    }

    private function entry(\ZipArchive $zip, string $name): string
    {
        $contents = $zip->getFromName($name);

        if (false === $contents) {
            throw new InvalidEpubException(\sprintf('The archive has no "%s".', $name));
        }

        return $contents;
    }

    private function loadXml(string $xml, string $what): \DOMDocument
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->loadXML($xml)) {
                throw new InvalidEpubException(\sprintf('Could not parse %s.', $what));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $document;
    }

    private function directoryOf(string $path): string
    {
        $directory = \dirname($path);

        return '.' === $directory ? '' : $directory;
    }

    private function resolve(string $directory, string $href): string
    {
        return '' === $directory ? $href : $directory.'/'.$href;
    }

    /**
     * An OPF href is a URL: a file called "my image.png" is declared as
     * "my%20image.png". Zip entry names are not URLs, and neither is the path a
     * controller sees once Symfony's router has decoded the request, so this is
     * the form both the archive and the preview compare against.
     */
    private function decode(string $href): string
    {
        // Segment po segmencie, tak samo jak w PreviewDecorator - obie strony
        // maja dostac te sama postac sciezki. Bariera to nie jest: o tym, co
        // wolno wydac, decyduje manifest sprawdzany przez AssetPathResolver.
        return implode('/', array_map(rawurldecode(...), explode('/', $href)));
    }
}
