<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220311131024 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user contact fields';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('
            ALTER TABLE user 
                ADD patronymic VARCHAR(50) DEFAULT \'\' NOT NULL, 
                ADD birthdate DATE, 
                ADD gender VARCHAR(25) DEFAULT \'\' NOT NULL, 
                ADD company LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', 
                ADD legal LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', 
                ADD messenger LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', 
                ADD website VARCHAR(100) DEFAULT \'\' NOT NULL, 
                ADD source VARCHAR(500) DEFAULT \'\' NOT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('
            ALTER TABLE user 
                DROP patronymic, 
                DROP birthdate, 
                DROP gender, 
                DROP company, 
                DROP legal, 
                DROP messenger, 
                DROP website, 
                DROP source
        ');
    }
}
