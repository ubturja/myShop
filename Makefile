.PHONY: help up down logs restart backend-shell frontend-shell migrate seed test cache-clear ai-sync

# Default target
.DEFAULT_GOAL := help

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-20s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

up: ## Start all Docker services
	docker-compose up -d

down: ## Stop all Docker services
	docker-compose down

logs: ## View logs from all services
	docker-compose logs -f

restart: down up ## Restart all services

backend-shell: ## SSH into backend container
	docker exec -it myshop-backend bash

frontend-shell: ## SSH into frontend container
	docker exec -it myshop-frontend sh

web3-shell: ## SSH into web3-service container
	docker exec -it myshop-web3 sh

migrate: ## Run database migrations
	docker exec -it myshop-backend php artisan migrate

seed: ## Run database seeders
	docker exec -it myshop-backend php artisan db:seed

migrate-seed: ## Run migrations and seed database
	docker exec -it myshop-backend php artisan migrate --seed

migrate-fresh: ## Drop all tables and re-run migrations
	docker exec -it myshop-backend php artisan migrate:fresh --seed

test: ## Run all tests
	@echo "Running backend tests..."
	docker exec -it myshop-backend php artisan test
	@echo "\nRunning frontend tests..."
	cd frontend && npm test -- --run
	@echo "\nRunning web3 tests..."
	cd web3-service && npx hardhat test

test-backend: ## Run backend tests only
	docker exec -it myshop-backend php artisan test

test-frontend: ## Run frontend tests only
	cd frontend && npm test -- --run

test-web3: ## Run web3/contract tests only
	cd web3-service && npx hardhat test

cache-clear: ## Clear all Laravel caches
	docker exec -it myshop-backend php artisan cache:clear
	docker exec -it myshop-backend php artisan config:clear
	docker exec -it myshop-backend php artisan route:clear
	docker exec -it myshop-backend php artisan view:clear

optimize: ## Optimize Laravel application
	docker exec -it myshop-backend php artisan config:cache
	docker exec -it myshop-backend php artisan route:cache
	docker exec -it myshop-backend php artisan view:cache

ai-sync: ## Sync products to AI vector database
	docker exec -it myshop-backend php artisan ai:sync-products

queue-work: ## Start queue worker
	docker exec -it myshop-backend php artisan queue:work

composer-install: ## Install Composer dependencies
	docker exec -it myshop-backend composer install

npm-install: ## Install npm dependencies (frontend)
	cd frontend && npm install

npm-install-web3: ## Install npm dependencies (web3-service)
	cd web3-service && npm install

db-reset: migrate-fresh ## Alias for migrate-fresh

build: ## Build all Docker images
	docker-compose build

rebuild: ## Rebuild all Docker images without cache
	docker-compose build --no-cache

ps: ## Show running containers
	docker-compose ps

clean: ## Remove all containers, volumes, and images
	docker-compose down -v --rmi all

install: ## Initial project setup
	@echo "Setting up myShop..."
	@echo "\n1. Creating .env files..."
	cp backend/.env.example backend/.env || true
	@echo "\n2. Starting Docker services..."
	docker-compose up -d
	@echo "\n3. Installing backend dependencies..."
	docker exec -it myshop-backend composer install
	@echo "\n4. Generating application key..."
	docker exec -it myshop-backend php artisan key:generate
	@echo "\n5. Running migrations and seeders..."
	docker exec -it myshop-backend php artisan migrate --seed
	@echo "\n6. Installing frontend dependencies..."
	cd frontend && npm install
	@echo "\n7. Installing web3-service dependencies..."
	cd web3-service && npm install
	@echo "\n✅ Setup complete! Access the app at:"
	@echo "   Frontend: http://localhost:3000"
	@echo "   Backend API: http://localhost:8000"
	@echo "   Web3 Service: http://localhost:3001"
