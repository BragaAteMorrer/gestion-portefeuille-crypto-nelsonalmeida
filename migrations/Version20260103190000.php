<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260103190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout prenom/nom/date de naissance sur app_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD first_name VARCHAR(180) DEFAULT NULL, ADD last_name VARCHAR(180) DEFAULT NULL, ADD birth_date DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP first_name, DROP last_name, DROP birth_date');
    }
}
