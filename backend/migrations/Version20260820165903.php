<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260820165903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chapter (id UUID NOT NULL, spine_order INT NOT NULL, href VARCHAR(512) NOT NULL, title VARCHAR(255) DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F981B52E166D1F9C ON chapter (project_id)');
        $this->addSql('CREATE INDEX idx_chapter_project_order ON chapter (project_id, spine_order)');
        $this->addSql('CREATE TABLE segment (id UUID NOT NULL, position INT NOT NULL, node_index INT NOT NULL, sub_index INT NOT NULL, source_text TEXT NOT NULL, placeholders JSON NOT NULL, translated_text TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, attempts INT NOT NULL, error_message TEXT DEFAULT NULL, project_id UUID NOT NULL, chapter_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_1881F565166D1F9C ON segment (project_id)');
        $this->addSql('CREATE INDEX IDX_1881F565579F4768 ON segment (chapter_id)');
        $this->addSql('CREATE INDEX idx_segment_project_status ON segment (project_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_segment_node ON segment (chapter_id, node_index, sub_index)');
        $this->addSql('ALTER TABLE chapter ADD CONSTRAINT FK_F981B52E166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE segment ADD CONSTRAINT FK_1881F565166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE segment ADD CONSTRAINT FK_1881F565579F4768 FOREIGN KEY (chapter_id) REFERENCES chapter (id) ON DELETE CASCADE NOT DEFERRABLE');
        // Deferred from stage 1: token_hash already carries a UNIQUE index, so the
        // plain index on the same column only costs write time.
        $this->addSql('DROP INDEX idx_refresh_token_hash');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_refresh_token_hash ON refresh_token (token_hash)');
        $this->addSql('ALTER TABLE chapter DROP CONSTRAINT FK_F981B52E166D1F9C');
        $this->addSql('ALTER TABLE segment DROP CONSTRAINT FK_1881F565166D1F9C');
        $this->addSql('ALTER TABLE segment DROP CONSTRAINT FK_1881F565579F4768');
        $this->addSql('DROP TABLE chapter');
        $this->addSql('DROP TABLE segment');
    }
}
