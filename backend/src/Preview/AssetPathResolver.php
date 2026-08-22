<?php

declare(strict_types=1);

namespace App\Preview;

/**
 * Turns a requested asset path into one the EPUB actually declares, or into
 * nothing at all. Two independent barriers guard this endpoint: the signature
 * says "we issued this URL", the manifest says "this file belongs to the
 * document". Neither substitutes for the other.
 */
final readonly class AssetPathResolver
{
    /**
     * @param list<string> $manifestHrefs
     */
    public function resolve(string $requested, array $manifestHrefs): ?string
    {
        $normalised = $this->normalise($requested);

        if (null === $normalised) {
            return null;
        }

        // Dokladne dopasowanie, nie prefiks: manifest jest lista tego, co wolno
        // wydac, a nie katalogiem, po ktorym mozna chodzic.
        return \in_array($normalised, $manifestHrefs, true) ? $normalised : null;
    }

    private function normalise(string $path): ?string
    {
        if (str_contains($path, '\\')) {
            // Separator windowsowy nie moze byc furtka omijajaca normalizacje.
            return null;
        }

        $segments = [];

        foreach (explode('/', ltrim($path, '/')) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    // Wyjscie ponad korzen dokumentu konczy sprawe od razu.
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return [] === $segments ? null : implode('/', $segments);
    }
}
