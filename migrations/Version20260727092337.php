<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727092337 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE poem SET comment = STR_TO_DATE(comment, \'%d.%m.%Y\') ' .
            'WHERE comment IS NOT NULL AND comment REGEXP \'^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}$\'');
        $this->addSql('UPDATE poem_version SET comment = STR_TO_DATE(comment, \'%d.%m.%Y\') ' .
            'WHERE comment IS NOT NULL AND comment REGEXP \'^[0-9]{2}\\.[0-9]{2}\\.[0-9]{4}$\'');
        $this->addSql('ALTER TABLE poem MODIFY comment DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE poem_version MODIFY comment DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE poem MODIFY comment VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE poem_version MODIFY comment VARCHAR(512) DEFAULT NULL');
        $this->addSql('UPDATE poem SET comment = DATE_FORMAT(comment, \'%d.%m.%Y\') WHERE comment IS NOT NULL');
        $this->addSql('UPDATE poem_version SET comment = DATE_FORMAT(comment, \'%d.%m.%Y\') WHERE comment IS NOT NULL');
    }
}
