<?php

namespace App;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    /**
     * Carica configurazione da .env e variabili d'ambiente
     */
    public static function load(string $envPath = '.env'): void
    {
        if (self::$loaded) {
            return;
        }

        if (!file_exists($envPath)) {
            throw new \RuntimeException("File configurazione non trovato: $envPath");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignora commenti
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Rimuovi eventuali apici
            if (preg_match('/^["\'](.*)["\']\s*$/', $value, $m)) {
                $value = $m[1];
            }

            self::$config[$key] = $value;
            putenv("$key=$value");
        }

        self::$loaded = true;
    }

    /**
     * Ottieni un valore di configurazione
     * @throws \RuntimeException se la chiave non esiste e $required è true
     */
    public static function get(string $key, ?string $default = null, bool $required = true): ?string
    {
        if (!isset(self::$config[$key])) {
            if ($required && $default === null) {
                throw new \RuntimeException("Configurazione mancante: $key");
            }
            return $default;
        }

        $value = self::$config[$key];
        if (empty($value) && $required) {
            throw new \RuntimeException("Configurazione vuota: $key");
        }

        return $value;
    }

    /**
     * Ottieni un intero
     */
    public static function getInt(string $key, ?int $default = null, bool $required = true): ?int
    {
        $value = self::get($key, (string)$default, $required);
        return $value !== null ? (int)$value : null;
    }

    /**
     * Validazione configurazione al boot
     */
    public static function validate(): array
    {
        $errors = [];

        // DB2
        if (empty(self::get('DB2_HOST', required: false))) {
            $errors[] = "DB2_HOST non configurato";
        }
        if (empty(self::get('DB2_USER', required: false))) {
            $errors[] = "DB2_USER non configurato";
        }

        // MySQL
        if (empty(self::get('MYSQL_HOST', required: false))) {
            $errors[] = "MYSQL_HOST non configurato";
        }
        if (empty(self::get('MYSQL_USER', required: false))) {
            $errors[] = "MYSQL_USER non configurato";
        }

        return $errors;
    }
}
