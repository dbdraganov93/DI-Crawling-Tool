<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250708071452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE company (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, iproto_id INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE crawler (id INT AUTO_INCREMENT NOT NULL, company_id_id INT NOT NULL, author_id INT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, source VARCHAR(255) NOT NULL, cron VARCHAR(255) NOT NULL, behaviour VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, script VARCHAR(255) NOT NULL, created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D42E5EA238B53C32 (company_id_id), INDEX IDX_D42E5EA2F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE iproto_token (id INT AUTO_INCREMENT NOT NULL, token LONGTEXT NOT NULL, token_type VARCHAR(255) NOT NULL, scope LONGTEXT NOT NULL, expires_in VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shopfully_log (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, company_name VARCHAR(255) NOT NULL, iproto_id INT NOT NULL, locale VARCHAR(255) NOT NULL, data JSON NOT NULL, status VARCHAR(255) DEFAULT NULL, notices_count INT NOT NULL, warnings_count INT NOT NULL, errors_count INT NOT NULL, import_type VARCHAR(255) NOT NULL, import_id INT DEFAULT NULL, reimport_count INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE shopfully_preset (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, scheduled_at DATETIME NOT NULL, executed_at DATETIME DEFAULT NULL, data JSON NOT NULL, status VARCHAR(50) NOT NULL, error_message LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, two_factor_secret VARCHAR(32) DEFAULT NULL, two_factor_enabled TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crawler ADD CONSTRAINT FK_D42E5EA238B53C32 FOREIGN KEY (company_id_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE crawler ADD CONSTRAINT FK_D42E5EA2F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crawler DROP FOREIGN KEY FK_D42E5EA238B53C32');
        $this->addSql('ALTER TABLE crawler DROP FOREIGN KEY FK_D42E5EA2F675F31B');
        $this->addSql('DROP TABLE company');
        $this->addSql('DROP TABLE crawler');
        $this->addSql('DROP TABLE iproto_token');
        $this->addSql('DROP TABLE shopfully_log');
        $this->addSql('DROP TABLE shopfully_preset');
        $this->addSql('DROP TABLE `user`');
    }
}
