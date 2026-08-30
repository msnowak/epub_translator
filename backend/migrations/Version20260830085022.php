<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Blad workera przestaje byc zdaniem, a staje sie kodem z parametrami.
 *
 * Worker nie ma zadania HTTP, wiec nie zna jezyka uzytkownika - zapisane
 * zdanie zamarzaloby w jednym jezyku na zawsze. Kod tlumaczy frontend przy
 * odczycie i dzieki temu wiersz sprzed tygodnia czyta sie w jezyku wybranym
 * przed chwila.
 */
final class Version20260830085022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the stored error sentence with a worker error code and its parameters.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD error_code VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD error_params JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE segment ADD error_code VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE segment ADD error_params JSON DEFAULT NULL');

        // Zdania, ktore workery zapisywaly do tej pory, sa policzalne i kazde
        // odpowiada dokladnie jednemu kodowi - przepisujemy je, zamiast kasowac
        // razem z kolumna. Liczbe prob bierzemy z kolumny "attempts", nie z
        // tresci zdania: to samo zrodlo, z ktorego czyta ja dzis kod.
        $this->addSql(<<<'SQL'
            UPDATE project SET error_code = 'epub_unreadable'
             WHERE error_message LIKE 'Nie udało się odczytać struktury pliku EPUB%'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE project SET error_code = 'ollama_unreachable_project'
             WHERE error_message LIKE 'Serwer Ollama jest nieosiągalny%'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE segment SET error_code = 'ollama_unreachable_segment'
             WHERE error_message LIKE 'Serwer Ollama jest nieosiągalny%'
            SQL);
        $this->addSql(<<<'SQL'
            UPDATE segment
               SET error_code = 'model_invalid_translation',
                   error_params = json_build_object('attempts', attempts)
             WHERE error_message LIKE 'Model nie zwrócił poprawnego tłumaczenia tego akapitu%'
            SQL);

        // Wiersz z komunikatem spoza tej listy traci zdanie i zostaje bez kodu.
        // Zachowuje status "failed", wiec dalej widac, ze cos poszlo nie tak -
        // pokazanie nieznanego kodu i tak dalo by pusty prostokat.
        $this->addSql('ALTER TABLE project DROP error_message');
        $this->addSql('ALTER TABLE segment DROP error_message');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE project ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE segment ADD error_message TEXT DEFAULT NULL');

        // Zdania nie odtwarzamy: jego brzmienie zalezy od jezyka, a ten jest
        // wlasnie tym, czego wiersz nie niesie. Kolumna wraca pusta.
        $this->addSql('ALTER TABLE project DROP error_code');
        $this->addSql('ALTER TABLE project DROP error_params');
        $this->addSql('ALTER TABLE segment DROP error_code');
        $this->addSql('ALTER TABLE segment DROP error_params');
    }
}
