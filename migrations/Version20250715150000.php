<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250715150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add searchWebsite column to brochure_job';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE brochure_job ADD search_website VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE brochure_job DROP search_website');
    }
}

