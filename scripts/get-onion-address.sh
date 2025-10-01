#!/bin/bash

# Onion Classifieds - Get .onion Address Script
# This script retrieves the generated .onion address from the Tor container

set -e

echo "🧅 Onion Classifieds - Getting .onion Address"
echo "============================================="

# Check if Tor container is running
if ! docker-compose ps tor | grep -q "Up"; then
    echo "❌ Error: Tor container is not running"
    echo "   Start with: docker-compose up -d tor"
    exit 1
fi

echo "🔍 Checking for .onion address..."

# Wait for Tor to generate the address (may take a few seconds)
for i in {1..30}; do
    if docker-compose exec -T tor test -f /var/lib/tor/hidden_service/hostname; then
        break
    fi
    
    if [ $i -eq 30 ]; then
        echo "❌ Error: .onion address not generated after 30 seconds"
        echo "   Check Tor logs: docker-compose logs tor"
        exit 1
    fi
    
    echo "⏳ Waiting for .onion address generation... ($i/30)"
    sleep 1
done

# Get the .onion address
ONION_ADDRESS=$(docker-compose exec -T tor cat /var/lib/tor/hidden_service/hostname 2>/dev/null | tr -d '\r\n')

if [ -z "$ONION_ADDRESS" ]; then
    echo "❌ Error: Could not retrieve .onion address"
    echo "   Check Tor logs: docker-compose logs tor"
    exit 1
fi

echo "✅ .onion address generated successfully!"
echo ""
echo "🌐 Your Onion Classifieds address:"
echo "   http://$ONION_ADDRESS"
echo ""

# Update .env file with the onion address
if [ -f .env ]; then
    echo "📝 Updating .env file with .onion address..."
    
    # Create backup
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    
    # Update APP_BASE_URL
    sed -i.tmp "s|APP_BASE_URL=.*|APP_BASE_URL=http://$ONION_ADDRESS|" .env
    rm -f .env.tmp
    
    echo "✅ .env file updated with .onion address"
else
    echo "⚠️  Warning: .env file not found. Please update APP_BASE_URL manually:"
    echo "   APP_BASE_URL=http://$ONION_ADDRESS"
fi

echo ""
echo "🔒 Security Information:"
echo "   - This address is only accessible via Tor Browser"
echo "   - The address is persistent and will not change"
echo "   - Keep this address private until you're ready to share"
echo "   - Always verify the address when sharing with users"
echo ""

echo "📋 Next Steps:"
echo "1. Test access via Tor Browser: http://$ONION_ADDRESS"
echo "2. Configure your payment addresses in .env"
echo "3. Set up your Tatum API key"
echo "4. Create your first admin account"
echo "5. Share the .onion address with users"
echo ""

echo "🎉 Your Onion Classifieds marketplace is ready!"
echo "   Access it at: http://$ONION_ADDRESS"