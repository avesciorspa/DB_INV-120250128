# DB_INV - Sistema di Importazione e Gestione Inventario

## 📋 Panoramica

Progetto per importare dati da **DB2 INVC** a **MySQL**, con gestione di marker, stampa CUPS e interfaccia web di configurazione.

---

## 🏗️ Struttura del Progetto

```
DB_INV/
├── .env                          # Configurazione (credenziali, percorsi)
├── .env.example                  # Esempio configurazione
├── composer.json                 # Dipendenze PHP
├── src/
│   ├── Config.php               # Caricamento e validazione .env
│   ├── Logger.php               # Sistema logging
│   ├── Database/
│   │   ├── MySQLConnection.php  # Connessione MySQL (PDO)
│   │   └── DB2Connection.php    # Connessione DB2 (ODBC)
│   ├── Repository/              # Data Access Objects
│   │   ├── ConfigOperatoreRepository.php
│   │   ├── StampantiRepository.php
│   │   ├── RepartiRepository.php
│   │   ├── AreeRepository.php
│   │   ├── MarkerRepository.php
│   │   └── ConteggiRepository.php
│   ├── Import/                  # Logica importazione
│   │   └── Importer.php         # LockManager, MarkerProcessor
│   └── Print/
│       └── PrinterManager.php   # Gestione CUPS e formattazione stampe
├── bin/
│   └── import_invc.php          # Script CLI per importazione periodica
├── web/
│   ├── index.php                # Home + CRUD CONF_OPERATORE
│   ├── printers.php             # Gestione STAMPANTI
│   ├── bootstrap.php            # Inizializzazione e DI
│   ├── autoload.php             # Autoloader PSR-4
│   ├── api/
│   │   ├── get-conf.php         # API GET configurazione operatore
│   │   └── get-printer.php      # API GET stampante
│   └── assets/
│       └── (CSS/JS stici in futuro)
├── db/
│   ├── init.sql                 # Schema iniziale (7 tabelle)
│   └── extend-schema.sql        # Estensione con REPARTI e AREE
└── logs/
    └── inv_import.log           # Log importazioni
```

---

## 🗄️ Database Schema

### Tabelle Principali

| Tabella | Descrizione | Righe |
|---------|-----------|-------|
| **CONTEGGI** | Mirror di INVC (DB2) + metadata | 0 (in build) |
| **CONF_OPERATORE** | Config operatore per reparto/inventario | 4 (test) |
| **STAMPANTI** | Mappatura IP → coda CUPS | 4 |
| **REPARTI** | Anagrafica reparti/negozi | 12 |
| **AREE** | Aree di inventario (GARDEN, CASA, etc.) | 10 |
| **MARKER** | Tipi di marker riconosciuti (ZZZ/AREA, ZZZ/STAMPA) | 2 |
| **IMPORT_LOG** | Traccia esecuzioni import | 0 |

### Relazioni

```
CONF_OPERATORE (CODICE, ID_REP, ID_INVENTARIO) → REPARTI (ID_REP)
CONF_OPERATORE (IP_STAMPANTE) → STAMPANTI (IP)
CONTEGGI (ID_AREA) → AREE (ID_AREA)
STAMPANTI (ID_REP) → REPARTI (ID_REP) [opzionale]
```

---

## 🌐 Web UI - Pagine

### index.php - Gestione Configurazioni Operatori

**Funzionalità:**
- ✅ **CRUD**: Creare, leggere, modificare, eliminare configurazioni
- ✅ **Bulk Operations**:
  - Eliminazione massiva
  - Aggiornamento area per più operatori
  - Aggiornamento stampante per più operatori
- ✅ **Validazione Lato Client**:
  - Codice operatore: max 10 char, lettere/numeri
  - Reparto/Inventario: obbligatori
  - Select dropdown per reparti/aree
- ✅ **Modal AJAX**: Caricamento dati via API `get-conf.php`

**Dati Visibili:**
- Operatore (CODICE)
- Nome completo
- Reparto (nome da REPARTI)
- Inventario
- Area corrente (nome da AREE)
- Stampante assegnata (IP)

### printers.php - Gestione Stampanti

**Funzionalità:**
- ✅ **CRUD**: Creare, modificare, eliminare stampanti
- ✅ **Validazione IP**: Regex `\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}`
- ✅ **Relazione Reparto**: Associazione opzionale a REPARTI
- ✅ **Protezione Delete**: Impedisce cancellazione se in uso
- ✅ **Conteggio Uso**: Mostra quanti operatori usano la stampante

**Dati Visibili:**
- IP stampante
- Coda CUPS (nome locale, es. INV_REPARTO_A)
- Reparto associato
- Note (ubicazione, descrizione)
- Conteggio utilizzi

---

## 🔌 API Endpoints

### GET `/api/get-conf.php?id=<ID_CONF>`
Restituisce dati configurazione in JSON.

**Response:**
```json
{
  "success": true,
  "data": {
    "ID_CONF": 1,
    "CODICE": "OP001",
    "NOME": "Marco Rossi",
    "ID_REP": 10,
    "ID_INVENTARIO": 2024,
    "ID_AREA": 1,
    "IP_STAMPANTE": "192.168.1.100"
  }
}
```

### GET `/api/get-printer.php?ip=<IP>`
Restituisce dati stampante in JSON.

---

## ⚙️ Configurazione

### `.env` Variabili Richieste

```env
# Database DB2
DB2_DSN=DB2
DB2_USER=xvmodbc
DB2_PASS=xvmodbc
DB2_HOST=10.151.30.1
DB2_PORT=50000
DB2_NAME=XVMWEB

# Database MySQL
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_USER=DB_INV
MYSQL_PASS=DB_INV
MYSQL_DB=DB_INV

# Logging
LOG_LEVEL=INFO
LOG_FILE=/var/www/DB_INV/logs/inv_import.log

# CUPS
PRINT_TEMP_DIR=/var/spool/inv_temp
PRINT_RETENTION_DAYS=7
PRINT_RETRY_ATTEMPTS=3

# Script CLI
SCRIPT_LOCK_FILE=/var/run/inv_import.lock
LOCK_TIMEOUT=300
```

---

## 🚀 Utilizzo

### Web UI - Development

```bash
cd /var/www/DB_INV/web
php -S localhost:8080
# Visita http://localhost:8080
```

### Web UI - Production

Configurare Apache/Nginx con VirtualHost pointing a `/var/www/DB_INV/web`.

```apache
<VirtualHost *:80>
    ServerName db-inv.example.com
    DocumentRoot /var/www/DB_INV/web
    <Directory /var/www/DB_INV/web>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Script Import CLI (DA IMPLEMENTARE)

```bash
php /var/www/DB_INV/bin/import_invc.php
```

Schedulare con cron:
```bash
* * * * * /usr/bin/php /var/www/DB_INV/bin/import_invc.php >/dev/null 2>&1
```

---

## 🔄 Flusso Dati

```
┌─────────────────────────────────────────────────────────┐
│ DB2 (XVMWEB)                                            │
│ Tabella: D01.INVC                                       │
│ (Dati inventario in tempo reale)                        │
└──────────────────┬──────────────────────────────────────┘
                   │
                   │ 1. SELECT INVC (1x/min via CLI)
                   │    - Marker detection
                   │    - Area lookup (CONF_OPERATORE)
                   ▼
┌─────────────────────────────────────────────────────────┐
│ MySQL (DB_INV)                                          │
│ Tabella: CONTEGGI                                       │
│ - INSERT ... ON DUPLICATE KEY UPDATE (idempotent)      │
│ - Marker processing:                                    │
│   • ZZZ/AREA: updateArea in CONF_OPERATORE             │
│   • ZZZ/STAMPA: generatePDF + sendToCUPS              │
└──────────────────┬──────────────────────────────────────┘
                   │
                   │ 2. Web UI (Apache)
                   │    - CRUD CONF_OPERATORE
                   │    - CRUD STAMPANTI
                   │    - Dropdown select (REPARTI/AREE)
                   │
                   ▼
            Ubuntu CUPS
            (lpr job queue)
                   │
                   ▼
            Stampante IP:9100
```

---

## 🔒 Sicurezza

### ✅ Implementato

- ✅ PDO prepared statements (anti SQL injection)
- ✅ Input validation lato server e client
- ✅ File .env non in repo (credenziali protette)
- ✅ Errori dettagliati solo in log, non UI

### ⚠️ TODO

- [ ] Basic Auth o IP whitelist per Web UI
- [ ] CSRF token per form POST
- [ ] Rate limiting su API
- [ ] Audit log (chi ha modificato cosa/quando)

---

## 🛠️ Development

### Sync Dati Test

```bash
# Inserire test data
mysql -u DB_INV -p DB_INV < db/test-data.sql

# Verificare
mysql -u DB_INV -p DB_INV -e "SELECT * FROM CONF_OPERATORE LIMIT 5"
```

---

## 🤖 Import da CLI

### Avvio Manuale

```bash
php /var/www/DB_INV/bin/import_invc.php
```

Output atteso:
```
✅ Importazione completata
   Conteggi: 42
   Marker: 3
```

### Configurazione Cron (1x/minuto)

```bash
crontab -e
```

Aggiungere riga:
```cron
* * * * * /usr/bin/php /var/www/DB_INV/bin/import_invc.php >> /var/www/DB_INV/logs/cron.log 2>&1
```

### Flusso Importazione

1. **Lock Acquisition**: Evita esecuzioni parallele (lock 5 minuti)
2. **DB2 Read**: Legge records da `D01.INVC` WHERE `DATA_CREAZ = CURRENT_DATE`
3. **Marker Detection**: Controlla se `PRECODICE='ZZZ'` e `CODICE_ART IN ('AREA','STAMPA')`
4. **Processing**:
   - Marker `AREA`: Aggiorna `CONF_OPERATORE.ID_AREA`
   - Marker `STAMPA`: Genera TXT + invia via CUPS ai tipi 1/3/4/5
   - Record normale: Upsert in `CONTEGGI`
5. **Logging**: Scrive su `/var/www/DB_INV/logs/inv_import.log`
6. **Lock Release**: Libera lock

---

## 🖨️ Stampa CUPS

### Configurazione Stampanti

Via web UI `http://localhost:8080/printers.php`:
- **IP**: Indirizzo IP stampante (es. `172.16.8.248`)
- **CODA_CUPS**: Nome coda CUPS (es. `INV_MAIDA_PRINCIPALE`)
- **REPARTO** (opzionale): ID reparto associato

### Tipi Stampa (Marker STAMPA)

| Tipo | Descrizione | Filtro |
|------|-------------|---------|
| 1 | Area attuale | `ID_AREA = <area_operatore> AND STAMPATO = 0` |
| 3 | Tutti i conteggi | `STAMPATO = 0` |
| 4 | Ultimi 50 | `STAMPATO = 0 LIMIT 50` |
| 5 | Non stampati | `STAMPATO = 0` |

### Formato Stampa

Genera file TXT con layout a tabella:
```
================================
INVENTARIO CONTEGGI
================================

Operatore: OP001
Area: 5
Data: 2025-01-18 14:30:45

PRECODICE | CODART | POSIZ | QTA | CONTEGGI
--------| ----| ---| ----------| ----------
PROD001 | PART01 | A1 | 100.500 | 1
PROD002 | PART02 | A2 | 50.000 | 2
--------| ----| ---| ----------| ----------
TOTALE | | | 150.500 | 2

================================
```

### Invio via lpr

```bash
lpr -P INV_MAIDA_PRINCIPALE -h 172.16.8.248 /tmp/inv/print_xyz.txt
```

---

## 📋 Prossimi Step

1. ✅ **Script Import CLI** - COMPLETATO
   - Connessione DB2 e lettura INVC ✅
   - Upsert in CONTEGGI ✅
   - Gestione marker ✅
   - Invio CUPS ✅

2. **Testing**
   - [ ] Test con dati reali da DB2
   - [ ] Verifica marker processing end-to-end
   - [ ] Test stampa CUPS su stampanti fisiche

3. **Enhanced Web UI**
   - [ ] Audit log di modifiche
   - [ ] Dashboard statistiche import
   - [ ] Download report XLSX

4. **Monitoring**
   - [ ] Alert via email se import fallisce
   - [ ] Dashboard salute sistema
   - [ ] Metriche di performance import

---

**Versione:** 0.1 (Beta)  
**Data:** 2026-01-18  
**Stack:** PHP 8.1+ | MySQL 8.0+ | DB2 (ODBC)
