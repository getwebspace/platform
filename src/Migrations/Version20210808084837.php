<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210808084837 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add catalog_order_product table and remove catalog_order.list field';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalog_order_product (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', order_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', product_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, INDEX IDX_59DD3D6B9C8E6AB1 (order_uuid), INDEX IDX_59DD3D6B5C977207 (product_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalog_order_product ADD CONSTRAINT FK_59DD3D6B9C8E6AB1 FOREIGN KEY (order_uuid) REFERENCES catalog_order (uuid)');
        $this->addSql('ALTER TABLE catalog_order_product ADD CONSTRAINT FK_59DD3D6B5C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid)');
        $this->addSql('ALTER TABLE catalog_order DROP list');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE catalog_order_product');
        $this->addSql('ALTER TABLE catalog_order ADD list LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:array)\'');
    }
}
