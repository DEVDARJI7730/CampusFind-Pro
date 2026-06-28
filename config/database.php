<?php
/**
 * CampusFind Pro - Database Class
 * Secure PDO-based Database wrapper using the Singleton design pattern.
 */

class Database {
    private static ?Database $instance = null;
    private ?PDO $conn = null;

    // Private constructor prevents direct instantiation
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Securely log DB error and present a user-friendly error page
            error_log("Database connection failed: " . $e->getMessage());
            die("
                <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; text-align: center; padding: 50px 20px; background: #fdfdfd; color: #333;'>
                    <div style='max-width: 500px; margin: 0 auto; padding: 40px; border-radius: 16px; box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05); border: 1px solid #eaeaea; background: #fff;'>
                        <h2 style='color: #ff3b30; font-weight: 600; margin-top: 0;'>System Maintenance</h2>
                        <p style='color: #666; font-size: 15px; line-height: 1.6;'>We are experiencing a temporary database connectivity issue. Please ensure the database is initialized and running.</p>
                        <hr style='border: 0; border-top: 1px solid #eaeaea; margin: 25px 0;'>
                        <button onclick='window.location.reload()' style='background: #007aff; color: #fff; border: 0; padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; transition: 0.2s;'>Retry Connection</button>
                    </div>
                </div>
            ");
        }
    }

    /**
     * Get Database class instance
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Get active PDO connection
     */
    public function getConnection(): PDO {
        return $this->conn;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
