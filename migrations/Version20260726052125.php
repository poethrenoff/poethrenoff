<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260726052125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_work_vote ON work_vote');
        $this->addSql('ALTER TABLE work_vote ADD user_agent_hash VARCHAR(64) DEFAULT \'\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_work_vote ON work_vote (work_id, ip_hash, user_agent_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_work_vote ON work_vote');
        $this->addSql('ALTER TABLE work_vote DROP user_agent_hash');
        $this->addSql('CREATE UNIQUE INDEX uniq_work_vote ON work_vote (work_id, ip_hash, session_hash)');
    }
}
