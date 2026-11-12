<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112052248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add staff/payment foundation fields and backfill existing booking and event join statuses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE COURTS ADD price NUMERIC(10, 2) DEFAULT NULL, ADD owner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE COURTS ADD CONSTRAINT FK_5DF38BA97E3C61F9 FOREIGN KEY (owner_id) REFERENCES USERS (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5DF38BA97E3C61F9 ON COURTS (owner_id)');
        $this->addSql('ALTER TABLE EVENTS ADD entrance_fee NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE EVENT_JOINS ADD status VARCHAR(20) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE USERS ADD account_status VARCHAR(20) DEFAULT \'active\' NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('UPDATE BOOKINGS SET status = CASE WHEN status = \'cancelled\' THEN \'cancelled\' ELSE \'confirmed\' END');
        $this->addSql('UPDATE EVENT_JOINS SET status = \'confirmed\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE COURTS DROP FOREIGN KEY FK_5DF38BA97E3C61F9');
        $this->addSql('DROP INDEX IDX_5DF38BA97E3C61F9 ON COURTS');
        $this->addSql('ALTER TABLE COURTS DROP price, DROP owner_id');
        $this->addSql('ALTER TABLE EVENTS DROP entrance_fee');
        $this->addSql('ALTER TABLE EVENT_JOINS DROP status');
        $this->addSql('ALTER TABLE USERS DROP account_status, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
