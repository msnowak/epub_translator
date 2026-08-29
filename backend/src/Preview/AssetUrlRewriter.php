<?php

declare(strict_types=1);

namespace App\Preview;

/**
 * The only knowledge in the project of how a book-internal address becomes a
 * signed preview asset URL. Extracted out of ElementSanitizer, which used to
 * own this exclusively for HTML attributes, so that StylesheetRewriter can
 * apply the exact same rule to addresses inside a stylesheet's url()
 * functions instead of duplicating it.
 */
final readonly class AssetUrlRewriter
{
    public function __construct(
        private AssetUrlSigner $signer,
    ) {
    }

    /**
     * Turns a book-internal address into a signed preview URL, or returns null
     * when the address does not point inside the book at all - an anchor, a
     * data: URI, or an absolute URL.
     *
     * @param string $base directory the address is relative to, inside the zip
     */
    public function rewrite(string $value, string $projectId, string $base): ?string
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
            $this->encodePath($resolved),
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
            // Router Symfony dekoduje cala sciezke przed dopasowaniem trasy,
            // wiec kontroler widzi nazwe pliku w postaci zdekodowanej.
            // Podpisujemy dokladnie ta postac - inaczej ksiazka z poprawnie
            // zakodowana spacja dostawalaby 403. Dekodujemy segment po
            // segmencie, tak samo jak EpubReader czyta manifest, zeby obie
            // strony mialy te sama sciezke. Bariera to nie jest: "%2F" po
            // zdekodowaniu jest ukosnikiem jak kazdy inny, a o tym, co wolno
            // wydac, decyduje manifest sprawdzany przez AssetPathResolver.
            $segment = rawurldecode($segment);

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

    private function encodePath(string $path): string
    {
        // Kodujemy segmenty, nie ukosniki: sciezka ma zostac sciezka.
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }
}
