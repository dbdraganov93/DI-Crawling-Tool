<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241105120336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE crawler (id INT AUTO_INCREMENT NOT NULL, company_id_id INT NOT NULL, author_id INT NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, source VARCHAR(255) NOT NULL, cron VARCHAR(255) NOT NULL, behaviour VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, script VARCHAR(255) NOT NULL, created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D42E5EA238B53C32 (company_id_id), INDEX IDX_D42E5EA2F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crawler ADD CONSTRAINT FK_D42E5EA238B53C32 FOREIGN KEY (company_id_id) REFERENCES company (id)');
        $this->addSql('ALTER TABLE crawler ADD CONSTRAINT FK_D42E5EA2F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crawler DROP FOREIGN KEY FK_D42E5EA238B53C32');
        $this->addSql('ALTER TABLE crawler DROP FOREIGN KEY FK_D42E5EA2F675F31B');
        $this->addSql('DROP TABLE crawler');
    }
}
