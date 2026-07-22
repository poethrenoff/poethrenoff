<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260722090207 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE audio (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              file_path VARCHAR(255) NOT NULL,
              duration INT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE blog_comment (
              id INT AUTO_INCREMENT NOT NULL,
              author VARCHAR(100) NOT NULL,
              content LONGTEXT NOT NULL,
              info VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              is_active TINYINT NOT NULL,
              post_id INT NOT NULL,
              parent_id INT DEFAULT NULL,
              INDEX IDX_7882EFEF4B89032C (post_id),
              INDEX idx_blog_comment_post_date (post_id, created_at),
              INDEX idx_blog_comment_parent (parent_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE blog_post (
              id INT AUTO_INCREMENT NOT NULL,
              content LONGTEXT NOT NULL,
              published_at DATETIME NOT NULL,
              is_active TINYINT NOT NULL,
              INDEX idx_blog_post_date (published_at),
              INDEX idx_blog_post_active (is_active),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tag_blog_post (
              blog_post_id INT NOT NULL,
              tag_id INT NOT NULL,
              INDEX IDX_AB6DDA3AA77FBEAF (blog_post_id),
              INDEX IDX_AB6DDA3ABAD26311 (tag_id),
              PRIMARY KEY (blog_post_id, tag_id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
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
            CREATE TABLE picture (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              image_path VARCHAR(255) NOT NULL,
              source_path VARCHAR(255) DEFAULT NULL,
              date DATE NOT NULL,
              position FLOAT NOT NULL,
              is_active TINYINT NOT NULL,
              INDEX idx_picture_date_position (date, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE poem (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(512) DEFAULT NULL,
              content LONGTEXT NOT NULL,
              comment VARCHAR(512) DEFAULT NULL,
              status VARCHAR(20) NOT NULL,
              position FLOAT NOT NULL,
              deleted_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              INDEX idx_poem_status_deleted (status, deleted_at),
              INDEX idx_poem_position (position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE poem_version (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(512) DEFAULT NULL,
              content LONGTEXT NOT NULL,
              comment VARCHAR(512) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              poem_id INT NOT NULL,
              INDEX IDX_A82761758938791B (poem_id),
              INDEX idx_poem_version_poem_date (poem_id, created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE static_text (
              id INT AUTO_INCREMENT NOT NULL,
              slug VARCHAR(100) NOT NULL,
              title VARCHAR(255) NOT NULL,
              content LONGTEXT NOT NULL,
              UNIQUE INDEX UNIQ_A025FE72989D9B62 (slug),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE tag (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(100) NOT NULL,
              UNIQUE INDEX UNIQ_389B7832B36786B (title),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email),
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
        $this->addSql(<<<'SQL'
            CREATE TABLE work (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              text LONGTEXT NOT NULL,
              comment VARCHAR(255) DEFAULT NULL,
              position FLOAT NOT NULL,
              is_active TINYINT NOT NULL,
              likes_count INT NOT NULL,
              dislikes_count INT NOT NULL,
              group_id INT NOT NULL,
              INDEX IDX_534E6880FE54D947 (group_id),
              INDEX idx_work_group_position (group_id, position),
              INDEX idx_work_active (is_active),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE work_comment (
              id INT AUTO_INCREMENT NOT NULL,
              author VARCHAR(100) NOT NULL,
              content LONGTEXT NOT NULL,
              info VARCHAR(255) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              is_active TINYINT NOT NULL,
              work_id INT NOT NULL,
              parent_id INT DEFAULT NULL,
              INDEX IDX_41BFEA4EBB3453DB (work_id),
              INDEX idx_work_comment_work_date (work_id, created_at),
              INDEX idx_work_comment_parent (parent_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE work_group (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(255) NOT NULL,
              comment VARCHAR(255) DEFAULT NULL,
              position FLOAT NOT NULL,
              is_favorite TINYINT NOT NULL,
              is_active TINYINT NOT NULL,
              parent_id INT DEFAULT NULL,
              INDEX IDX_453B3FEA727ACA70 (parent_id),
              INDEX idx_group_parent_position (parent_id, position),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE work_vote (
              id INT AUTO_INCREMENT NOT NULL,
              ip_hash VARCHAR(64) NOT NULL,
              session_hash VARCHAR(64) DEFAULT NULL,
              vote_type VARCHAR(10) NOT NULL,
              created_at DATETIME NOT NULL,
              work_id INT NOT NULL,
              INDEX IDX_A3D3345CBB3453DB (work_id),
              UNIQUE INDEX uniq_work_vote (work_id, ip_hash, session_hash),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              blog_comment
            ADD
              CONSTRAINT FK_7882EFEF4B89032C FOREIGN KEY (post_id) REFERENCES blog_post (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              blog_comment
            ADD
              CONSTRAINT FK_7882EFEF727ACA70 FOREIGN KEY (parent_id) REFERENCES blog_comment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tag_blog_post
            ADD
              CONSTRAINT FK_AB6DDA3AA77FBEAF FOREIGN KEY (blog_post_id) REFERENCES blog_post (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              tag_blog_post
            ADD
              CONSTRAINT FK_AB6DDA3ABAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              poem_version
            ADD
              CONSTRAINT FK_A82761758938791B FOREIGN KEY (poem_id) REFERENCES poem (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work
            ADD
              CONSTRAINT FK_534E6880FE54D947 FOREIGN KEY (group_id) REFERENCES work_group (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_comment
            ADD
              CONSTRAINT FK_41BFEA4EBB3453DB FOREIGN KEY (work_id) REFERENCES work (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_comment
            ADD
              CONSTRAINT FK_41BFEA4E727ACA70 FOREIGN KEY (parent_id) REFERENCES work_comment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_group
            ADD
              CONSTRAINT FK_453B3FEA727ACA70 FOREIGN KEY (parent_id) REFERENCES work_group (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              work_vote
            ADD
              CONSTRAINT FK_A3D3345CBB3453DB FOREIGN KEY (work_id) REFERENCES work (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEF4B89032C');
        $this->addSql('ALTER TABLE blog_comment DROP FOREIGN KEY FK_7882EFEF727ACA70');
        $this->addSql('ALTER TABLE tag_blog_post DROP FOREIGN KEY FK_AB6DDA3AA77FBEAF');
        $this->addSql('ALTER TABLE tag_blog_post DROP FOREIGN KEY FK_AB6DDA3ABAD26311');
        $this->addSql('ALTER TABLE poem_version DROP FOREIGN KEY FK_A82761758938791B');
        $this->addSql('ALTER TABLE work DROP FOREIGN KEY FK_534E6880FE54D947');
        $this->addSql('ALTER TABLE work_comment DROP FOREIGN KEY FK_41BFEA4EBB3453DB');
        $this->addSql('ALTER TABLE work_comment DROP FOREIGN KEY FK_41BFEA4E727ACA70');
        $this->addSql('ALTER TABLE work_group DROP FOREIGN KEY FK_453B3FEA727ACA70');
        $this->addSql('ALTER TABLE work_vote DROP FOREIGN KEY FK_A3D3345CBB3453DB');
        $this->addSql('DROP TABLE audio');
        $this->addSql('DROP TABLE blog_comment');
        $this->addSql('DROP TABLE blog_post');
        $this->addSql('DROP TABLE tag_blog_post');
        $this->addSql('DROP TABLE monster');
        $this->addSql('DROP TABLE picture');
        $this->addSql('DROP TABLE poem');
        $this->addSql('DROP TABLE poem_version');
        $this->addSql('DROP TABLE static_text');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE vozdukh');
        $this->addSql('DROP TABLE work');
        $this->addSql('DROP TABLE work_comment');
        $this->addSql('DROP TABLE work_group');
        $this->addSql('DROP TABLE work_vote');
    }
}
