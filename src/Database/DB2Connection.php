<?php

namespace App\Database;

class DB2Connection
{
    private \PDO $pdo;
    private \App\Logger $logger;

    public function __construct(\App\Logger $logger)
    {
        $this->logger = $logger;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $dsn = \App\Config::get('DB2_DSN');  // Should be "DB2"
            $user = \App\Config::get('DB2_USER');
            $pass = \App\Config::get('DB2_PASS');
            $host = \App\Config::get('DB2_HOST', required: false);
            $port = \App\Config::get('DB2_PORT', '50000', required: false);

            // Per IBM DB2 via ODBC - usa DSN configurato nel sistema
            $fullDsn = "odbc:$dsn";
            
            $this->pdo = new \PDO($fullDsn, $user, $pass);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            
            $this->logger->info("DB2 connection established via ODBC DSN: $dsn");
        } catch (\PDOException $e) {
            $this->logger->error("DB2 connection failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}
