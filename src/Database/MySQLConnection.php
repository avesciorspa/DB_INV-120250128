<?php

namespace App\Database;

use App\Config;
use App\Logger;
use PDO;
use PDOException;

class MySQLConnection
{
    private PDO $pdo;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $host = Config::get('MYSQL_HOST');
            $port = Config::getInt('MYSQL_PORT', 3306);
            $db = Config::get('MYSQL_DB');
            $user = Config::get('MYSQL_USER');
            $pass = Config::get('MYSQL_PASS');

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
            
            $this->pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 30,
            ]);

            $this->logger->info('Connessione MySQL stabilita', [
                'host' => $host,
                'db' => $db,
            ]);
        } catch (PDOException $e) {
            $this->logger->critical('Errore connessione MySQL', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}
