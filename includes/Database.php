<?php
class Database {
    private $conn;

    public function connect() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($this->conn->connect_error) {
            throw new Exception('Connessione DB fallita: ' . $this->conn->connect_error);
        }

        $this->conn->set_charset('utf8mb4');
        return $this;
    }

    public function getConn() {
        return $this->conn;
    }
}
