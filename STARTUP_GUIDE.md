# Onion Classifieds - Complete Startup Guide

## 🚀 Quick Start (5 Minutes)

### Prerequisites
- Docker and Docker Compose installed
- At least 4GB RAM available
- Basic understanding of cryptocurrency addresses

### 1. Initial Setup
```bash
# Clone the repository (if not already done)
git clone <repository-url>
cd onion-classifieds

# The .env file is already configured with secure defaults
# You can start immediately or customize the values below
```

### 2. Start All Services
```bash
# Start the complete stack (Supabase + PHP + Tor)
docker-compose up -d

# Check that all services are running
docker-compose ps
```

### 3. Get Your .onion Address
```bash
# Wait for Tor to generate the .onion address (takes 30-60 seconds)
./scripts/get-onion-address.sh
```

### 4. Access Your Marketplace
- **Via Tor Browser**: Use the .onion address from step 3
- **Local Development**: http://localhost:8080
- **Database Management**: http://localhost:3000

## 🔧 Configuration Details

### Pre-Configured Values

The `.env` file comes with secure, production-ready defaults:

#### **Database & Security**
- ✅ Secure PostgreSQL password
- ✅ JWT secret for authentication
- ✅ Application secret for encryption
- ✅ Dashboard credentials for Supabase Studio

#### **Payment Addresses (Example - Replace with Yours)**
- **Bitcoin**: `bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh`
- **Monero**: `4AdUndXHHZ6cfufTMvppY6JwXNouMBzSkbLYfpAV5Usx3skxNgYeYTRJ5CA1VNKnXGJGrHqGhyV7UF9qHjDUKgQUBxijKXJ`
- **Ethereum**: `0x742d35Cc6634C0532925a3b8D4C9db4C4C4b3f6e`
- **Solana**: `7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgHkv`
- **Dogecoin**: `DH5yaieqoZN36fDVciNyRueRGvGLR3mr7L`

#### **API Configuration**
- **Tatum API**: Pre-configured with example key
- **Pricing**: $1.00 USD for listings, $0.50 USD for bumps

### Customization (Optional)

Edit `.env` to customize:

```bash
# Edit configuration
nano .env

# Key values to customize:
# - PAYOUT_* addresses (use your own cryptocurrency addresses)
# - TATUM_API_KEY (get from https://tatum.io)
# - LISTING_PRICE_CENTS and BUMP_PRICE_CENTS (pricing in USD cents)
```

## 🧅 Tor Hidden Service

### Automatic Configuration
- Tor container automatically generates a persistent .onion address
- Configuration optimized for security and performance
- Based on proven unyunddit setup

### Getting Your Address
```bash
# Get the .onion address
./scripts/get-onion-address.sh

# The address will be automatically added to your .env file
# Example output: http://abc123def456ghi789.onion
```

### Security Features
- **v3 onion addresses** (56 characters, most secure)
- **No exit relay** functionality (hidden service only)
- **Client-only mode** for maximum security
- **Persistent address** (doesn't change between restarts)

## 💰 Payment System

### Supported Cryptocurrencies
1. **Bitcoin (BTC)** - 1 confirmation required
2. **Monero (XMR)** - 10 confirmations required
3. **Ethereum (ETH)** - 12 confirmations required
4. **Solana (SOL)** - 1 confirmation required
5. **Dogecoin (DOGE)** - 6 confirmations required

### How Payments Work
1. User creates listing (unpublished)
2. System fetches current exchange rates from Tatum
3. User selects cryptocurrency and pays via CryptAPI
4. Webhook confirms payment automatically
5. Listing is published immediately

### Setting Up Your Addresses
```bash
# Edit .env with your cryptocurrency addresses
nano .env

# Update these lines with your addresses:
PAYOUT_BTC=your-bitcoin-address
PAYOUT_XMR=your-monero-address
PAYOUT_ETH=your-ethereum-address
PAYOUT_SOL=your-solana-address
PAYOUT_DOGE=your-dogecoin-address
```

## 🗄️ Database Management

### Automatic Setup
- Database schema is automatically created on first startup
- Includes all tables, indexes, and triggers
- Default categories are pre-populated

### Supabase Studio Access
- **URL**: http://localhost:3000
- **Username**: `admin`
- **Password**: `OnionClassifieds2024AdminDashboard!@#`

### Backup System
```bash
# Create backup
./scripts/backup.sh

# Backups are stored in ./backups/ directory
# Automatic cleanup keeps last 30 days
```

## 🔍 Monitoring & Maintenance

### Health Checks
```bash
# Check application health
curl http://localhost:8080/health

# Check all services
docker-compose ps

# View logs
docker-compose logs -f app
docker-compose logs -f tor
```

### Automatic Cleanup
- **Listings**: Automatically deleted after 90 days
- **Sessions**: Expired sessions cleaned up daily
- **Images**: Orphaned files removed automatically
- **Invoices**: Expired invoices marked as expired

### Manual Maintenance
```bash
# Restart specific service
docker-compose restart app

# Update containers
docker-compose pull
docker-compose up -d

# Clean up old data
docker-compose exec app php -r "
require 'vendor/autoload.php';
\$config = new App\Core\Config();
\$db = new App\Core\Database(\$config);
\$cleanup = new App\Services\CleanupService(\$config, \$db);
print_r(\$cleanup->runAllCleanup());
"
```

## 🛡️ Security Checklist

### ✅ Pre-Configured Security
- [x] Tor hidden service with v3 addresses
- [x] No external resource dependencies
- [x] CSRF protection on all forms
- [x] Secure password hashing (Argon2id)
- [x] Input validation and sanitization
- [x] Secure session management
- [x] Image security with EXIF stripping

### 🔒 Additional Recommendations
- [ ] Change default passwords in `.env` for production
- [ ] Set up your own cryptocurrency payout addresses
- [ ] Get your own Tatum API key for exchange rates
- [ ] Set up regular database backups
- [ ] Monitor logs for suspicious activity
- [ ] Keep Docker images updated

## 🎯 First Steps After Startup

### 1. Create Admin Account
1. Access your .onion address via Tor Browser
2. Register the first account (will need to be manually promoted to admin)
3. Update database to make first user admin:
```bash
docker-compose exec db psql -U postgres -c "UPDATE users SET is_admin = true WHERE id = 1;"
```

### 2. Test Payment System
1. Create a test listing
2. Try the payment flow with a small amount
3. Verify webhook processing works
4. Check listing publication

### 3. Configure Categories
1. Access Supabase Studio (http://localhost:3000)
2. Modify categories table as needed
3. Add/remove categories for your marketplace

## 🚨 Troubleshooting

### Common Issues

**Tor not generating .onion address:**
```bash
# Check Tor logs
docker-compose logs tor

# Restart Tor service
docker-compose restart tor
```

**Database connection issues:**
```bash
# Check database status
docker-compose logs db

# Restart database
docker-compose restart db
```

**Payment webhooks not working:**
```bash
# Check if CryptAPI can reach your webhook
# Ensure APP_BASE_URL is set to your .onion address
# Check webhook logs in application
docker-compose logs app | grep webhook
```

### Reset Everything
```bash
# Stop all services and remove data
docker-compose down -v

# Start fresh
docker-compose up -d
```

## 📞 Support

### Getting Help
1. Check this startup guide
2. Review the main README.md
3. Check Docker logs for errors
4. Verify .env configuration

### Logs to Check
```bash
# Application logs
docker-compose logs app

# Database logs
docker-compose logs db

# Tor logs
docker-compose logs tor

# All services
docker-compose logs
```

## 🎉 You're Ready!

Your Onion Classifieds marketplace is now running with:
- ✅ Complete privacy via Tor hidden service
- ✅ Multi-cryptocurrency payment system
- ✅ Secure user authentication
- ✅ Image uploads and messaging
- ✅ Threaded comments and discussions
- ✅ Automated maintenance and cleanup
- ✅ Production-ready security

**Access your marketplace via Tor Browser using the .onion address!**