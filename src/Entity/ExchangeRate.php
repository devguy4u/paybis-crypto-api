<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExchangeRateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExchangeRateRepository::class)]
#[ORM\Table(name: 'exchange_rates')]
#[ORM\Index(name: 'idx_pair_timestamp', columns: ['pair', 'timestamp'])]
#[ORM\Index(name: 'idx_timestamp', columns: ['timestamp'])]
#[ORM\Index(name: 'idx_pair', columns: ['pair'])]
class ExchangeRate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['EUR/BTC', 'EUR/ETH', 'EUR/LTC'])]
    private string $pair;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 8)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private string $rate;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull]
    private \DateTimeImmutable $timestamp;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPair(): string
    {
        return $this->pair;
    }

    public function setPair(string $pair): static
    {
        $this->pair = $pair;
        return $this;
    }

    public function getRate(): string
    {
        return $this->rate;
    }

    public function setRate(string $rate): static
    {
        $this->rate = $rate;
        return $this;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get the base currency from the pair (e.g., 'EUR' from 'EUR/BTC')
     */
    public function getBaseCurrency(): string
    {
        return explode('/', $this->pair)[0];
    }

    /**
     * Get the quote currency from the pair (e.g., 'BTC' from 'EUR/BTC')
     */
    public function getQuoteCurrency(): string
    {
        return explode('/', $this->pair)[1];
    }

    /**
     * Get the rate as a float for calculations
     */
    public function getRateAsFloat(): float
    {
        return (float) $this->rate;
    }
}
