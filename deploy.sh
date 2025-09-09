#!/bin/bash

# Paybis Crypto API Deployment Script
# This script helps with setting up the application

set -e

echo "🚀 Paybis Crypto API Deployment Script"
echo "======================================"

# Check if Docker is available
if command -v docker &> /dev/null && command -v docker-compose &> /dev/null; then
    echo "✅ Docker and Docker Compose found"
    DOCKER_AVAILABLE=true
else
    echo "⚠️  Docker not found, will use manual setup"
    DOCKER_AVAILABLE=false
fi

# Function to setup with Docker
setup_with_docker() {
    echo ""
    echo "🐳 Setting up with Docker..."
    
    # Create .env.local if it doesn't exist
    if [ ! -f .env.local ]; then
        echo "📝 Creating .env.local file..."
        cp .env .env.local
        
        # Generate a random secret
        SECRET=$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | xxd -p)
        sed -i "s/your-secret-key-here-change-in-production/$SECRET/" .env.local
        
        echo "✅ Created .env.local with random secret"
    fi
    
    echo "🏗️  Building and starting containers..."
    docker-compose up -d --build
    
    echo "⏳ Waiting for database to be ready..."
    sleep 10
    
    echo "🗄️  Running database migrations..."
    docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
    
    echo "📊 Testing the API..."
    sleep 5
    curl -s http://localhost:8000/api/rates/pairs | jq . || echo "API response received"
    
    echo ""
    echo "✅ Docker setup complete!"
    echo "🌐 API is available at: http://localhost:8000"
    echo "📊 Test endpoint: http://localhost:8000/api/rates/pairs"
    echo ""
    echo "📋 Useful commands:"
    echo "   docker-compose logs app     # View application logs"
    echo "   docker-compose logs database # View database logs"
    echo "   docker-compose exec app php bin/console app:update-exchange-rates # Update rates manually"
    echo "   docker-compose down         # Stop all services"
}

# Function to setup manually
setup_manually() {
    echo ""
    echo "🔧 Setting up manually..."
    
    # Check PHP version
    if ! command -v php &> /dev/null; then
        echo "❌ PHP not found. Please install PHP 8.3 or higher"
        exit 1
    fi
    
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo "✅ PHP version: $PHP_VERSION"
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        echo "❌ Composer not found. Please install Composer"
        exit 1
    fi
    
    echo "✅ Composer found"
    
    # Install dependencies
    echo "📦 Installing PHP dependencies..."
    composer install --optimize-autoloader
    
    # Create .env.local if it doesn't exist
    if [ ! -f .env.local ]; then
        echo "📝 Creating .env.local file..."
        cp .env .env.local
        
        # Generate a random secret
        SECRET=$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | xxd -p)
        sed -i "s/your-secret-key-here-change-in-production/$SECRET/" .env.local
        
        echo "✅ Created .env.local with random secret"
        echo ""
        echo "⚠️  Please update the DATABASE_URL in .env.local with your MySQL credentials"
        echo "   Current: mysql://crypto_user:crypto_pass@127.0.0.1:3306/paybis_crypto?serverVersion=8.0.32&charset=utf8mb4"
        echo ""
        read -p "Press Enter to continue after updating the database configuration..."
    fi
    
    # Test database connection
    echo "🗄️  Testing database connection..."
    if php bin/console doctrine:schema:validate --skip-sync 2>/dev/null; then
        echo "✅ Database connection successful"
        
        echo "🗄️  Running database migrations..."
        php bin/console doctrine:migrations:migrate --no-interaction
    else
        echo "❌ Database connection failed. Please check your DATABASE_URL in .env.local"
        echo "   Make sure MySQL is running and the database exists"
        exit 1
    fi
    
    # Setup cron job
    echo ""
    echo "⏰ Setting up cron job..."
    CRON_COMMAND="*/5 * * * * cd $(pwd) && php bin/console app:update-exchange-rates >> /var/log/crypto-rates.log 2>&1"
    
    echo "Add this line to your crontab (run 'crontab -e'):"
    echo "$CRON_COMMAND"
    echo ""
    
    # Start development server
    echo "🚀 Starting development server..."
    echo "🌐 API will be available at: http://localhost:8000"
    echo "📊 Test endpoint: http://localhost:8000/api/rates/pairs"
    echo ""
    echo "Press Ctrl+C to stop the server"
    php -S localhost:8000 -t public
}

# Main menu
echo ""
echo "Choose setup method:"
echo "1) Docker (recommended)"
echo "2) Manual setup"
echo "3) Run tests only"
echo "4) Exit"
echo ""

read -p "Enter your choice (1-4): " choice

case $choice in
    1)
        if [ "$DOCKER_AVAILABLE" = true ]; then
            setup_with_docker
        else
            echo "❌ Docker is not available. Please install Docker and Docker Compose first."
            exit 1
        fi
        ;;
    2)
        setup_manually
        ;;
    3)
        echo "🧪 Running tests..."
        if [ -f vendor/bin/phpunit ]; then
            vendor/bin/phpunit
        else
            echo "❌ PHPUnit not found. Please run 'composer install' first."
            exit 1
        fi
        ;;
    4)
        echo "👋 Goodbye!"
        exit 0
        ;;
    *)
        echo "❌ Invalid choice. Please run the script again."
        exit 1
        ;;
esac
