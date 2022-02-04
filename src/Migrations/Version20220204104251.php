<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220204104251 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean db structure for ver.2.0';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE catalog_attribute (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(500) DEFAULT \'\' NOT NULL, type VARCHAR(100) DEFAULT \'string\' NOT NULL COMMENT \'(DC2Type:CatalogAttributeTypeType)\', UNIQUE INDEX UNIQ_470546D4E6F81 (address), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_category (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', parent CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, description TEXT NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, field1 LONGTEXT NOT NULL, field2 LONGTEXT NOT NULL, field3 LONGTEXT NOT NULL, product LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', pagination INT DEFAULT 10 NOT NULL, children TINYINT(1) DEFAULT 0 NOT NULL, `order` INT DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL COMMENT \'(DC2Type:CatalogCategoryStatusType)\', sort LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', meta LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', template LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(50) DEFAULT \'manual\' NOT NULL, INDEX catalog_category_address_idx (address), INDEX catalog_category_parent_idx (parent), INDEX catalog_category_order_idx (`order`), UNIQUE INDEX catalog_category_unique (parent, address, external_id), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_category_attributes (category_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', attribute_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_1D53E6C95AE42AE1 (category_uuid), INDEX IDX_1D53E6C98A97F42E (attribute_uuid), PRIMARY KEY(category_uuid, attribute_uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_measure (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, contraction VARCHAR(255) DEFAULT \'\' NOT NULL, value DOUBLE PRECISION DEFAULT \'1\' NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_order (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', serial VARCHAR(7) DEFAULT \'\' NOT NULL, delivery LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', shipping DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment VARCHAR(255) DEFAULT \'\' NOT NULL, phone VARCHAR(25) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, status VARCHAR(100) DEFAULT \'new\' NOT NULL COMMENT \'(DC2Type:CatalogOrderStatusType)\', date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(50) DEFAULT \'manual\' NOT NULL, system VARCHAR(500) DEFAULT \'\' NOT NULL, INDEX IDX_4C3AF221ABFE1C6F (user_uuid), INDEX catalog_order_status_idx (status), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_order_product (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', order_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', product_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, INDEX IDX_59DD3D6B9C8E6AB1 (order_uuid), INDEX IDX_59DD3D6B5C977207 (product_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_product (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', category CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(100) DEFAULT \'product\' NOT NULL COMMENT \'(DC2Type:CatalogProductTypeType)\', description TEXT NOT NULL, extra TEXT NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, vendorcode LONGTEXT NOT NULL, barcode LONGTEXT NOT NULL, tax DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceFirst DOUBLE PRECISION DEFAULT \'0\' NOT NULL, price DOUBLE PRECISION DEFAULT \'0\' NOT NULL, priceWholesale DOUBLE PRECISION DEFAULT \'0\' NOT NULL, special TINYINT(1) DEFAULT 0 NOT NULL, volume DOUBLE PRECISION DEFAULT \'1\' NOT NULL, unit VARCHAR(255) DEFAULT \'kg\' NOT NULL, stock DOUBLE PRECISION DEFAULT \'0\' NOT NULL, field1 LONGTEXT NOT NULL, field2 LONGTEXT NOT NULL, field3 LONGTEXT NOT NULL, field4 LONGTEXT NOT NULL, field5 LONGTEXT NOT NULL, country VARCHAR(255) DEFAULT \'\' NOT NULL, manufacturer VARCHAR(255) DEFAULT \'\' NOT NULL, tags LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', `order` INT DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL COMMENT \'(DC2Type:CatalogProductStatusType)\', date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, meta LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', external_id VARCHAR(255) DEFAULT \'\' NOT NULL, export VARCHAR(50) DEFAULT \'manual\' NOT NULL, INDEX catalog_product_address_idx (address), INDEX catalog_product_category_idx (category), INDEX catalog_product_price_idx (price, priceFirst, priceWholesale), INDEX catalog_product_volume_idx (volume, unit), INDEX catalog_product_manufacturer_idx (manufacturer), INDEX catalog_product_country_idx (country), INDEX catalog_product_order_idx (`order`), UNIQUE INDEX catalog_product_unique (category, address, external_id), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_product_attributes (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', product_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', attribute_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', value VARCHAR(1000) DEFAULT \'\' NOT NULL, INDEX IDX_747A21D55C977207 (product_uuid), INDEX IDX_747A21D58A97F42E (attribute_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE catalog_product_related (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', product_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', related_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', count DOUBLE PRECISION DEFAULT \'1\' NOT NULL, INDEX IDX_CFAC628F5C977207 (product_uuid), INDEX IDX_CFAC628F3A6DF4A3 (related_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE file (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) DEFAULT \'\' NOT NULL, ext VARCHAR(255) DEFAULT \'\' NOT NULL, type VARCHAR(255) DEFAULT \'\' NOT NULL, size INT DEFAULT 0 NOT NULL, salt VARCHAR(255) DEFAULT \'\' NOT NULL, hash VARCHAR(255) DEFAULT \'\' NOT NULL, private TINYINT(1) DEFAULT 0 NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE file_related (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', file_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', entity_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', `order` INT DEFAULT 1 NOT NULL, comment TEXT NOT NULL, object_type VARCHAR(255) NOT NULL, INDEX IDX_3B31C9AB588338C8 (file_uuid), INDEX IDX_3B31C9AB99B3E98D (entity_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE form (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, template LONGTEXT NOT NULL, recaptcha TINYINT(1) DEFAULT 1 NOT NULL, authorSend TINYINT(1) DEFAULT 0 NOT NULL, origin LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', mailto LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', duplicate VARCHAR(250) DEFAULT \'\' NOT NULL, UNIQUE INDEX UNIQ_5288FD4FD4E6F81 (address), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE form_data (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', form_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL COMMENT \'(DC2Type:uuid)\', message LONGTEXT NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE guestbook (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, message LONGTEXT NOT NULL, response LONGTEXT NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL COMMENT \'(DC2Type:GuestBookStatusType)\', date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, message LONGTEXT NOT NULL, params LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE page (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, address VARCHAR(1000) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, content LONGTEXT NOT NULL, type VARCHAR(100) NOT NULL COMMENT \'(DC2Type:PageTypeType)\', meta LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', template VARCHAR(50) DEFAULT \'\' NOT NULL, UNIQUE INDEX UNIQ_140AB620D4E6F81 (address), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE params (name VARCHAR(50) DEFAULT \'\' NOT NULL, value TEXT NOT NULL, PRIMARY KEY(name)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', category_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' COMMENT \'(DC2Type:uuid)\', address VARCHAR(1000) DEFAULT \'\' NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, content LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', poll LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', meta LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', external_id VARCHAR(255) DEFAULT \'\' NOT NULL, UNIQUE INDEX UNIQ_AF3C6779D4E6F81 (address), INDEX IDX_AF3C6779ABFE1C6F (user_uuid), INDEX IDX_AF3C67795AE42AE1 (category_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE publication_category (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', address VARCHAR(1000) DEFAULT \'\' NOT NULL, title VARCHAR(255) DEFAULT \'\' NOT NULL, description TEXT NOT NULL, parent CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL COMMENT \'(DC2Type:uuid)\', pagination INT DEFAULT 10 NOT NULL, children TINYINT(1) DEFAULT 0 NOT NULL, public TINYINT(1) DEFAULT 1 NOT NULL, sort LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', meta LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', template LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', UNIQUE INDEX UNIQ_292BEC90D4E6F81 (address), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, action VARCHAR(255) DEFAULT \'\' NOT NULL, progress DOUBLE PRECISION DEFAULT \'0\' NOT NULL, status VARCHAR(100) DEFAULT \'queue\' NOT NULL COMMENT \'(DC2Type:TaskStatusType)\', params LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', output VARCHAR(1000) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', group_uuid CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', username VARCHAR(50) DEFAULT \'\' NOT NULL, email VARCHAR(120) DEFAULT \'\' NOT NULL, phone VARCHAR(25) DEFAULT \'\' NOT NULL, password VARCHAR(140) DEFAULT \'\' NOT NULL, firstname VARCHAR(50) DEFAULT \'\' NOT NULL, lastname VARCHAR(50) DEFAULT \'\' NOT NULL, address VARCHAR(500) DEFAULT \'\' NOT NULL, additional VARCHAR(250) DEFAULT \'\' NOT NULL, allow_mail TINYINT(1) DEFAULT 1 NOT NULL, status VARCHAR(100) DEFAULT \'work\' NOT NULL COMMENT \'(DC2Type:UserStatusType)\', register DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, `change` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, auth_code VARCHAR(12) DEFAULT \'\' NOT NULL, external_id VARCHAR(255) DEFAULT \'\' NOT NULL, token LONGTEXT DEFAULT \'a:0:{}\' NOT NULL COMMENT \'(DC2Type:array)\', UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), INDEX IDX_8D93D649F8250BD6 (group_uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_group (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', title VARCHAR(255) DEFAULT \'\' NOT NULL, description TEXT NOT NULL, access LONGTEXT DEFAULT \'a:0:{}\' NOT NULL COMMENT \'(DC2Type:array)\', PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_integration (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', provider LONGTEXT NOT NULL, `unique` VARCHAR(20) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_54F2A40EABFE1C6F (user_uuid), UNIQUE INDEX user_provider_unique (user_uuid, provider, `unique`), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_session (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', ip VARCHAR(16) DEFAULT \'\' NOT NULL, agent VARCHAR(256) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX unique_uuid (uuid), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_subscriber (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', email VARCHAR(120) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX UNIQ_A679D85E7927C74 (email), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE catalog_category_attributes ADD CONSTRAINT FK_1D53E6C95AE42AE1 FOREIGN KEY (category_uuid) REFERENCES catalog_category (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_category_attributes ADD CONSTRAINT FK_1D53E6C98A97F42E FOREIGN KEY (attribute_uuid) REFERENCES catalog_attribute (uuid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE catalog_order ADD CONSTRAINT FK_4C3AF221ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid)');
        $this->addSql('ALTER TABLE catalog_order_product ADD CONSTRAINT FK_59DD3D6B9C8E6AB1 FOREIGN KEY (order_uuid) REFERENCES catalog_order (uuid)');
        $this->addSql('ALTER TABLE catalog_order_product ADD CONSTRAINT FK_59DD3D6B5C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid)');
        $this->addSql('ALTER TABLE catalog_product_attributes ADD CONSTRAINT FK_747A21D55C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid)');
        $this->addSql('ALTER TABLE catalog_product_attributes ADD CONSTRAINT FK_747A21D58A97F42E FOREIGN KEY (attribute_uuid) REFERENCES catalog_attribute (uuid)');
        $this->addSql('ALTER TABLE catalog_product_related ADD CONSTRAINT FK_CFAC628F5C977207 FOREIGN KEY (product_uuid) REFERENCES catalog_product (uuid)');
        $this->addSql('ALTER TABLE catalog_product_related ADD CONSTRAINT FK_CFAC628F3A6DF4A3 FOREIGN KEY (related_uuid) REFERENCES catalog_product (uuid)');
        $this->addSql('ALTER TABLE file_related ADD CONSTRAINT FK_3B31C9AB588338C8 FOREIGN KEY (file_uuid) REFERENCES file (uuid)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C6779ABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C67795AE42AE1 FOREIGN KEY (category_uuid) REFERENCES publication_category (uuid)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649F8250BD6 FOREIGN KEY (group_uuid) REFERENCES user_group (uuid)');
        $this->addSql('ALTER TABLE user_integration ADD CONSTRAINT FK_54F2A40EABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid)');
        $this->addSql('ALTER TABLE user_session ADD CONSTRAINT FK_8849CBDED17F50A6 FOREIGN KEY (uuid) REFERENCES user (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE catalog_category_attributes DROP FOREIGN KEY FK_1D53E6C98A97F42E');
        $this->addSql('ALTER TABLE catalog_product_attributes DROP FOREIGN KEY FK_747A21D58A97F42E');
        $this->addSql('ALTER TABLE catalog_category_attributes DROP FOREIGN KEY FK_1D53E6C95AE42AE1');
        $this->addSql('ALTER TABLE catalog_order_product DROP FOREIGN KEY FK_59DD3D6B9C8E6AB1');
        $this->addSql('ALTER TABLE catalog_order_product DROP FOREIGN KEY FK_59DD3D6B5C977207');
        $this->addSql('ALTER TABLE catalog_product_attributes DROP FOREIGN KEY FK_747A21D55C977207');
        $this->addSql('ALTER TABLE catalog_product_related DROP FOREIGN KEY FK_CFAC628F5C977207');
        $this->addSql('ALTER TABLE catalog_product_related DROP FOREIGN KEY FK_CFAC628F3A6DF4A3');
        $this->addSql('ALTER TABLE file_related DROP FOREIGN KEY FK_3B31C9AB588338C8');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C67795AE42AE1');
        $this->addSql('ALTER TABLE catalog_order DROP FOREIGN KEY FK_4C3AF221ABFE1C6F');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C6779ABFE1C6F');
        $this->addSql('ALTER TABLE user_integration DROP FOREIGN KEY FK_54F2A40EABFE1C6F');
        $this->addSql('ALTER TABLE user_session DROP FOREIGN KEY FK_8849CBDED17F50A6');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649F8250BD6');
        $this->addSql('DROP TABLE catalog_attribute');
        $this->addSql('DROP TABLE catalog_category');
        $this->addSql('DROP TABLE catalog_category_attributes');
        $this->addSql('DROP TABLE catalog_measure');
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
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_group');
        $this->addSql('DROP TABLE user_integration');
        $this->addSql('DROP TABLE user_session');
        $this->addSql('DROP TABLE user_subscriber');
    }
}
