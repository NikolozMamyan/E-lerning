<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Use varied creation dates for existing courses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE course
            SET created_at = CASE id
                WHEN 41 THEN '2026-09-15 14:37:05'
                WHEN 40 THEN '2026-09-03 09:18:42'
                WHEN 39 THEN '2026-08-21 16:52:11'
                WHEN 38 THEN '2026-08-06 11:07:36'
                WHEN 37 THEN '2026-06-12 07:46:58'
                WHEN 36 THEN '2026-05-27 15:23:14'
                WHEN 35 THEN '2026-05-08 10:41:33'
                WHEN 34 THEN '2026-04-19 13:56:27'
                WHEN 33 THEN '2026-04-04 08:32:49'
                WHEN 32 THEN '2026-03-22 17:09:18'
                WHEN 31 THEN '2026-03-07 09:44:52'
                WHEN 30 THEN '2026-02-20 14:12:07'
                WHEN 29 THEN '2026-02-03 11:38:25'
                WHEN 28 THEN '2026-01-24 16:05:43'
                WHEN 27 THEN '2026-01-09 08:57:16'
                WHEN 26 THEN '2025-12-21 13:26:39'
                WHEN 25 THEN '2025-12-04 10:14:51'
                WHEN 24 THEN '2025-11-18 15:48:22'
                WHEN 23 THEN '2025-11-02 09:31:47'
                WHEN 22 THEN '2025-10-23 12:06:35'
                WHEN 21 THEN '2025-10-07 16:42:19'
                WHEN 20 THEN '2025-09-19 08:24:56'
                WHEN 19 THEN '2025-09-02 14:53:08'
                WHEN 18 THEN '2025-08-17 10:36:41'
                WHEN 17 THEN '2025-08-03 17:15:29'
                WHEN 16 THEN '2025-07-22 09:08:54'
                WHEN 15 THEN '2025-07-06 13:47:12'
                WHEN 14 THEN '2025-06-24 16:19:38'
                WHEN 13 THEN '2025-06-08 11:25:03'
                WHEN 12 THEN '2025-05-20 08:51:46'
                WHEN 11 THEN '2025-05-03 15:34:21'
                WHEN 10 THEN '2025-04-18 10:02:57'
                WHEN 9 THEN '2025-04-05 12:43:31'
                WHEN 8 THEN '2025-03-17 09:16:44'
                ELSE created_at
            END
            WHERE id BETWEEN 8 AND 41
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE course
            SET created_at = CASE id
                WHEN 41 THEN '2026-09-15 14:37:05'
                WHEN 40 THEN '2026-09-01 14:37:05'
                WHEN 39 THEN '2026-08-15 14:37:05'
                WHEN 38 THEN '2026-08-01 14:37:05'
                WHEN 37 THEN '2026-06-01 14:37:05'
                WHEN 36 THEN '2026-05-15 14:37:05'
                WHEN 35 THEN '2026-05-01 14:37:05'
                WHEN 34 THEN '2026-04-15 14:37:05'
                WHEN 33 THEN '2026-04-01 14:37:05'
                WHEN 32 THEN '2026-03-15 14:37:05'
                WHEN 31 THEN '2026-03-01 14:37:05'
                WHEN 30 THEN '2026-02-15 14:37:05'
                WHEN 29 THEN '2026-02-01 14:37:05'
                WHEN 28 THEN '2026-01-15 14:37:05'
                WHEN 27 THEN '2026-01-01 14:37:05'
                WHEN 26 THEN '2025-12-15 14:37:05'
                WHEN 25 THEN '2025-12-01 14:37:05'
                WHEN 24 THEN '2025-11-15 14:37:05'
                WHEN 23 THEN '2025-11-01 14:37:05'
                WHEN 22 THEN '2025-10-15 14:37:05'
                WHEN 21 THEN '2025-10-01 14:37:05'
                WHEN 20 THEN '2025-09-15 14:37:05'
                WHEN 19 THEN '2025-09-01 14:37:05'
                WHEN 18 THEN '2025-08-15 14:37:05'
                WHEN 17 THEN '2025-08-01 14:37:05'
                WHEN 16 THEN '2025-07-15 14:37:05'
                WHEN 15 THEN '2025-07-01 14:37:05'
                WHEN 14 THEN '2025-06-15 14:37:05'
                WHEN 13 THEN '2025-06-01 14:37:05'
                WHEN 12 THEN '2025-05-15 14:37:05'
                WHEN 11 THEN '2025-05-01 14:37:05'
                WHEN 10 THEN '2025-04-15 14:37:05'
                WHEN 9 THEN '2025-04-01 14:37:05'
                WHEN 8 THEN '2025-03-15 14:37:05'
                ELSE created_at
            END
            WHERE id BETWEEN 8 AND 41
            SQL);
    }
}
