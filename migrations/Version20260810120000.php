<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Italian video and quiz content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video ADD url_it VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz_question ADD question_it TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE quiz_answer ADD text_it VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE video DROP url_it');
        $this->addSql('ALTER TABLE quiz_question DROP question_it');
        $this->addSql('ALTER TABLE quiz_answer DROP text_it');
    }
}
