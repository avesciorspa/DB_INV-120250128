<?php

namespace App\Repository;

use App\Database\MySQLConnection;
use PDO;

class MarkerRepository
{
    private MySQLConnection $db;
    private static array $cache = [];
    private static int $cacheTime = 0;
    private const CACHE_TTL = 300; // 5 minuti

    public function __construct(MySQLConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Ottieni tutti i marker
     * Con cache in memoria per evitare query frequenti
     */
    public function getAll(): array
    {
        // Usa cache se non scaduta
        $now = time();
        if (!empty(self::$cache) && ($now - self::$cacheTime) < self::CACHE_TTL) {
            return self::$cache;
        }

        $sql = "SELECT PRECODICE, CODICE_ART FROM MARKER ORDER BY PRECODICE, CODICE_ART";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Salva in cache
        self::$cache = $result;
        self::$cacheTime = $now;

        return $result;
    }

    /**
     * Verifica se una combinazione PRECODICE/CODICE_ART è un marker
     */
    public function isMarker(string $precodice, string $codiceArt): bool
    {
        $markers = $this->getAll();
        foreach ($markers as $m) {
            if ($m['PRECODICE'] === $precodice && $m['CODICE_ART'] === $codiceArt) {
                return true;
            }
        }
        return false;
    }

    /**
     * Ottieni tipo marker (es. 'AREA', 'STAMPA')
     */
    public function getMarkerType(string $precodice, string $codiceArt): ?string
    {
        if ($this->isMarker($precodice, $codiceArt)) {
            return $codiceArt; // Ritorna direttamente il codice (AREA o STAMPA)
        }
        return null;
    }

    /**
     * Invalida cache (utile dopo aggiornamenti)
     */
    public static function invalidateCache(): void
    {
        self::$cache = [];
        self::$cacheTime = 0;
    }
}
