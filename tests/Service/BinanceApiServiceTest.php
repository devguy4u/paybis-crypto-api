<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BinanceApiException;
use App\Service\BinanceApiService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BinanceApiServiceTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testGetExchangeRateSuccess(): void
    {
        $mockResponse = new MockResponse(json_encode(['price' => '89000.50']));
        $httpClient = new MockHttpClient($mockResponse);
        
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $rate = $service->getExchangeRate('EUR/BTC');
        
        // Rate should be inverted (1 / 89000.50)
        $this->assertEqualsWithDelta(1 / 89000.50, $rate, 0.000000001);
    }

    public function testGetExchangeRateUnsupportedPair(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $this->expectException(BinanceApiException::class);
        $this->expectExceptionMessage('Unsupported pair: EUR/DOGE');
        
        $service->getExchangeRate('EUR/DOGE');
    }

    public function testGetExchangeRateInvalidResponse(): void
    {
        $mockResponse = new MockResponse(json_encode(['invalid' => 'response']));
        $httpClient = new MockHttpClient($mockResponse);
        
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $this->expectException(BinanceApiException::class);
        $this->expectExceptionMessage('Invalid response format: missing price field');
        
        $service->getExchangeRate('EUR/BTC');
    }

    public function testGetExchangeRateInvalidPrice(): void
    {
        $mockResponse = new MockResponse(json_encode(['price' => '0']));
        $httpClient = new MockHttpClient($mockResponse);
        
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $this->expectException(BinanceApiException::class);
        $this->expectExceptionMessage('Invalid price received: 0');
        
        $service->getExchangeRate('EUR/BTC');
    }

    public function testGetAllExchangeRatesSuccess(): void
    {
        $responses = [
            new MockResponse(json_encode(['price' => '89000.50'])), // BTC
            new MockResponse(json_encode(['price' => '3200.75'])),  // ETH
            new MockResponse(json_encode(['price' => '95.25']))     // LTC
        ];
        
        $httpClient = new MockHttpClient($responses);
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $rates = $service->getAllExchangeRates();
        
        $this->assertCount(3, $rates);
        $this->assertArrayHasKey('EUR/BTC', $rates);
        $this->assertArrayHasKey('EUR/ETH', $rates);
        $this->assertArrayHasKey('EUR/LTC', $rates);
        
        $this->assertEqualsWithDelta(1 / 89000.50, $rates['EUR/BTC'], 0.000000001);
        $this->assertEqualsWithDelta(1 / 3200.75, $rates['EUR/ETH'], 0.000000001);
        $this->assertEqualsWithDelta(1 / 95.25, $rates['EUR/LTC'], 0.000000001);
    }

    public function testGetSupportedPairs(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $pairs = $service->getSupportedPairs();
        
        $this->assertEquals(['EUR/BTC', 'EUR/ETH', 'EUR/LTC'], $pairs);
    }

    public function testIsApiAvailableSuccess(): void
    {
        $mockResponse = new MockResponse('{}', ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $this->assertTrue($service->isApiAvailable());
    }

    public function testIsApiAvailableFailure(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        
        $service = new BinanceApiService($httpClient, $this->logger);
        
        $this->assertFalse($service->isApiAvailable());
    }
}
