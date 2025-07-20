#!/bin/bash

# JEE Portal Laravel/PHP Development Environment Setup Script
# This script sets up the complete development environment

set -e

echo "🚀 Setting up JEE Portal Laravel/PHP Development Environment..."

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

# Check if running on supported OS
check_os() {
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        OS="linux"
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macos"
    elif [[ "$OSTYPE" == "msys" ]] || [[ "$OSTYPE" == "cygwin" ]]; then
        OS="windows"
    else
        print_error "Unsupported operating system: $OSTYPE"
        exit 1
    fi
    print_status "Detected OS: $OS"
}

# Check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."
    
    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        echo "Visit: https://docs.docker.com/get-docker/"
        exit 1
    fi
    
    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        echo "Visit: https://docs.docker.com/compose/install/"
        exit 1
    fi
    
    # Check PHP (for local development)
    if ! command -v php &> /dev/null; then
        print_warning "PHP is not installed locally. This is optional for containerized development."
    else
        php_version=$(php -r "echo PHP_VERSION;")
        print_status "PHP version: $php_version"
    fi
    
    # Check Composer (for local development)
    if ! command -v composer &> /dev/null; then
        print_warning "Composer is not installed locally. This is optional for containerized development."
    fi
    
    # Check Node.js (for frontend)
    if ! command -v node &> /dev/null; then
        print_error "Node.js is not installed. Please install Node.js first."
        echo "Visit: https://nodejs.org/"
        exit 1
    fi
    
    node_version=$(node --version)
    print_status "Node.js version: $node_version"
    
    # Check npm
    if ! command -v npm &> /dev/null; then
        print_error "npm is not installed. Please install npm first."
        exit 1
    fi
    
    npm_version=$(npm --version)
    print_status "npm version: $npm_version"
    
    print_success "All prerequisites are satisfied!"
}

# Create environment files
create_env_files() {
    print_status "Creating environment files..."
    
    # Root .env file
    if [ ! -f ".env" ]; then
        cat > .env << EOF
# JEE Portal Environment Configuration
COMPOSE_PROJECT_NAME=jee-portal
COMPOSE_FILE=docker-compose.yml

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=jee_portal
DB_USERNAME=jee_user
DB_PASSWORD=jee_password

# MongoDB Configuration
MONGODB_HOST=mongodb
MONGODB_PORT=27017
MONGODB_DATABASE=jee_portal
MONGODB_USERNAME=jee_user
MONGODB_PASSWORD=jee_password

# Redis Configuration
REDIS_HOST=redis
REDIS_PORT=6379

# RabbitMQ Configuration
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=jee_user
RABBITMQ_PASS=jee_password

# Application URLs
API_GATEWAY_URL=http://localhost:8000
STUDENT_PORTAL_URL=http://localhost:3000
ADMIN_PORTAL_URL=http://localhost:3001

# Monitoring
PROMETHEUS_URL=http://localhost:9090
GRAFANA_URL=http://localhost:3002
EOF
        print_success "Created root .env file"
    else
        print_warning ".env file already exists, skipping..."
    fi
    
    # Laravel services environment files
    create_laravel_env_files
}

create_laravel_env_files() {
    local services=("api-gateway" "auth-service" "user-service" "exam-service" "question-service" "submission-service")
    
    for service in "${services[@]}"; do
        local env_file="services/$service/.env"
        
        if [ ! -f "$env_file" ]; then
            mkdir -p "services/$service"
            cat > "$env_file" << EOF
APP_NAME=JEE-Portal-$(echo $service | tr '[:lower:]' '[:upper:]' | tr '-' '_')
APP_ENV=development
APP_KEY=base64:$(openssl rand -base64 32 2>/dev/null || echo 'your-app-key-here')
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=jee_portal
DB_USERNAME=jee_user
DB_PASSWORD=jee_password

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@jeeportal.com"
MAIL_FROM_NAME="\${APP_NAME}"

# JWT Configuration
JWT_SECRET=$(openssl rand -base64 32 2>/dev/null || echo 'your-jwt-secret-here')
JWT_TTL=60
JWT_REFRESH_TTL=20160

# Service URLs
AUTH_SERVICE_URL=http://auth-service:8000
USER_SERVICE_URL=http://user-service:8000
EXAM_SERVICE_URL=http://exam-service:8000
QUESTION_SERVICE_URL=http://question-service:8000
SUBMISSION_SERVICE_URL=http://submission-service:8000
EVALUATION_SERVICE_URL=http://evaluation-service
PROCTOR_SERVICE_URL=http://proctor-service

# MongoDB (for services that need it)
MONGODB_HOST=mongodb
MONGODB_PORT=27017
MONGODB_DATABASE=jee_portal
MONGODB_USERNAME=jee_user
MONGODB_PASSWORD=jee_password
EOF
            print_success "Created $env_file"
        fi
    done
}

# Setup Laravel services
setup_laravel_services() {
    print_status "Setting up Laravel services..."
    
    local services=("api-gateway" "auth-service" "user-service" "exam-service" "question-service" "submission-service")
    
    for service in "${services[@]}"; do
        print_status "Setting up $service..."
        
        local service_dir="services/$service"
        
        if [ ! -f "$service_dir/composer.json" ]; then
            print_warning "composer.json not found for $service, creating basic structure..."
            mkdir -p "$service_dir/app/Http/Controllers"
            mkdir -p "$service_dir/app/Models"
            mkdir -p "$service_dir/app/Services"
            mkdir -p "$service_dir/config"
            mkdir -p "$service_dir/routes"
            mkdir -p "$service_dir/database/migrations"
            mkdir -p "$service_dir/tests"
        fi
        
        # Create basic Laravel structure files
        create_laravel_structure "$service_dir"
    done
}

create_laravel_structure() {
    local service_dir=$1
    
    # Create routes/api.php if it doesn't exist
    if [ ! -f "$service_dir/routes/api.php" ]; then
        cat > "$service_dir/routes/api.php" << 'EOF'
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'service' => basename(dirname(__DIR__)),
    ]);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
EOF
    fi
    
    # Create config/app.php if it doesn't exist
    if [ ! -f "$service_dir/config/app.php" ]; then
        mkdir -p "$service_dir/config"
        cat > "$service_dir/config/app.php" << 'EOF'
<?php

return [
    'name' => env('APP_NAME', 'Laravel'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
EOF
    fi
}

# Setup native PHP services
setup_php_services() {
    print_status "Setting up native PHP services..."
    
    local services=("evaluation-service" "proctor-service" "analytics-service")
    
    for service in "${services[@]}"; do
        print_status "Setting up $service..."
        
        local service_dir="services/$service"
        mkdir -p "$service_dir/src/Controllers"
        mkdir -p "$service_dir/src/Services"
        mkdir -p "$service_dir/src/Core"
        mkdir -p "$service_dir/vendor"
        
        # Create composer.json for native PHP services
        if [ ! -f "$service_dir/composer.json" ]; then
            cat > "$service_dir/composer.json" << EOF
{
    "name": "jee-portal/$service",
    "type": "project",
    "description": "Native PHP microservice for JEE Portal",
    "keywords": ["php", "microservice", "api"],
    "license": "MIT",
    "require": {
        "php": "^8.1",
        "mongodb/mongodb": "^1.15",
        "predis/predis": "^2.0",
        "monolog/monolog": "^3.0",
        "vlucas/phpdotenv": "^5.5",
        "guzzlehttp/guzzle": "^7.5"
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "src/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true
    }
}
EOF
            print_success "Created composer.json for $service"
        fi
        
        # Create bootstrap file
        if [ ! -f "$service_dir/src/bootstrap.php" ]; then
            cat > "$service_dir/src/bootstrap.php" << 'EOF'
<?php

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Set timezone
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');
EOF
        fi
    done
}

# Setup frontend applications
setup_frontend() {
    print_status "Setting up frontend applications..."
    
    local apps=("student-portal" "admin-portal")
    
    for app in "${apps[@]}"; do
        print_status "Setting up $app..."
        
        local app_dir="frontend/$app"
        
        if [ ! -f "$app_dir/package.json" ]; then
            mkdir -p "$app_dir/src/components"
            mkdir -p "$app_dir/src/pages"
            mkdir -p "$app_dir/src/services"
            mkdir -p "$app_dir/public"
            
            # Create package.json
            cat > "$app_dir/package.json" << EOF
{
  "name": "jee-portal-$app",
  "version": "1.0.0",
  "private": true,
  "dependencies": {
    "@testing-library/jest-dom": "^5.16.4",
    "@testing-library/react": "^13.3.0",
    "@testing-library/user-event": "^13.5.0",
    "@types/jest": "^27.5.2",
    "@types/node": "^16.11.47",
    "@types/react": "^18.0.15",
    "@types/react-dom": "^18.0.6",
    "react": "^18.2.0",
    "react-dom": "^18.2.0",
    "react-scripts": "5.0.1",
    "typescript": "^4.7.4",
    "web-vitals": "^2.1.4",
    "@mui/material": "^5.10.0",
    "@mui/icons-material": "^5.10.0",
    "@emotion/react": "^11.10.0",
    "@emotion/styled": "^11.10.0",
    "@reduxjs/toolkit": "^1.8.3",
    "react-redux": "^8.0.2",
    "react-router-dom": "^6.3.0",
    "axios": "^0.27.2",
    "face-api.js": "^0.22.2",
    "socket.io-client": "^4.5.1"
  },
  "scripts": {
    "start": "react-scripts start",
    "build": "react-scripts build",
    "test": "react-scripts test",
    "eject": "react-scripts eject"
  },
  "eslintConfig": {
    "extends": [
      "react-app",
      "react-app/jest"
    ]
  },
  "browserslist": {
    "production": [
      ">0.2%",
      "not dead",
      "not op_mini all"
    ],
    "development": [
      "last 1 chrome version",
      "last 1 firefox version",
      "last 1 safari version"
    ]
  },
  "proxy": "http://localhost:8000"
}
EOF
            print_success "Created package.json for $app"
        fi
    done
}

# Create Docker network
create_docker_network() {
    print_status "Creating Docker network..."
    
    if ! docker network ls | grep -q "jee-network"; then
        docker network create jee-network
        print_success "Created Docker network: jee-network"
    else
        print_warning "Docker network 'jee-network' already exists"
    fi
}

# Start infrastructure services
start_infrastructure() {
    print_status "Starting infrastructure services..."
    
    # Start databases and supporting services first
    docker-compose up -d mysql mongodb redis rabbitmq prometheus grafana
    
    print_status "Waiting for databases to be ready..."
    sleep 30
    
    # Check if MySQL is ready
    print_status "Checking MySQL connection..."
    until docker-compose exec -T mysql mysqladmin ping -h"localhost" --silent; do
        print_status "Waiting for MySQL..."
        sleep 5
    done
    print_success "MySQL is ready!"
    
    # Check if MongoDB is ready
    print_status "Checking MongoDB connection..."
    until docker-compose exec -T mongodb mongosh --eval "print('MongoDB is ready')" > /dev/null 2>&1; do
        print_status "Waiting for MongoDB..."
        sleep 5
    done
    print_success "MongoDB is ready!"
    
    print_success "Infrastructure services are running!"
}

# Install dependencies
install_dependencies() {
    print_status "Installing dependencies..."
    
    # Install frontend dependencies
    local apps=("student-portal" "admin-portal")
    for app in "${apps[@]}"; do
        if [ -f "frontend/$app/package.json" ]; then
            print_status "Installing dependencies for $app..."
            (cd "frontend/$app" && npm install)
            print_success "Dependencies installed for $app"
        fi
    done
    
    # Install PHP dependencies for native services
    local services=("evaluation-service" "proctor-service" "analytics-service")
    for service in "${services[@]}"; do
        if [ -f "services/$service/composer.json" ]; then
            print_status "Installing PHP dependencies for $service..."
            (cd "services/$service" && composer install --no-dev --optimize-autoloader 2>/dev/null || echo "Composer install skipped")
        fi
    done
}

# Run database migrations
run_migrations() {
    print_status "Running database migrations..."
    
    # MySQL migrations are handled by init.sql
    print_status "MySQL schema will be initialized automatically"
    
    # MongoDB initialization is handled by init.js
    print_status "MongoDB collections will be initialized automatically"
    
    print_success "Database setup completed!"
}

# Display final information
display_info() {
    print_success "🎉 JEE Portal Laravel/PHP Development Environment Setup Complete!"
    echo
    echo "📋 Next Steps:"
    echo "1. Start all services: docker-compose up -d"
    echo "2. View logs: docker-compose logs -f"
    echo "3. Stop services: docker-compose down"
    echo
    echo "🌐 Access URLs:"
    echo "• Student Portal: http://localhost:3000"
    echo "• Admin Portal: http://localhost:3001"
    echo "• API Gateway: http://localhost:8000"
    echo "• Prometheus: http://localhost:9090"
    echo "• Grafana: http://localhost:3002 (admin/admin123)"
    echo
    echo "🔧 Development Commands:"
    echo "• View API Gateway logs: docker-compose logs -f api-gateway"
    echo "• Access MySQL: docker-compose exec mysql mysql -u jee_user -p jee_portal"
    echo "• Access MongoDB: docker-compose exec mongodb mongosh jee_portal"
    echo "• Access Redis: docker-compose exec redis redis-cli"
    echo
    echo "📚 Default Credentials:"
    echo "• Admin: admin@jeeportal.com / admin123"
    echo "• Student: student1@example.com / password"
    echo
    print_warning "Make sure to change default passwords in production!"
}

# Main execution
main() {
    echo "🚀 JEE Portal Laravel/PHP Development Setup"
    echo "==========================================="
    echo
    
    check_os
    check_prerequisites
    create_env_files
    setup_laravel_services
    setup_php_services
    setup_frontend
    create_docker_network
    install_dependencies
    start_infrastructure
    run_migrations
    
    display_info
}

# Run main function
main "$@"