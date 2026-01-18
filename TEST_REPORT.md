# Test Plan - DB_INV System

## ✅ Completed Tests

### 1. Syntax Validation (PASSED)
All 19 PHP files pass `php -l` validation:
- ✅ Config.php
- ✅ Logger.php
- ✅ Database/MySQLConnection.php
- ✅ Database/DB2Connection.php
- ✅ Repository/ConfigOperatoreRepository.php
- ✅ Repository/StampantiRepository.php
- ✅ Repository/RepartiRepository.php
- ✅ Repository/AreeRepository.php
- ✅ Repository/MarkerRepository.php
- ✅ Repository/ConteggiRepository.php
- ✅ Import/Importer.php (LockManager, MarkerProcessor)
- ✅ Print/PrinterManager.php
- ✅ web/autoload.php
- ✅ web/bootstrap.php
- ✅ web/index.php
- ✅ web/printers.php
- ✅ web/api/get-conf.php
- ✅ web/api/get-printer.php
- ✅ bin/import_invc.php

### 2. Database Schema (PASSED)
- ✅ MySQL DB_INV created with 7 tables
- ✅ MARKER: 2 rows (AREA, STAMPA)
- ✅ CONTEGGI: 0 rows (ready for import)
- ✅ CONF_OPERATORE: 4 test rows
- ✅ STAMPANTI: 4 test rows
- ✅ REPARTI: 12 rows
- ✅ AREE: 10 rows
- ✅ IMPORT_LOG: 0 rows (ready for logging)

### 3. Web UI (TESTED via test-ui.sh - 8/8 PASSED)
- ✅ index.php loads (HTTP 200)
- ✅ printers.php loads (HTTP 200)
- ✅ CRUD forms render correctly
- ✅ AJAX endpoints respond with JSON
- ✅ Database records visible in UI
- ✅ Bulk selection with hidden IDs
- ✅ Validation works (client + server)
- ✅ Modal dialogs render

### 4. API Endpoints (TESTED)
- ✅ GET /web/api/get-conf.php returns JSON with operator config
- ✅ GET /web/api/get-printer.php returns JSON with printer data
- ✅ Error handling for missing records
- ✅ PDO prepared statements prevent SQL injection

---

## 🔬 Unit Tests (Manual)

### Test 1: Lock Manager
```bash
php -r "
require_once '/var/www/DB_INV/web/bootstrap.php';
use App\Import\LockManager;
\$lock = new LockManager('/tmp/test.lock');
echo \$lock->acquire() ? '✅ Lock acquired' : '❌ Lock failed';
\$lock->release();
echo ' and released';
"
```

### Test 2: Logger
```bash
php -r "
require_once '/var/www/DB_INV/web/bootstrap.php';
use App\Logger;
\$logger = new Logger('/tmp/test.log');
\$logger->info('Test message', ['key' => 'value']);
echo file_exists('/tmp/test.log') ? '✅ Log file created' : '❌ Log failed';
"
```

### Test 3: ConteggiRepository
```bash
php -r "
require_once '/var/www/DB_INV/web/bootstrap.php';
\$mysql = new \App\Database\MySQLConnection(
    \$_ENV['MYSQL_HOST'],
    \$_ENV['MYSQL_PORT'],
    \$_ENV['MYSQL_DATABASE'],
    \$_ENV['MYSQL_USER'],
    \$_ENV['MYSQL_PASS']
);
\$repo = new \App\Repository\ConteggiRepository(\$mysql);
echo 'ConteggiRepository instantiated successfully';
"
```

### Test 4: MarkerRepository
```bash
php -r "
require_once '/var/www/DB_INV/web/bootstrap.php';
\$mysql = new \App\Database\MySQLConnection(
    \$_ENV['MYSQL_HOST'],
    \$_ENV['MYSQL_PORT'],
    \$_ENV['MYSQL_DATABASE'],
    \$_ENV['MYSQL_USER'],
    \$_ENV['MYSQL_PASS']
);
\$repo = new \App\Repository\MarkerRepository(\$mysql);
\$result = \$repo->isMarker('ZZZ', 'AREA');
echo \$result ? '✅ Marker AREA detected' : '❌ Marker not detected';
"
```

### Test 5: PrinterManager Formatting
```bash
php -r "
require_once '/var/www/DB_INV/web/bootstrap.php';
use App\Print\PrinterManager;

\$testData = [
    ['PRECODICE' => 'PROD001', 'CODICE_ART' => 'PART01', 'POSIZIONE' => 'A1', 'QTA_CONTEGGIATA' => 100.50, 'ID_CONTEGGIO' => 1],
    ['PRECODICE' => 'PROD002', 'CODICE_ART' => 'PART02', 'POSIZIONE' => 'A2', 'QTA_CONTEGGIATA' => 50.00, 'ID_CONTEGGIO' => 2],
];

\$lines = PrinterManager::formatInventoryPrint(\$testData, ['operatore' => 'OP001', 'area' => 5]);
echo count(\$lines) > 10 ? '✅ Print format generated (' . count(\$lines) . ' lines)' : '❌ Print format failed';
"
```

---

## 🔗 Integration Tests (Pending)

### Test 6: CLI Import Dry Run
```bash
# Set DB2 environment and run:
php /var/www/DB_INV/bin/import_invc.php
```

Expected:
- Lock acquired and released
- Records read from DB2.D01.INVC
- Markers processed
- Conteggi upserted
- Log file updated

### Test 7: Marker Processing
```bash
# Insert test marker record in DB2
# Run CLI import
# Verify:
# - CONF_OPERATORE.ID_AREA updated (if AREA marker)
# - File generated in CUPS temp dir (if STAMPA marker)
# - CONTEGGI.STAMPATO = 1 after printing
```

### Test 8: CUPS Integration
```bash
# Verify lpr command execution
# Check CUPS queue status: lpstat -p -d
# Monitor print job: lpq -P <queue_name>
```

---

## 📊 Coverage Summary

| Component | Status | Notes |
|-----------|--------|-------|
| Config.php | ✅ Loaded | Environment validation works |
| Logger.php | ✅ Loaded | File-based logging functional |
| MySQLConnection.php | ✅ Loaded | PDO transactions ready |
| DB2Connection.php | ✅ Loaded | ODBC template ready (needs test) |
| All 6 Repositories | ✅ Loaded | CRUD + bulk ops functional |
| Web UI (index.php) | ✅ Tested | CRUD + bulk edit working |
| Web UI (printers.php) | ✅ Tested | CRUD with usage counter working |
| API endpoints | ✅ Tested | JSON responses correct |
| LockManager | ✅ Code review | Implementation correct |
| MarkerProcessor | ✅ Code review | Marker detection + processing ready |
| PrinterManager | ✅ Code review | TXT generation + lpr ready |
| CLI import | ⏳ Pending | Awaiting DB2 test environment |

---

## 🚀 Deployment Checklist

- [x] All PHP files syntax validated
- [x] Database schema created and tested
- [x] Web UI functional (manual + automated tests)
- [x] API endpoints working
- [x] CLI structure in place
- [x] Lock manager implemented
- [x] Marker processor implemented
- [x] Printer manager implemented
- [ ] DB2 connection tested with real data
- [ ] CLI import executed successfully
- [ ] CUPS printing tested on real printer
- [ ] Cron job configured
- [ ] Monitoring alerts configured
- [ ] Documentation complete

---

**Generated:** 2025-01-18  
**Status:** Ready for DB2 Testing
