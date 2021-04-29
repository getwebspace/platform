<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210429132743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add catalog product new field type';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_product ADD type VARCHAR(100) DEFAULT \'product\' NOT NULL COMMENT \'(DC2Type:CatalogProductTypeType)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_product DROP type');
    }
}
