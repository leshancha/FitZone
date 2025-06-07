<?php
// Config/db.php
define('DB_HOST', 'localhost');
define('DB_USER', 'fitzone_admin');
define('DB_PASS', 'SuperSecureAdminPassword456!');
define('DB_NAME', 'fitzone');

if (!class_exists('Database')) {
    class Database {
        private $connection;
        private $lastError = null;
        
        public function __construct() {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                
            if ($this->connection->connect_error) {
                error_log("Database Error: " . $this->connection->connect_error);
                throw new Exception("Database connection failed");
            }
            
            $this->connection->set_charset("utf8mb4");
        }
        public function getConnection() {
            return $this->connection;
        }
        
        public function prepare($query) {
            try {
                $stmt = $this->connection->prepare($query);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->connection->error);
                }
                return $stmt;
            } catch (Exception $e) {
                $this->lastError = $e->getMessage();
                error_log("Database Prepare Error: " . $e->getMessage());
                throw $e;
            }
        }
        
        public function beginTransaction() {
            return $this->connection->begin_transaction();
        }
        
        public function commit() {
            return $this->connection->commit();
        }
        
        public function rollback() {
            return $this->connection->rollback();
        }
        
        public function getLastError() {
            return $this->lastError ?: $this->connection->error;
        }
        
        public function __destruct() {
            try {
                if ($this->connection && !$this->connection->connect_error) {
                    $this->connection->close();
                }
            } catch (Exception $e) {
                error_log("Database destructor error: " . $e->getMessage());
            }
        }
    }
}
?>