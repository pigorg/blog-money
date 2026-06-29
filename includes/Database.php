<?php
class Database {
    private $conn;

    public function connect() {
        $env = $this->loadEnv();

        $this->conn = new mysqli(
            $env['DB_HOST'] ?? 'localhost',
            $env['DB_USER'],
            $env['DB_PASS'],
            $env['DB_NAME']
        );

        if ($this->conn->connect_error) {
            throw new Exception('Connessione DB fallita: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset('utf8mb4');
        return $this;
    }

    public function getConn() {
        return $this->conn;
    }

    private function loadEnv() {
        $envFile = dirname(__DIR__) . '/.env';
        if (!file_exists($envFile)) {
            throw new Exception('.env non trovato: ' . $envFile);
        }
        return parse_ini_file($envFile);
    }
}
