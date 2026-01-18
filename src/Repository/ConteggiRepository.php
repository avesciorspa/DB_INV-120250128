<?php

namespace App\Repository;

use App\Database\MySQLConnection;
use PDOException;

class ConteggiRepository
{
    public function __construct(private MySQLConnection $db) {}

    /**
     * Upsert record CONTEGGI da DB2
     * ID_AREA viene valorizzato se il marker corrisponde
     */
    public function upsertFromINVC(array $data): bool
    {
        try {
            $sql = <<<SQL
INSERT INTO CONTEGGI (
    REPARTO, NUMERO_INV, PRECODICE, CODICE_ART, POSIZIONE, NUMERO_CONTA,
    PROG, QTA_CONTEGGIATA, OPER_CREAZ, TS_CREAZIONE, ID_AREA
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
ON DUPLICATE KEY UPDATE
    QTA_CONTEGGIATA = VALUES(QTA_CONTEGGIATA),
    STAMPATO = 0,
    ID_AREA = VALUES(ID_AREA)
SQL;

            $stmt = $this->db->getPDO()->prepare($sql);
            return $stmt->execute([
                $data['reparto'] ?? null,
                $data['numero_inv'] ?? null,
                $data['precodice'] ?? null,
                $data['codice_art'] ?? null,
                $data['posizione'] ?? null,
                $data['numero_conta'] ?? null,
                $data['prog'] ?? null,
                $data['qty'] ?? null,
                $data['oper_creaz'] ?? null,
                $data['ts_creazione'] ?? date('Y-m-d H:i:s'),
                $data['id_area'] ?? null
            ]);
        } catch (PDOException $e) {
            throw new \Exception("Errore upsert CONTEGGI: " . $e->getMessage());
        }
    }

    /**
     * Leggi record da stampare per tipo
     * 1=area attuale operatore, 3=tutti, 4=ultimi 50, 5=non ancora stampati
     */
    public function getForPrint(int $type, ?int $idArea = null): array
    {
        try {
            switch ($type) {
                case 1: // Area attuale
                    if (!$idArea) {
                        return [];
                    }
                    $sql = "SELECT * FROM CONTEGGI WHERE ID_AREA = ? AND STAMPATO = 0 ORDER BY TS_CREAZIONE";
                    $stmt = $this->db->getPDO()->prepare($sql);
                    $stmt->execute([$idArea]);
                    break;

                case 3: // Tutti i conteggi
                    $sql = "SELECT * FROM CONTEGGI WHERE STAMPATO = 0 ORDER BY REPARTO, NUMERO_INV";
                    $stmt = $this->db->getPDO()->prepare($sql);
                    $stmt->execute();
                    break;

                case 4: // Ultimi 50
                    $sql = "SELECT * FROM CONTEGGI WHERE STAMPATO = 0 ORDER BY TS_CREAZIONE DESC LIMIT 50";
                    $stmt = $this->db->getPDO()->prepare($sql);
                    $stmt->execute();
                    break;

                case 5: // Non ancora stampati (same as 3)
                    $sql = "SELECT * FROM CONTEGGI WHERE STAMPATO = 0 ORDER BY REPARTO, NUMERO_INV";
                    $stmt = $this->db->getPDO()->prepare($sql);
                    $stmt->execute();
                    break;

                default:
                    return [];
            }

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new \Exception("Errore lettura CONTEGGI: " . $e->getMessage());
        }
    }

    /**
     * Segna record come stampato
     */
    public function markAsPrinted(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE CONTEGGI SET STAMPATO = 1 WHERE REPARTO IN ($placeholders)";

            $stmt = $this->db->getPDO()->prepare($sql);
            return $stmt->execute($ids);
        } catch (PDOException $e) {
            throw new \Exception("Errore marcatura CONTEGGI: " . $e->getMessage());
        }
    }

    /**
     * Conta record non stampati per area
     */
    public function countUnprinted(?int $idArea = null): int
    {
        try {
            if ($idArea) {
                $sql = "SELECT COUNT(*) as cnt FROM CONTEGGI WHERE ID_AREA = ? AND STAMPATO = 0";
                $stmt = $this->db->getPDO()->prepare($sql);
                $stmt->execute([$idArea]);
            } else {
                $sql = "SELECT COUNT(*) as cnt FROM CONTEGGI WHERE STAMPATO = 0";
                $stmt = $this->db->getPDO()->prepare($sql);
                $stmt->execute();
            }

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['cnt'] ?? 0;
        } catch (PDOException $e) {
            throw new \Exception("Errore count CONTEGGI: " . $e->getMessage());
        }
    }
}
