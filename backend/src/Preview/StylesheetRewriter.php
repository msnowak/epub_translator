<?php

declare(strict_types=1);

namespace App\Preview;

/**
 * Signs the addresses inside a stylesheet's own url() functions, the same
 * way ElementSanitizer signs HTML attributes. A stylesheet's url() values
 * resolve against the stylesheet's own location, not the chapter that links
 * it - a browser resolving "OEBPS/font.ttf" against the stylesheet's address
 * drops the query string along the way, and ProjectAssetController answers
 * an unsigned request with 403. This is what actually happened with a real
 * book's page_styles.css and its four @font-face rules.
 */
final readonly class StylesheetRewriter
{
    // Cudzyslow jest opcjonalny - url(a.ttf) jest tak samo legalnym CSS-em
    // jak url("a.ttf") - a wsteczne odwolanie \1 w negatywnym lookaheadzie
    // pozwala jednym wzorcem obsluzyc cudzyslow, apostrof i jego brak: gdy
    // cudzyslowu nie ma, \1 to pusty lancuch i petla zatrzymuje sie tam,
    // gdzie zaczyna sie "biale znaki + )".
    private const string URL_PATTERN = '/url\(\s*([\'"]?)((?:(?!\1\s*\)).)*)\1\s*\)/i';

    public function __construct(
        private AssetUrlRewriter $rewriter,
    ) {
    }

    /**
     * @param string $cssHref path of this stylesheet inside the zip, which is
     *                        what its own url() values are relative to
     */
    public function rewrite(string $css, string $projectId, string $cssHref): string
    {
        $base = $this->rewriter->baseFor($cssHref);

        $rewritten = preg_replace_callback(
            self::URL_PATTERN,
            function (array $matches) use ($projectId, $base): string {
                $signed = $this->rewriter->rewrite($matches[2], $projectId, $base);

                if (null === $signed) {
                    // data:, adresy bezwzgledne i puste wartosci wracaja
                    // doslownie niezmienione - nie ma tu czego podpisywac,
                    // a przepisywanie "na wszelki wypadek" tylko by je psulo.
                    return $matches[0];
                }

                // Wynik zawsze w cudzyslowie, niezaleznie od tego, jak
                // wygladal oryginal: podpisany adres nie moze rozjechac sie
                // o nawias ani spacje, ktorych sam nie ma jak zawierac, ale
                // ktore czasem otaczaly oryginalna wartosc.
                return 'url("'.$signed.'")';
            },
            $css,
        );

        // preg_replace_callback zwraca null tylko przy bledzie silnika regex
        // (np. limit glebokosci nawrotow) - w takim wypadku lepiej oddac
        // arkusz nietkniety niz cichy pusty string.
        return $rewritten ?? $css;
    }
}
