# myShop - Production-Ready E-Commerce Platform

A full-stack e-commerce platform featuring:
- **Multi-seller marketplace** with seller dashboards
- **Community group buying** (Pinduoduo-style) for team discounts
- **Hyperlocal quick commerce** with micro-fulfillment centers
- **AI shopping agent** powered by embeddings and LLMs
- **Supply chain provenance** anchored to IPFS and Polygon Mumbai
- **Loyalty NFTs** (ERC-721) minted on Polygon testnet
- **Gamification** (spin wheel, quizzes, referral rewards)

## Tech Stack

### Backend
- **Laravel 11** (PHP 8.2+)
- **MySQL** for primary data storage
- **Redis** for caching and queues
- **Laravel Sanctum** for API authentication

### Frontend
- **React 18** with TypeScript
- **Vite** for build tooling
- **Tailwind CSS** for styling

### Blockchain & AI
- **Solidity** smart contracts (ERC-721)
- **Hardhat** for contract development
- **Node.js/Express** microservice for web3 operations
- **OpenAI** for embeddings and chat
- **Pinecone** for vector search
- **IPFS** (Pinata) for decentralized storage

## Prerequisites

- **Docker** and **Docker Compose** (recommended)
- **PHP 8.2+** and **Composer** (for local development)
- **Node.js 18+** and **npm** (for frontend)
- **MySQL 8.0+** or **PostgreSQL**
- **Redis** for caching

## Quick Start with Docker

### 1. Clone the repository

```bash
git clone <repository-url>
cd myShop
```

### 2. Set up environment variables

```bash
# Backend
cp backend/.env.example backend/.env

# Edit backend/.env and set your API keys (or use mock mode)
# AI_MOCK=true
# PINECONE_MOCK=true
# MAPS_MOCK=true
```

### 3. Start all services

```bash
docker-compose up -d
```

This will start:
- MySQL (port 3306)
- Redis (port 6379)
- Laravel backend (port 8000)
- React frontend (port 3000)
- Web3 microservice (port 3001)

### 4. Run database migrations and seed data

```bash
docker exec -it myshop-backend php artisan migrate --seed
```

### 5. Access the application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **API Health Check**: http://localhost:8000/api/health

### Default Login Credentials

```
Admin: admin@myshop.local / password
Seller 1: seller1@myshop.local / password
Seller 2: seller2@myshop.local / password
Seller 3: seller3@myshop.local / password
```

## Local Development (Without Docker)

### Backend Setup

```bash
cd backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run migrations and seed
php artisan migrate --seed

# Start development server
php artisan serve
```

Backend will be available at http://localhost:8000

### Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev
```

Frontend will be available at http://localhost:3000

### Web3 Service Setup

```bash
cd web3-service

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Start service
npm run dev
```

Service will be available at http://localhost:3001

## Makefile Commands

For convenience, a Makefile is provided with common commands:

```bash
# Start all services
make up

# Stop all services
make down

# View logs
make logs

# Run migrations and seed
make migrate

# Run tests
make test

# SSH into backend container
make backend-shell

# Clear caches
make cache-clear

# Sync AI embeddings (requires OpenAI API key or AI_MOCK=true)
make ai-sync
```

## Running Tests

### Backend (PHPUnit)

```bash
cd backend
php artisan test
```

### Frontend (Vitest)

```bash
cd frontend
npm test
```

### Web3 Contracts (Hardhat)

```bash
cd web3-service
npx hardhat test
```

### E2E Tests (Cypress)

```bash
cd frontend
npm run cypress:open
```

## API Documentation

### Authentication

- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login and get token
- `POST /api/auth/logout` - Logout
- `GET /api/user/profile` - Get user profile

### Products

- `GET /api/products` - List products (with filters)
- `GET /api/products/{id}` - Get product details
- `POST /api/seller/products` - Create product (seller only)
- `PUT /api/seller/products/{id}` - Update product (seller only)
- `DELETE /api/seller/products/{id}` - Delete product (seller only)

### Group Buying

- `POST /api/group-buys` - Create group buy
- `GET /api/group-buys/{id}` - Get group buy details
- `POST /api/group-buys/{id}/join` - Join group buy
- `GET /api/group-buys/product/{productId}` - Get active group buys for product

For full API documentation, see [docs/api.md](docs/api.md) (TODO)

## Project Structure

```
myShop/
├── backend/                 # Laravel application
│   ├── app/
│   │   ├── Http/Controllers/
│   │   ├── Models/
│   │   └── Services/       # AI, Web3, Provenance services
│   ├── database/
│   │   ├── migrations/
│   │   ├── factories/
│   │   └── seeders/
│   └── routes/
├── frontend/                # React application
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   └── services/
│   └── public/
├── web3-service/           # Node.js microservice
│   ├── contracts/          # Solidity contracts
│   └── app.js
├── contracts/              # Shared contracts
├── docs/                   # Documentation
├── docker/                 # Dockerfiles
├── .github/workflows/      # CI/CD pipelines
└── docker-compose.yml
```

## Environment Variables

Key environment variables (see `.env.example` files for complete list):

```bash
# Mock flags - set to true to run without paid APIs
AI_MOCK=true
PINECONE_MOCK=true
MAPS_MOCK=true
WEB3_MOCK=true
IPFS_MOCK=true

# API Keys (optional if using mocks)
OPENAI_API_KEY=
PINECONE_API_KEY=
PINATA_API_KEY=
ALCHEMY_API_KEY=
MAPBOX_API_KEY=
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For issues and questions, please open an issue on GitHub.

## Roadmap

See [docs/roadmap.md](docs/roadmap.md) for planned features and improvements.
