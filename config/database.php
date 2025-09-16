<?php
/**
 * Database Connection Class using PDO
 * Supports MySQL, PostgreSQL, and SQLite
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    private $charset;
    private $pdo;
    private $error;

    public function __construct() {
        // Include environment configuration
        require_once __DIR__ . '/env.php';

        // Set database connection parameters from environment variables
        $this->host = $_ENV['DB_HOST'] ?? $envVars['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? $envVars['DB_NAME'] ?? 'pharmApp';
        $this->username = $_ENV['DB_USERNAME'] ?? $envVars['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? $envVars['DB_PASSWORD'] ?? '';
        $this->port = $_ENV['DB_PORT'] ?? $envVars['DB_PORT'] ?? '3306';
        $this->charset = $_ENV['DB_CHARSET'] ?? $envVars['DB_CHARSET'] ?? 'utf8mb4';
    }

    /**
     * Create PDO connection
     */
    public function connect() {
        $this->pdo = null;

        try {
            // MySQL DSN
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            // PDO options for better security and performance
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $this->charset
            ];

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            throw new Exception("Connection failed: " . $this->error);
        }

        return $this->pdo;
    }

    /**
     * Get the PDO connection
     */
    public function getConnection() {
        if ($this->pdo === null) {
            $this->connect();
        }
        return $this->pdo;
    }

    /**
     * Prepare and execute a query
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }

  

    /**
     * Fetch all results
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch single result
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Get row count
     */


    public function prepare ($sql, $params = []) {


        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }
   public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }


    public function rowCount($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function insert($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount() > 0 ? $this->lastInsertId() : false;
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId() {


        return $this->getConnection()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getConnection()->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }

    /**
     * Close connection
     */
    public function close() {
        $this->pdo = null;
    }

    /**
     * Get error message
     */
    public function getError() {
        return $this->error;
    }
}



    $db = new Database();
    $pdo = $db->connect();

?>