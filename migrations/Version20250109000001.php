<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250109000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create exchange_rates table with indexes for cryptocurrency exchange rates';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exchange_rates (
            id INT AUTO_INCREMENT NOT NULL, 
            pair VARCHAR(10) NOT NULL, 
            rate NUMERIC(20, 8) NOT NULL, 
            timestamp DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('CREATE INDEX idx_pair_timestamp ON exchange_rates (pair, timestamp)');
        $this->addSql('CREATE INDEX idx_timestamp ON exchange_rates (timestamp)');
        $this->addSql('CREATE INDEX idx_pair ON exchange_rates (pair)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE exchange_rates');
    }
}
