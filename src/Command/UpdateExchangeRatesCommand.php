<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ExchangeRate;
use App\Repository\ExchangeRateRepository;
use App\Service\BinanceApiException;
use App\Service\BinanceApiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update-exchange-rates',
    description: 'Fetch and store cryptocurrency exchange rates from Binance API'
)]
class UpdateExchangeRatesCommand extends Command
{
    public function __construct(
        private readonly BinanceApiService $binanceApiService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ExchangeRateRepository $exchangeRateRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'pair',
                'p',
                InputOption::VALUE_OPTIONAL,
                'Specific pair to update (e.g., EUR/BTC). If not specified, all pairs will be updated.'
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Perform a dry run without saving to database'
            )
            ->setHelp('This command fetches exchange rates from Binance API and stores them in the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $specificPair = $input->getOption('pair');
        $isDryRun = $input->getOption('dry-run');

        $io->title('Updating Exchange Rates');

        if ($isDryRun) {
            $io->note('Running in dry-run mode - no data will be saved to database');
        }

        // Check API availability first
        if (!$this->binanceApiService->isApiAvailable()) {
            $io->error('Binance API is not available');
            $this->logger->error('Binance API is not available');
            return Command::FAILURE;
        }

        $timestamp = new \DateTimeImmutable();
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        try {
            if ($specificPair) {
                // Update specific pair
                $result = $this->updateSinglePair($specificPair, $timestamp, $isDryRun);
                if ($result['success']) {
                    $successCount++;
                    $io->success("Updated {$specificPair}: {$result['rate']}");
                } else {
                    $errorCount++;
                    $errors[] = $result['error'];
                    $io->error("Failed to update {$specificPair}: {$result['error']}");
                }
            } else {
                // Update all pairs
                $rates = $this->binanceApiService->getAllExchangeRates();
                
                foreach ($rates as $pair => $rate) {
                    $result = $this->savePairRate($pair, $rate, $timestamp, $isDryRun);
                    if ($result['success']) {
                        $successCount++;
                        $io->writeln("✓ {$pair}: {$rate}");
                    } else {
                        $errorCount++;
                        $errors[] = $result['error'];
                        $io->error("Failed to save {$pair}: {$result['error']}");
                    }
                }
            }

            if (!$isDryRun && $successCount > 0) {
                $this->entityManager->flush();
            }

        } catch (BinanceApiException $e) {
            $io->error('Binance API error: ' . $e->getMessage());
            $this->logger->error('Binance API error in update command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $io->error('Unexpected error: ' . $e->getMessage());
            $this->logger->error('Unexpected error in update command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }

        // Summary
        $io->section('Summary');
        $io->writeln("Successfully updated: {$successCount} pairs");
        
        if ($errorCount > 0) {
            $io->writeln("Errors: {$errorCount}");
            foreach ($errors as $error) {
                $io->writeln("  - {$error}");
            }
        }

        $this->logger->info('Exchange rates update completed', [
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'dry_run' => $isDryRun,
            'timestamp' => $timestamp->format('Y-m-d H:i:s')
        ]);

        return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function updateSinglePair(string $pair, \DateTimeImmutable $timestamp, bool $isDryRun): array
    {
        try {
            $rate = $this->binanceApiService->getExchangeRate($pair);
            return $this->savePairRate($pair, $rate, $timestamp, $isDryRun);
        } catch (BinanceApiException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function savePairRate(string $pair, float $rate, \DateTimeImmutable $timestamp, bool $isDryRun): array
    {
        try {
            if (!$isDryRun) {
                $exchangeRate = new ExchangeRate();
                $exchangeRate->setPair($pair);
                $exchangeRate->setRate((string) $rate);
                $exchangeRate->setTimestamp($timestamp);

                $this->exchangeRateRepository->save($exchangeRate);
            }

            return ['success' => true, 'rate' => $rate];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
