# myShop Initial Scaffold - Setup Commands

## Quick Start (Recommended)

```bash
# 1. Navigate to project directory
cd /home/turja/Projects/myShop

# 2. Create backend .env file
cp backend/.env.example backend/.env

# 3. Start all Docker services
docker-compose up -d

# 4. Wait for services to be healthy (30-60 seconds)
docker-compose ps

# 5. Install backend dependencies and generate key
docker exec -it myshop-backend composer install
docker exec -it myshop-backend php artisan key:generate

# 6. Run migrations and seed database
docker exec -it myshop-backend php artisan migrate --seed

# 7. Verify installation
curl http://localhost:8000/api/health
curl http://localhost:3001/health

# 8. Test login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@myshop.local","password":"password"}'
```

## Or Use Makefile

```bash
# Run complete installation
make install

# This will:
# - Copy .env files
# - Start Docker services
# - Install dependencies
# - Generate app key
# - Run migrations and seeders
```

## Access Points

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8000
- **Web3 Service**: http://localhost:3001
- **MySQL**: localhost:3306 (root/secret)
- **Redis**: localhost:6379

## Login Credentials

```
Admin:    admin@myshop.local / password
Seller 1: seller1@myshop.local / password
Seller 2: seller2@myshop.local / password
Seller 3: seller3@myshop.local / password
```

## Database Contents After Seeding

- **Users**: 14 (1 admin, 3 sellers, 10 customers)
- **Products**: 51 (across 10 categories)
- **Stores**: 5 (with geolocation)
- **Group Buys**: 5 (2 active, 1 fulfilled, 1 failed, 1 expired)
- **Sellers**: 3 (all verified)
- **Inventory Records**: ~45 (for quick commerce items)

## Useful Commands

```bash
# View logs
make logs

# Run migrations fresh
make migrate-fresh

# Access backend shell
make backend-shell

# Run tests
make test

# Stop services
make down
```

## Troubleshooting

### Services won't start
```bash
# Check if ports are in use
sudo lsof -i :8000
sudo lsof -i :3000
sudo lsof -i :3306

# Stop and rebuild
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d
```

### Database connection failed
```bash
# Wait for MySQL to be ready
docker-compose logs mysql

# Check MySQL health
docker exec -it myshop-mysql mysqladmin ping -h localhost -uroot -psecret
```

### Backend error: "No application encryption key"
```bash
docker exec -it myshop-backend php artisan key:generate
```

## What's Next?

See `PR_DESCRIPTION.md` for:
- Complete file listing
- Testing instructions
- API endpoint documentation
- Next steps for development
