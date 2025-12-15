<?php
require_once __DIR__ . '/../config.php';

class Database {
     protected $host = "127.0.0.1";
    protected $username = "root";    
    protected $password = "";        
    protected $dbname = "garment_ordering_system";
    
    public $conn; 

    public function __construct($existing_conn = null) {
        if ($existing_conn) {
            $this->conn = $existing_conn;
            return;
        }

        
        $this->conn = null;
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->dbname . ";charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   
            PDO::ATTR_EMULATE_PREPARES   => false,          
        ];

        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);

            $this->conn->exec("SET time_zone = '+08:00'");
            $this->conn->exec("SET SESSION innodb_lock_wait_timeout = 10"); 
            $this->conn->exec("SET SESSION wait_timeout = 10");

        } catch (PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
            error_log("Database connection failed: " . $e->getMessage());
            die("Sorry, there was a problem connecting to the database. Please try again later.");
        }
    }

    public function select($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Database select error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
            throw new Exception("Database select failed: " . $e->getMessage());
        }
    }

    public function execute($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database execute error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
            throw new Exception("Database operation failed: " . $e->getMessage());
        }
    }

    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }

    
    protected function addStockMovementLog($stock_id, $change_quantity, $new_stock_level, $movement_type, $user_id = null, $order_id = null, $notes = null) {
        if (!is_numeric($stock_id) || !is_numeric($change_quantity) || !is_numeric($new_stock_level) || empty($movement_type)) {
            error_log("addStockMovementLog: Invalid parameters provided.");
            return false;
        }

        $sql = "INSERT INTO stock_Movement_Log 
                    (stock_id, user_id, order_id, change_quantity, new_stock_level, movement_type, notes)
                VALUES 
                    (:stock_id, :user_id, :order_id, :change_quantity, :new_stock_level, :movement_type, :notes)";
        
        $params = [
            ':stock_id' => $stock_id,
            ':user_id' => $user_id, 
            ':order_id' => $order_id,
            ':change_quantity' => $change_quantity,
            ':new_stock_level' => $new_stock_level,
            ':movement_type' => $movement_type,
            ':notes' => $notes 
        ];

        try {
            $this->execute($sql, $params);
            return true;
        } catch (Exception $e) {
            error_log("Failed to add stock movement log: " . $e->getMessage() . " | Params: " . json_encode($params));
            return false;
        }
    }

    protected function createNotification($user_id, $message, $link = null) {
        if (empty($user_id) || empty($message)) {
             error_log("createNotification: Missing user_id or message.");
             return false;
        }
        
        try {
            $sql = "INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)";
            $this->execute($sql, [
                ':user_id' => $user_id,
                ':message' => $message,
                ':link' => $link
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
            return false;
        }
    }

    public function createNotificationForAdmins($message, $link = null) {
        if (empty($message)) {
             error_log("createNotificationForAdmins: Missing message.");
             return false;
        }
        
        try {
            $admin_sql = "SELECT user_id FROM users WHERE role = 'admin' AND is_active = 1";
            $admins = $this->select($admin_sql);
            
            if (empty($admins)) {
                error_log("createNotificationForAdmins: No active admin users found to notify.");
                return false;
            }

            $sql = "INSERT INTO Notifications (user_id, message, link) VALUES (:user_id, :message, :link)";
            
            foreach ($admins as $admin) {
                $this->execute($sql, [
                    ':user_id' => $admin['user_id'],
                    ':message' => $message,
                    ':link' => $link
                ]);
            }
            return true;

        } catch (Exception $e) {
            error_log("Failed to create admin notifications: " . $e->getMessage());
            return false;
        }
    }
}
?>