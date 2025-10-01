#!/bin/bash

# Onion Classifieds - Secret Generation Script
# This script generates secure random secrets for the application

set -e

echo "🧅 Onion Classifieds - Generating Secrets"
echo "========================================"

# Check if .env exists
if [ ! -f .env ]; then
    echo "❌ .env file not found. Please copy .env.example to .env first."
    exit 1
fi

# Generate secrets
echo "🔐 Generating secure secrets..."

# Generate 64-character app secret
APP_SECRET=$(openssl rand -hex 32)

# Generate 32+ character JWT secret
JWT_SECRET=$(openssl rand -base64 48 | tr -d "=+/" | cut -c1-32)

# Generate secure postgres password
POSTGRES_PASSWORD=$(openssl rand -base64 32 | tr -d "=+/" | cut -c1-32)

# Generate dashboard password
DASHBOARD_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)

# Generate logflare API key
LOGFLARE_API_KEY=$(openssl rand -hex 32)

echo "✅ Secrets generated successfully!"
echo ""

# Update .env file
echo "📝 Updating .env file..."

# Create backup
cp .env .env.backup

# Update secrets in .env file
sed -i.tmp "s/APP_SECRET=.*/APP_SECRET=${APP_SECRET}/" .env
sed -i.tmp "s/JWT_SECRET=.*/JWT_SECRET=${JWT_SECRET}/" .env
sed -i.tmp "s/POSTGRES_PASSWORD=.*/POSTGRES_PASSWORD=${POSTGRES_PASSWORD}/" .env
sed -i.tmp "s/DASHBOARD_PASSWORD=.*/DASHBOARD_PASSWORD=${DASHBOARD_PASSWORD}/" .env
sed -i.tmp "s/LOGFLARE_API_KEY=.*/LOGFLARE_API_KEY=${LOGFLARE_API_KEY}/" .env

# Remove temporary file
rm .env.tmp

echo "✅ .env file updated with new secrets!"
echo ""

echo "🔍 Generated secrets:"
echo "APP_SECRET: ${APP_SECRET}"
echo "JWT_SECRET: ${JWT_SECRET}"
echo "POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}"
echo "DASHBOARD_PASSWORD: ${DASHBOARD_PASSWORD}"
echo "LOGFLARE_API_KEY: ${LOGFLARE_API_KEY}"
echo ""

echo "⚠️  IMPORTANT REMINDERS:"
echo "1. Keep these secrets secure and never commit them to version control"
echo "2. Update the following in your .env file manually:"
echo "   - PAYOUT_BTC, PAYOUT_XMR, PAYOUT_ETH, PAYOUT_SOL, PAYOUT_DOGE"
echo "   - TATUM_API_KEY"
echo "3. Backup created at .env.backup"
echo ""

echo "🚀 Ready to start with: docker-compose up -d"