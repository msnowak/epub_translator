<?php

declare(strict_types=1);

namespace App\Epub;

/**
 * Rewrites the two pieces of package metadata a translation invalidates: the
 * language the book is written in and its title. Identifier, author, dates, the
 * manifest and the spine are left exactly as they were.
 */
final readonly class OpfMetadataWriter
{
    private const string OPF_NS = 'http://www.idpf.org/2007/opf';
    private const string DC_NS = 'http://purl.org/dc/elements/1.1/';

    public function __construct(
        private XhtmlDocument $xhtml,
    ) {
    }

    /**
     * @throws InvalidEpubException
     */
    public function update(string $opfXml, string $language, string $title): string
    {
        $document = $this->xhtml->load($opfXml);

        // Jezyk zrodlowy przestaje byc prawda w chwili, gdy plik wychodzi
        // z eksportu, wiec zostaje jeden wpis - ten docelowy.
        $this->rewrite($document, 'language', $language, dropExtras: true);
        $this->rewrite($document, 'title', $title, dropExtras: false);

        return $this->xhtml->save($document);
    }

    private function rewrite(\DOMDocument $document, string $name, string $value, bool $dropExtras): void
    {
        $elements = $this->elements($document, $name);
        $first = $elements[0] ?? null;

        if (null === $first) {
            $element = $document->createElementNS(self::DC_NS, 'dc:'.$name);
            // Wartosc przez textContent, nie przez trzeci argument
            // createElementNS - ten nie escape'uje ampersanda i tytul
            // z "&" rozwalilby pakiet.
            $element->textContent = $value;
            $this->metadata($document)->appendChild($element);

            return;
        }

        $first->textContent = $value;

        if (!$dropExtras) {
            return;
        }

        foreach (\array_slice($elements, 1) as $extra) {
            $extra->parentNode?->removeChild($extra);
        }
    }

    /**
     * @return list<\DOMElement>
     */
    private function elements(\DOMDocument $document, string $name): array
    {
        $elements = [];

        foreach ($document->getElementsByTagNameNS(self::DC_NS, $name) as $element) {
            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * @throws InvalidEpubException
     */
    private function metadata(\DOMDocument $document): \DOMElement
    {
        $metadata = $document->getElementsByTagNameNS(self::OPF_NS, 'metadata')->item(0);

        if (!$metadata instanceof \DOMElement) {
            throw new InvalidEpubException('The OPF package document has no metadata element.');
        }

        return $metadata;
    }
}
