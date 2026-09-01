<?php

declare(strict_types=1);

namespace App\Tests\Configuration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class MonologProductionHandlerTest extends TestCase
{
    /**
     * Ten test pilnuje decyzji, nie zachowania. Domyslna recepta Flexa dla
     * symfony/monolog-bundle ustawia produkcji handler fingers_crossed z
     * action_level: error - buforuje wszystko ponizej bledu i zrzuca bufor
     * dopiero, gdy blad wystapi.
     *
     * Sciezka odrzucenia akapitu celowo nigdy nie rzuca: SegmentTranslator
     * lapie TranslationRejectedException, loguje notice i po wyczerpaniu
     * budzetu prob ustawia status Failed, po czym wraca normalnie. Zaden
     * rekord error nigdy nie powstaje, wiec pod receptowa konfiguracja bufor
     * zostalby po cichu wyrzucony, a notice zniknalby dokladnie tak, jak
     * znikal przed wprowadzeniem monologa.
     *
     * Bez tego testu ktos "porzadkujacy" monolog.yaml do postaci receptowej
     * cicho przywroci tamten blad i nic mu tego nie zglosi.
     *
     * Odrzucamy caly rodzaj handlerow buforujacych, nie sam fingers_crossed:
     * type "buffer" ma dokladnie te sama wade. Sprawdzamy wlasnosc, na ktorej
     * nam zalezy - handler nie moze buforowac - a nie konkretna implementacje,
     * wiec pozniejsza zmiana stream na rotating_file nie wywola falszywego
     * alarmu.
     */
    public function testProductionHandlerLetsNoticeRecordsThrough(): void
    {
        $config = Yaml::parseFile(__DIR__.'/../../config/packages/monolog.yaml');

        $handlers = $config['when@prod']['monolog']['handlers'] ?? null;

        self::assertIsArray($handlers, 'monolog.yaml nie ma sekcji when@prod z handlerami.');
        self::assertArrayHasKey('main', $handlers, 'Sekcja when@prod nie definiuje handlera "main".');

        $main = $handlers['main'];

        self::assertNotContains(
            $main['type'] ?? null,
            ['fingers_crossed', 'buffer'],
            'Produkcyjny handler buforuje rekordy do czasu bledu, ktory na tej sciezce nigdy nie nastepuje.',
        );

        self::assertContains(
            $main['level'] ?? null,
            ['debug', 'info', 'notice'],
            'Prog produkcyjnego handlera odcina notice - a to jedyny poziom, na ktorym loguje SegmentTranslator.',
        );
    }
}
