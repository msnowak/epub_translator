<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A real Symfony Translator loaded from the project's own catalogs, for unit
 * tests that construct a collaborator directly instead of booting the kernel.
 * The project mocks no collaborators; this follows the same line as
 * FakeTranslationEngine and RecordingLogger.
 */
final class TestTranslator
{
    public static function create(string $locale = 'pl'): TranslatorInterface
    {
        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());

        $translationsDir = \dirname(__DIR__, 2).'/translations';

        foreach (['messages', 'validators'] as $domain) {
            foreach (['pl', 'en'] as $catalogLocale) {
                $translator->addResource(
                    'yaml',
                    \sprintf('%s/%s.%s.yaml', $translationsDir, $domain, $catalogLocale),
                    $catalogLocale,
                    $domain,
                );
            }
        }

        return $translator;
    }
}
