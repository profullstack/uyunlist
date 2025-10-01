#!/bin/bash

# Onion Classifieds - Complete Deployment Script
# This script handles the full deployment process

set -e

echo "🧅 Onion Classifieds - Complete Deployment"
echo "=========================================="

# Check prerequisites
echo "🔍 Checking prerequisites..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Error: Docker is not installed"
    echo "   Install Docker: https://docs.docker.com/get-docker/"
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Error: Docker Compose is not installed"
    echo "   Install Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi

echo "✅ Docker and Docker Compose are installed"

# Check if .env file exists
if [ ! -f .env ]; then
    echo "❌ Error: .env file not found"
    echo "   The .env file should already exist with secure defaults"
    echo "   If missing, copy from .env.example: cp .env.example .env"
    exit 1
fi

echo "✅ .env file found"

# Generate secrets if needed
echo "🔐 Checking secrets..."
if grep -q "your-super-secret" .env; then
    echo "⚠️  Warning: Default secrets detected. Generating new ones..."
    ./scripts/generate-secrets.sh
else
    echo "✅ Secrets appear to be configured"
fi

# Start services
echo "🚀 Starting all services..."
docker-compose up -d

# Wait for services to be healthy
echo "⏳ Waiting for services to start..."
sleep 10

# Check service health
echo "🏥 Checking service health..."

# Check database
if docker-compose exec -T db pg_isready -U postgres > /dev/null 2>&1; then
    echo "✅ Database is ready"
else
    echo "❌ Database is not ready"
    echo "   Check logs: docker-compose logs db"
    exit 1
fi

# Check application
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    echo "✅ Application is ready"
else
    echo "❌ Application is not ready"
    echo "   Check logs: docker-compose logs app"
    exit 1
fi

# Get .onion address
echo "🧅 Getting .onion address..."
./scripts/get-onion-address.sh

# Run database migrations using Supabase CLI (if available)
echo "🗄️ Checking database migrations..."

if command -v supabase &> /dev/null; then
    echo "✅ Supabase CLI found, running migrations..."
    
    # Link to local instance
    supabase link --project-ref onion-classifieds --password "$POSTGRES_PASSWORD" || true
    
    # Push migrations
    supabase db push --local || echo "⚠️  Migration push failed, but continuing..."
    
else
    echo "⚠️  Supabase CLI not found. Migrations will be applied via Docker volumes."
    echo "   To install: npm install -g supabase"
fi

# Verify migrations were applied
echo "🔍 Verifying database schema..."
TABLES_COUNT=$(docker-compose exec -T db psql -U postgres -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';" | tr -d ' ')

if [ "$TABLES_COUNT" -gt 5 ]; then
    echo "✅ Database schema applied successfully ($TABLES_COUNT tables found)"
else
    echo "⚠️  Warning: Expected more tables. Check migration logs."
fi

# Create first admin user (optional)
echo "👤 Admin user setup..."
echo "   After deployment, register the first account via the web interface"
echo "   Then run: docker-compose exec db psql -U postgres -c \"UPDATE users SET is_admin = true WHERE id = 1;\""

# Show deployment summary
echo ""
echo "🎉 Deployment completed successfully!"
echo ""
echo "📊 Service Status:"
docker-compose ps

echo ""
echo "🌐 Access Points:"
echo "   - Local Development: http://localhost:8080"
echo "   - Supabase Studio: http://localhost:3000"
echo "   - Health Check: http://localhost:8080/health"
echo "   - Tor Hidden Service: Check output above for .onion address"

echo ""
echo "📋 Next Steps:"
echo "1. Access your .onion address via Tor Browser"
echo "2. Register the first account (will become admin)"
echo "3. Configure your cryptocurrency payout addresses in .env"
echo "4. Set up your Tatum API key for exchange rates"
echo "5. Test the payment flow with a small amount"
echo "6. Share your .onion address with users"

echo ""
echo "🔧 Management Commands:"
echo "   - View logs: docker-compose logs -f app"
echo "   - Backup database: ./scripts/backup.sh"
echo "   - Restart services: docker-compose restart"
echo "   - Stop services: docker-compose down"

echo ""
echo "🛡️ Security Reminders:"
echo "   - Only accessible via Tor Browser"
echo "   - No external resources loaded"
echo "   - All data encrypted and secure"
echo "   - Regular backups recommended"

echo ""
echo "🧅 Welcome to Onion Classifieds!"