<?php

namespace App\Import;

/**
 * Gestione del lock file per evitare esecuzioni parallele
 */
class LockManager
{
    private string $lockFile;
    private int $timeout;
    private $handle;

    public function __construct(string $lockFile, int $timeout = 300)
    {
        $this->lockFile = $lockFile;
        $this->timeout = $timeout;
    }

    public function acquire(): bool
    {
        $dir = dirname($this->lockFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (file_exists($this->lockFile)) {
            $age = time() - filemtime($this->lockFile);
            if ($age < $this->timeout) {
                return false; // Lock ancora valido
            }
            // Lock scaduto, rimuovi
            @unlink($this->lockFile);
        }

        $this->handle = fopen($this->lockFile, 'w');
        if (!$this->handle) {
            return false;
        }

        $result = flock($this->handle, LOCK_EX | LOCK_NB);
        if (!$result) {
            fclose($this->handle);
            return false;
        }

        return true;
    }

    public function release(): void
    {
        if ($this->handle) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }
}

/**
 * Gestore dei marker nell'importazione
 */
class MarkerProcessor
{
    private MySQLConnection $mysql;
    private MarkerRepository $markerRepo;
    private ConfigOperatoreRepository $confRepo;
    private ConteggiRepository $conteggiRepo;
    private StampantiRepository $stampantiRepo;
    private PrinterManager $printerManager;
    private Logger $logger;

    public function __construct(
        MySQLConnection $mysql,
        MarkerRepository $markerRepo,
        ConfigOperatoreRepository $confRepo,
        ConteggiRepository $conteggiRepo,
        StampantiRepository $stampantiRepo,
        PrinterManager $printerManager,
        Logger $logger
    ) {
        $this->mysql = $mysql;
        $this->markerRepo = $markerRepo;
        $this->confRepo = $confRepo;
        $this->conteggiRepo = $conteggiRepo;
        $this->stampantiRepo = $stampantiRepo;
        $this->printerManager = $printerManager;
        $this->logger = $logger;
    }

    /**
     * Processa marker ZZZ/AREA o ZZZ/STAMPA
     */
    public function process(
        string $operatore,
        int $reparto,
        int $numeroInv,
        string $markerType,
        ?float $qtaConteggiata
    ): void {
        if ($markerType === 'AREA') {
            $this->handleMarkerArea($operatore, $reparto, $numeroInv, (int)$qtaConteggiata);
        } elseif ($markerType === 'STAMPA') {
            $this->handleMarkerStampa($operatore, $reparto, $numeroInv, (int)$qtaConteggiata);
        }
    }

    /**
     * Marker AREA: aggiorna area corrente operatore
     */
    private function handleMarkerArea(string $operatore, int $reparto, int $numeroInv, int $nuovaArea): void
    {
        $conf = $this->confRepo->findByKey($operatore, $reparto, $numeroInv);

        if ($conf) {
            // Aggiorna area
            $this->confRepo->update($conf['ID_CONF'], $conf['NOME'], $nuovaArea, $conf['IP_STAMPANTE']);
            $this->logger->info('Marker AREA processed', [
                'operatore' => $operatore,
                'area' => $nuovaArea,
            ]);
        } else {
            $this->logger->warning('Marker AREA: config non trovata', [
                'operatore' => $operatore,
                'reparto' => $reparto,
                'inventario' => $numeroInv,
            ]);
        }
    }

    /**
     * Marker STAMPA: genera e invia stampa
     */
    private function handleMarkerStampa(string $operatore, int $reparto, int $numeroInv, int $tipo): void
    {
        $conf = $this->confRepo->findByKey($operatore, $reparto, $numeroInv);
        if (!$conf) {
            $this->logger->warning('Marker STAMPA: config non trovata', [
                'operatore' => $operatore,
            ]);
            return;
        }

        $area = $conf['ID_AREA'];
        $rows = $this->conteggiRepo->getForPrint($operatore, $reparto, $numeroInv, $tipo, $area);

        if (empty($rows)) {
            $this->logger->info('Marker STAMPA: nessun record da stampare', ['tipo' => $tipo]);
            return;
        }

        // Recupera stampante per l'operatore
        $printerIp = $conf['IP_STAMPANTE'];
        $printer = $this->stampantiRepo->findByIp($printerIp);
        
        if (!$printer) {
            $this->logger->error('Stampante non trovata', ['ip' => $printerIp]);
            return;
        }

        // Genera contenuto stampa formattato
        $printLines = PrinterManager::formatInventoryPrint($rows, [
            'operatore' => $operatore,
            'area' => $area,
        ]);

        // Invia a CUPS
        $success = $this->printerManager->printToQueue($printerIp, $printLines);

        if ($success) {
            $this->logger->info('Marker STAMPA processed', [
                'tipo' => $tipo,
                'rowCount' => count($rows),
                'printer' => $printer['CODA_CUPS'],
            ]);

            // Marca come stampato
            $ids = array_column($rows, 'ID_CONTEGGIO');
            $this->conteggiRepo->markAsPrinted($ids);
        } else {
            $this->logger->error('Errore durante stampa', [
                'tipo' => $tipo,
                'printer' => $printer['CODA_CUPS'],
            ]);
        }
    }
}


/**
 * Main Importer class - wrapper for daemon use
 */
class Importer
{
    private \App\Database\MySQLConnection $mysql;
    private \App\Database\DB2Connection $db2;
    private \App\Logger $logger;

    public function __construct(\App\Database\MySQLConnection $mysql, \App\Database\DB2Connection $db2, \App\Logger $logger)
    {
        $this->mysql = $mysql;
        $this->db2 = $db2;
        $this->logger = $logger;
    }

    /**
     * Process a record (marker or inventory)
     */
    public function process(string $operatore, int $reparto, int $numInv, ?int $area = null): void
    {
        // This is a placeholder that gets called by daemon for ZZZ/AREA markers
        // The actual processing is handled by MarkerProcessor
        if ($area !== null) {
            // Update CONF_OPERATORE area
            $sql = "UPDATE CONF_OPERATORE SET ID_AREA = ? WHERE CODICE = ? AND ID_REP = ? AND ID_INVENTARIO = ?";
            $stmt = $this->mysql->getPDO()->prepare($sql);
            $stmt->execute([$area, $operatore, $reparto, $numInv]);
            $this->logger->debug("Updated operator $operatore area to $area");
        }
    }
}
