<?php

namespace App\Entity;

use App\Repository\PriceHistoryRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PriceHistoryRepository::class)]
class PriceHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 12)]
    private string $symbol = '';

    #[ORM\Column(type: 'decimal', precision: 20, scale: 8)]
    private string $price = '0';

    #[ORM\Column(length: 8)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $collectedAt;

    public function __construct()
    {
        $this->collectedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = strtoupper($symbol);
        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function setCollectedAt(DateTimeImmutable $collectedAt): self
    {
        $this->collectedAt = $collectedAt;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getCollectedAt(): DateTimeImmutable
    {
        return $this->collectedAt;
    }
}
