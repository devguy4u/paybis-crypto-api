<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ExchangeRate;
use PHPUnit\Framework\TestCase;

class ExchangeRateTest extends TestCase
{
    public function testExchangeRateCreation(): void
    {
        $rate = new ExchangeRate();
        $timestamp = new \DateTimeImmutable();
        
        $rate->setPair('EUR/BTC');
        $rate->setRate('0.000012345');
        $rate->setTimestamp($timestamp);

        $this->assertEquals('EUR/BTC', $rate->getPair());
        $this->assertEquals('0.000012345', $rate->getRate());
        $this->assertEquals($timestamp, $rate->getTimestamp());
        $this->assertInstanceOf(\DateTimeImmutable::class, $rate->getCreatedAt());
    }

    public function testGetBaseCurrency(): void
    {
        $rate = new ExchangeRate();
        $rate->setPair('EUR/BTC');

        $this->assertEquals('EUR', $rate->getBaseCurrency());
    }

    public function testGetQuoteCurrency(): void
    {
        $rate = new ExchangeRate();
        $rate->setPair('EUR/BTC');

        $this->assertEquals('BTC', $rate->getQuoteCurrency());
    }

    public function testGetRateAsFloat(): void
    {
        $rate = new ExchangeRate();
        $rate->setRate('0.000012345');

        $this->assertEquals(0.000012345, $rate->getRateAsFloat());
    }

    public function testFluentInterface(): void
    {
        $rate = new ExchangeRate();
        $timestamp = new \DateTimeImmutable();

        $result = $rate
            ->setPair('EUR/ETH')
            ->setRate('0.000567890')
            ->setTimestamp($timestamp);

        $this->assertSame($rate, $result);
        $this->assertEquals('EUR/ETH', $rate->getPair());
        $this->assertEquals('0.000567890', $rate->getRate());
        $this->assertEquals($timestamp, $rate->getTimestamp());
    }
}
