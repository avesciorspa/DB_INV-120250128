<?php

namespace App\Repository;

use App\Database\MySQLConnection;
use PDO;

class AreeRepository
{
    private MySQLConnection $db;

    public function __construct(MySQLConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Ottieni tutte le aree
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM AREE ORDER BY ID_AREA";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni per ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM AREE WHERE ID_AREA = ? LIMIT 1";
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
        $sql = "SELECT ID_AREA as id, DESCRIZIONE as label FROM AREE ORDER BY ID_AREA";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['id']] = $row['label'];
        }
        return $result;
    }

    /**
     * Conta conteggi per area (per riferimenti)
     */
    public function countConteggi(int $idArea): int
    {
        $sql = "SELECT COUNT(*) FROM CONTEGGI WHERE ID_AREA = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idArea]);
        return (int)$stmt->fetchColumn();
    }
}
