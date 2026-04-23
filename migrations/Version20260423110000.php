<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260423110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add duration_label and trainer_name to certificate table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE certificate ADD duration_label VARCHAR(255) DEFAULT NULL, ADD trainer_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE certificate DROP duration_label, DROP trainer_name');
    }
}
