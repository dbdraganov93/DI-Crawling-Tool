<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250717160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add link prefix and suffix columns to brochure_job';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE brochure_job ADD link_prefix VARCHAR(255) DEFAULT NULL, ADD link_suffix VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE brochure_job DROP link_prefix, DROP link_suffix');
    }
}
