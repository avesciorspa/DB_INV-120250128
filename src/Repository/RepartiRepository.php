<?php

namespace App\Repository;

use App\Database\MySQLConnection;
use PDO;

class RepartiRepository
{
    private MySQLConnection $db;

    public function __construct(MySQLConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Ottieni tutti i reparti
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM REPARTI ORDER BY ID_REP";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni per ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM REPARTI WHERE ID_REP = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Ottieni lista per select (ID => Descrizione)
     */
    public function getForSelect(): array
    {
        $sql = "SELECT ID_REP as id, DESCRIZIONE as label FROM REPARTI ORDER BY ID_REP";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['id']] = $row['label'];
        }
        return $result;
    }
}
