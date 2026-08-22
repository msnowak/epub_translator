<?php

declare(strict_types=1);

namespace App\Preview;

use App\Epub\BlockExtractor;

/**
 * Turns a composed chapter into something an iframe can safely display:
 * blocks get an id the editor can address, asset paths get rewritten and
 * signed, and anything executable is removed. The sandbox attribute on the
 * iframe is the second layer - a book can come from anywhere, and the JWT
 * lives in the same browser tab.
 */
final readonly class PreviewDecorator
{
    private const array URL_ATTRIBUTES = ['src', 'href', 'poster'];

    public function __construct(
        private BlockExtractor $blockExtractor,
        private AssetUrlSigner $signer,
    ) {
    }

    /**
     * @param array<int, string> $segmentIdsByNodeIndex
     */
    public function decorate(
        \DOMDocument $document,
        string $projectId,
        string $chapterHref,
        array $segmentIdsByNodeIndex,
    ): void {
        $this->markBlocks($document, $segmentIdsByNodeIndex);
        $this->removeScripts($document);
        $this->rewriteUrls($document, $projectId, $chapterHref);
    }

    /**
     * @param array<int, string> $segmentIdsByNodeIndex
     */
    private function markBlocks(\DOMDocument $document, array $segmentIdsByNodeIndex): void
    {
        foreach ($this->blockExtractor->elements($document) as $nodeIndex => $element) {
            $segmentId = $segmentIdsByNodeIndex[$nodeIndex] ?? null;

            if (null === $segmentId) {
                continue;
            }

            $element->setAttribute('data-segment-id', $segmentId);
        }
    }

    private function removeScripts(\DOMDocument $document): void
    {
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        $scripts = $xpath->query('//xhtml:script | //script');

        if (false !== $scripts) {
            foreach (iterator_to_array($scripts) as $script) {
                if (!$script instanceof \DOMElement) {
                    continue;
                }

                $script->parentNode?->removeChild($script);
            }
        }

        $all = $document->getElementsByTagName('*');

        foreach (iterator_to_array($all) as $element) {
            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->name);

                if (str_starts_with($name, 'on')) {
                    $element->removeAttribute($attribute->name);

                    continue;
                }

                if (\in_array($name, self::URL_ATTRIBUTES, true)
                    && str_starts_with(strtolower(trim($attribute->value)), 'javascript:')
                ) {
                    $element->removeAttribute($attribute->name);
                }
            }
        }
    }

    private function rewriteUrls(\DOMDocument $document, string $projectId, string $chapterHref): void
    {
        $base = \dirname($chapterHref);
        $base = '.' === $base ? '' : $base;

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            foreach (self::URL_ATTRIBUTES as $attribute) {
                if (!$element->hasAttribute($attribute)) {
                    continue;
                }

                $rewritten = $this->rewrite($element->getAttribute($attribute), $projectId, $base);

                if (null !== $rewritten) {
                    $element->setAttribute($attribute, $rewritten);
                }
            }
        }
    }

    private function rewrite(string $value, string $projectId, string $base): ?string
    {
        $trimmed = trim($value);

        if ('' === $trimmed
            || str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, 'data:')
            || preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $trimmed)
        ) {
            // Kotwice i adresy bezwzgledne nie wskazuja na wnetrze ksiazki.
            return null;
        }

        [$path, $fragment] = $this->splitFragment($trimmed);
        $resolved = $this->resolveAgainst($base, $path);

        return \sprintf(
            '/api/projects/%s/assets/%s?t=%s',
            $projectId,
            $resolved,
            $this->signer->sign($projectId, $resolved),
        ).$fragment;
    }

    /**
     * @return array{string, string}
     */
    private function splitFragment(string $value): array
    {
        $hash = strpos($value, '#');

        return false === $hash
            ? [$value, '']
            : [substr($value, 0, $hash), substr($value, $hash)];
    }

    // Adresy w ksiazce sa wzgledem pliku rozdzialu, a nie korzenia zipa -
    // bez tego "images/cover.png" z OEBPS/ch1.xhtml szukaloby obrazu
    // w korzeniu i nie znalazloby go.
    private function resolveAgainst(string $base, string $path): string
    {
        $segments = '' === $base ? [] : explode('/', $base);

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }
}
