<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260822090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create publication_log table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE publication_log (
              id INT AUTO_INCREMENT NOT NULL,
              poem_id INT NOT NULL,
              platform VARCHAR(32) NOT NULL,
              status VARCHAR(16) NOT NULL,
              external_post_id VARCHAR(255) DEFAULT NULL,
              external_url VARCHAR(255) DEFAULT NULL,
              error_message LONGTEXT DEFAULT NULL,
              published_at DATETIME NOT NULL,
              UNIQUE INDEX uq_publication_log_poem_platform (poem_id, platform),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              publication_log
            ADD
              CONSTRAINT FK_PUBLICATION_LOG_POEM FOREIGN KEY (poem_id) REFERENCES poem (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE publication_log DROP FOREIGN KEY FK_PUBLICATION_LOG_POEM');
        $this->addSql('DROP TABLE publication_log');
    }
}