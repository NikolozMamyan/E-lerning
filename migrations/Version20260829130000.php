<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add community job offers, CV applications, events and registrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE job_offer (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, is_active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE community_event (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, starts_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', location VARCHAR(180) DEFAULT NULL, is_published TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_COMMUNITY_EVENT_START (starts_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE job_application (id INT AUTO_INCREMENT NOT NULL, job_offer_id INT NOT NULL, applicant_id INT NOT NULL, cv_filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_JOB_APPLICATION_OFFER (job_offer_id), INDEX IDX_JOB_APPLICATION_USER (applicant_id), UNIQUE INDEX uniq_job_application_member (job_offer_id, applicant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE community_event_registration (id INT AUTO_INCREMENT NOT NULL, community_event_id INT NOT NULL, member_id INT NOT NULL, registered_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EVENT_REGISTRATION_EVENT (community_event_id), INDEX IDX_EVENT_REGISTRATION_MEMBER (member_id), UNIQUE INDEX uniq_community_event_member (community_event_id, member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_JOB_APPLICATION_OFFER FOREIGN KEY (job_offer_id) REFERENCES job_offer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_application ADD CONSTRAINT FK_JOB_APPLICATION_USER FOREIGN KEY (applicant_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_event_registration ADD CONSTRAINT FK_EVENT_REGISTRATION_EVENT FOREIGN KEY (community_event_id) REFERENCES community_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_event_registration ADD CONSTRAINT FK_EVENT_REGISTRATION_MEMBER FOREIGN KEY (member_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE community_event_registration');
        $this->addSql('DROP TABLE job_application');
        $this->addSql('DROP TABLE community_event');
        $this->addSql('DROP TABLE job_offer');
    }
}
