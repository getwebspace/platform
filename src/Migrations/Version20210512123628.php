<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210512123628 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unique from email and change default value user_uuid';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_order CHANGE user_uuid user_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE publication CHANGE user_uuid user_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('DROP INDEX UNIQ_88704138E7927C74 ON guestbook');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_order CHANGE user_uuid user_uuid CHAR(36) CHARACTER SET utf8 DEFAULT \'00000000-0000-0000-0000-000000000000\' COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE publication CHANGE user_uuid user_uuid CHAR(36) CHARACTER SET utf8 DEFAULT \'00000000-0000-0000-0000-000000000000\' COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88704138E7927C74 ON guestbook (email)');
    }
}
