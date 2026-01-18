<?php

namespace App\Repository;

use App\Database\MySQLConnection;
use PDO;

class StampantiRepository
{
    private MySQLConnection $db;

    public function __construct(MySQLConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Ottieni tutte le stampanti
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM STAMPANTI ORDER BY IP";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni per IP
     */
    public function findByIp(string $ip): ?array
    {
        $sql = "SELECT * FROM STAMPANTI WHERE IP = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ip]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crea stampante
     */
    public function create(string $ip, string $codaCups): bool
    {
        $sql = "INSERT INTO STAMPANTI (IP, CODA_CUPS)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                CODA_CUPS = VALUES(CODA_CUPS)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$ip, $codaCups]);
    }

    /**
     * Aggiorna stampante
     */
    public function update(string $ip, string $codaCups): bool
    {
        $sql = "UPDATE STAMPANTI SET CODA_CUPS = ? WHERE IP = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$codaCups, $ip]);
    }

    /**
     * Elimina stampante
     */
    public function delete(string $ip): bool
    {
        $sql = "DELETE FROM STAMPANTI WHERE IP = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$ip]);
    }

    /**
     * Ottieni stampante di default (primo per IP)
     */
    public function getDefault(): ?array
    {
        $sql = "SELECT * FROM STAMPANTI ORDER BY IP LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Conta quante configurazioni usano questa stampante
     */
    public function countUsage(string $ip): int
    {
        $sql = "SELECT COUNT(*) FROM CONF_OPERATORE WHERE IP_STAMPANTE = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ip]);
        return (int)$stmt->fetchColumn();
    }
}
