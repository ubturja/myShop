# MyShop - Quick Start Guide

This guide will help you get the MyShop e-commerce platform up and running on your local machine for development and testing purposes.

## Prerequisites

- [Docker](https://www.docker.com/) and [Docker Compose](https://docs.docker.com/compose/install/)
- [Node.js](https://nodejs.org/) (v18 or higher recommended)
- [npm](https://www.npmjs.com/) (v9 or higher) or [Yarn](https://yarnpkg.com/)
- [Git](https://git-scm.com/)

## Getting Started

### 1. Clone the Repository

```bash
git clone <repository-url>
cd myShop
```

### 2. Set Up Environment Variables

#### Backend (.env)
Create a `.env` file in the `backend` directory based on the example:

```bash
cd backend
cp .env.example .env
```

#### Frontend (.env)
Create a `.env` file in the `frontend` directory:

```bash
cd ../frontend
cp .env.example .env
```

### 3. Start the Backend Services

From the project root, start the required services using Docker Compose:

```bash
docker-compose up -d mysql redis backend web3-service
```

This will start:
- MySQL database on port 3306
- Redis cache on port 6379
- Laravel backend on port 8000
- Web3 service on port 3001

### 4. Set Up the Backend

Run database migrations and seed initial data:

```bash
docker-compose exec backend php artisan migrate --seed
```

### 5. Set Up the Frontend

Install dependencies and start the development server:

```bash
cd frontend
npm install
npm run dev
```

The frontend will be available at `http://localhost:3000`

## Accessing the Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **Admin Panel**: http://localhost:8000/admin
  - Default admin credentials (if seeded):
    - Email: admin@example.com
    - Password: password

## Development Workflow

### Running Tests

#### Frontend Tests
```bash
cd frontend
npm test
```

#### Backend Tests
```bash
docker-compose exec backend php artisan test
```

### Stopping the Application

To stop all services:
```bash
docker-compose down
```

## Troubleshooting

### Common Issues

1. **Port Conflicts**
   - If you get port conflicts, check which ports are in use and update the `docker-compose.yml` or frontend port in `vite.config.ts`

2. **Database Connection Issues**
   - Ensure the MySQL container is running: `docker ps | grep mysql`
   - Check logs: `docker-compose logs mysql`

3. **Frontend Not Connecting to Backend**
   - Verify the backend is running: `curl http://localhost:8000/api`
   - Check CORS settings in the backend if you see CORS errors

## Need Help?

If you encounter any issues, please check the following:
1. All services are running: `docker ps`
2. Check logs for errors: `docker-compose logs`
3. Ensure all environment variables are set correctly

For additional help, please open an issue in the repository.
