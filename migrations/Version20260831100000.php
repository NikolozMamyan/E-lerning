<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260831100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add professional experiences to user profiles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE professional_experience (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, title VARCHAR(180) NOT NULL, employment_type VARCHAR(60) DEFAULT NULL, company_name VARCHAR(180) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, is_current TINYINT(1) NOT NULL, location VARCHAR(180) DEFAULT NULL, workplace_type VARCHAR(40) DEFAULT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_PROFESSIONAL_EXPERIENCE_USER (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE professional_experience ADD CONSTRAINT FK_PROFESSIONAL_EXPERIENCE_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE professional_experience');
    }
}
