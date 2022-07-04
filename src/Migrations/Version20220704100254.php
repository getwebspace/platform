<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220704100254 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean database for version 2.0';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalog_attribute (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(500) DEFAULT \'\' NOT NULL, type VARCHAR(100) DEFAULT \'string\' NOT NULL --(DC2Type:CatalogAttributeTypeType)
        , PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_470546D4E6F81 ON catalog_attribute (address)');
        $this->addSql('CREATE TABLE catalog_category (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , parent CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, description CLOB DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, field1 CLOB DEFAULT \'\' NOT NULL, field2 CLOB DEFAULT \'\' NOT NULL, field3 CLOB DEFAULT \'\' NOT NULL, product CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , pagination INTEGER DEFAULT 10 NOT NULL, children BOOLEAN DEFAULT 0 NOT NULL, "order" INTEGER DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:CatalogCategoryStatusType)
        , sort CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , meta CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , template CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(50) DEFAULT \'manual\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX catalog_category_address_idx ON catalog_category (address)');
        $this->addSql('CREATE INDEX catalog_category_parent_idx ON catalog_category (parent)');
        $this->addSql('CREATE INDEX catalog_category_order_idx ON catalog_category ("order")');
        $this->addSql('CREATE UNIQUE INDEX catalog_category_unique ON catalog_category (parent, address, external_id)');
        $this->addSql('CREATE TABLE catalog_category_attributes (category_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , attribute_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , PRIMARY KEY(category_uuid, attribute_uuid))');
        $this->addSql('CREATE INDEX IDX_1D53E6C95AE42AE1 ON catalog_category_attributes (category_uuid)');
        $this->addSql('CREATE INDEX IDX_1D53E6C98A97F42E ON catalog_category_attributes (attribute_uuid)');
        $this->addSql('CREATE TABLE catalog_measure (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, contraction VARCHAR(255) DEFAULT \'\' NOT NULL, value DOUBLE PRECISION DEFAULT \'1\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX catalog_measure_contraction_unique ON catalog_measure (contraction)');
        $this->addSql('CREATE TABLE catalog_order (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , status_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , serial VARCHAR(12) DEFAULT \'\' NOT NULL, delivery CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , shipping DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment VARCHAR(255) DEFAULT \'\' NOT NULL, phone VARCHAR(25) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(50) DEFAULT \'manual\' NOT NULL, system VARCHAR(500) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_4C3AF221ABFE1C6F ON catalog_order (user_uuid)');
        $this->addSql('CREATE INDEX catalog_order_serial_idx ON catalog_order (serial)');
        $this->addSql('CREATE INDEX catalog_order_status_idx ON catalog_order (status_uuid)');
        $this->addSql('CREATE TABLE catalog_order_product (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , order_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , product_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_59DD3D6B9C8E6AB1 ON catalog_order_product (order_uuid)');
        $this->addSql('CREATE INDEX IDX_59DD3D6B5C977207 ON catalog_order_product (product_uuid)');
        $this->addSql('CREATE TABLE catalog_order_status (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, "order" INTEGER DEFAULT 1 NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX catalog_order_status_unique ON catalog_order_status (title)');
        $this->addSql('CREATE TABLE catalog_product (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , category CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(100) DEFAULT \'product\' NOT NULL --(DC2Type:CatalogProductTypeType)
        , description CLOB DEFAULT \'\' NOT NULL, extra CLOB DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, vendorcode CLOB DEFAULT \'\' NOT NULL, barcode CLOB DEFAULT \'\' NOT NULL, tax DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceFirst DOUBLE PRECISION DEFAULT \'0\' NOT NULL, price DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceWholesale DOUBLE PRECISION DEFAULT \'0\' NOT NULL, discount DOUBLE PRECISION DEFAULT \'0\' NOT NULL, special BOOLEAN DEFAULT 0 NOT NULL, dimension CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , volume DOUBLE PRECISION DEFAULT \'1\' NOT NULL, unit VARCHAR(64) DEFAULT \'\' NOT NULL, stock DOUBLE PRECISION DEFAULT \'0\' NOT NULL, field1 CLOB DEFAULT \'\' NOT NULL, field2 CLOB DEFAULT \'\' NOT NULL, field3 CLOB DEFAULT \'\' NOT NULL, field4 CLOB DEFAULT \'\' NOT NULL, field5 CLOB DEFAULT \'\' NOT NULL, country VARCHAR(255) DEFAULT \'\' NOT NULL, manufacturer VARCHAR(255) DEFAULT \'\' NOT NULL, tags CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , "order" INTEGER DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:CatalogProductStatusType)
        , date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, meta CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(50) DEFAULT \'manual\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX catalog_product_address_idx ON catalog_product (address)');
        $this->addSql('CREATE INDEX catalog_product_category_idx ON catalog_product (category)');
        $this->addSql('CREATE INDEX catalog_product_price_idx ON catalog_product (price, priceFirst, priceWholesale)');
        $this->addSql('CREATE INDEX catalog_product_volume_idx ON catalog_product (volume, unit)');
        $this->addSql('CREATE INDEX catalog_product_manufacturer_idx ON catalog_product (manufacturer)');
        $this->addSql('CREATE INDEX catalog_product_country_idx ON catalog_product (country)');
        $this->addSql('CREATE INDEX catalog_product_order_idx ON catalog_product ("order")');
        $this->addSql('CREATE UNIQUE INDEX catalog_product_unique ON catalog_product (category, address, volume, unit, external_id)');
        $this->addSql('CREATE TABLE catalog_product_attributes (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , product_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , attribute_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , value VARCHAR(1000) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_747A21D55C977207 ON catalog_product_attributes (product_uuid)');
        $this->addSql('CREATE INDEX IDX_747A21D58A97F42E ON catalog_product_attributes (attribute_uuid)');
        $this->addSql('CREATE TABLE catalog_product_related (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , product_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , related_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_CFAC628F5C977207 ON catalog_product_related (product_uuid)');
        $this->addSql('CREATE INDEX IDX_CFAC628F3A6DF4A3 ON catalog_product_related (related_uuid)');
        $this->addSql('CREATE TABLE file (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , name VARCHAR(255) DEFAULT \'\' NOT NULL, ext VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(255) DEFAULT \'\' NOT NULL, size INTEGER DEFAULT 0 NOT NULL, salt VARCHAR(255) DEFAULT \'\' NOT NULL, hash VARCHAR(255) DEFAULT \'\' NOT NULL, private BOOLEAN DEFAULT 0 NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE file_related (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , file_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , entity_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , "order" INTEGER DEFAULT 1 NOT NULL, comment CLOB DEFAULT \'\' NOT NULL, object_type VARCHAR(255) NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_3B31C9AB588338C8 ON file_related (file_uuid)');
        $this->addSql('CREATE INDEX IDX_3B31C9AB99B3E98D ON file_related (entity_uuid)');
        $this->addSql('CREATE TABLE form (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, template CLOB DEFAULT \'\' NOT NULL, templateFile VARCHAR(50) DEFAULT \'\' NOT NULL, recaptcha BOOLEAN DEFAULT 1 NOT NULL, authorSend BOOLEAN DEFAULT 0 NOT NULL, origin CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , mailto CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , duplicate VARCHAR(250) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5288FD4FD4E6F81 ON form (address)');
        $this->addSql('CREATE TABLE form_data (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , form_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , message CLOB DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE guestbook (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , name VARCHAR(255) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, message CLOB DEFAULT \'\' NOT NULL, response CLOB DEFAULT \'\' NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:GuestBookStatusType)
        , date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE notification (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, message CLOB DEFAULT \'\' NOT NULL, params CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE page (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, content CLOB DEFAULT \'\' NOT NULL, type VARCHAR(100) NOT NULL --(DC2Type:PageTypeType)
        , meta CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , template VARCHAR(50) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_140AB620D4E6F81 ON page (address)');
        $this->addSql('CREATE TABLE params (name VARCHAR(50) DEFAULT \'\' NOT NULL, value CLOB DEFAULT \'\' NOT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE TABLE publication (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , category_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' --(DC2Type:uuid)
        , address VARCHAR(1000) DEFAULT \'\' NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, content CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , poll CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , meta CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , external_id VARCHAR(255) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF3C6779D4E6F81 ON publication (address)');
        $this->addSql('CREATE INDEX IDX_AF3C6779ABFE1C6F ON publication (user_uuid)');
        $this->addSql('CREATE INDEX IDX_AF3C67795AE42AE1 ON publication (category_uuid)');
        $this->addSql('CREATE TABLE publication_category (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , address VARCHAR(1000) DEFAULT \'\' NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, description CLOB DEFAULT \'\' NOT NULL, parent CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , pagination INTEGER DEFAULT 10 NOT NULL, children BOOLEAN DEFAULT 0 NOT NULL, public BOOLEAN DEFAULT 1 NOT NULL, sort CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , meta CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , template CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_292BEC90D4E6F81 ON publication_category (address)');
        $this->addSql('CREATE TABLE task (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, "action" VARCHAR(255) DEFAULT \'\' NOT NULL, progress DOUBLE PRECISION DEFAULT \'0\' NOT NULL, status VARCHAR(100) DEFAULT \'queue\' NOT NULL --(DC2Type:TaskStatusType)
        , params CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , output VARCHAR(1000) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE user (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , group_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , username VARCHAR(50) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, phone VARCHAR(25) DEFAULT \'\' NOT NULL, password VARCHAR(140) DEFAULT \'\' NOT NULL, firstname VARCHAR(50) DEFAULT \'\' NOT NULL, lastname VARCHAR(50) DEFAULT \'\' NOT NULL, patronymic VARCHAR(50) DEFAULT \'\' NOT NULL, birthdate DATE DEFAULT NULL, gender VARCHAR(25) DEFAULT \'\' NOT NULL, country VARCHAR(100) DEFAULT \'\' NOT NULL, city VARCHAR(100) DEFAULT \'\' NOT NULL, address VARCHAR(500) DEFAULT \'\' NOT NULL, postcode VARCHAR(50) DEFAULT \'\' NOT NULL, additional VARCHAR(250) DEFAULT \'\' NOT NULL, allow_mail BOOLEAN DEFAULT 1 NOT NULL, company CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , legal CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , messenger CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:UserStatusType)
        , register DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, "change" DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, website VARCHAR(100) DEFAULT \'\' NOT NULL, source VARCHAR(500) DEFAULT \'\' NOT NULL, auth_code VARCHAR(12) DEFAULT \'\' NOT NULL, language VARCHAR(5) DEFAULT \'\' NOT NULL, external_id VARCHAR(255) DEFAULT \'\' NOT NULL, token CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_8D93D649F8250BD6 ON user (group_uuid)');
        $this->addSql('CREATE TABLE user_group (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, description CLOB DEFAULT \'\' NOT NULL, access CLOB DEFAULT \'a:0:{}\' NOT NULL --(DC2Type:array)
        , PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE user_integration (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , provider CLOB DEFAULT \'\' NOT NULL, "unique" VARCHAR(20) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE INDEX IDX_54F2A40EABFE1C6F ON user_integration (user_uuid)');
        $this->addSql('CREATE UNIQUE INDEX user_provider_unique ON user_integration (user_uuid, provider, "unique")');
        $this->addSql('CREATE TABLE user_session (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , ip VARCHAR(16) DEFAULT \'\' NOT NULL, agent VARCHAR(256) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX unique_uuid ON user_session (uuid)');
        $this->addSql('CREATE TABLE user_subscriber (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , email VARCHAR(120) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A679D85E7927C74 ON user_subscriber (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE catalog_attribute');
        $this->addSql('DROP TABLE catalog_category');
        $this->addSql('DROP TABLE catalog_category_attributes');
        $this->addSql('DROP TABLE catalog_measure');
        $this->addSql('DROP TABLE catalog_order');
        $this->addSql('DROP TABLE catalog_order_product');
        $this->addSql('DROP TABLE catalog_order_status');
        $this->addSql('DROP TABLE catalog_product');
        $this->addSql('DROP TABLE catalog_product_attributes');
        $this->addSql('DROP TABLE catalog_product_related');
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE file_related');
        $this->addSql('DROP TABLE form');
        $this->addSql('DROP TABLE form_data');
        $this->addSql('DROP TABLE guestbook');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE page');
        $this->addSql('DROP TABLE params');
        $this->addSql('DROP TABLE publication');
        $this->addSql('DROP TABLE publication_category');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_group');
        $this->addSql('DROP TABLE user_integration');
        $this->addSql('DROP TABLE user_session');
        $this->addSql('DROP TABLE user_subscriber');
    }
}
