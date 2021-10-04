<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210917174531 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publication link to publication_category';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE publication CHANGE category category_uuid CHAR(36) DEFAULT \'00000000-0000-0000-0000-000000000000\' NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C67795AE42AE1 FOREIGN KEY (category_uuid) REFERENCES publication_category (uuid)');
        $this->addSql('CREATE INDEX IDX_AF3C67795AE42AE1 ON publication (category_uuid)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C67795AE42AE1');
        $this->addSql('DROP INDEX IDX_AF3C67795AE42AE1 ON publication');
        $this->addSql('ALTER TABLE publication CHANGE category_uuid category CHAR(36) CHARACTER SET utf8 DEFAULT \'00000000-0000-0000-0000-000000000000\' NOT NULL COLLATE `utf8_unicode_ci` COMMENT \'(DC2Type:uuid)\'');
    }
}
