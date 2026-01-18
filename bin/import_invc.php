#!/usr/bin/env php
<?php

/**
 * Script per l'importazione periodica da DB2 XVMWEB → MySQL DB_INV
 * 
 * Utilizzo:
 *   /var/www/DB_INV/bin/import_invc.php
 * 
 * Previsto per esecuzione via cron (1x/minuto):
 *   * * * * * /usr/bin/php /var/www/DB_INV/bin/import_invc.php >> /var/www/DB_INV/logs/cron.log 2>&1
 */

require_once __DIR__ . '/../web/autoload.php';
require_once __DIR__ . '/../web/bootstrap.php';

use App\Config;
use App\Logger;
use App\Database\DB2Connection;
use App\Database\MySQLConnection;
use App\Repository\ConfigOperatoreRepository;
use App\Repository\ConteggiRepository;
use App\Repository\MarkerRepository;
use App\Repository\StampantiRepository;
use App\Import\LockManager;
use App\Import\MarkerProcessor;
use App\Print\PrinterManager;

try {
    // Carica configurazione
    $config = Config::load();
    $logger = new Logger($config->get('LOG_PATH') . '/inv_import.log');
    
    // Log dell'avvio
    $logger->info('=== INIZIO IMPORTAZIONE ===');

    // Gestione lock: evita esecuzioni parallele
    $lockFile = $config->get('LOCK_FILE');
    $lockManager = new LockManager($lockFile, 300); // timeout 5 minuti

    if (!$lockManager->acquire()) {
        $logger->warning('Lock acquisito da altro processo, skip importazione');
        exit(0); // Esci silenziosamente, non è un errore
    }

    // Connessioni
    $db2 = new DB2Connection(
        $config->get('DB2_HOST'),
        $config->get('DB2_PORT'),
        $config->get('DB2_NAME'),
        $config->get('DB2_USER'),
        $config->get('DB2_PASS')
    );

    $mysql = new MySQLConnection(
        $config->get('MYSQL_HOST'),
        $config->get('MYSQL_PORT'),
        $config->get('MYSQL_DATABASE'),
        $config->get('MYSQL_USER'),
        $config->get('MYSQL_PASS')
    );

    // Repository
    $confOperatoreRepo = new ConfigOperatoreRepository($mysql);
    $conteggiRepo = new ConteggiRepository($mysql);
    $markerRepo = new MarkerRepository($mysql);
    $stampantiRepo = new StampantiRepository($mysql);

    // Inizializza PrinterManager
    $printerManager = new PrinterManager(
        $config->get('CUPS_TEMP_DIR'),
        $logger,
        $stampantiRepo
    );

    // Inizializza processore marker
    $markerProcessor = new MarkerProcessor(
        $mysql,
        $markerRepo,
        $confOperatoreRepo,
        $conteggiRepo,
        $stampantiRepo,
        $printerManager,
        $logger
    );

    // Leggi dati da DB2
    $logger->info('Lettura dati da DB2...');
    $records = readFromDB2($db2, $logger);
    $logger->info("Record letti da DB2: " . count($records));

    // Processa ogni record
    $conteggiCount = 0;
    $markerCount = 0;
    
    foreach ($records as $row) {
        try {
            // Controlla se è marker
            if ($markerRepo->isMarker($row['PRECODICE'], $row['CODICE_ART'])) {
                $markerType = $markerRepo->getMarkerType($row['PRECODICE'], $row['CODICE_ART']);
                
                $logger->info("Marker rilevato", [
                    'tipo' => $markerType,
                    'operatore' => $row['OPER_CREAZ'],
                    'reparto' => $row['REPARTO'],
                ]);

                // Processa marker
                $markerProcessor->process(
                    $row['OPER_CREAZ'],
                    (int)$row['REPARTO'],
                    (int)$row['NUMERO_INV'],
                    $markerType,
                    $row['QTA_CONTEGGIATA'] ?? 0
                );

                $markerCount++;
            } else {
                // Upsert normale a CONTEGGI
                $conteggiRepo->upsertFromINVC([
                    'reparto' => $row['REPARTO'],
                    'numero_inv' => $row['NUMERO_INV'],
                    'precodice' => $row['PRECODICE'],
                    'codice_art' => $row['CODICE_ART'],
                    'posizione' => $row['POSIZIONE'] ?? '',
                    'numero_conta' => $row['NUMERO_CONTA'] ?? 0,
                    'prog' => $row['PROG'] ?? 0,
                    'qty' => $row['QTA_CONTEGGIATA'] ?? 0,
                    'oper_creaz' => $row['OPER_CREAZ'],
                    'ts_creazione' => ($row['DATA_CREAZ'] ?? date('Y-m-d')) . ' ' . ($row['ORA_CREAZ'] ?? date('H:i:s')),
                    'id_area' => -1, // di default -1, aggiornato da marker se necessario
                ]);

                $conteggiCount++;
            }
        } catch (\Exception $e) {
            $logger->error('Errore durante elaborazione record', [
                'precodice' => $row['PRECODICE'],
                'codice_art' => $row['CODICE_ART'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Log risultati
    $logger->info('=== IMPORTAZIONE COMPLETATA ===', [
        'conteggi_inserted' => $conteggiCount,
        'marker_processed' => $markerCount,
        'total_records' => count($records),
    ]);

    // Log al console
    echo "✅ Importazione completata\n";
    echo "   Conteggi: $conteggiCount\n";
    echo "   Marker: $markerCount\n";

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->critical('ERRORE FATALE', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);
    }
    
    echo "❌ Errore: " . $e->getMessage() . "\n";
    exit(1);

} finally {
    // Rilascia lock
    if (isset($lockManager)) {
        $lockManager->release();
    }
}

/**
 * Legge dal DB2 usando la query specificata
 * 
 * Schema atteso da D01.INVC:
 *   REPARTO, NUMERO_INV, PRECODICE, CODICE_ART, POSIZIONE, NUMERO_CONTA, 
 *   PROG, QTA_CONTEGGIATA, OPER_CREAZ, DATA_CREAZ, ORA_CREAZ
 */
function readFromDB2(DB2Connection $db2, Logger $logger): array
{
    try {
        $sql = <<<SQL
        SELECT
            REPARTO,
            NUMERO_INV,
            PRECODICE,
            CODICE_ART,
            POSIZIONE,
            NUMERO_CONTA,
            PROG,
            QTA_CONTEGGIATA,
            OPER_CREAZ,
            DATA_CREAZ,
            ORA_CREAZ
        FROM D01.INVC
        WHERE DATA_CREAZ = CURRENT_DATE
        ORDER BY REPARTO, NUMERO_INV
        SQL;

        $result = $db2->query($sql);
        
        if (!$result) {
            throw new \Exception('Errore esecuzione query DB2');
        }

        $records = [];
        while ($row = $db2->fetchArray($result)) {
            $records[] = $row;
        }

        return $records;

    } catch (\Exception $e) {
        $logger->error('Errore lettura DB2', ['error' => $e->getMessage()]);
        throw $e;
    }
}
