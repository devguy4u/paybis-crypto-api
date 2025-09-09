<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ExchangeRateRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/rates', name: 'api_rates_')]
class ExchangeRateController extends AbstractController
{
    private const SUPPORTED_PAIRS = ['EUR/BTC', 'EUR/ETH', 'EUR/LTC'];

    public function __construct(
        private readonly ExchangeRateRepository $exchangeRateRepository,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }

    #[Route('/last-24h', name: 'last_24h', methods: ['GET'])]
    public function getLast24Hours(Request $request): JsonResponse
    {
        try {
            $pair = $request->query->get('pair');
            
            // Validate pair parameter
            $violations = $this->validator->validate($pair, [
                new Assert\NotBlank(message: 'Pair parameter is required'),
                new Assert\Choice(
                    choices: self::SUPPORTED_PAIRS,
                    message: 'Invalid pair. Supported pairs: {{ choices }}'
                )
            ]);

            if (count($violations) > 0) {
                return $this->createErrorResponse(
                    'Validation failed',
                    $this->formatValidationErrors($violations),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->logger->info('Fetching last 24h rates', ['pair' => $pair]);

            $rates = $this->exchangeRateRepository->findLast24Hours($pair);

            $data = [
                'pair' => $pair,
                'period' => 'last-24h',
                'count' => count($rates),
                'rates' => array_map(function ($rate) {
                    return [
                        'rate' => (float) $rate->getRate(),
                        'timestamp' => $rate->getTimestamp()->format('Y-m-d H:i:s'),
                        'timestamp_iso' => $rate->getTimestamp()->format(\DateTimeInterface::ATOM)
                    ];
                }, $rates)
            ];

            return $this->json($data);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching last 24h rates', [
                'pair' => $pair ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse(
                'Internal server error',
                'An error occurred while fetching exchange rates',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/day', name: 'day', methods: ['GET'])]
    public function getDay(Request $request): JsonResponse
    {
        try {
            $pair = $request->query->get('pair');
            $dateString = $request->query->get('date');

            // Validate pair parameter
            $pairViolations = $this->validator->validate($pair, [
                new Assert\NotBlank(message: 'Pair parameter is required'),
                new Assert\Choice(
                    choices: self::SUPPORTED_PAIRS,
                    message: 'Invalid pair. Supported pairs: {{ choices }}'
                )
            ]);

            // Validate date parameter
            $dateViolations = $this->validator->validate($dateString, [
                new Assert\NotBlank(message: 'Date parameter is required'),
                new Assert\Regex(
                    pattern: '/^\d{4}-\d{2}-\d{2}$/',
                    message: 'Date must be in YYYY-MM-DD format'
                )
            ]);

            $violations = array_merge(
                iterator_to_array($pairViolations),
                iterator_to_array($dateViolations)
            );

            if (!empty($violations)) {
                return $this->createErrorResponse(
                    'Validation failed',
                    $this->formatValidationErrors($violations),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Parse and validate date
            try {
                $date = new \DateTimeImmutable($dateString);
            } catch (\Exception $e) {
                return $this->createErrorResponse(
                    'Invalid date',
                    'Date must be a valid date in YYYY-MM-DD format',
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Check if date is not in the future
            $today = new \DateTimeImmutable('today');
            if ($date > $today) {
                return $this->createErrorResponse(
                    'Invalid date',
                    'Date cannot be in the future',
                    Response::HTTP_BAD_REQUEST
                );
            }

            $this->logger->info('Fetching rates for specific day', [
                'pair' => $pair,
                'date' => $dateString
            ]);

            $rates = $this->exchangeRateRepository->findByDay($pair, $date);

            $data = [
                'pair' => $pair,
                'date' => $dateString,
                'count' => count($rates),
                'rates' => array_map(function ($rate) {
                    return [
                        'rate' => (float) $rate->getRate(),
                        'timestamp' => $rate->getTimestamp()->format('Y-m-d H:i:s'),
                        'timestamp_iso' => $rate->getTimestamp()->format(\DateTimeInterface::ATOM)
                    ];
                }, $rates)
            ];

            return $this->json($data);

        } catch (\Exception $e) {
            $this->logger->error('Error fetching rates for day', [
                'pair' => $pair ?? 'unknown',
                'date' => $dateString ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->createErrorResponse(
                'Internal server error',
                'An error occurred while fetching exchange rates',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/pairs', name: 'pairs', methods: ['GET'])]
    public function getSupportedPairs(): JsonResponse
    {
        return $this->json([
            'supported_pairs' => self::SUPPORTED_PAIRS,
            'count' => count(self::SUPPORTED_PAIRS)
        ]);
    }

    #[Route('/latest', name: 'latest', methods: ['GET'])]
    public function getLatestRates(Request $request): JsonResponse
    {
        try {
            $pair = $request->query->get('pair');

            if ($pair) {
                // Validate specific pair
                $violations = $this->validator->validate($pair, [
                    new Assert\Choice(
                        choices: self::SUPPORTED_PAIRS,
                        message: 'Invalid pair. Supported pairs: {{ choices }}'
                    )
                ]);

                if (count($violations) > 0) {
                    return $this->createErrorResponse(
                        'Validation failed',
                        $this->formatValidationErrors($violations),
                        Response::HTTP_BAD_REQUEST
                    );
                }

                $rate = $this->exchangeRateRepository->findLatestByPair($pair);
                
                if (!$rate) {
                    return $this->createErrorResponse(
                        'Not found',
                        "No rates found for pair: {$pair}",
                        Response::HTTP_NOT_FOUND
                    );
                }

                return $this->json([
                    'pair' => $pair,
                    'rate' => (float) $rate->getRate(),
                    'timestamp' => $rate->getTimestamp()->format('Y-m-d H:i:s'),
                    'timestamp_iso' => $rate->getTimestamp()->format(\DateTimeInterface::ATOM)
                ]);
            } else {
                // Get latest rates for all pairs
                $latestRates = [];
                foreach (self::SUPPORTED_PAIRS as $supportedPair) {
                    $rate = $this->exchangeRateRepository->findLatestByPair($supportedPair);
                    if ($rate) {
                        $latestRates[] = [
                            'pair' => $supportedPair,
                            'rate' => (float) $rate->getRate(),
                            'timestamp' => $rate->getTimestamp()->format('Y-m-d H:i:s'),
                            'timestamp_iso' => $rate->getTimestamp()->format(\DateTimeInterface::ATOM)
                        ];
                    }
                }

                return $this->json([
                    'rates' => $latestRates,
                    'count' => count($latestRates)
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Error fetching latest rates', [
                'pair' => $pair ?? 'all',
                'error' => $e->getMessage()
            ]);

            return $this->createErrorResponse(
                'Internal server error',
                'An error occurred while fetching latest rates',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    private function createErrorResponse(string $error, string $message, int $statusCode): JsonResponse
    {
        return $this->json([
            'error' => $error,
            'message' => $message,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ], $statusCode);
    }

    private function formatValidationErrors(iterable $violations): string
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = $violation->getMessage();
        }
        return implode(', ', $errors);
    }
}
