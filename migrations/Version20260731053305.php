<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260731053305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_245EC6F4AA08CB10 ON monster (login)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_245EC6F4AA08CB10 ON monster');
    }
}
