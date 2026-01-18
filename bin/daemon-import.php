#!/usr/bin/env php
<?php

require_once '/var/www/DB_INV/src/Logger.php';
require_once '/var/www/DB_INV/src/Database/DB2Connection.php';
require_once '/var/www/DB_INV/src/Database/MySQLConnection.php';
require_once '/var/www/DB_INV/src/Import/Importer.php';
require_once '/var/www/DB_INV/src/Print/StampaManager.php';

use App\Logger;
use App\Database\DB2Connection;
use App\Database\MySQLConnection;
use App\Import\Importer;
use App\Print\StampaManager;

define('LOOP_INTERVAL', 60);
define('MAX_RECORDS_PER_ITERATION', 1000);
define('LOCK_FILE', '/.lock/daemon-import.lock');

$iteration = 0;

try {
    $logFile = '/var/www/DB_INV/logs/inv_import.log';
    $logger = new Logger($logFile);
    $logger->info('=== DAEMON STARTED ===');
    
    $db2 = new DB2Connection($logger);
    $mysql = new MySQLConnection();
    $logger->info('Connessione DB2 stabilita');
    $logger->info('Connessione MySQL stabilita {"host":"localhost","db":"DB_INV"}');
    
    $importer = new Importer($mysql, $db2, $logger);
    
    $sqlConf = "SELECT CODICE, ID_REP, ID_INVENTARIO, ID_AREA FROM CONF_OPERATORE WHERE ATTIVO = 1";
    $stmtConf = $mysql->getPDO()->prepare($sqlConf);
    $stmtConf->execute();
    $confOperadores = $stmtConf->fetchAll(\PDO::FETCH_ASSOC);
    $logger->info("Loaded " . count($confOperadores) . " operator configs");
    
    $operatoriMap = [];
    foreach ($confOperadores as $conf) {
        $key = "{$conf['ID_REP']}:{$conf['ID_INVENTARIO']}:" . trim($conf['CODICE']);
        $operatoriMap[$key] = $conf;
    }
    
    $stampaManager = new StampaManager($mysql->getPDO(), $logger);
    
    while (true) {
        $iteration++;
        
        $lockHandle = @fopen(LOCK_FILE, 'a');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) fclose($lockHandle);
            sleep(LOOP_INTERVAL);
            continue;
        }
        
        try {
            $query = "SELECT REPARTO, NUMERO, PRECODICE, CODICE_ART, POSIZIONE, NUMERO_CONTA, PROG, QTA_CONTEGGIATA, RIFERIMENTI, OPER_CREAZ FROM D01.INVC LIMIT " . MAX_RECORDS_PER_ITERATION;
            $records = [];
            $stmt = $db2->getConnection()->prepare($query);
            $stmt->execute();
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $records[] = $row;
            }
            
            if (count($records) === 0) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                sleep(LOOP_INTERVAL);
                continue;
            }
            
            $conteggiCount = 0;
            $skippedCount = 0;
            $markerCount = 0;
            foreach ($records as $row) {
                $precodice = trim($row['PRECODICE']);
                $codiceArt = trim($row['CODICE_ART']);
                
                if ($precodice === 'ZZZ') {
                    $markerType = $codiceArt;
                    $logger->info("DEBUG: Found ZZZ marker: CODICE_ART=$markerType, OPER={$row['OPER_CREAZ']}, REP={$row['REPARTO']}, NUM={$row['NUMERO']}, QTA={$row['QTA_CONTEGGIATA']}");
                    
                    try {
                        if ($markerType === 'AREA') {
                            $importer->process(trim($row['OPER_CREAZ']), (int)$row['REPARTO'], (int)$row['NUMERO'], $markerType, (float)($row['QTA_CONTEGGIATA'] ?? 0));
                            $markerCount++;
                        }
                        else if ($markerType === 'STAMPA') {
                            $operatore = trim($row['OPER_CREAZ']);
                            $reparto = (int)$row['REPARTO'];
                            $numInv = (int)$row['NUMERO'];
                            $idTipo = (int)($row['QTA_CONTEGGIATA'] ?? 0);
                            
                            $sqlCheckTipo = "SELECT CODICE FROM TIPO_STAMPA WHERE ID_TIPO = ?";
                            $stmtCheckTipo = $mysql->getPDO()->prepare($sqlCheckTipo);
                            $stmtCheckTipo->execute([$idTipo]);
                            $codiceTipo = $stmtCheckTipo->fetchColumn();
                            
                            if ($codiceTipo === false) {
                                $logger->warning("Marker STAMPA: ID_TIPO=$idTipo not found in TIPO_STAMPA");
                            } else {
                                $areaCorrente = $stampaManager->getAreaCorrente($operatore, $reparto, $numInv);
                                $righe = $stampaManager->selectRighePerTipo($codiceTipo, $operatore, $reparto, $numInv, $areaCorrente);
                                
                                if (!empty($righe)) {
                                    $filePath = $stampaManager->generateTxtFile($righe, $operatore, $reparto, $numInv, $idTipo);
                                    if ($filePath) {
                                        $logger->info("Marker STAMPA: Generated file=$filePath (tipo=$codiceTipo, rows=" . count($righe) . ")");
                                    } else {
                                        $logger->warning("Marker STAMPA: File generation failed for operatore=$operatore");
                                    }
                                } else {
                                    $logger->info("Marker STAMPA: No rows found for tipo=$codiceTipo, operatore=$operatore");
                                }
                                $markerCount++;
                            }
                        }
                    } catch (\Exception $e) {
                        $logger->error("Marker processing error: " . $e->getMessage());
                    }
                    continue;
                }
                
                $key = "{$row['REPARTO']}:{$row['NUMERO']}:" . trim($row['OPER_CREAZ']);
                
                if (!isset($operatoriMap[$key])) {
                    $skippedCount++;
                    continue;
                }
                
                $conteggiCount++;
            }
            
            $logger->info("Iter $iteration: imported=$conteggiCount skipped=$skippedCount markers=$markerCount");
            
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
        
        sleep(LOOP_INTERVAL);
    }
    
} catch (\Exception $e) {
    $logger->error("Daemon error: " . $e->getMessage());
    exit(1);
}

exit(0);
?>
