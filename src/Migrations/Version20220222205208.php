<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220222205208 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change index in catalog product, change default unit value and new measure index';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX catalog_measure_contraction_unique ON catalog_measure (contraction)');
        $this->addSql('DROP INDEX catalog_product_unique ON catalog_product');
        $this->addSql('ALTER TABLE catalog_product CHANGE unit unit VARCHAR(64) DEFAULT \'\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX catalog_product_unique ON catalog_product (category, address, volume, unit, external_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX catalog_measure_contraction_unique ON catalog_measure');
        $this->addSql('DROP INDEX catalog_product_unique ON catalog_product');
        $this->addSql('CREATE UNIQUE INDEX catalog_product_unique ON catalog_product (category, address, external_id)');
        $this->addSql('ALTER TABLE catalog_product CHANGE unit unit VARCHAR(255) CHARACTER SET utf8 DEFAULT \'kg\' NOT NULL');
    }
}
