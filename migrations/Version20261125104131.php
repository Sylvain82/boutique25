<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261125104131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes clients : adresse et lien commande → compte';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande ADD utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6EEAA67DFB88E14F ON commande (utilisateur_id)');
        $this->addSql('ALTER TABLE utilisateur ADD nom VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD adresse VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD code_postal VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE utilisateur ADD ville VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE commande DROP CONSTRAINT FK_6EEAA67DFB88E14F');
        $this->addSql('DROP INDEX IDX_6EEAA67DFB88E14F');
        $this->addSql('ALTER TABLE commande DROP utilisateur_id');
        $this->addSql('ALTER TABLE utilisateur DROP nom');
        $this->addSql('ALTER TABLE utilisateur DROP adresse');
        $this->addSql('ALTER TABLE utilisateur DROP code_postal');
        $this->addSql('ALTER TABLE utilisateur DROP ville');
    }
}
