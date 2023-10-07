<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231006215241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean database for version 4.0.0 (SQLite)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalog_attribute (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(100) DEFAULT \'string\' NOT NULL --(DC2Type:CatalogAttributeTypeType)
        , PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_470546D4E6F81 ON catalog_attribute (address)');
        $this->addSql('CREATE TABLE catalog_category (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , parent_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, description CLOB DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, pagination INTEGER DEFAULT 10 NOT NULL, children BOOLEAN DEFAULT 0 NOT NULL, hidden BOOLEAN DEFAULT 0 NOT NULL, "order" INTEGER DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:CatalogCategoryStatusType)
        , sort CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , meta CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , template CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(64) DEFAULT \'manual\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_349BC7DFEC9C6612 FOREIGN KEY (parent_uuid) REFERENCES catalog_category (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX catalog_category_address_idx ON catalog_category (address)');
        $this->addSql('CREATE INDEX catalog_category_parent_idx ON catalog_category (parent_uuid)');
        $this->addSql('CREATE INDEX catalog_category_order_idx ON catalog_category ("order")');
        $this->addSql('CREATE UNIQUE INDEX catalog_category_unique ON catalog_category (parent_uuid, address, external_id)');
        $this->addSql('CREATE TABLE catalog_category_attributes (category_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , attribute_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , PRIMARY KEY(category_uuid, attribute_uuid), CONSTRAINT FK_1D53E6C95AE42AE1 FOREIGN KEY (category_uuid) REFERENCES catalog_category (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1D53E6C98A97F42E FOREIGN KEY (attribute_uuid) REFERENCES catalog_attribute (uuid) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1D53E6C95AE42AE1 ON catalog_category_attributes (category_uuid)');
        $this->addSql('CREATE INDEX IDX_1D53E6C98A97F42E ON catalog_category_attributes (attribute_uuid)');
        $this->addSql('CREATE TABLE catalog_order (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , status_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , serial VARCHAR(12) DEFAULT \'\' NOT NULL, delivery CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , shipping DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment VARCHAR(1000) DEFAULT \'\' NOT NULL, phone VARCHAR(25) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(64) DEFAULT \'manual\' NOT NULL, system VARCHAR(512) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_4C3AF221ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4C3AF221E979FD32 FOREIGN KEY (status_uuid) REFERENCES reference (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4C3AF221ABFE1C6F ON catalog_order (user_uuid)');
        $this->addSql('CREATE INDEX catalog_order_serial_idx ON catalog_order (serial)');
        $this->addSql('CREATE INDEX catalog_order_status_idx ON catalog_order (status_uuid)');
        $this->addSql('CREATE TABLE catalog_order_product (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , order_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , product_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , price DOUBLE PRECISION DEFAULT \'0\' NOT NULL, price_type VARCHAR(16) DEFAULT \'price\' NOT NULL, count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_59DD3D6B9C8E6AB1 FOREIGN KEY (order_uuid) REFERENCES catalog_order (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_59DD3D6B5C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_59DD3D6B9C8E6AB1 ON catalog_order_product (order_uuid)');
        $this->addSql('CREATE INDEX IDX_59DD3D6B5C977207 ON catalog_order_product (product_uuid)');
        $this->addSql('CREATE TABLE catalog_product (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , category_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(100) DEFAULT \'product\' NOT NULL --(DC2Type:CatalogProductTypeType)
        , description CLOB DEFAULT \'\' NOT NULL, extra CLOB DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, vendorcode CLOB DEFAULT \'\' NOT NULL, barcode CLOB DEFAULT \'\' NOT NULL, tax DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceFirst DOUBLE PRECISION DEFAULT \'0\' NOT NULL, price DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceWholesale DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceWholesaleFrom DOUBLE PRECISION DEFAULT \'0\' NOT NULL, discount DOUBLE PRECISION DEFAULT \'0\' NOT NULL, special BOOLEAN DEFAULT 0 NOT NULL, dimension CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , quantity DOUBLE PRECISION DEFAULT \'1\' NOT NULL, quantityMin DOUBLE PRECISION DEFAULT \'1\' NOT NULL, stock DOUBLE PRECISION DEFAULT \'0\' NOT NULL, country VARCHAR(255) DEFAULT \'\' NOT NULL, manufacturer VARCHAR(255) DEFAULT \'\' NOT NULL, tags CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , "order" INTEGER DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:CatalogProductStatusType)
        , date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, meta CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(64) DEFAULT \'manual\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_DCF8F9815AE42AE1 FOREIGN KEY (category_uuid) REFERENCES catalog_category (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX catalog_product_address_idx ON catalog_product (address)');
        $this->addSql('CREATE INDEX catalog_product_category_idx ON catalog_product (category_uuid)');
        $this->addSql('CREATE INDEX catalog_product_price_idx ON catalog_product (price, priceFirst, priceWholesale)');
        $this->addSql('CREATE INDEX catalog_product_manufacturer_idx ON catalog_product (manufacturer)');
        $this->addSql('CREATE INDEX catalog_product_country_idx ON catalog_product (country)');
        $this->addSql('CREATE INDEX catalog_product_order_idx ON catalog_product ("order")');
        $this->addSql('CREATE UNIQUE INDEX catalog_product_unique ON catalog_product (category_uuid, address, dimension, external_id)');
        $this->addSql('CREATE TABLE catalog_product_attributes (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , product_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , attribute_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , value VARCHAR(1000) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_747A21D55C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_747A21D58A97F42E FOREIGN KEY (attribute_uuid) REFERENCES catalog_attribute (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_747A21D55C977207 ON catalog_product_attributes (product_uuid)');
        $this->addSql('CREATE INDEX IDX_747A21D58A97F42E ON catalog_product_attributes (attribute_uuid)');
        $this->addSql('CREATE TABLE catalog_product_related (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , product_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , related_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_CFAC628F5C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_CFAC628F3A6DF4A3 FOREIGN KEY (related_uuid) REFERENCES catalog_product (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_CFAC628F5C977207 ON catalog_product_related (product_uuid)');
        $this->addSql('CREATE INDEX IDX_CFAC628F3A6DF4A3 ON catalog_product_related (related_uuid)');
        $this->addSql('CREATE TABLE file (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , name VARCHAR(255) DEFAULT \'\' NOT NULL, ext VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(255) DEFAULT \'\' NOT NULL, size INTEGER DEFAULT 0 NOT NULL, salt VARCHAR(255) DEFAULT \'\' NOT NULL, hash VARCHAR(255) DEFAULT \'\' NOT NULL, private BOOLEAN DEFAULT 0 NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE file_related (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , file_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , entity_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , "order" INTEGER DEFAULT 1 NOT NULL, comment CLOB DEFAULT \'\' NOT NULL, object_type VARCHAR(255) NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_3B31C9AB588338C8 FOREIGN KEY (file_uuid) REFERENCES file (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3B31C9AB588338C8 ON file_related (file_uuid)');
        $this->addSql('CREATE INDEX IDX_3B31C9AB99B3E98D ON file_related (entity_uuid)');
        $this->addSql('CREATE TABLE form (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, template CLOB DEFAULT \'\' NOT NULL, templateFile VARCHAR(255) DEFAULT \'\' NOT NULL, recaptcha BOOLEAN DEFAULT 1 NOT NULL, authorSend BOOLEAN DEFAULT 0 NOT NULL, origin CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , mailto CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , duplicate VARCHAR(255) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5288FD4FD4E6F81 ON form (address)');
        $this->addSql('CREATE TABLE form_data (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , form_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , data CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , message CLOB DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE guestbook (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , name VARCHAR(255) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, message CLOB DEFAULT \'\' NOT NULL, response CLOB DEFAULT \'\' NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:GuestBookStatusType)
        , date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE notification (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, message CLOB DEFAULT \'\' NOT NULL, params CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE page (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, content CLOB DEFAULT \'\' NOT NULL, type VARCHAR(100) NOT NULL --(DC2Type:PageTypeType)
        , meta CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , template VARCHAR(255) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_140AB620D4E6F81 ON page (address)');
        $this->addSql('CREATE TABLE params (name VARCHAR(255) DEFAULT \'\' NOT NULL, value CLOB DEFAULT \'\' NOT NULL, PRIMARY KEY(name))');
        $this->addSql('CREATE TABLE publication (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , category_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , address VARCHAR(1000) DEFAULT \'\' NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, content CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , poll CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , meta CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , external_id VARCHAR(255) DEFAULT \'\' NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_AF3C6779ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AF3C67795AE42AE1 FOREIGN KEY (category_uuid) REFERENCES publication_category (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AF3C6779D4E6F81 ON publication (address)');
        $this->addSql('CREATE INDEX IDX_AF3C6779ABFE1C6F ON publication (user_uuid)');
        $this->addSql('CREATE INDEX IDX_AF3C67795AE42AE1 ON publication (category_uuid)');
        $this->addSql('CREATE TABLE publication_category (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , parent_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , address VARCHAR(1000) DEFAULT \'\' NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, description CLOB DEFAULT \'\' NOT NULL, pagination INTEGER DEFAULT 10 NOT NULL, children BOOLEAN DEFAULT 0 NOT NULL, public BOOLEAN DEFAULT 1 NOT NULL, sort CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , meta CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , template CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , PRIMARY KEY(uuid), CONSTRAINT FK_292BEC90EC9C6612 FOREIGN KEY (parent_uuid) REFERENCES publication_category (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_292BEC90D4E6F81 ON publication_category (address)');
        $this->addSql('CREATE INDEX IDX_292BEC90EC9C6612 ON publication_category (parent_uuid)');
        $this->addSql('CREATE TABLE reference (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , type VARCHAR(100) DEFAULT \'text\' NOT NULL --(DC2Type:ReferenceTypeType)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, value CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , "order" INTEGER DEFAULT 1 NOT NULL, status BOOLEAN DEFAULT 0 NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX reference_unique ON reference (type, title)');
        $this->addSql('CREATE TABLE task (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, "action" VARCHAR(255) DEFAULT \'\' NOT NULL, progress DOUBLE PRECISION DEFAULT \'0\' NOT NULL, status VARCHAR(100) DEFAULT \'queue\' NOT NULL --(DC2Type:TaskStatusType)
        , params CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , output VARCHAR(1000) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE user (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , group_uuid CHAR(36) DEFAULT NULL --(DC2Type:uuid)
        , username VARCHAR(64) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, phone VARCHAR(25) DEFAULT \'\' NOT NULL, password VARCHAR(140) DEFAULT \'\' NOT NULL, firstname VARCHAR(64) DEFAULT \'\' NOT NULL, lastname VARCHAR(64) DEFAULT \'\' NOT NULL, patronymic VARCHAR(64) DEFAULT \'\' NOT NULL, birthdate DATE DEFAULT NULL, gender VARCHAR(64) DEFAULT \'\' NOT NULL, country VARCHAR(128) DEFAULT \'\' NOT NULL, city VARCHAR(128) DEFAULT \'\' NOT NULL, address VARCHAR(512) DEFAULT \'\' NOT NULL, postcode VARCHAR(32) DEFAULT \'\' NOT NULL, additional VARCHAR(1000) DEFAULT \'\' NOT NULL, allow_mail BOOLEAN DEFAULT 1 NOT NULL, company CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , legal CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , messenger CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , status VARCHAR(100) DEFAULT \'work\' NOT NULL --(DC2Type:UserStatusType)
        , register DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, "change" DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, website VARCHAR(128) DEFAULT \'\' NOT NULL, source VARCHAR(512) DEFAULT \'\' NOT NULL, auth_code VARCHAR(12) DEFAULT \'\' NOT NULL, language VARCHAR(5) DEFAULT \'\' NOT NULL, external_id VARCHAR(255) DEFAULT \'\' NOT NULL, token CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , PRIMARY KEY(uuid), CONSTRAINT FK_8D93D649F8250BD6 FOREIGN KEY (group_uuid) REFERENCES user_group (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8D93D649F8250BD6 ON user (group_uuid)');
        $this->addSql('CREATE TABLE user_group (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , title VARCHAR(255) DEFAULT \'\' NOT NULL, description CLOB DEFAULT \'\' NOT NULL, access CLOB DEFAULT \'{}\' NOT NULL --(DC2Type:json)
        , PRIMARY KEY(uuid))');
        $this->addSql('CREATE TABLE user_integration (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , provider CLOB DEFAULT \'\' NOT NULL, "unique" VARCHAR(128) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_54F2A40EABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_54F2A40EABFE1C6F ON user_integration (user_uuid)');
        $this->addSql('CREATE UNIQUE INDEX user_provider_unique ON user_integration (user_uuid, provider, "unique")');
        $this->addSql('CREATE TABLE user_subscriber (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , email VARCHAR(120) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A679D85E7927C74 ON user_subscriber (email)');
        $this->addSql('CREATE TABLE user_token (uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , user_uuid CHAR(36) NOT NULL --(DC2Type:uuid)
        , "unique" CLOB DEFAULT \'\' NOT NULL, comment CLOB DEFAULT \'\' NOT NULL, ip VARCHAR(16) DEFAULT \'\' NOT NULL, agent VARCHAR(255) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid), CONSTRAINT FK_BDF55A63ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_BDF55A63ABFE1C6F ON user_token (user_uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE catalog_attribute');
        $this->addSql('DROP TABLE catalog_category');
        $this->addSql('DROP TABLE catalog_category_attributes');
        $this->addSql('DROP TABLE catalog_order');
        $this->addSql('DROP TABLE catalog_order_product');
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
        $this->addSql('DROP TABLE reference');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_group');
        $this->addSql('DROP TABLE user_integration');
        $this->addSql('DROP TABLE user_subscriber');
        $this->addSql('DROP TABLE user_token');
    }
}
