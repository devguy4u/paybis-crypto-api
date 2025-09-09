<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExchangeRateControllerTest extends WebTestCase
{
    public function testGetSupportedPairs(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/pairs');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('supported_pairs', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertEquals(['EUR/BTC', 'EUR/ETH', 'EUR/LTC'], $data['supported_pairs']);
        $this->assertEquals(3, $data['count']);
    }

    public function testGetLast24HoursWithoutPair(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/last-24h');

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testGetLast24HoursWithInvalidPair(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/last-24h?pair=INVALID');

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testGetLast24HoursWithValidPair(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/last-24h?pair=EUR/BTC');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('pair', $data);
        $this->assertArrayHasKey('period', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('rates', $data);
        
        $this->assertEquals('EUR/BTC', $data['pair']);
        $this->assertEquals('last-24h', $data['period']);
        $this->assertIsArray($data['rates']);
    }

    public function testGetDayWithoutParameters(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/day');

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testGetDayWithInvalidDate(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/day?pair=EUR/BTC&date=invalid-date');

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testGetDayWithFutureDate(): void
    {
        $client = static::createClient();
        $futureDate = (new \DateTime('+1 day'))->format('Y-m-d');
        $client->request('GET', "/api/rates/day?pair=EUR/BTC&date={$futureDate}");

        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Invalid date', $data['error']);
        $this->assertStringContains('cannot be in the future', $data['message']);
    }

    public function testGetDayWithValidParameters(): void
    {
        $client = static::createClient();
        $yesterday = (new \DateTime('-1 day'))->format('Y-m-d');
        $client->request('GET', "/api/rates/day?pair=EUR/BTC&date={$yesterday}");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('pair', $data);
        $this->assertArrayHasKey('date', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('rates', $data);
        
        $this->assertEquals('EUR/BTC', $data['pair']);
        $this->assertEquals($yesterday, $data['date']);
        $this->assertIsArray($data['rates']);
    }

    public function testGetLatestRatesAll(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/latest');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('rates', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertIsArray($data['rates']);
    }

    public function testGetLatestRatesSpecificPair(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/rates/latest?pair=EUR/BTC');

        // This might return 404 if no data exists, which is acceptable
        $this->assertThat(
            $client->getResponse()->getStatusCode(),
            $this->logicalOr(
                $this->equalTo(200),
                $this->equalTo(404)
            )
        );
        
        $this->assertResponseHeaderSame('content-type', 'application/json');
    }
}
