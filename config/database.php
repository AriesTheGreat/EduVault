<?php
/**
 * Database Configuration for EduVault System
 * Handles database connections using MySQLi
 */

class Database {
    private $host = 'localhost';
    private $database = 'eduvault';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $conn = null;
    
    public function __construct($host = null, $database = null, $username = null, $password = null) {
        if ($host) $this->host = $host;
        if ($database) $this->database = $database;
        if ($username) $this->username = $username;
        if ($password !== null) $this->password = $password;
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->conn->connect_error) {
                throw new Exception("Database connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset($this->charset);
            
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function isConnected() {
        return $this->conn !== null && !$this->conn->connect_error;
    }
    
    public function close() {
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            
            if (!empty($params)) {
                $types = '';
                $values = [];
                
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } else {
                        $types .= 's';
                    }
                    $values[] = $param;
                }
                
                $stmt->bind_param($types, ...$values);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            return $stmt;
            
        } catch (Exception $e) {
            error_log("Database execution error: " . $e->getMessage());
            throw new Exception("Database execution error: " . $e->getMessage());
        }
    }
    
    /**
     * Fetch all results from a query
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }
    
    /**
     * Fetch single row from a query
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return $row;
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->conn->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->conn->rollback();
    }
    
    /**
     * Get affected rows
     */
    public function affectedRows() {
        return $this->conn->affected_rows;
    }
    
    /**
     * Escape string
     */
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
}

// Global database instance
$GLOBALS['database'] = null;

/**
 * Get database instance
 */
function getDatabase() {
    if ($GLOBALS['database'] === null) {
        $GLOBALS['database'] = new Database();
    }
    return $GLOBALS['database'];
}

/**
 * Get database connection
 */
function getConnection() {
    return getDatabase()->getConnection();
}
