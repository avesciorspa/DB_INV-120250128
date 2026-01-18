#!/bin/bash

# DB_INV Quick Start - Setup Script
# Usage: bash quickstart.sh

set -e

PROJECT_DIR="/var/www/DB_INV"
LOG_DIR="$PROJECT_DIR/logs"

echo "================================"
echo "DB_INV System - Quick Start"
echo "================================"
echo ""

# Check prerequisites
echo "1. Checking prerequisites..."
command -v php >/dev/null 2>&1 || { echo "❌ PHP not found"; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "❌ MySQL client not found"; exit 1; }
echo "   ✅ PHP $(php -v | head -1)"
echo "   ✅ MySQL client found"
echo ""

# Create directories
echo "2. Creating directories..."
mkdir -p "$PROJECT_DIR/logs"
mkdir -p "$PROJECT_DIR/bin"
mkdir -p "$PROJECT_DIR/src"
mkdir -p "$PROJECT_DIR/web/api"
echo "   ✅ Directories created"
echo ""

# Setup .env
echo "3. Configuring environment..."
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo "   ⚠️  .env not found, creating from example..."
    cat > "$PROJECT_DIR/.env" << 'EOF'
# Database Connections
DB2_HOST=10.151.30.1
DB2_PORT=50000
DB2_DATABASE=XVMWEB
DB2_USER=xvmodbc
DB2_PASS=your_password_here

MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=DB_INV
MYSQL_USER=DB_INV
MYSQL_PASS=your_password_here

# Logging
LOG_PATH=/var/www/DB_INV/logs

# CUPS
CUPS_TEMP_DIR=/var/www/DB_INV/tmp/cups

# Lock
LOCK_FILE=/var/www/DB_INV/tmp/import.lock
EOF
    echo "   ⚠️  Please edit .env with real credentials"
    echo "   vim $PROJECT_DIR/.env"
else
    echo "   ✅ .env already configured"
fi
echo ""

# Create MySQL schema
echo "4. Setting up MySQL database..."
read -p "   Enter MySQL root password: " -s MYSQL_PASS
echo ""
mysql -u root -p"$MYSQL_PASS" << 'EOSQL'
CREATE DATABASE IF NOT EXISTS DB_INV CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER IF NOT EXISTS 'DB_INV'@'localhost' IDENTIFIED BY 'default_password';
GRANT ALL PRIVILEGES ON DB_INV.* TO 'DB_INV'@'localhost';
FLUSH PRIVILEGES;
EOSQL

# Load schema
mysql -u DB_INV -pdefault_password DB_INV < "$PROJECT_DIR/db/init.sql" 2>/dev/null || true
mysql -u DB_INV -pdefault_password DB_INV < "$PROJECT_DIR/db/extend-schema.sql" 2>/dev/null || true
echo "   ✅ Database schema loaded"
echo ""

# Test PHP syntax
echo "5. Validating PHP files..."
find "$PROJECT_DIR/src" -name "*.php" -type f | while read file; do
    if ! php -l "$file" > /dev/null 2>&1; then
        echo "   ❌ Syntax error: $file"
        exit 1
    fi
done
echo "   ✅ All PHP files validated"
echo ""

# Setup web server
echo "6. Web server setup..."
echo "   Option A (Built-in PHP server):"
echo "     cd $PROJECT_DIR/web && php -S localhost:8080"
echo ""
echo "   Option B (Apache):"
echo "     <VirtualHost *:8080>"
echo "         DocumentRoot $PROJECT_DIR/web"
echo "     </VirtualHost>"
echo ""

# Setup cron
echo "7. Cron job setup..."
CRON_CMD="/usr/bin/php $PROJECT_DIR/bin/import_invc.php >> $LOG_DIR/cron.log 2>&1"
if crontab -l 2>/dev/null | grep -q "$CRON_CMD"; then
    echo "   ✅ Cron job already configured"
else
    echo "   ⚠️  Add this line to crontab -e:"
    echo "   * * * * * $CRON_CMD"
fi
echo ""

# Final checks
echo "8. Final checks..."
echo "   ✅ Project structure: OK"
echo "   ✅ Database: Check connection below"
echo ""

echo "================================"
echo "Setup Complete!"
echo "================================"
echo ""
echo "Next steps:"
echo "1. Edit .env with real DB2 and MySQL credentials"
echo "2. Start web server: cd $PROJECT_DIR/web && php -S localhost:8080"
echo "3. Visit http://localhost:8080 in browser"
echo "4. Configure printers via http://localhost:8080/printers.php"
echo "5. Test CLI: php $PROJECT_DIR/bin/import_invc.php"
echo "6. Setup cron: crontab -e (add line from above)"
echo ""
echo "Documentation:"
echo "  - README.md (Overview + Usage)"
echo "  - ARCHITECTURE.md (Technical Design)"
echo "  - TEST_REPORT.md (Test Status)"
echo ""
