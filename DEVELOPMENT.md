# Onion Classifieds - Development Guide

## Overview

Onion Classifieds is a privacy-first Craigslist-style marketplace designed exclusively for Tor users. This guide covers the development setup, architecture, and deployment process.

## Architecture

### Tech Stack
- **Backend**: PHP 8.2+ with custom MVC framework
- **Database**: PostgreSQL (via Supabase)
- **Payments**: CryptAPI (BTC, XMR, ETH, SOL, DOGE)
- **Exchange Rates**: Tatum API
- **Deployment**: Railway with Docker
- **Privacy**: Tor hidden service only

### Core Components

1. **Core Framework** (`src/Core/`)
   - `Application.php` - Main application bootstrap
   - `Config.php` - Environment configuration management
   - `Database.php` - PostgreSQL database abstraction
   - `Router.php` - URL routing and middleware
   - `Session.php` - Secure session management with CSRF

2. **Controllers** (`src/Controllers/`)
   - `BaseController.php` - Common controller functionality
   - `AuthController.php` - User authentication and profiles
   - `HomeController.php` - Homepage and browsing
   - Additional controllers for listings, messaging, payments, admin

3. **Templates** (`templates/`)
   - Server-side rendered PHP templates
   - No client-side JavaScript for Tor compatibility
   - Minimal CSS with no external resources

## Development Setup

### Prerequisites
- PHP 8.2+
- Composer
- PostgreSQL (or Supabase account)
- Required PHP extensions: pdo, pdo_pgsql, json, curl, gd, imagick

### Installation

1. **Clone and Install Dependencies**
   ```bash
   git clone <repository>
   cd onion-classifieds
   composer install
   ```

2. **Environment Configuration**
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

3. **Run Setup Script**
   ```bash
   php setup.php
   ```

4. **Database Migration**
   - Execute `database/migrations/001_initial_schema.sql` in your PostgreSQL database
   - This creates all necessary tables and indexes

5. **Web Server Configuration**
   - Point document root to `public/` directory
   - Ensure URL rewriting is enabled (Apache mod_rewrite)

### Environment Variables

Key configuration in `.env`:

```env
# Database
DATABASE_URL=postgresql://user:pass@host:port/database

# Application
APP_SECRET=your-64-char-secret-key
APP_BASE_URL=http://your-onion-address.onion
APP_DEBUG=false

# CryptAPI Payout Addresses
PAYOUT_BTC=your-btc-address
PAYOUT_XMR=your-xmr-address
PAYOUT_ETH=your-eth-address
PAYOUT_SOL=your-sol-address
PAYOUT_DOGE=your-doge-address

# Tatum API
TATUM_API_URL=https://api.tatum.io/v3
TATUM_API_KEY=your-tatum-api-key

# Pricing (USD cents)
LISTING_PRICE_CENTS=100
BUMP_PRICE_CENTS=50
```

## Security Features

### Privacy Protection
- Tor-only access with .onion address
- No external resources (fonts, CDNs, analytics)
- Strict Content Security Policy
- No client-side JavaScript
- IP address protection via Tor

### Authentication Security
- Argon2id password hashing
- Secure session management
- CSRF protection on all forms
- HttpOnly, Secure, SameSite cookies
- Session database storage with expiration

### Data Protection
- Input validation and sanitization
- SQL injection prevention with prepared statements
- XSS prevention with output escaping
- File upload security with type/size validation
- EXIF data stripping from images

## Database Schema

### Core Tables
- `users` - User accounts (handle, password hash, profile)
- `sessions` - Active user sessions with CSRF tokens
- `categories` - Listing categories
- `listings` - User listings with content and pricing
- `listing_images` - Image attachments for listings
- `conversations` - 1-to-1 messaging between users
- `messages` - Individual messages in conversations
- `invoices` - Payment tracking for CryptAPI
- `reports` - Content moderation reports

### Key Features
- Full-text search on listings
- Automatic timestamp management
- Foreign key constraints for data integrity
- Indexes for performance optimization

## Payment System

### Supported Cryptocurrencies
- Bitcoin (BTC)
- Monero (XMR)
- Ethereum (ETH)
- Solana (SOL)
- Dogecoin (DOGE)

### Payment Flow
1. User creates listing (unpublished)
2. System creates invoice with crypto amount from Tatum rates
3. CryptAPI generates payment address
4. User pays to address
5. CryptAPI webhook confirms payment
6. Listing is automatically published

### Webhook Security
- Validates webhook signatures
- Idempotent processing
- Comprehensive logging
- Error handling and retry logic

## Development Workflow

### Adding New Features

1. **Database Changes**
   - Create new migration file in `database/migrations/`
   - Use timestamp prefix: `002_feature_name.sql`
   - Never modify existing migrations

2. **Controller Development**
   - Extend `BaseController` for common functionality
   - Implement proper input validation
   - Use CSRF protection for POST requests
   - Handle errors gracefully

3. **Template Creation**
   - Use PHP templates with output buffering
   - Include base layout for consistency
   - Sanitize all output with `htmlspecialchars()`
   - No external resources or JavaScript

4. **Route Registration**
   - Add routes in `Application::setupRoutes()`
   - Apply appropriate middleware (auth, csrf, admin)
   - Use RESTful URL patterns

### Testing

- Manual testing via Tor Browser
- Database integrity checks
- Payment flow testing with testnet
- Security header validation
- Performance testing over Tor

## Deployment

### Railway Deployment

1. **Dockerfile** (to be created)
   - PHP 8.2 with required extensions
   - Composer dependency installation
   - Proper file permissions
   - Health check endpoint

2. **Environment Variables**
   - Configure all required variables in Railway
   - Use Railway's PostgreSQL addon or external Supabase
   - Set production-appropriate values

3. **Domain Configuration**
   - Railway provides HTTPS endpoint
   - Configure reverse proxy to Tor hidden service
   - Ensure only .onion access in production

### Tor Hidden Service

1. **Server Configuration**
   ```
   # torrc configuration
   HiddenServiceDir /var/lib/tor/onion-classifieds/
   HiddenServicePort 80 127.0.0.1:8080
   ```

2. **Security Considerations**
   - Disable access logs or anonymize IPs
   - Use fail2ban for brute force protection
   - Regular security updates
   - Monitor for suspicious activity

## Monitoring and Maintenance

### Logging
- Application errors to `logs/` directory
- Database query logging (development only)
- Payment webhook logs
- Security event logging

### Backups
- Daily database backups
- User upload backups
- Configuration backups
- Test restore procedures

### Updates
- Regular PHP and dependency updates
- Security patch monitoring
- Database migration testing
- Staged deployment process

## Contributing

### Code Standards
- PSR-12 coding standards
- Comprehensive input validation
- Proper error handling
- Security-first approach
- Documentation for complex logic

### Pull Request Process
1. Create feature branch
2. Implement changes with tests
3. Update documentation
4. Security review
5. Merge to main branch

## Support

For development questions or security concerns, please refer to the project documentation or create an issue in the repository.

---

**Remember**: This application handles sensitive user data and financial transactions. Always prioritize security and privacy in all development decisions.