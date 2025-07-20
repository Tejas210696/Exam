#!/bin/bash

# JEE Portal Development Setup Script
set -e

echo "🚀 Setting up JEE Portal development environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    print_error "Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    print_error "Node.js is not installed. Please install Node.js 18+ first."
    exit 1
fi

# Check Node.js version
NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 18 ]; then
    print_error "Node.js version 18 or higher is required. Current version: $(node --version)"
    exit 1
fi

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    print_error "npm is not installed. Please install npm first."
    exit 1
fi

print_status "All prerequisites are installed ✓"

# Create .env file if it doesn't exist
if [ ! -f .env ]; then
    print_status "Creating .env file..."
    cat > .env << EOL
# Environment
NODE_ENV=development

# Database URLs
POSTGRES_URL=postgresql://jee_user:jee_password@localhost:5432/jee_portal
MONGODB_URL=mongodb://jee_user:jee_password@localhost:27017/jee_portal
REDIS_URL=redis://localhost:6379
RABBITMQ_URL=amqp://jee_user:jee_password@localhost:5672

# JWT Configuration
JWT_SECRET=your-super-secret-jwt-key-change-in-production
JWT_EXPIRES_IN=1h
JWT_REFRESH_EXPIRES_IN=7d

# API URLs (for frontend)
REACT_APP_API_URL=http://localhost:8000
REACT_APP_WS_URL=ws://localhost:8000

# Email Configuration (optional)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your-email@gmail.com
SMTP_PASS=your-app-password

# File Upload
UPLOAD_MAX_SIZE=10485760
UPLOAD_ALLOWED_TYPES=image/jpeg,image/png,application/pdf

# Rate Limiting
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# Monitoring
ENABLE_METRICS=true
METRICS_PORT=9090
EOL
    print_success ".env file created"
else
    print_status ".env file already exists"
fi

# Install root dependencies
print_status "Installing root dependencies..."
npm install

# Install shared types dependencies
print_status "Installing shared types dependencies..."
cd shared/types
npm install
npm run build
cd ../..

# Install service dependencies
print_status "Installing service dependencies..."
services=("api-gateway" "auth-service" "user-service" "exam-service" "question-service" "submission-service" "evaluation-service" "proctor-service")

for service in "${services[@]}"; do
    if [ -d "services/$service" ]; then
        print_status "Installing dependencies for $service..."
        cd "services/$service"
        npm install
        cd ../..
    fi
done

# Install frontend dependencies
print_status "Installing frontend dependencies..."
frontends=("student-portal" "admin-portal")

for frontend in "${frontends[@]}"; do
    if [ -d "frontend/$frontend" ]; then
        print_status "Installing dependencies for $frontend..."
        cd "frontend/$frontend"
        npm install
        cd ../..
    fi
done

# Create Docker network
print_status "Creating Docker network..."
docker network create jee-network 2>/dev/null || print_warning "Docker network 'jee-network' already exists"

# Pull Docker images
print_status "Pulling Docker images..."
docker-compose pull

# Start databases and infrastructure
print_status "Starting databases and infrastructure..."
docker-compose up -d postgres mongodb redis rabbitmq prometheus grafana

# Wait for databases to be ready
print_status "Waiting for databases to be ready..."
sleep 30

# Run database migrations/setup
print_status "Setting up databases..."

# PostgreSQL setup
print_status "Setting up PostgreSQL..."
docker-compose exec -T postgres psql -U jee_user -d jee_portal -c "SELECT 1;" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    print_success "PostgreSQL is ready"
else
    print_warning "PostgreSQL is not ready yet, please wait and run migrations manually"
fi

# MongoDB setup
print_status "Setting up MongoDB..."
docker-compose exec -T mongodb mongosh --eval "db.runCommand('ping')" > /dev/null 2>&1
if [ $? -eq 0 ]; then
    print_success "MongoDB is ready"
else
    print_warning "MongoDB is not ready yet, please wait and run setup manually"
fi

# Create necessary directories
print_status "Creating necessary directories..."
mkdir -p logs uploads temp

# Set permissions
chmod +x scripts/*.sh

print_success "Development environment setup complete! 🎉"
echo ""
echo "Next steps:"
echo "1. Start all services: npm run dev"
echo "2. Access the applications:"
echo "   - Student Portal: http://localhost:3000"
echo "   - Admin Portal: http://localhost:3001"
echo "   - API Gateway: http://localhost:8000"
echo "   - Grafana: http://localhost:3002 (admin/admin123)"
echo "   - Prometheus: http://localhost:9090"
echo ""
echo "3. Run tests:"
echo "   - Unit tests: npm test"
echo "   - Load tests: npm run k6:load-test"
echo ""
echo "For more information, see README.md"