#!/usr/bin/env php
<?php

/**
 * Database Inventory Daemon - Process Records from DB2
 * Reads D01.INVC and processes markers (ZZZ/AREA, ZZZ/STAMPA)
 */

// Register PSR-4 autoloader FIRST
spl_autoload_register(function ($class) {
    if (0 === strpos($class, 'App\\')) {
        $path = '/var/www/DB_INV/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($path)) {
            require_once $path;
            return true;
        }
    }
    return false;
});

// Load Config and Logger explicitly BEFORE anything else
require_once '/var/www/DB_INV/src/Config.php';
require_once '/var/www/DB_INV/src/Logger.php';

// NOW load .env configuration
\App\Config::load('/var/www/DB_INV/.env');

// NOW use statements and instantiations (autoloader will handle loading other classes)
use App\Config;
use App\Logger;
use App\Database\DB2Connection;
use App\Database\MySQLConnection;
use App\Import\Importer;
use App\Print\StampaManager;

define('LOOP_INTERVAL', 60);
define('MAX_RECORDS_PER_ITERATION', 1000);
define('LOCK_FILE', '/tmp/daemon-import.lock');
define('MAX_RUNTIME', 3600);

$iteration = 0;
$startTime = time();

try {
    // Initialize logger
    $logFile = '/var/www/DB_INV/logs/inv_import.log';
    $logger = new Logger($logFile);
    
    $logger->info('=== DAEMON STARTED ===');
    
    // Connect to databases (DB2Connection requires Logger)
    $db2 = new DB2Connection($logger);
    $mysql = new MySQLConnection($logger);
    
    $logger->info('Connessione DB2 stabilita');
    $logger->info('Connessione MySQL stabilita {"host":"localhost","db":"DB_INV"}');
    
    // Initialize repositories
    $importer = new Importer($mysql, $db2, $logger);
    
    // Load operator configurations
    $sqlConf = "SELECT CODICE, ID_REP, ID_INVENTARIO, ID_AREA, IP_STAMPANTE FROM CONF_OPERATORE ";
    $stmtConf = $mysql->getPDO()->prepare($sqlConf);
    $stmtConf->execute();
    $confOperadores = $stmtConf->fetchAll(\PDO::FETCH_ASSOC);
    
    $logger->info("Loaded " . count($confOperadores) . " operator configs");
    
    // Build operator lookup: "REP:NUMERO:OPER_CREAZ" => config
    $operatoriMap = [];
    foreach ($confOperadores as $conf) {
        $key = "{$conf['ID_REP']}:{$conf['ID_INVENTARIO']}:" . trim($conf['CODICE']);
        $operatoriMap[$key] = $conf;
    }
    
    // Initialize StampaManager
    $stampaManager = new StampaManager($mysql->getPDO(), $logger);
    
    // Main loop
    while (true) {
        $iteration++;
        $nowTime = time();
        
        // Check runtime limit
        if (($nowTime - $startTime) > MAX_RUNTIME) {
            $logger->info("Max runtime reached, exiting");
            break;
        }
        
        // Acquire lock (file-based, no external dependencies)
        $lockHandle = @fopen(LOCK_FILE, 'a');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) fclose($lockHandle);
            sleep(LOOP_INTERVAL);
            continue;
        }
        
        try {
            // Fetch records from DB2 D01.INVC
            $queryD01 = "
                SELECT REPARTO, NUMERO, PRECODICE, CODICE_ART, POSIZIONE, NUMERO_CONTA, PROG, QTA_CONTEGGIATA, RIFERIMENTI, OPER_CREAZ, UTEN_CREAZ, DATA_CREAZ, ORA_CREAZ, OPER_MODIF, UTEN_MODIF, DATA_MODIF, ORA_MODIF
                FROM D01.INVC
                LIMIT " . MAX_RECORDS_PER_ITERATION . "
            ";
            
            $stmtD01 = $db2->getPDO()->prepare($queryD01);
            $stmtD01->execute();
            $records = [];
            
            while ($row = $stmtD01->fetch(\PDO::FETCH_ASSOC)) {
                // Trim whitespace from key fields (DB2 may pad with spaces)
                $row['PRECODICE'] = trim($row['PRECODICE'] ?? '');
                $row['CODICE_ART'] = trim($row['CODICE_ART'] ?? '');
                $row['OPER_CREAZ'] = trim($row['OPER_CREAZ'] ?? '');
                $records[] = $row;
            }
            
            $logger->debug("Iter $iteration: Fetched " . count($records) . " records from D01.INVC");
            
            // Process each record
            $imported = 0;
            $skipped = 0;
            $markerCount = 0;
            
            foreach ($records as $row) {
                // Check for markers first - ZZZ markers are identified by PRECODICE = 'ZZZ'
                $precodice = trim($row['PRECODICE'] ?? '');
                
                if ($precodice === 'ZZZ') {
                    $markerType = trim($row['CODICE_ART'] ?? '');  // Marker type is in CODICE_ART
                    
                    if ($markerType === 'AREA') {
                        // ZZZ/AREA marker: update operator area
                        // For ZZZ markers, the value (area/tipo) is in QTA_CONTEGGIATA
                        $operatore = $row['OPER_CREAZ'];
                        $reparto = $row['REPARTO'];
                        $numInv = $row['NUMERO'];
                        $area = (int)($row['QTA_CONTEGGIATA'] ?? -1);
                        
                        try {
                            $importer->process($operatore, $reparto, $numInv, $area);
                            $markerCount++;
                        } catch (\Exception $e) {
                            $logger->error("Marker AREA failed: " . $e->getMessage());
                        }
                        
                    } elseif ($markerType === 'STAMPA') {
                        // ZZZ/STAMPA marker: generate print file
                        $operatore = $row['OPER_CREAZ'];
                        $reparto = $row['REPARTO'];
                        $numInv = $row['NUMERO'];
                        $codiceTipo = trim($row['CODICE_ART'] ?? '');
                        
                        try {
                            // Validate ID_TIPO exists in TIPO_STAMPA
                            $sqlValidate = "SELECT ID_TIPO FROM TIPO_STAMPA WHERE CODICE = ? LIMIT 1";
                            $stmtValidate = $mysql->getPDO()->prepare($sqlValidate);
                            $stmtValidate->execute([$codiceTipo]);
                            $tipoRow = $stmtValidate->fetch(\PDO::FETCH_ASSOC);
                            
                            if (!$tipoRow) {
                                $logger->error("Marker STAMPA: Invalid CODICE=$codiceTipo");
                                // continue; // Ora inseriamo il marker anche se fallisce
                            }
                            
                            $idTipo = $tipoRow['ID_TIPO'];
                            
                            // Get operator's current area
                            $areaCorrente = $stampaManager->getAreaCorrente($operatore, $reparto, $numInv);
                            if ($areaCorrente === -1) {
                                $logger->error("Marker STAMPA: Could not get current area for $operatore");
                                // continue; // Ora inseriamo il marker anche se fallisce
                            }
                            
                            // Select rows based on type
                            $righe = $stampaManager->selectRighePerTipo($codiceTipo, $operatore, $reparto, $numInv, $areaCorrente);
                            
                            if (count($righe) === 0) {
                                $logger->debug("Marker STAMPA: No rows found for $codiceTipo");
                                $markerCount++;
                                // continue; // Ora inseriamo il marker anche se no rows
                            }
                            
                            // Generate TXT file
                            $filePath = $stampaManager->generateTxtFile($righe, $operatore, $reparto, $numInv, $idTipo);
                            
                            if ($filePath) {
                                $logger->info("Marker STAMPA: Generated file=$filePath (tipo=$codiceTipo, rows=" . count($righe) . ")");
                                $markerCount++;
                            } else {
                                $logger->error("Marker STAMPA: Failed to generate file");
                            }
                        } catch (\Exception $e) {
                            $logger->error("Marker STAMPA failed: " . $e->getMessage());
                        }
                    }
                    
                    // IMPORTANTE: Inserisci il marker in CONTEGGI (business logic requirement)

                    // IMPORTANTE: Inserisci il marker in CONTEGGI (business logic requirement)
                    // I marker ZZZ devono essere inseriti come record normali DOPO il processing
                    try {
                        // Format time from HHMM to HH:MM:SS
                        $timeStr = (string)($row["ORA_CREAZ"] ?? date("Hi"));
                        if (strlen($timeStr) == 4) {
                            $timeStr = substr($timeStr, 0, 2) . ':' . substr($timeStr, 2, 2) . ':00';
                        }
                        
                        // Build timestamp from DATA_CREAZ and ORA_CREAZ
                        $dataStr = $row['DATA_CREAZ'] ?? date('Y-m-d');
                        $oraStr = (string)($row['ORA_CREAZ'] ?? date("Hi"));
                        if (strlen($oraStr) == 4) {
                            $oraStr = substr($oraStr, 0, 2) . ':' . substr($oraStr, 2, 2) . ':00';
                        } else {
                            $oraStr = '00:00:00';
                        }
                        $tsCreazione = $dataStr . ' ' . $oraStr;
                        
                        $sqlMarkerInsert = "INSERT INTO CONTEGGI 
                                           (REPARTO, NUMERO_INV, PRECODICE, CODICE_ART, POSIZIONE, 
                                            NUMERO_CONTA, PROG, QTA_CONTEGGIATA, OPER_CREAZ, TS_CREAZIONE, 
                                            CODICE_OPERATORE, ID_AREA, STAMPATO)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                           ON DUPLICATE KEY UPDATE
                                           QTA_CONTEGGIATA = VALUES(QTA_CONTEGGIATA),
                                           TS_CREAZIONE = VALUES(TS_CREAZIONE)";
                        
                        $stmtMarkerInsert = $mysql->getPDO()->prepare($sqlMarkerInsert);
                        $stmtMarkerInsert->execute([
                            $row['REPARTO'],
                            $row['NUMERO'],
                            $row['PRECODICE'],
                            $row['CODICE_ART'],
                            $row['POSIZIONE'] ?? null,
                            $row['NUMERO_CONTA'] ?? 0,
                            $row['PROG'] ?? 0,
                            $row['QTA_CONTEGGIATA'] ?? 0,
                            trim($row['OPER_CREAZ']),
                            $tsCreazione,
                            trim($row['OPER_CREAZ']),
                            -1,
                            0
                        ]);
                        
                        $imported++; // Count marker as imported
                        
                    } catch (\Exception $e) {
                        $logger->error("Marker CONTEGGI insertion error: " . $e->getMessage());
                    }
                    
                } else {
                    // Regular inventory record
                    $operatore = $row['OPER_CREAZ'];
                    $reparto = $row['REPARTO'];
                    $numInv = $row['NUMERO'];
                    
                    $key = "$reparto:$numInv:$operatore";
                    
                    if (isset($operatoriMap[$key])) {
                        try {
                            // Insert record into CONTEGGI table
                            $sqlInsert = "INSERT INTO CONTEGGI 
                                         (REPARTO, NUMERO_INV, PRECODICE, CODICE_ART, POSIZIONE, 
                                          NUMERO_CONTA, PROG, QTA_CONTEGGIATA, OPER_CREAZ, TS_CREAZIONE, 
                                          CODICE_OPERATORE, ID_AREA, STAMPATO)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE
                                         QTA_CONTEGGIATA = VALUES(QTA_CONTEGGIATA),
                                         TS_CREAZIONE = VALUES(TS_CREAZIONE)";
                            
                            $stmtInsert = $mysql->getPDO()->prepare($sqlInsert);
                            
                            // Format timestamp from DB2
                            $dataStr = $row['DATA_CREAZ'] ?? date('Y-m-d');
                            $oraStr = (string)($row["ORA_CREAZ"] ?? date("Hi"));
                            if (strlen($oraStr) == 4) {
                                $oraStr = substr($oraStr, 0, 2) . ':' . substr($oraStr, 2, 2) . ':00';
                            } else {
                                $oraStr = '00:00:00';
                            }
                            $timestamp = $dataStr . ' ' . $oraStr;
                            
                            $stmtInsert->execute([
                                $row['REPARTO'],
                                $row['NUMERO'],
                                $row['PRECODICE'],
                                $row['CODICE_ART'] ?? '',
                                $row['POSIZIONE'] ?? '',
                                $row['NUMERO_CONTA'] ?? 0,
                                $row['PROG'] ?? 0,
                                $row['QTA_CONTEGGIATA'] ?? 0,
                                $operatore,
                                $timestamp,
                                $operatore,
                                -1,
                                0
                            ]);
                            
                            $importer->process($operatore, $reparto, $numInv, null);
                            $imported++;
                        } catch (\Exception $e) {
                            $logger->error("Import failed for $key: " . $e->getMessage());
                            $skipped++;
                        }
                    } else {
                        $skipped++;
                    }
                }
            }
            
            $logger->info("Iter $iteration: imported=$imported skipped=$skipped markers=$markerCount");
            
        } finally {
            // Release lock
            if ($lockHandle) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
        }
        
        // Sleep before next iteration
        sleep(LOOP_INTERVAL);
    }
    
} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error("DAEMON ERROR: " . $e->getMessage());
        $logger->error($e->getTraceAsString());
    }
    exit(255);
}

$logger->info("=== DAEMON STOPPED ===");
exit(0);
