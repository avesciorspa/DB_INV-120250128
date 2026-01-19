<?php

namespace App\Print;

use PDO;
use App\Logger;

/**
 * StampaManager
 * Gestisce la generazione di report di stampa basati su TIPO_STAMPA
 */
class StampaManager
{
    private PDO $mysql;
    private Logger $logger;

    public function __construct(PDO $mysql, Logger $logger)
    {
        $this->mysql = $mysql;
        $this->logger = $logger;
    }

    /**
     * Recupera area corrente da CONF_OPERATORE
     */
    public function getAreaCorrente(string $operatore, int $reparto, int $numInv): int
    {
        try {
            $sql = "SELECT ID_AREA
                    FROM CONF_OPERATORE
                    WHERE CODICE = ? AND ID_REP = ? AND ID_INVENTARIO = ?
                    LIMIT 1";
            
            $stmt = $this->mysql->prepare($sql);
            $stmt->execute([$operatore, $reparto, $numInv]);
            
            $area = $stmt->fetchColumn();
            return $area !== false ? (int)$area : -1;
        } catch (\Exception $e) {
            $this->logger->error("StampaManager::getAreaCorrente ERRORE: " . $e->getMessage());
            return -1;
        }
    }

    /**
     * Interpreta CODICE logico e genera query corretta
     */
    public function selectRighePerTipo(
        string $codiceTipo,
        string $operatore,
        int $reparto,
        int $numInv,
        int $idAreaCorrente
    ): array
    {
        try {
            $params = [$operatore, $reparto, $numInv];
            $sql = "";

            switch ($codiceTipo) {
                case 'AREA_ATTUALE':
                    $sql = "SELECT *
                            FROM CONTEGGI
                            WHERE CODICE_OPERATORE = ?
                              AND REPARTO = ?
                              AND NUMERO_INV = ?
                              AND ID_AREA = ?
                            ORDER BY TS_CREAZIONE ASC";
                    $params[] = $idAreaCorrente;
                    break;

                case 'TUTTI':
                    $sql = "SELECT *
                            FROM CONTEGGI
                            WHERE CODICE_OPERATORE = ?
                              AND REPARTO = ?
                              AND NUMERO_INV = ?
                            ORDER BY TS_CREAZIONE ASC";
                    break;

                case 'ULTIMI_50':
                    $sql = "SELECT *
                            FROM CONTEGGI
                            WHERE CODICE_OPERATORE = ?
                              AND REPARTO = ?
                              AND NUMERO_INV = ?
                            ORDER BY TS_CREAZIONE DESC
                            LIMIT 50";
                    break;

                case 'AREA_ATTUALE_NON_STAMPATI':
                    $sql = "SELECT *
                            FROM CONTEGGI
                            WHERE CODICE_OPERATORE = ?
                              AND REPARTO = ?
                              AND NUMERO_INV = ?
                              AND ID_AREA = ?
                              AND STAMPATO = 0
                            ORDER BY TS_CREAZIONE ASC";
                    $params[] = $idAreaCorrente;
                    break;

                default:
                    $this->logger->warning("StampaManager::selectRighePerTipo CODICE non riconosciuto: $codiceTipo");
                    return [];
            }

            if (empty($sql)) {
                return [];
            }

            $stmt = $this->mysql->prepare($sql);
            $stmt->execute($params);
            
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->info("StampaManager::selectRighePerTipo CODICE=$codiceTipo, rows=" . count($rows));
            
            return $rows;
        } catch (\Exception $e) {
            $this->logger->error("StampaManager::selectRighePerTipo ERRORE: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Genera file TXT formattato
     * Nome file: operatore-reparto-numInv_YYYYMMDDHHMMSS.txt
     * Default path: /var/www/DB_INV/stampe
     */
    public function generateTxtFile(
        array $rows,
        string $operatore,
        int $reparto,
        int $numInv,
        int $idTipo,
        string $basePath = '/var/www/DB_INV/stampe'
    ): string
    {
        try {
            if (empty($rows)) {
                $this->logger->warning("StampaManager::generateTxtFile NESSUNA RIGA per operatore=$operatore");
                return "";
            }

            $ts = date('YmdHis');
            $fileName = sprintf('%s-%d-%d_%s.txt', $operatore, $reparto, $numInv, $ts);
            $filePath = $basePath . '/' . $fileName;

            $fh = fopen($filePath, 'w');
            if ($fh === false) {
                $this->logger->error("StampaManager::generateTxtFile ERRORE apertura file: $filePath");
                return "";
            }

            fwrite($fh, "=====================================\n");
            fwrite($fh, "RAPPORTO CONTEGGI\n");
            fwrite($fh, "=====================================\n");
            fwrite($fh, "Operatore:  $operatore\n");
            fwrite($fh, "Reparto:    $reparto\n");
            fwrite($fh, "Inventario: $numInv\n");
            fwrite($fh, "Tipo Stampa: $idTipo\n");
            fwrite($fh, "Data/Ora:   " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "=====================================\n\n");

            fwrite($fh, sprintf(
                "%-8s %-3s %-20s %10s %8s %10s\n",
                "POSIZ",
                "PRE",
                "CODICE ART",
                "QTA",
                "AREA",
                "STAMP"
            ));
            fwrite($fh, str_repeat("-", 70) . "\n");

            foreach ($rows as $row) {
                $posizione = trim($row['POSIZIONE']);
                $precodice = trim($row['PRECODICE']);
                $codiceArt = trim($row['CODICE_ART']);
                $qta = $row['QTA_CONTEGGIATA'] ?? '0';
                $area = $row['ID_AREA'] ?? '-';
                $stampato = ($row['STAMPATO'] == 1) ? 'SI' : 'NO';

                fwrite($fh, sprintf(
                    "%-8s %-3s %-20s %10s %8s %10s\n",
                    $posizione,
                    $precodice,
                    $codiceArt,
                    $qta,
                    $area,
                    $stampato
                ));
            }

            fwrite($fh, str_repeat("-", 70) . "\n");
            fwrite($fh, sprintf("TOTALE RIGHE: %d\n", count($rows)));
            fwrite($fh, "=====================================\n");

            fclose($fh);

            $this->logger->info("StampaManager::generateTxtFile File generato: $filePath (" . count($rows) . " righe)");
            
            return $filePath;
        } catch (\Exception $e) {
            $this->logger->error("StampaManager::generateTxtFile ERRORE: " . $e->getMessage());
            return "";
        }
    }
}
