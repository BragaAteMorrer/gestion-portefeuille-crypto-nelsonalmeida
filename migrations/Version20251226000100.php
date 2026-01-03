<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251226000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add price_quote table for cached prices';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform()->getName();

        if ($platform === 'mysql') {
            $this->addSql('CREATE TABLE price_quote (id INT AUTO_INCREMENT NOT NULL, symbol VARCHAR(12) NOT NULL, price NUMERIC(20, 8) NOT NULL, currency VARCHAR(8) NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_6F2DA9585E237E06 (symbol), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            return;
        }

        $this->addSql('CREATE TABLE price_quote (id SERIAL NOT NULL, symbol VARCHAR(12) NOT NULL, price NUMERIC(20, 8) NOT NULL, currency VARCHAR(8) NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6F2DA9585E237E06 ON price_quote (symbol)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE price_quote');
    }
}
