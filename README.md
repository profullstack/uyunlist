# Onion Classifieds

A privacy-first Craigslist-style marketplace available only via Tor .onion address.

## Overview

- **Backend**: PHP 8.2+ with server-side rendering (no client-side JavaScript)
- **Database**: Local Supabase Postgres (Docker)
- **Payments**: CryptAPI (BTC, XMR, ETH, SOL, DOGE)
- **Exchange Rates**: Tatum API
- **Deployment**: Docker Compose with Tor hidden service
- **Privacy**: Tor hidden service only

## Features

- Anonymous accounts (handle + password, no email)
- Listings with image uploads
- 1-to-1 private messaging
- Multi-cryptocurrency payments for posting/bumping
- Admin moderation tools
- Zero external resources for Tor Browser compatibility

## Tech Stack

- **Application**: PHP 8.2+ with Apache
- **Database**: Supabase Postgres (self-hosted)
- **Containerization**: Docker & Docker Compose
- **Privacy**: Tor hidden service
- **Payments**: CryptAPI integration
- **Exchange Rates**: Tatum API
- **Image Processing**: Imagick/GD

## Security Features

- Strict CSP headers
- No external resources
- CSRF protection
- Secure session handling
- EXIF stripping from images
- Input validation and sanitization
- Tor-only access

## Quick Start with Docker

### Prerequisites

- Docker and Docker Compose installed
- At least 4GB RAM available for containers
- Basic understanding of Tor hidden services

### 1. Clone and Setup

```bash
git clone <repository-url>
cd onion-classifieds

# Copy and customize environment file
cp .env.example .env
# Edit .env with your configuration (see Environment Variables section)
```

### 2. Generate Secrets

```bash
# Generate secure passwords and keys
./scripts/generate-secrets.sh
```

### 3. Start Services

```bash
# Start all services (Supabase + PHP App + Tor)
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f app
```

### 4. Initialize Database

```bash
# The database will be automatically initialized with our schema
# Check if initialization completed successfully
docker-compose logs db | grep "database system is ready"
```

### 5. Access the Application

- **PHP Application**: http://localhost:8080
- **Supabase Studio**: http://localhost:3000
- **Tor Hidden Service**: Check logs for .onion address

```bash
# Get your .onion address
docker-compose logs tor | grep "onion"
```

## Environment Variables

### Required Configuration

Edit `.env` file with your specific values:

```env
# Database (auto-generated secure password)
POSTGRES_PASSWORD=your-super-secret-and-long-postgres-password

# JWT Secret (auto-generated)
JWT_SECRET=your-super-secret-jwt-token-with-at-least-32-characters-long

# Application Secret (auto-generated)
APP_SECRET=your-64-character-secret-key-for-application-security-here

# CryptAPI Payout Addresses (REQUIRED)
PAYOUT_BTC=your-btc-address-here
PAYOUT_XMR=your-xmr-address-here
PAYOUT_ETH=your-eth-address-here
PAYOUT_SOL=your-sol-address-here
PAYOUT_DOGE=your-doge-address-here

# Tatum API (REQUIRED for exchange rates)
TATUM_API_KEY=your-tatum-api-key-here

# Pricing (USD cents)
LISTING_PRICE_CENTS=100
BUMP_PRICE_CENTS=50
```

## Development Workflow

### Local Development

```bash
# Start development environment
docker-compose up -d

# Watch application logs
docker-compose logs -f app

# Access database directly
docker-compose exec db psql -U postgres

# Restart specific service
docker-compose restart app

# Stop all services
docker-compose down
```

### Database Management

```bash
# Access Supabase Studio (Database UI)
open http://localhost:3000

# Run SQL migrations
docker-compose exec db psql -U postgres -f /docker-entrypoint-initdb.d/migrations/custom/001_initial_schema.sql

# Backup database
docker-compose exec db pg_dump -U postgres postgres > backup.sql

# Restore database
docker-compose exec -T db psql -U postgres postgres < backup.sql
```

### Adding New Features

1. **Database Changes**: Add new migration files to `database/migrations/`
2. **PHP Code**: Modify files in `src/` directory
3. **Templates**: Update templates in `templates/` directory
4. **Restart**: `docker-compose restart app` to apply changes

## Production Deployment

### Security Checklist

- [ ] Change all default passwords in `.env`
- [ ] Set `APP_DEBUG=false`
- [ ] Configure proper CryptAPI payout addresses
- [ ] Set up Tatum API key
- [ ] Configure proper backup strategy
- [ ] Set up monitoring and alerting
- [ ] Review and test Tor hidden service configuration

### Deployment Steps

```bash
# 1. Prepare production environment
cp .env.example .env.production
# Edit .env.production with production values

# 2. Deploy with production config
docker-compose -f docker-compose.yml --env-file .env.production up -d

# 3. Verify deployment
docker-compose ps
docker-compose logs app | grep "Health check"

# 4. Get .onion address for distribution
docker-compose logs tor | grep -E "\.onion"
```

## Architecture

### Container Services

- **app**: PHP 8.2 + Apache (Onion Classifieds application)
- **db**: PostgreSQL 15 (Supabase-compatible)
- **kong**: API Gateway for Supabase services
- **auth**: Supabase Auth service
- **rest**: PostgREST API
- **realtime**: Supabase Realtime
- **storage**: Supabase Storage
- **studio**: Supabase Studio (Database UI)
- **analytics**: Supabase Analytics
- **tor**: Tor hidden service

### Data Flow

1. User accesses .onion address via Tor Browser
2. Tor container forwards requests to PHP application
3. PHP application connects to local Supabase database
4. Payment requests go to CryptAPI
5. Exchange rates fetched from Tatum API

## Monitoring and Maintenance

### Health Checks

```bash
# Check application health
curl http://localhost:8080/health

# Check database connectivity
docker-compose exec app php -r "
require 'vendor/autoload.php';
\$config = new App\Core\Config();
\$db = new App\Core\Database(\$config);
echo \$db->ping() ? 'DB OK' : 'DB FAIL';
"
```

### Logs

```bash
# Application logs
docker-compose logs -f app

# Database logs
docker-compose logs -f db

# Tor logs
docker-compose logs -f tor

# All services
docker-compose logs -f
```

### Backups

```bash
# Automated backup script
./scripts/backup.sh

# Manual database backup
docker-compose exec db pg_dump -U postgres postgres > "backup-$(date +%Y%m%d).sql"
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   ```bash
   # Check database status
   docker-compose logs db
   # Restart database
   docker-compose restart db
   ```

2. **Tor Hidden Service Not Working**
   ```bash
   # Check Tor logs
   docker-compose logs tor
   # Restart Tor service
   docker-compose restart tor
   ```

3. **PHP Application Errors**
   ```bash
   # Check application logs
   docker-compose logs app
   # Check health endpoint
   curl http://localhost:8080/health
   ```

### Reset Everything

```bash
# Stop all services and remove data
docker-compose down -v

# Remove all images
docker-compose down --rmi all

# Start fresh
docker-compose up -d
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make changes and test locally with Docker
4. Submit a pull request

## Security

This application handles sensitive user data and cryptocurrency transactions. Always:

- Keep Docker images updated
- Monitor security advisories
- Use strong, unique passwords
- Regularly backup data
- Test security configurations
- Monitor for suspicious activity

## License

MIT License

## Support

For issues and questions:
1. Check the troubleshooting section
2. Review Docker logs
3. Create an issue in the repository