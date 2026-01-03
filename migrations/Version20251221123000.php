<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251221123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user, portfolio_entry and portfolio_transaction tables';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql') {
            $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C2502824E7927C74 ON app_user (email)');

            $this->addSql('CREATE TABLE portfolio_entry (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, symbol VARCHAR(12) NOT NULL, label VARCHAR(255) NOT NULL, quantity NUMERIC(20, 8) NOT NULL, average_price NUMERIC(20, 4) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX IDX_5E04BE6EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('CREATE TABLE portfolio_transaction (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, symbol VARCHAR(12) NOT NULL, side VARCHAR(10) NOT NULL, quantity NUMERIC(20, 8) NOT NULL, price NUMERIC(20, 4) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_1EDA25C2A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE portfolio_entry ADD CONSTRAINT FK_5E04BE6EA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE portfolio_transaction ADD CONSTRAINT FK_1EDA25C2A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
            return;
        }

        $this->addSql('CREATE TABLE app_user (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, display_name VARCHAR(255) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C2502824E7927C74 ON app_user (email)');

        $this->addSql('CREATE TABLE portfolio_entry (id SERIAL NOT NULL, user_id INT NOT NULL, symbol VARCHAR(12) NOT NULL, label VARCHAR(255) NOT NULL, quantity NUMERIC(20, 8) NOT NULL, average_price NUMERIC(20, 4) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_5E04BE6EA76ED395 ON portfolio_entry (user_id)');
        $this->addSql('ALTER TABLE portfolio_entry ADD CONSTRAINT FK_5E04BE6EA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE portfolio_transaction (id SERIAL NOT NULL, user_id INT NOT NULL, symbol VARCHAR(12) NOT NULL, side VARCHAR(10) NOT NULL, quantity NUMERIC(20, 8) NOT NULL, price NUMERIC(20, 4) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_1EDA25C2A76ED395 ON portfolio_transaction (user_id)');
        $this->addSql('ALTER TABLE portfolio_transaction ADD CONSTRAINT FK_1EDA25C2A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->getDatabasePlatform()->getName() === 'mysql') {
            $this->addSql('ALTER TABLE portfolio_entry DROP FOREIGN KEY FK_5E04BE6EA76ED395');
            $this->addSql('ALTER TABLE portfolio_transaction DROP FOREIGN KEY FK_1EDA25C2A76ED395');
            $this->addSql('DROP TABLE portfolio_entry');
            $this->addSql('DROP TABLE portfolio_transaction');
            $this->addSql('DROP TABLE app_user');
            return;
        }

        $this->addSql('ALTER TABLE portfolio_entry DROP CONSTRAINT FK_5E04BE6EA76ED395');
        $this->addSql('ALTER TABLE portfolio_transaction DROP CONSTRAINT FK_1EDA25C2A76ED395');
        $this->addSql('DROP TABLE portfolio_entry');
        $this->addSql('DROP TABLE portfolio_transaction');
        $this->addSql('DROP TABLE app_user');
    }
}
