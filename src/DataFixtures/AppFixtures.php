<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\PortfolioEntry;
use App\Entity\Transaction;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setDisplayName('Admin');
        $admin->setFirstName('Ada');
        $admin->setLastName('Lovelace');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $user = new User();
        $user->setEmail('demo@example.com');
        $user->setDisplayName('Demo');
        $user->setFirstName('Demo');
        $user->setLastName('User');
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demo1234'));
        $manager->persist($user);

        $btc = new PortfolioEntry();
        $btc->setUser($user);
        $btc->setSymbol('BTC');
        $btc->setLabel('Bitcoin');
        $btc->setQuantity('0.25');
        $btc->setAveragePrice('25000');
        $manager->persist($btc);

        $eth = new PortfolioEntry();
        $eth->setUser($user);
        $eth->setSymbol('ETH');
        $eth->setLabel('Ethereum');
        $eth->setQuantity('2.5');
        $eth->setAveragePrice('1500');
        $manager->persist($eth);

        $tx1 = new Transaction();
        $tx1->setUser($user);
        $tx1->setSymbol('BTC');
        $tx1->setSide('buy');
        $tx1->setQuantity('0.1');
        $tx1->setPrice('24000');
        $manager->persist($tx1);

        $tx2 = new Transaction();
        $tx2->setUser($user);
        $tx2->setSymbol('ETH');
        $tx2->setSide('buy');
        $tx2->setQuantity('1.0');
        $tx2->setPrice('1400');
        $manager->persist($tx2);

        $manager->flush();
    }
}
