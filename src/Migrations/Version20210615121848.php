<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210615121848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_integration table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_integration (uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_uuid CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', provider LONGTEXT NOT NULL, `unique` VARCHAR(20) DEFAULT \'\' NOT NULL, date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_54F2A40EABFE1C6F (user_uuid), UNIQUE INDEX user_provider_unique (user_uuid, provider, unique), PRIMARY KEY(uuid)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_integration ADD CONSTRAINT FK_54F2A40EABFE1C6F FOREIGN KEY (user_uuid) REFERENCES user (uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_integration');
    }
}
