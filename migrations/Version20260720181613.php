<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260720181613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE monster (
              id INT AUTO_INCREMENT NOT NULL,
              login VARCHAR(255) NOT NULL,
              author VARCHAR(255) NOT NULL,
              poems INT NOT NULL,
              poems_old INT NOT NULL,
              place INT DEFAULT NULL,
              place_old INT DEFAULT NULL,
              last_visit_date DATETIME DEFAULT NULL,
              is_active TINYINT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE vozdukh (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              subtitle VARCHAR(255) DEFAULT NULL,
              author VARCHAR(255) NOT NULL,
              text LONGTEXT NOT NULL,
              url VARCHAR(255) DEFAULT NULL,
              is_active TINYINT NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE monster');
        $this->addSql('DROP TABLE vozdukh');
    }
}
