# Paybis Cryptocurrency Exchange Rate API

A Symfony-based web application that provides REST API endpoints for cryptocurrency exchange rates (EUR to BTC, ETH, and LTC) using data from the Binance API.

## Features

-   **Real-time Exchange Rates**: Fetches current rates from Binance API
-   **Periodic Updates**: Stores rates every 5 minutes using cron jobs
-   **REST API Endpoints**:
    -   Get rates for the last 24 hours
    -   Get rates for a specific day
    -   Get latest rates for all pairs
    -   Get supported currency pairs
-   **Production Ready**: Comprehensive error handling, logging, and validation
-   **Docker Support**: Easy deployment with Docker and docker-compose

## Supported Currency Pairs

-   EUR/BTC (Euro to Bitcoin)
-   EUR/ETH (Euro to Ethereum)
-   EUR/LTC (Euro to Litecoin)

## Requirements

-   PHP 8.3+
-   MySQL 8.0+
-   Composer
-   Symfony CLI (recommended for development)
-   Docker & Docker Compose (optional)

## Installation

### Option 1: Docker (Recommended)

1. Clone the repository:

```bash
git clone <repository-url>
cd paybis-crypto-api
```

2. Start the services:

```bash
docker-compose up -d
```

3. Run database migrations:

```bash
docker-compose exec app php bin/console doctrine:migrations:migrate
```

4. Test the API:

```bash
curl http://localhost:8000/api/rates/pairs
```

### Option 2: Advanced Development Setup (Recommended)

1. **Install Symfony CLI:**

```bash
# Download and install Symfony CLI
curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash
sudo apt install symfony-cli
```

2. **Quick setup with Composer scripts:**

```bash
# Install dependencies
composer install

# Configure environment
cp .env .env.local
# Edit .env.local with your database credentials

# Complete setup (creates DB, runs migrations, fetches rates, starts server)
composer setup
```

3. **Or step by step:**

```bash
# Create database
composer db-create

# Run migrations
composer db-migrate

# Fetch initial exchange rates
composer update-rates

# Start development server with Symfony CLI
composer start
# or manually: symfony serve --no-tls
```

### Option 3: Manual Installation (Traditional)

1. Install dependencies:

```bash
composer install
```

2. Configure environment:

```bash
cp .env .env.local
# Edit .env.local with your database credentials
```

3. Create database and run migrations:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

4. Set up cron job:

```bash
# Add to crontab (crontab -e):
*/5 * * * * cd /path/to/paybis-crypto-api && php bin/console app:update-exchange-rates >> var/log/crypto-rates.log 2>&1
```

5. Start the development server:

```bash
php -S localhost:8000 -t public
# or with Symfony CLI: symfony serve --no-tls
```

## API Endpoints

### Get Last 24 Hours Rates

```
GET /api/rates/last-24h?pair=EUR/BTC
```

**Parameters:**

-   `pair` (required): Currency pair (EUR/BTC, EUR/ETH, or EUR/LTC)

**Response:**

```json
{
    "pair": "EUR/BTC",
    "period": "last-24h",
    "count": 288,
    "rates": [
        {
            "rate": 0.000011234,
            "timestamp": "2025-01-08 10:00:00",
            "timestamp_iso": "2025-01-08T10:00:00+00:00"
        }
    ]
}
```

### Get Rates for Specific Day

```
GET /api/rates/day?pair=EUR/BTC&date=2025-01-08
```

**Parameters:**

-   `pair` (required): Currency pair (EUR/BTC, EUR/ETH, or EUR/LTC)
-   `date` (required): Date in YYYY-MM-DD format

**Response:**

```json
{
    "pair": "EUR/BTC",
    "date": "2025-01-08",
    "count": 288,
    "rates": [
        {
            "rate": 0.000011234,
            "timestamp": "2025-01-08 00:00:00",
            "timestamp_iso": "2025-01-08T00:00:00+00:00"
        }
    ]
}
```

### Get Latest Rates

```
GET /api/rates/latest
GET /api/rates/latest?pair=EUR/BTC
```

**Parameters:**

-   `pair` (optional): Specific currency pair

**Response (all pairs):**

```json
{
    "rates": [
        {
            "pair": "EUR/BTC",
            "rate": 0.000011234,
            "timestamp": "2025-01-08 15:30:00",
            "timestamp_iso": "2025-01-08T15:30:00+00:00"
        }
    ],
    "count": 3
}
```

### Get Supported Pairs

```
GET /api/rates/pairs
```

**Response:**

```json
{
    "supported_pairs": ["EUR/BTC", "EUR/ETH", "EUR/LTC"],
    "count": 3
}
```

## Development Commands

### Composer Scripts (Recommended)

```bash
# Start development server
composer start
# or: composer dev

# Stop development server
composer stop

# Update exchange rates
composer update-rates

# Update rates (dry run)
composer update-rates-dry

# Database operations
composer db-create          # Create database
composer db-migrate         # Run migrations
composer db-reset           # Drop, create, and migrate

# Testing
composer test               # Run tests
composer test-coverage      # Run tests with coverage

# Utilities
composer cache-clear        # Clear Symfony cache
composer logs              # Tail development logs

# Complete setup (for new installations)
composer setup             # Creates DB, runs migrations, fetches rates, starts server
```

### Symfony CLI Commands

```bash
# Start development server with Symfony CLI
symfony serve --no-tls

# Stop server
symfony server:stop

# Check requirements
symfony check:requirements

# View server logs
symfony server:log
```

### Traditional Console Commands

```bash
# Update all pairs
php bin/console app:update-exchange-rates

# Update specific pair
php bin/console app:update-exchange-rates --pair=EUR/BTC

# Dry run (don't save to database)
php bin/console app:update-exchange-rates --dry-run

# Database commands
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console cache:clear
```

## Configuration

### Environment Variables

-   `DATABASE_URL`: MySQL connection string
-   `BINANCE_API_BASE_URL`: Binance API base URL (default: https://api.binance.com)
-   `APP_ENV`: Application environment (dev/prod)
-   `APP_SECRET`: Application secret key

### Logging

Logs are written to:

-   Application logs: `var/log/dev.log` (development) or `var/log/prod.log` (production)
-   Cron job logs: `/var/log/crypto-rates.log`

## Error Handling

The API returns standardized error responses:

```json
{
    "error": "Bad Request",
    "message": "Validation failed",
    "timestamp": "2025-01-08T15:30:00+00:00",
    "path": "/api/rates/last-24h"
}
```

## Development

### Running Tests

```bash
php bin/phpunit
```

### Code Quality

```bash
# Check code style
vendor/bin/php-cs-fixer fix --dry-run

# Static analysis
vendor/bin/phpstan analyse
```

## Production Deployment

1. Set environment to production:

```bash
APP_ENV=prod
```

2. Install production dependencies:

```bash
composer install --no-dev --optimize-autoloader
```

3. Clear cache:

```bash
php bin/console cache:clear --env=prod
```

4. Set up proper cron job with full paths
5. Configure proper logging and monitoring
6. Use a proper web server (nginx/Apache) instead of PHP built-in server

## License

This project is proprietary software developed for Paybis.

## Support

For support and questions, please contact the development team.
