<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Katalogi tlumaczen nie maja typow, wiec nic poza tym testem ich nie pilnuje.
 *
 * Frontend ma swoj odpowiednik w frontend/src/i18n/catalogs.test.ts i tam
 * kompilator lapie brakujacy klucz sam. Tutaj kompilatora nie ma, a
 * konfiguracja dodatkowo ukrywa pomylke: przy fallbacks: [pl] klucz obecny
 * po polsku i zapomniany po angielsku nie daje zadnego bledu - anglojezyczny
 * uzytkownik po prostu dostaje polskie zdanie.
 */
final class CatalogTest extends TestCase
{
    private const DOMAINS = ['messages', 'validators'];
    private const LOCALES = ['pl', 'en'];

    /**
     * Sciezka asocjacji Doctrine, nie klucz tlumaczenia. Dzieli z katalogiem
     * prefiks "project." i jest jedyna taka kolizja w src - reszta stringow o
     * ksztalcie klucza (identyfikatory serwisow ApiPlatform, aliasy DQL, nazwy
     * plikow) zaczyna sie od prefiksu, ktorego katalog nie uzywa, wiec odpada
     * sama.
     */
    private const NOT_KEYS = ['project.owner'];

    /**
     * @return array<string, string>
     */
    private function catalog(string $domain, string $locale): array
    {
        /** @var array<string, string> $parsed */
        $parsed = Yaml::parseFile(\sprintf('%s/translations/%s.%s.yaml', \dirname(__DIR__, 2), $domain, $locale));

        return $parsed;
    }

    /**
     * @return list<array{string}>
     */
    public static function domains(): array
    {
        return array_map(static fn (string $domain): array => [$domain], self::DOMAINS);
    }

    #[DataProvider('domains')]
    public function testEveryLocaleCarriesTheSameKeys(string $domain): void
    {
        $polish = array_keys($this->catalog($domain, 'pl'));
        sort($polish);

        foreach (self::LOCALES as $locale) {
            $keys = array_keys($this->catalog($domain, $locale));
            sort($keys);

            self::assertSame($polish, $keys, \sprintf('%s.%s.yaml does not carry the Polish key set', $domain, $locale));
        }
    }

    #[DataProvider('domains')]
    public function testNoTranslationIsEmpty(string $domain): void
    {
        foreach (self::LOCALES as $locale) {
            foreach ($this->catalog($domain, $locale) as $key => $value) {
                // Puste tlumaczenie nie jest bledem dla Symfony - po prostu
                // wypisuje uzytkownikowi nic.
                self::assertNotSame('', trim($value), \sprintf('%s in %s.%s.yaml is empty', $key, $domain, $locale));
            }
        }
    }

    #[DataProvider('domains')]
    public function testPlaceholdersMatchAcrossLocales(string $domain): void
    {
        $polish = $this->catalog($domain, 'pl');

        foreach (self::LOCALES as $locale) {
            foreach ($this->catalog($domain, $locale) as $key => $value) {
                // Zgubiony {{ limit }} po jednej stronie daje zdanie bez liczby,
                // ktorej ono wprost dotyczy.
                self::assertSame(
                    $this->placeholders($polish[$key]),
                    $this->placeholders($value),
                    \sprintf('%s uses different placeholders in %s.%s.yaml', $key, $domain, $locale),
                );
            }
        }
    }

    /**
     * Kazdy klucz musi byc gdzies uzyty - i to lapie takze literowke po drugiej
     * stronie. Jesli ktos napisze trans('project.not_foundd') albo przemianuje
     * klucz tylko w kodzie, to zdefiniowany klucz przestaje byc gdziekolwiek
     * wspominany i ten test pada. Idziemy wlasnie w te strone, bo skan
     * odwrotny - szukanie w src stringow wygladajacych na klucz - lapie
     * identyfikatory serwisow ApiPlatform i aliasy DQL, wiec wymagalby listy
     * wyjatkow, ktora zestarzalaby sie szybciej niz sam katalog.
     */
    #[DataProvider('domains')]
    public function testEveryKeyIsReferencedInTheSource(string $domain): void
    {
        $source = '';

        foreach ($this->sourceFiles() as $file) {
            $source .= file_get_contents($file);
        }

        foreach (array_keys($this->catalog($domain, 'pl')) as $key) {
            self::assertStringContainsString(
                \sprintf("'%s'", $key),
                $source,
                \sprintf('%s is defined in %s.pl.yaml but nothing in src/ uses it', $key, $domain),
            );
        }
    }

    /**
     * Druga strona tego samego kontraktu, i ta wazniejsza.
     *
     * Test powyzej pilnuje, ze zdefiniowany klucz jest gdzies uzyty - ale klucz
     * uzywany w kilku miejscach przezyje literowke w jednym z nich. Tu idziemy
     * w przod: kazdy string o ksztalcie klucza, ktorego prefiks nalezy do
     * katalogu, musi byc w katalogu. Bez tego trans('project.not_foundd')
     * wypisuje uzytkownikowi surowy identyfikator, a nic nie protestuje.
     */
    public function testEveryKeyShapedStringInTheSourceIsDefined(): void
    {
        $defined = array_merge(
            array_keys($this->catalog('messages', 'pl')),
            array_keys($this->catalog('validators', 'pl')),
        );

        $prefixes = array_unique(array_map(
            static fn (string $key): string => strstr($key, '.', true) ?: $key,
            $defined,
        ));

        foreach ($this->sourceFiles() as $file) {
            preg_match_all("/'([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)'/", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $candidate) {
                if (\in_array($candidate, self::NOT_KEYS, true)) {
                    continue;
                }

                if (!\in_array(strstr($candidate, '.', true) ?: $candidate, $prefixes, true)) {
                    continue;
                }

                self::assertContains(
                    $candidate,
                    $defined,
                    \sprintf('%s in %s looks like a translation key, but no catalog defines it', $candidate, basename($file)),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $files = [];
        $directory = new \RecursiveDirectoryIterator(\dirname(__DIR__, 2).'/src');

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ('php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $value): array
    {
        preg_match_all('/\{\{\s*\w+\s*\}\}/', $value, $matches);

        $found = array_map(static fn (string $match): string => preg_replace('/\s+/', ' ', $match) ?? $match, $matches[0]);
        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }
}
