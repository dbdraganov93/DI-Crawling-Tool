<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250708071457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status tracking to Flipify imports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE flipify_import ADD status VARCHAR(32) NOT NULL DEFAULT 'pending', ADD error_message LONGTEXT DEFAULT NULL, ADD processed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("UPDATE flipify_import SET status = 'completed', processed_at = created_at");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE flipify_import DROP status, DROP error_message, DROP processed_at');
    }
}
