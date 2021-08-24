<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210823143305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change length of external_id field in catalog';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_category CHANGE external_id external_id VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE catalog_order CHANGE external_id external_id VARCHAR(255) DEFAULT \'\' NOT NULL');
        $this->addSql('ALTER TABLE catalog_product CHANGE external_id external_id VARCHAR(255) DEFAULT \'\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_category CHANGE external_id external_id VARCHAR(50) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE catalog_order CHANGE external_id external_id VARCHAR(50) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`');
        $this->addSql('ALTER TABLE catalog_product CHANGE external_id external_id VARCHAR(50) CHARACTER SET utf8 DEFAULT \'\' NOT NULL COLLATE `utf8_unicode_ci`');
    }
}
