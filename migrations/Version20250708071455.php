<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250708071455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add author column to shopfully tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopfully_preset ADD author VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE shopfully_log ADD author VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopfully_preset DROP author');
        $this->addSql('ALTER TABLE shopfully_log DROP author');
    }
}
