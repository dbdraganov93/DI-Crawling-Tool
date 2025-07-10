<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250708071454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Approve existing users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE `user` SET approved = 1");
    }

    public function down(Schema $schema): void
    {
        // revert users to unapproved
        $this->addSql("UPDATE `user` SET approved = 0");
    }
}
