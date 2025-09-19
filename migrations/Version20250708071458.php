<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250708071458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add processing_started_at column for Flipify imports to track worker claims';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE flipify_import ADD processing_started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flipify_import DROP processing_started_at');
    }
}
