<?php

namespace App\Database;

use App\Config;
use App\Logger;

class DB2Connection
{
    private \PDO $pdo;
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $dsn = "odbc:" . \App\Config::get("DB2_DSN");
            $user = \App\Config::get("DB2_USER");
            $pass = \App\Config::get("DB2_PASS");

            $this->pdo = new \PDO($dsn, $user, $pass);

            $this->logger->info("Connessione DB2 stabilita");
        } catch (\PDOException $e) {
            $this->logger->critical("DB2 connection error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    public function query(string $sql)
    {
        return $this->pdo->query($sql);
    }

    public function prepare(string $sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}
