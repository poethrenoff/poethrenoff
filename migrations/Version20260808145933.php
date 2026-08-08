<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260808145933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE recognize_task (
              id VARCHAR(64) NOT NULL,
              status VARCHAR(32) NOT NULL,
              result_text LONGTEXT DEFAULT NULL,
              error_message LONGTEXT DEFAULT NULL,
              step_data JSON DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              audio_id INT NOT NULL,
              INDEX IDX_E4E008643A3123C7 (audio_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              recognize_task
            ADD
              CONSTRAINT FK_E4E008643A3123C7 FOREIGN KEY (audio_id) REFERENCES audio (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recognize_task DROP FOREIGN KEY FK_E4E008643A3123C7');
        $this->addSql('DROP TABLE recognize_task');
    }
}
