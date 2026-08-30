<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260830085022 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD error_code VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD error_params JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE project DROP error_message');
        $this->addSql('ALTER TABLE segment ADD error_code VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE segment ADD error_params JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE segment DROP error_message');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE project DROP error_code');
        $this->addSql('ALTER TABLE project DROP error_params');
        $this->addSql('ALTER TABLE segment ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE segment DROP error_code');
        $this->addSql('ALTER TABLE segment DROP error_params');
    }
}
