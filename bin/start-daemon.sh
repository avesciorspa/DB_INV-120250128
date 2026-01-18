#!/bin/bash

# Daemon Startup Script - Ensure Single Instance
# Uso: sudo ./start-daemon.sh

set -e

DAEMON_PID_FILE="/var/run/daemon-import.pid"
DAEMON_SCRIPT="/var/www/DB_INV/bin/daemon-import.php"
DAEMON_LOG="/var/www/DB_INV/logs/inv_import.log"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting DB_INV Daemon..."

# 1. Kill any existing instances (gracefully first, then force)
if pgrep -f "daemon-import.php" > /dev/null 2>&1; then
    echo "Stopping existing daemon instances..."
    pkill -15 -f "daemon-import.php" 2>/dev/null || true
    sleep 2
    pkill -9 -f "daemon-import.php" 2>/dev/null || true
    sleep 1
fi

# 2. Remove old PID file if exists
rm -f "$DAEMON_PID_FILE" 2>/dev/null || true

# 2.5. Ensure lock file exists and is world-writable
rm -f /tmp/daemon-import.lock 2>/dev/null || true
touch /tmp/daemon-import.lock
chmod 777 /tmp/daemon-import.lock

# 3. Start new daemon instance in background
echo "Launching new daemon instance..."
nohup php "$DAEMON_SCRIPT" >> "$DAEMON_LOG" 2>&1 &
DAEMON_PID=$!

# 4. Store PID for reference
echo "$DAEMON_PID" > "$DAEMON_PID_FILE"
sleep 2

# 5. Verify daemon started successfully
if ps -p "$DAEMON_PID" > /dev/null 2>&1; then
    echo "✅ Daemon started successfully (PID: $DAEMON_PID)"
    echo ""
    echo "Monitoring first 3 log lines:"
    sleep 2
    tail -3 "$DAEMON_LOG"
else
    echo "❌ FAILED: Daemon did not start (PID: $DAEMON_PID)"
    echo "Last 10 log lines:"
    tail -10 "$DAEMON_LOG"
    exit 1
fi

echo ""
echo "Start time: $(date)"
echo "Run 'sudo tail -f $DAEMON_LOG' to monitor logs"
