<?php

namespace App\Print;

use App\Logger;
use App\Repository\StampantiRepository;

class PrinterManager
{
    private string $tempDir;
    private Logger $logger;
    private StampantiRepository $stampantiRepo;

    public function __construct(
        string $tempDir,
        Logger $logger,
        StampantiRepository $stampantiRepo
    ) {
        $this->tempDir = $tempDir;
        $this->logger = $logger;
        $this->stampantiRepo = $stampantiRepo;

        // Crea directory temp se non esiste
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Invia stampa a una stampante CUPS via socket
     * 
     * @param string $ip Indirizzo IP della stampante
     * @param array<string> $lines Righe di testo da stampare
     * @return bool True se successo
     */
    public function printToQueue(string $ip, array $lines): bool
    {
        try {
            // Recupera info stampante
            $printer = $this->stampantiRepo->findByIp($ip);
            if (!$printer) {
                $this->logger->warning('Stampante non trovata', ['ip' => $ip]);
                return false;
            }

            $queueName = $printer['CODA_CUPS'];

            // Genera nome file temporaneo
            $filename = sprintf(
                '%s/print_%s_%d.txt',
                rtrim($this->tempDir, '/'),
                preg_replace('/[^a-zA-Z0-9_-]/', '', $queueName),
                time()
            );

            // Scrive il file
            $content = implode("\n", $lines) . "\n";
            $bytesWritten = file_put_contents($filename, $content);
            
            if ($bytesWritten === false) {
                $this->logger->error('Errore scrittura file stampa', ['filename' => $filename]);
                return false;
            }

            $this->logger->info('File stampa generato', [
                'filename' => $filename,
                'size' => $bytesWritten,
                'queue' => $queueName,
            ]);

            // Invia a CUPS via lpr
            $command = sprintf(
                'lpr -P %s -h %s %s 2>&1',
                escapeshellarg($queueName),
                escapeshellarg($ip),
                escapeshellarg($filename)
            );

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $this->logger->error('Errore invio stampa', [
                    'queue' => $queueName,
                    'ip' => $ip,
                    'error' => implode("\n", $output),
                ]);
                @unlink($filename); // Pulisci il file
                return false;
            }

            $this->logger->info('Stampa inviata con successo', [
                'queue' => $queueName,
                'ip' => $ip,
            ]);

            // Pulisci il file temporaneo
            @unlink($filename);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Eccezione during print', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Genera contenuto testuale formattato per stampa
     */
    public static function formatInventoryPrint(array $conteggi, array $metadata = []): array
    {
        $lines = [];

        // Header
        $lines[] = '================================';
        $lines[] = 'INVENTARIO CONTEGGI';
        $lines[] = '================================';
        $lines[] = '';

        // Metadata (operatore, area, etc.)
        if (!empty($metadata)) {
            $lines[] = sprintf('Operatore: %s', $metadata['operatore'] ?? 'N/A');
            $lines[] = sprintf('Area: %s', $metadata['area'] ?? 'N/A');
            $lines[] = sprintf('Data: %s', date('Y-m-d H:i:s'));
            $lines[] = '';
        }

        // Intestazione tabella
        $lines[] = sprintf(
            '%-10s | %-6s | %-6s | %-12s | %-10s',
            'PRECODICE',
            'CODART',
            'POSIZ',
            'QTA',
            'CONTEGGI'
        );
        $lines[] = str_repeat('-', 80);

        // Righe conteggio
        $totalQta = 0;
        foreach ($conteggi as $row) {
            $lines[] = sprintf(
                '%-10s | %-6s | %-6s | %12.3f | %10s',
                $row['PRECODICE'] ?? '',
                $row['CODICE_ART'] ?? '',
                $row['POSIZIONE'] ?? '',
                $row['QTA_CONTEGGIATA'] ?? 0,
                $row['ID_CONTEGGIO'] ?? ''
            );
            $totalQta += $row['QTA_CONTEGGIATA'] ?? 0;
        }

        // Footer
        $lines[] = str_repeat('-', 80);
        $lines[] = sprintf(
            '%-10s | %-6s | %-6s | %12.3f | %10s',
            'TOTALE',
            '',
            '',
            $totalQta,
            count($conteggi)
        );
        $lines[] = '';
        $lines[] = '================================';

        return $lines;
    }
}
