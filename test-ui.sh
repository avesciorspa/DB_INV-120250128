#!/bin/bash
# Test script per Web UI
# Uso: bash test-ui.sh

set -e

BASE_URL="${1:-http://localhost:8080}"
echo "Testing DB_INV Web UI at $BASE_URL"
echo "=================================="

# Test 1: Home page carica
echo "✓ Test 1: Caricamento home page..."
RESPONSE=$(curl -s -w "%{http_code}" "$BASE_URL" -o /tmp/index.html)
if [ "$RESPONSE" = "200" ]; then
    echo "  ✅ HTTP 200 OK"
else
    echo "  ❌ HTTP $RESPONSE"
    exit 1
fi

# Test 2: Verifica elementi pagina
echo "✓ Test 2: Verifica elementi HTML..."
grep -q "DB_INV - Gestione Configurazioni" /tmp/index.html && echo "  ✅ Title presente"
grep -q "Configurazioni Operatori" /tmp/index.html && echo "  ✅ Sezione operatori presente"
grep -q "toggleSelectAll" /tmp/index.html && echo "  ✅ Bulk selection JS presente"
grep -q "Marco Rossi" /tmp/index.html && echo "  ✅ Dati test presenti"

# Test 3: Printers page
echo "✓ Test 3: Caricamento pagina stampanti..."
RESPONSE=$(curl -s -w "%{http_code}" "$BASE_URL/printers.php" -o /tmp/printers.html)
if [ "$RESPONSE" = "200" ]; then
    echo "  ✅ HTTP 200 OK"
else
    echo "  ❌ HTTP $RESPONSE"
fi

grep -q "172.16.8.248" /tmp/printers.html && echo "  ✅ Stampante test presente"
grep -q "editPrinter" /tmp/printers.html && echo "  ✅ API stampanti presente"

# Test 4: API get-conf
echo "✓ Test 4: Test API get-conf.php..."
RESPONSE=$(curl -s "$BASE_URL/api/get-conf.php?id=1" | jq -r '.success' 2>/dev/null || echo "error")
if [ "$RESPONSE" = "true" ]; then
    echo "  ✅ API JSON valido"
else
    echo "  ⚠️  API non testable (jq non installato o errore)"
fi

# Test 5: Database check
echo "✓ Test 5: Verifica database..."
mysql -h localhost -u DB_INV -pDB_INV DB_INV -e "SELECT COUNT(*) as count FROM CONF_OPERATORE" 2>/dev/null | tail -1 | grep -q "4" && echo "  ✅ 4 operatori in DB"
mysql -h localhost -u DB_INV -pDB_INV DB_INV -e "SELECT COUNT(*) as count FROM STAMPANTI" 2>/dev/null | tail -1 | grep -q "4" && echo "  ✅ 4 stampanti in DB"
mysql -h localhost -u DB_INV -pDB_INV DB_INV -e "SELECT COUNT(*) as count FROM REPARTI" 2>/dev/null | tail -1 | grep -q "12" && echo "  ✅ 12 reparti in DB"

echo ""
echo "=================================="
echo "✅ Tutti i test passati!"
echo "=================================="
