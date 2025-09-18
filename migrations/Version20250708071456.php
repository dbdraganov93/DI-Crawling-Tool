<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250708071456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create flipify_import table to store brochure analyses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE flipify_import (id INT AUTO_INCREMENT NOT NULL, original_filename VARCHAR(255) NOT NULL, stored_filename VARCHAR(255) NOT NULL, company_website VARCHAR(255) DEFAULT NULL, products JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE flipify_import');
    }
}
