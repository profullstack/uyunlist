#!/bin/bash

# Onion Classifieds - Database Backup Script
# This script creates backups of the PostgreSQL database

set -e

BACKUP_DIR="./backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="onion_classifieds_backup_${TIMESTAMP}.sql"

echo "🧅 Onion Classifieds - Database Backup"
echo "====================================="

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Check if Docker Compose is running
if ! docker-compose ps db | grep -q "Up"; then
    echo "❌ Error: Database container is not running"
    echo "   Start with: docker-compose up -d db"
    exit 1
fi

echo "📦 Creating database backup..."

# Create backup using pg_dump
docker-compose exec -T db pg_dump -U postgres -h localhost postgres > "$BACKUP_DIR/$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Backup created successfully: $BACKUP_DIR/$BACKUP_FILE"
    
    # Compress the backup
    gzip "$BACKUP_DIR/$BACKUP_FILE"
    echo "✅ Backup compressed: $BACKUP_DIR/$BACKUP_FILE.gz"
    
    # Get file size
    BACKUP_SIZE=$(du -h "$BACKUP_DIR/$BACKUP_FILE.gz" | cut -f1)
    echo "📊 Backup size: $BACKUP_SIZE"
    
    # Clean up old backups (keep last 30 days)
    echo "🧹 Cleaning up old backups..."
    find "$BACKUP_DIR" -name "onion_classifieds_backup_*.sql.gz" -mtime +30 -delete
    
    REMAINING_BACKUPS=$(ls -1 "$BACKUP_DIR"/onion_classifieds_backup_*.sql.gz 2>/dev/null | wc -l)
    echo "📁 Remaining backups: $REMAINING_BACKUPS"
    
else
    echo "❌ Error: Backup failed"
    rm -f "$BACKUP_DIR/$BACKUP_FILE"
    exit 1
fi

# Optional: Upload to external storage
if [ ! -z "$BACKUP_UPLOAD_COMMAND" ]; then
    echo "☁️  Uploading backup to external storage..."
    eval "$BACKUP_UPLOAD_COMMAND $BACKUP_DIR/$BACKUP_FILE.gz"
    
    if [ $? -eq 0 ]; then
        echo "✅ Backup uploaded successfully"
    else
        echo "⚠️  Warning: Backup upload failed"
    fi
fi

echo ""
echo "🎉 Backup completed successfully!"
echo "   File: $BACKUP_DIR/$BACKUP_FILE.gz"
echo "   Size: $BACKUP_SIZE"
echo "   Timestamp: $TIMESTAMP"

# Show backup restore instructions
echo ""
echo "📖 To restore this backup:"
echo "   1. Stop the application: docker-compose down"
echo "   2. Start only the database: docker-compose up -d db"
echo "   3. Restore: gunzip -c $BACKUP_DIR/$BACKUP_FILE.gz | docker-compose exec -T db psql -U postgres"
echo "   4. Start all services: docker-compose up -d"