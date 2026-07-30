<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260715130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized SCORM video files and titles to video';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video ADD scorm_fr VARCHAR(255) DEFAULT NULL, ADD scorm_en VARCHAR(255) DEFAULT NULL, ADD scorm_de VARCHAR(255) DEFAULT NULL, ADD scorm_title_fr VARCHAR(255) DEFAULT NULL, ADD scorm_title_en VARCHAR(255) DEFAULT NULL, ADD scorm_title_de VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP scorm_fr, DROP scorm_en, DROP scorm_de, DROP scorm_title_fr, DROP scorm_title_en, DROP scorm_title_de');
    }
}
