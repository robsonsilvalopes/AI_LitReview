#!/bin/bash

# WSL Setup Script for AILitReview
# This script helps set up the development environment in WSL

set -e

echo "🚀 Setting up AILitReview in WSL..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if running in WSL
if ! grep -qEi "(Microsoft|WSL)" /proc/version &> /dev/null ; then
    echo -e "${YELLOW}Warning: This doesn't appear to be running in WSL${NC}"
fi

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}Docker is not installed. Please install Docker Desktop with WSL 2 backend.${NC}"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo -e "${RED}Docker is not running. Please start Docker Desktop.${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Docker is installed and running${NC}"

# Check if Composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}Composer is not installed. Installing...${NC}"
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    echo -e "${GREEN}✓ Composer installed${NC}"
else
    echo -e "${GREEN}✓ Composer is installed${NC}"
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo -e "${YELLOW}Node.js is not installed. Installing...${NC}"
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
    echo -e "${GREEN}✓ Node.js installed${NC}"
else
    echo -e "${GREEN}✓ Node.js is installed${NC}"
fi

# Install PHP dependencies
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Installing PHP dependencies...${NC}"
    composer install
    echo -e "${GREEN}✓ PHP dependencies installed${NC}"
else
    echo -e "${GREEN}✓ PHP dependencies already installed${NC}"
fi

# Install Node dependencies
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}Installing Node dependencies...${NC}"
    npm install
    echo -e "${GREEN}✓ Node dependencies installed${NC}"
else
    echo -e "${GREEN}✓ Node dependencies already installed${NC}"
fi

# Setup .env file
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}Creating .env file...${NC}"
    cp .env.example .env
    php artisan key:generate
    echo -e "${GREEN}✓ .env file created${NC}"
else
    echo -e "${GREEN}✓ .env file already exists${NC}"
fi

# Fix permissions
echo -e "${YELLOW}Setting up permissions...${NC}"
sudo chown -R $USER:$USER storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
echo -e "${GREEN}✓ Permissions set${NC}"

# Build Docker containers
echo -e "${YELLOW}Building Docker containers...${NC}"
docker-compose build
echo -e "${GREEN}✓ Docker containers built${NC}"

# Start Docker containers
echo -e "${YELLOW}Starting Docker containers...${NC}"
docker-compose up -d
echo -e "${GREEN}✓ Docker containers started${NC}"

# Wait for database to be ready
echo -e "${YELLOW}Waiting for database to be ready...${NC}"
sleep 10

# Run migrations
echo -e "${YELLOW}Running migrations...${NC}"
docker-compose exec -T app php artisan migrate --force || echo -e "${YELLOW}Migrations may have failed (database might not be ready yet)${NC}"

echo ""
echo -e "${GREEN}✅ Setup complete!${NC}"
echo ""
echo "Next steps:"
echo "1. Access the application at: http://localhost:8000"
echo "2. Run 'npm run dev' to build frontend assets"
echo "3. Check container status: docker-compose ps"
echo "4. View logs: docker-compose logs -f"
echo ""
