<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112055548 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE EVENTS ADD court_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE EVENTS ADD CONSTRAINT FK_A37F90CCE3184009 FOREIGN KEY (court_id) REFERENCES COURTS (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A37F90CCE3184009 ON EVENTS (court_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE EVENTS DROP FOREIGN KEY FK_A37F90CCE3184009');
        $this->addSql('DROP INDEX IDX_A37F90CCE3184009 ON EVENTS');
        $this->addSql('ALTER TABLE EVENTS DROP court_id');
    }
}
