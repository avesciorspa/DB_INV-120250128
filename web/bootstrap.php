<?php

// Autoloader per PSR-4
require_once __DIR__ . '/autoload.php';

use App\Config;
use App\Logger;
use App\Database\MySQLConnection;

// Carica configurazione da .env
$envFile = __DIR__ . '/../.env';
Config::load($envFile);

// Validazione configurazione
$errors = Config::validate();
if (!empty($errors)) {
    die("Errori di configurazione:\n" . implode("\n", $errors));
}

// Crea logger
$logFile = Config::get('LOG_FILE', '/tmp/inv.log', false);
$logLevel = Config::get('LOG_LEVEL', 'INFO', false);
$logger = new Logger($logFile, $logLevel);

// Crea connessione MySQL
try {
    $mysqlDb = new MySQLConnection($logger);
} catch (\Exception $e) {
    $logger->critical('Impossibile connettersi a MySQL', ['error' => $e->getMessage()]);
    http_response_code(500);
    die("Errore database: impossibile connettersi a MySQL");
}

return [
    'config' => Config::class,
    'logger' => $logger,
    'db' => $mysqlDb,
];
