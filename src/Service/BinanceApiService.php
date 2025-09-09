<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class BinanceApiService
{
    private const BINANCE_API_BASE_URL = 'https://api.binance.com';
    private const TICKER_ENDPOINT = '/api/v3/ticker/price';
    
    // Mapping from our pairs to Binance symbols
    private const PAIR_MAPPING = [
        'EUR/BTC' => 'BTCEUR',
        'EUR/ETH' => 'ETHEUR',
        'EUR/LTC' => 'LTCEUR',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $binanceApiBaseUrl = self::BINANCE_API_BASE_URL
    ) {
    }

    /**
     * Fetch exchange rate for a specific pair from Binance
     *
     * @throws BinanceApiException
     */
    public function getExchangeRate(string $pair): float
    {
        if (!isset(self::PAIR_MAPPING[$pair])) {
            throw new BinanceApiException("Unsupported pair: {$pair}");
        }

        $symbol = self::PAIR_MAPPING[$pair];
        
        try {
            $this->logger->info('Fetching exchange rate from Binance', [
                'pair' => $pair,
                'symbol' => $symbol
            ]);

            $response = $this->httpClient->request('GET', $this->binanceApiBaseUrl . self::TICKER_ENDPOINT, [
                'query' => ['symbol' => $symbol],
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'PaybisCryptoAPI/1.0'
                ]
            ]);

            $data = $this->handleResponse($response);
            
            if (!isset($data['price'])) {
                throw new BinanceApiException('Invalid response format: missing price field');
            }

            $price = (float) $data['price'];
            
            if ($price <= 0) {
                throw new BinanceApiException('Invalid price received: ' . $price);
            }

            // For EUR/BTC, EUR/ETH, EUR/LTC we need to invert the rate
            // because Binance returns BTC/EUR, ETH/EUR, LTC/EUR
            $rate = 1 / $price;

            $this->logger->info('Successfully fetched exchange rate', [
                'pair' => $pair,
                'binance_price' => $price,
                'calculated_rate' => $rate
            ]);

            return $rate;

        } catch (TransportException $e) {
            $this->logger->error('Transport error while fetching from Binance', [
                'pair' => $pair,
                'error' => $e->getMessage()
            ]);
            throw new BinanceApiException('Network error: ' . $e->getMessage(), 0, $e);
            
        } catch (ClientException | ServerException $e) {
            $this->logger->error('HTTP error while fetching from Binance', [
                'pair' => $pair,
                'status_code' => $e->getResponse()->getStatusCode(),
                'error' => $e->getMessage()
            ]);
            throw new BinanceApiException('API error: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fetch all supported exchange rates
     *
     * @return array<string, float> Array of pair => rate
     * @throws BinanceApiException
     */
    public function getAllExchangeRates(): array
    {
        $rates = [];
        $errors = [];

        foreach (array_keys(self::PAIR_MAPPING) as $pair) {
            try {
                $rates[$pair] = $this->getExchangeRate($pair);
            } catch (BinanceApiException $e) {
                $errors[$pair] = $e->getMessage();
                $this->logger->warning('Failed to fetch rate for pair', [
                    'pair' => $pair,
                    'error' => $e->getMessage()
                ]);
            }
        }

        if (empty($rates) && !empty($errors)) {
            throw new BinanceApiException('Failed to fetch any exchange rates: ' . implode(', ', $errors));
        }

        if (!empty($errors)) {
            $this->logger->warning('Some exchange rates could not be fetched', [
                'successful_pairs' => array_keys($rates),
                'failed_pairs' => array_keys($errors)
            ]);
        }

        return $rates;
    }

    /**
     * Check if Binance API is available
     */
    public function isApiAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->binanceApiBaseUrl . '/api/v3/ping', [
                'timeout' => 5
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (\Exception $e) {
            $this->logger->warning('Binance API availability check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle HTTP response and decode JSON
     *
     * @throws BinanceApiException
     */
    private function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        
        if ($statusCode !== 200) {
            throw new BinanceApiException("HTTP {$statusCode}: " . $response->getContent(false));
        }

        $content = $response->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BinanceApiException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Get supported pairs
     *
     * @return string[]
     */
    public function getSupportedPairs(): array
    {
        return array_keys(self::PAIR_MAPPING);
    }
}
