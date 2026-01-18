# DB_INV System Architecture

## 🏛️ High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                       DB_INV SYSTEM                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────┐         ┌──────────────────┐             │
│  │   DB2 XVMWEB     │         │   MySQL DB_INV   │             │
│  │  (D01.INVC)      │         │   (7 Tables)     │             │
│  └────────┬─────────┘         └──────────────────┘             │
│           │                                                     │
│           │                                                     │
│     ┌─────▼──────────────────────────────────────┐             │
│     │      CLI IMPORT SCRIPT                     │             │
│     │   (bin/import_invc.php - 1x/min cron)     │             │
│     ├──────────────────────────────────────────────┤           │
│     │ 1. Lock Manager (prevents parallel runs)   │           │
│     │ 2. DB2 Reader (SELECT from D01.INVC)      │           │
│     │ 3. Marker Processor (AREA/STAMPA logic)    │           │
│     │ 4. CONTEGGI Upsert (dedup + insert)        │           │
│     │ 5. Printer Manager (CUPS integration)      │           │
│     │ 6. Logger (audit trail)                    │           │
│     └─────┬──────────────────────────────────────┘           │
│           │                                                     │
│     ┌─────▼──────────────────────────────────────┐             │
│     │      WEB UI INTERFACE                      │             │
│     │   (web/index.php, web/printers.php)        │             │
│     ├──────────────────────────────────────────────┤           │
│     │ • CRUD Operators (CONF_OPERATORE)          │           │
│     │ • CRUD Printers (STAMPANTI)                │           │
│     │ • Bulk Operations (edit/delete)            │           │
│     │ • API endpoints (JSON)                     │           │
│     └──────────────────────────────────────────────┘           │
│                                                                 │
│  ┌──────────────────────────────────────────────┐              │
│  │      CUPS / PRINT INFRASTRUCTURE             │              │
│  │  (lpr command → socket://IP:9100)            │              │
│  └──────────────────────────────────────────────┘              │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📦 Component Structure

### Core Application (src/)

#### Configuration & Logging
- **Config.php**: Environment loader + validator (PSR-4)
- **Logger.php**: File-based logging with 5 severity levels

#### Database Connections
- **MySQLConnection.php**: PDO wrapper with transaction support
  - Supports: SELECT, INSERT, UPDATE, DELETE with prepared statements
  - Features: Transaction begin/commit/rollback, error handling

- **DB2Connection.php**: ODBC wrapper for remote DB2 access
  - Supports: TCP/IP connection to 10.151.30.1:50000
  - Features: Connection pooling ready, query result fetching

#### Data Access Layer (Repository Pattern)
Each repository provides a clean interface to its table:

1. **ConfigOperatoreRepository** (CONF_OPERATORE)
   - Methods: create, read, update, delete, bulkDelete, bulkUpdateArea, bulkUpdatePrinter
   - Queries: 8 SQL operations with bound parameters

2. **StampantiRepository** (STAMPANTI)
   - Methods: getAll, findByIp, create, update, delete, getDefault, countUsage
   - Features: Usage counting prevents deletion of active printers

3. **RepartiRepository** (REPARTI - Read-only)
   - Methods: getAll, getForSelect (for UI dropdowns)
   - Cache: None (small table, 12 rows)

4. **AreeRepository** (AREE - Read-only)
   - Methods: getAll, getForSelect (for UI dropdowns)
   - Cache: None (small table, 10 rows)

5. **MarkerRepository** (MARKER - Read-only)
   - Methods: isMarker, getMarkerType, invalidateCache
   - Cache: 5-minute TTL to reduce DB queries

6. **ConteggiRepository** (CONTEGGI)
   - Methods: upsertFromINVC, getForPrint, markAsPrinted, countUnprinted
   - Features: Type-based filtering for print jobs

#### Import Pipeline (Import/)
- **Importer.php** contains 2 classes:

  1. **LockManager** (300s timeout)
     - Acquire: Create lock file with exclusive lock
     - Release: Remove lock file
     - Purpose: Prevent cron job races

  2. **MarkerProcessor**
     - Input: Operator, marker type, area
     - Output: CONF_OPERATORE updated (AREA) or print job (STAMPA)
     - Dependency injection: Repos + PrinterManager

#### Print Management (Print/)
- **PrinterManager**
  - Methods: printToQueue, formatInventoryPrint (static)
  - Features: 
    - Generates formatted TXT files
    - Executes `lpr` command to CUPS
    - Cleans up temp files
    - Full error handling & logging

### Web Application (web/)

#### Bootstrapping
- **autoload.php**: PSR-4 autoloader for `App\` namespace
- **bootstrap.php**: 
  - Loads Config from .env
  - Instantiates Logger
  - Creates MySQLConnection (PDO singleton pattern ready)
  - Returns DI container

#### UI Pages
- **index.php** (573 lines)
  - Tabbed interface: Operatori | Stampanti
  - CRUD forms with validation (client + server)
  - Bulk operations (select/delete/update)
  - AJAX API calls with error handling
  - Responsive grid layout

- **printers.php** (241 lines)
  - CRUD for STAMPANTI
  - IP/queue validation
  - Usage counter prevents deletion
  - Modal edit forms

#### API Endpoints
- **api/get-conf.php**: Returns JSON operator config by ID
- **api/get-printer.php**: Returns JSON printer by IP

### CLI Application (bin/)

- **import_invc.php** (Main entry point)
  - Flow:
    1. Load config + logger
    2. Acquire lock (exit if locked)
    3. Connect DB2 + MySQL
    4. Instantiate all repos + PrinterManager
    5. Read INVC from DB2 (WHERE DATE = TODAY)
    6. For each record: detect marker → process → upsert
    7. Log results to IMPORT_LOG
    8. Release lock
  - Exit codes:
    - 0: Success or already running (no error)
    - 1: Fatal error (logged to critical)

---

## 🗄️ Data Flow

### Import Flow (CLI)

```
┌─────────────────────────────────┐
│  DB2: D01.INVC                  │
│  (New records from today)       │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  LockManager.acquire()          │
│  (Prevent parallel execution)   │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  Read from DB2 (readFromDB2)    │
│  SELECT REPARTO, NUMERO_INV,    │
│         PRECODICE, CODICE_ART   │
│  ...                            │
└────────────┬────────────────────┘
             │
             ▼
      ┌──────┴─────────┐
      │                │
      ▼                ▼
  ┌────────────┐  ┌──────────────────┐
  │ Is Marker? │  │ Normal Record    │
  └────┬───────┘  └────────┬─────────┘
       │                   │
       ├─ AREA marker:     ├─ upsertFromINVC()
       │  updateArea()     │  (INSERT...ON DUPLICATE)
       │  (CONF_OP)        │
       │                   │
       ├─ STAMPA marker:   └─ MySQL: CONTEGGI
       │  getForPrint()       (Upserted)
       │  formatPrint()
       │  PrinterManager
       │  printToQueue()
       │  (CUPS via lpr)
       │
       └─ markAsPrinted()
          (CONTEGGI.STAMPATO=1)
          
Result: Logger.info()
        IMPORT_LOG updated
        Lock released
```

### Marker Logic

```
┌──────────────────────────────────────────┐
│ MARKER RECORD DETECTED                   │
│ PRECODICE='ZZZ', CODICE_ART='AREA'/'STAMPA' │
└──────────────────────────────────────────┘
         │
         ▼
    ┌────────────────┐
    │ Type = AREA?   │
    └────────┬───────┘
             │Yes
             ▼
    ┌────────────────────────────┐
    │ MarkerProcessor            │
    │ .handleMarkerArea()        │
    │ • Look up operator config  │
    │ • Update ID_AREA field     │
    │ • Log change               │
    │ No print output            │
    └────────────────────────────┘
    
         │No (STAMPA)
         ▼
    ┌──────────────────────────────────┐
    │ MarkerProcessor                  │
    │ .handleMarkerStampa()            │
    │ • Look up operator config        │
    │ • Get print type from QTA        │
    │ • Query CONTEGGI by type:        │
    │   - Type 1: By area              │
    │   - Type 3: All unstamped        │
    │   - Type 4: Last 50              │
    │   - Type 5: Unstamped            │
    │ • Format as TXT                  │
    │ • Send to CUPS via lpr           │
    │ • Mark records STAMPATO=1        │
    └──────────────────────────────────┘
```

---

## 🔐 Security Design

### SQL Injection Prevention
- **All queries**: Use PDO prepared statements with bound parameters
- **Example**: `$stmt->execute([$userId, $name])` ← safe
- **No string concatenation** in SQL strings

### Input Validation
- **Client-side**: HTML5 form validation (regex, length, type)
- **Server-side**: 
  - Codice: `preg_match('/^[A-Z0-9]{1,10}$/', $input)`
  - IP: Regex validation before CUPS
  - Numeric fields: type casting (int, float)

### File Operations
- **Temp files**: Unique names with timestamp
- **Cleanup**: Unlink after CUPS transmission
- **Directory**: Created with 0755 permissions

### Logging
- **Sensitive data**: Never logged (no passwords, no full credit cards)
- **Audit trail**: All mutations recorded with timestamp + operator
- **Log location**: `/var/www/DB_INV/logs/` (outside web root)

---

## 📊 Database Schema

### MARKER (Read-only, 2 rows)
```
+----------+-----------+-----------+
| PRECODICE| CODICE_ART| (Markers) |
+----------+-----------+-----------+
| ZZZ      | AREA      | 1 row     |
| ZZZ      | STAMPA    | 1 row     |
+----------+-----------+-----------+
```
**Key**: `PK (PRECODICE, CODICE_ART)`

### CONF_OPERATORE (Main operator config)
Columns:
- ID_CONF (PK)
- CODICE (VARCHAR 10, unique)
- NOME (VARCHAR 100)
- ID_REPARTO (FK → REPARTI)
- ID_AREA (FK → AREE, can be -1 for "no area")
- IP_STAMPANTE (FK → STAMPANTI.IP)

### STAMPANTI (Printer registry)
Columns:
- IP (CHAR 15, PK)
- CODA_CUPS (VARCHAR 50, unique)
(Simplified: no REPARTO, no metadata tracking)

### CONTEGGI (Import target, 0 rows initially)
Columns:
- ID_CONTEGGIO (PK, auto)
- REPARTO (int)
- NUMERO_INV (int)
- PRECODICE (CHAR 3)
- CODICE_ART (CHAR 6)
- POSIZIONE (VARCHAR 20)
- NUMERO_CONTA (int)
- PROG (int)
- QTA_CONTEGGIATA (DECIMAL 11,3)
- OPER_CREAZ (VARCHAR 20)
- DATA_CREAZ (DATE)
- ORA_CREAZ (TIME)
- ID_AREA (int, default -1)
- STAMPATO (TINYINT, 0=no, 1=yes)

**Key**: `UNIQUE KEY (REPARTO, NUMERO_INV)` for upsert

### REPARTI (12 locations)
Read-only master data

### AREE (10 inventory areas)
Read-only master data

### IMPORT_LOG (Audit trail)
Tracks each import execution with timestamp + stats

---

## 🚀 Deployment

### Prerequisites
- PHP 8.1+
- MySQL 8.0+ (InnoDB)
- DB2 with ODBC driver (for CLI)
- CUPS server (for printing)
- Linux cron daemon

### Installation Steps
1. Clone to `/var/www/DB_INV`
2. Copy `.env.example` → `.env`
3. Update `.env` with real DB2/MySQL credentials
4. `php bin/init.php` (creates schema)
5. Configure cron: `* * * * * /usr/bin/php /var/www/DB_INV/bin/import_invc.php`

### Web Server
```apache
<VirtualHost *:8080>
    DocumentRoot /var/www/DB_INV/web
    <Directory /var/www/DB_INV/web>
        DirectoryIndex index.php
        AllowOverride All
    </Directory>
</VirtualHost>
```

Or use built-in server:
```bash
cd /var/www/DB_INV/web
php -S localhost:8080
```

---

## 📈 Performance Considerations

### Marker Cache
- **TTL**: 5 minutes
- **Benefit**: Reduces DB queries (marker check on every import)
- **Invalidation**: Manual via `invalidateCache()` after insert

### Prepared Statements
- All queries use bound parameters
- PDO driver handles escaping
- Benefit: Connection pooling ready (MySQL)

### Lock Timeout
- **Duration**: 300 seconds (5 minutes)
- **Rationale**: Typical import should finish in <60s, 5min buffer for slow networks

### Batch Processing
- **Cron**: 1x per minute
- **Assumption**: <100 new records per minute from DB2

---

## 🔄 State Management

### Lock File State
- **Absent**: Previous import completed
- **Present, <300s**: Currently importing (skip)
- **Present, >300s**: Stale lock (remove & retry)

### Marker Cache State
- **Memory**: Stored in PHP static `$cache` array
- **Persistence**: Per-process (new process = cache miss)
- **TTL check**: Time-based expiry

### Database Transactions
- **CONTEGGI upsert**: Runs without explicit transaction
- **Marker processing**: May span multiple tables (no transaction currently)
  - Risk: Partial update if failure between CONF_OP + CONTEGGI
  - Mitigation: Idempotent upsert (INSERT...ON DUPLICATE KEY UPDATE)

---

## 📝 Audit Trail

### Logged Events
1. Import start/end (INFO)
2. Lock acquisition (WARNING if locked)
3. DB2 read errors (ERROR)
4. Marker processing (INFO)
5. CUPS print success/failure (INFO/ERROR)
6. Upsert failures (ERROR)
7. Fatal errors (CRITICAL)

### Log Format
```
[2025-01-18 14:30:45] INFO: Marker AREA processed {"operatore":"OP001","area":5}
[2025-01-18 14:30:46] ERROR: Stampante non trovata {"ip":"172.16.8.248"}
```

### Log Location
- CLI: `/var/www/DB_INV/logs/inv_import.log`
- Cron: Also to `/var/www/DB_INV/logs/cron.log` (via redirection)

---

**Version**: 1.0  
**Last Updated**: 2025-01-18  
**Author**: AI Code Generator
