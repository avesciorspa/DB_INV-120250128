<?php

namespace App\Repository;

use App\Database\MySQLConnection;
use PDO;

class ConfigOperatoreRepository
{
    private MySQLConnection $db;

    public function __construct(MySQLConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Ottieni tutte le configurazioni
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM CONF_OPERATORE ORDER BY CODICE, ID_REP, ID_INVENTARIO";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ottieni configurazione per operatore/reparto/inventario
     */
    public function findByKey(string $codice, int $idRep, int $idInventario): ?array
    {
        $sql = "SELECT * FROM CONF_OPERATORE 
                WHERE CODICE = ? AND ID_REP = ? AND ID_INVENTARIO = ? 
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codice, $idRep, $idInventario]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Ottieni per ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM CONF_OPERATORE WHERE ID_CONF = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Crea nuova configurazione
     */
    public function create(string $codice, ?string $nome, int $idRep, int $idInventario, int $idArea, string $ipStampante): int
    {
        $sql = "INSERT INTO CONF_OPERATORE (CODICE, NOME, ID_REP, ID_INVENTARIO, ID_AREA, IP_STAMPANTE)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$codice, $nome, $idRep, $idInventario, $idArea, $ipStampante]);
        return (int)$this->db->getPDO()->lastInsertId();
    }

    /**
     * Aggiorna configurazione
     */
    public function update(int $id, ?string $nome, int $idArea, string $ipStampante): bool
    {
        $sql = "UPDATE CONF_OPERATORE 
                SET NOME = ?, ID_AREA = ?, IP_STAMPANTE = ?
                WHERE ID_CONF = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$nome, $idArea, $ipStampante, $id]);
    }

    /**
     * Eliminare configurazione
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM CONF_OPERATORE WHERE ID_CONF = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Bulk delete
     */
    public function bulkDelete(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM CONF_OPERATORE WHERE ID_CONF IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    /**
     * Bulk update area
     */
    public function bulkUpdateArea(array $ids, int $newArea): int
    {
        if (empty($ids)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE CONF_OPERATORE SET ID_AREA = ? WHERE ID_CONF IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$newArea], $ids));
        return $stmt->rowCount();
    }

    /**
     * Bulk update stampante
     */
    public function bulkUpdatePrinter(array $ids, string $ipStampante): int
    {
        if (empty($ids)) {
            return 0;
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE CONF_OPERATORE SET IP_STAMPANTE = ? WHERE ID_CONF IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$ipStampante], $ids));
        return $stmt->rowCount();
    }

    /**
     * Ottieni operatori unici
     */
    public function getOperators(): array
    {
        $sql = "SELECT DISTINCT CODICE FROM CONF_OPERATORE ORDER BY CODICE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
