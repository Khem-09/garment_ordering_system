<?php
    require_once "database.php";
    require_once "EmailSender.php";

    class Admin extends Database{
        public $garment_id="";
        public $item_name="";
        public $category="";
        public $unit_price="";
        public $image_url="";
        public $size="";
        public $initial_stock="";

        public function __construct($existing_conn = null)
        {
            parent::__construct($existing_conn);
        }

        // --- ACCOUNT MANAGEMENT METHODS ---
        
        public function getProfile($user_id) {
            $sql = "SELECT user_id, student_id, first_name, last_name, email_address, contact_number FROM Users WHERE user_id = :user_id LIMIT 1";
            $result = $this->select($sql, [':user_id' => $user_id]);
            
            if ($result) {
                $profile = $result[0];
                $profile['full_name'] = trim($profile['first_name'] . ' ' . $profile['last_name']);
                return $profile;
            }
            return null;
        }

        public function updateProfile($user_id, $first_name, $last_name, $contact_number) {
            $sql = "UPDATE Users SET first_name = :first_name, last_name = :last_name, contact_number = :contact_number WHERE user_id = :user_id";
            $params = [
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':contact_number' => $contact_number,
                ':user_id' => $user_id
            ];
            return $this->execute($sql, $params);
        }

        public function verifyCurrentPassword($user_id, $password) {
            $sql = "SELECT password_hash FROM Users WHERE user_id = :user_id LIMIT 1";
            $result = $this->select($sql, [':user_id' => $user_id]);
            if ($result) {
                return password_verify($password, $result[0]['password_hash']);
            }
            return false;
        }

        public function requestPasswordChangeOTP($user_id) {
            $sql_user = "SELECT email_address, first_name FROM Users WHERE user_id = :user_id LIMIT 1";
            $user = $this->select($sql_user, [':user_id' => $user_id]);
            
            if (empty($user)) return false;
            
            $email = $user[0]['email_address'];
            $first_name = $user[0]['first_name'];

            $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $sql_update = "UPDATE Users SET reset_token = :otp, reset_token_expiry = :expiry WHERE user_id = :user_id";
            $this->execute($sql_update, [
                ':otp' => $otp_code,
                ':expiry' => $expiry,
                ':user_id' => $user_id
            ]);

            try {
                $mailer = new EmailSender();
                $subject = "Admin Security Verification";
                $header = "Verify Password Change";
                $body = "<p>Hi " . htmlspecialchars($first_name) . ",</p>"
                      . "<p>You have requested to change your <strong>Administrator Password</strong>.</p>"
                      . "<div style='text-align:center; margin: 20px 0;'>"
                      . "<span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #8B0000; background: #f8f9fa; padding: 10px 20px; border-radius: 5px; border: 1px solid #dee2e6;'>" . $otp_code . "</span>"
                      . "</div>"
                      . "<p>This code expires in 10 minutes.</p>"
                      . "<p>If this wasn't you, please secure your account immediately.</p>";
                
                return $mailer->sendEmail($email, $first_name, $subject, $header, $body);
            } catch (Exception $e) {
                error_log("Failed to send Admin OTP email: " . $e->getMessage());
                return false;
            }
        }

        public function verifyOTPAndChangePassword($user_id, $code, $new_password) {
            $current_time_php = date('Y-m-d H:i:s');

            $sql = "SELECT user_id FROM Users WHERE user_id = :user_id AND reset_token = :otp AND reset_token_expiry > :current_time LIMIT 1";
            $result = $this->select($sql, [
                ':user_id' => $user_id, 
                ':otp' => $code,
                ':current_time' => $current_time_php
            ]);

            if (empty($result)) {
                return false;
            }

            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update = "UPDATE Users SET password_hash = :hash, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id";
            $this->execute($sql_update, [
                ':hash' => $new_hash,
                ':user_id' => $user_id
            ]);
            
            $this->createNotificationForAdmins("Admin ID {$user_id} changed their password.");
            
            return true;
        }

        // -------------------------------------

        public function getGarmentDetails($garment_id) {
            $sql = "SELECT garment_id, item_name, category, unit_price, image_url, is_deleted FROM Garment WHERE garment_id = :garment_id LIMIT 1";
            $result = $this->select($sql, [':garment_id' => $garment_id]);
            return $result ? $result[0] : null;
        }

       public function addGarments(){
            $checkSql = "SELECT garment_id, is_deleted FROM Garment WHERE item_name = :item_name LIMIT 1";
            $existing = $this->select($checkSql, [':item_name' => $this->item_name]);
            
            if (!empty($existing)) {
                $row = $existing[0];
                if ($row['is_deleted'] == 0) {
                    return "DUPLICATE";
                } else {
                    $updateSql = "UPDATE Garment 
                                  SET category = :category, 
                                      unit_price = :unit_price, 
                                      is_deleted = 0 
                                  WHERE garment_id = :garment_id";
                                  
                    $params = [
                        ':category' => $this->category,
                        ':unit_price' => $this->unit_price,
                        ':garment_id' => $row['garment_id']
                    ];

                    if (!empty($this->image_url)) {
                        $updateSql = "UPDATE Garment 
                                      SET category = :category, 
                                          unit_price = :unit_price, 
                                          image_url = :image_url,
                                          is_deleted = 0 
                                      WHERE garment_id = :garment_id";
                        $params[':image_url'] = $this->image_url;
                    }

                    if ($this->execute($updateSql, $params)) {
                        return $row['garment_id']; 
                    }
                    return false;
                }
            }

            $sql = "INSERT INTO Garment (item_name, category, unit_price, image_url)
                    VALUES (:item_name, :category, :unit_price, :image_url)";
            $params = [
                ':item_name' => $this->item_name,
                ':category' => $this->category,
                ':unit_price' => $this->unit_price,
                ':image_url' => $this->image_url
            ];
            
            try {
                if ($this->execute($sql, $params)) {
                    return $this->lastInsertId();
                }
            } catch (PDOException $e) {
                if ($e->errorInfo[1] == 1062) {
                    return "DUPLICATE";
                }
                throw $e;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    return "DUPLICATE";
                }
                throw $e;
            }
            
            return false;
        }

        public function updateGarmentDetails($garment_id, $item_name, $category, $unit_price, $new_image_url = null) {
            try {
                $garment_details = $this->getGarmentDetails($garment_id);
                if (!$garment_details) {
                    return false;
                }
                $old_image_url = $garment_details['image_url'] ?? null;

                $sql_parts = [];
                $params = [':garment_id' => $garment_id];

                if (!empty($item_name)) {
                    $sql_parts[] = "item_name = :item_name";
                    $params[':item_name'] = $item_name;
                }
                if (!empty($category)) {
                    $sql_parts[] = "category = :category";
                    $params[':category'] = $category;
                }
                if (($unit_price !== null && $unit_price !== '') && is_numeric($unit_price) && $unit_price >= 0) {
                    $sql_parts[] = "unit_price = :unit_price";
                    $params[':unit_price'] = $unit_price;
                }
                if ($new_image_url !== null) {
                    $sql_parts[] = "image_url = :image_url";
                    $params[':image_url'] = ($new_image_url === '') ? null : $new_image_url; 
                }

                if (empty($sql_parts)) {
                    return true; 
                }

                $sql = "UPDATE Garment SET " . implode(", ", $sql_parts) . " WHERE garment_id = :garment_id";

                if ($this->execute($sql, $params)) {
                    if ($new_image_url !== null && !empty($old_image_url) && $old_image_url != $new_image_url) {
                        return $old_image_url; 
                    }
                    return true;
                }
                return false; 
            } catch (Exception $e) {
                error_log("updateGarmentDetails failed: " . $e->getMessage());
                return false;
            }
        }

        // *** UPDATED: Pagination for Garments ***
        public function viewGarments($search = "", $category = "", $page = 1, $limit = 10) {
            $offset = ($page - 1) * $limit;
            $params = [];
            $conditions = ["g.is_deleted = 0"];

            if (!empty($search)) {
                $conditions[] = "g.item_name LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            if (!empty($category)) {
                $conditions[] = "g.category = :category";
                $params[':category'] = $category;
            }

            $where_sql = " WHERE " . implode(" AND ", $conditions);

            // 1. Get Total Count
            $count_sql = "SELECT COUNT(DISTINCT g.garment_id) as total FROM Garment g LEFT JOIN Stocks s ON g.garment_id = s.garment_id " . $where_sql;
            $count_res = $this->select($count_sql, $params);
            $total_records = $count_res[0]['total'] ?? 0;

            // 2. Get Data with Limit/Offset
            $sql = "SELECT g.garment_id, g.item_name, g.category, g.unit_price, g.image_url,
                           SUM(s.current_stock) AS total_stock,
                           GROUP_CONCAT(CONCAT(s.size, ': ', s.current_stock) ORDER BY FIELD(s.size, 'Extra Small', 'Small', 'Medium', 'Large', 'Extra Large') SEPARATOR '<br>') AS stock_breakdown
                    FROM Garment g
                    LEFT JOIN Stocks s ON g.garment_id = s.garment_id
                    $where_sql
                    GROUP BY g.garment_id, g.item_name, g.category, g.unit_price, g.image_url
                    ORDER BY g.item_name ASC
                    LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $data = $this->select($sql, $params);

            return [
                'data' => $data,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit)
            ];
        }

        public function getAllGarments() {
            $sql = "SELECT garment_id, item_name, category, unit_price FROM Garment WHERE is_deleted = 0 ORDER BY item_name ASC";
            $result = $this->conn->query($sql);
            return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
        }

        public function getGarmentsByName($search_query) {
            $sql = "SELECT garment_id, item_name, category, unit_price FROM Garment 
                    WHERE item_name LIKE :search_query 
                    AND is_deleted = 0 
                    ORDER BY item_name ASC";
            $params = [':search_query' => '%' . $search_query . '%'];
            return $this->select($sql, $params);
        }

        public function getStocks($garment_id) {
            $sql = "SELECT stock_id, garment_id, size, current_stock FROM Stocks WHERE garment_id = :garment_id ORDER BY FIELD(size, 'Extra Small', 'Small', 'Medium', 'Large', 'Extra Large')";
            return $this->select($sql, [':garment_id' => $garment_id]);
        }
        
        private function getCurrentStockLevel($stock_id) {
            $result = $this->select("SELECT current_stock FROM Stocks WHERE stock_id = :stock_id LIMIT 1", [':stock_id' => $stock_id]);
            return $result ? (int)$result[0]['current_stock'] : null;
        }

       public function addStocks($garment_id, $size, $initial_stock) {
            $checkSql = "SELECT stock_id, current_stock FROM Stocks WHERE garment_id = :garment_id AND size = :size LIMIT 1";
            $existing = $this->select($checkSql, [':garment_id' => $garment_id, ':size' => $size]);

            if (!empty($existing)) {
                $stock_id = $existing[0]['stock_id'];
                $new_total = $existing[0]['current_stock'] + $initial_stock;
                
                $sql = "UPDATE Stocks SET current_stock = :current_stock WHERE stock_id = :stock_id";
                $this->execute($sql, [':current_stock' => $new_total, ':stock_id' => $stock_id]);
                
                $admin_user_id = $_SESSION['user']['user_id'] ?? null;
                $this->addStockMovementLog($stock_id, $initial_stock, $new_total, 'restock', $admin_user_id, null, "Admin added stock to existing size.");
                return true;
            } else {
                $sql = "INSERT INTO Stocks (garment_id, size, current_stock) VALUES (:garment_id, :size, :current_stock)";
                $params = [':garment_id' => $garment_id, ':size' => $size, ':current_stock' => $initial_stock];
                $stmt = $this->execute($sql, $params);
                if ($stmt && $stmt->rowCount() > 0) {
                    $stock_id = $this->lastInsertId();
                    $admin_user_id = $_SESSION['user']['user_id'] ?? null;
                    $this->addStockMovementLog($stock_id, $initial_stock, $initial_stock, 'initial_stock', $admin_user_id, null, "Admin added new size/stock.");
                    return true;
                }
            }
            return false;
        }

        public function updateStocks($stock_id, $new_stock_level) {
            $old_stock_level = $this->getCurrentStockLevel($stock_id);
            if ($old_stock_level === null) {
                error_log("updateStocks: Could not find old stock level for stock_id: $stock_id");
                return false; 
            }

            $sql = "UPDATE Stocks SET current_stock = :current_stock WHERE stock_id = :stock_id";
            $params = [':current_stock' => $new_stock_level, ':stock_id' => $stock_id];
            $stmt = $this->execute($sql, $params);
            
            if ($stmt && $stmt->rowCount() > 0) {
                 $change_quantity = $new_stock_level - $old_stock_level;
                 $admin_user_id = $_SESSION['user']['user_id'] ?? null;
                 $this->addStockMovementLog($stock_id, $change_quantity, $new_stock_level, 'manual_adjustment', $admin_user_id, null, "Admin updated stock level.");
                 return true;
            } elseif ($new_stock_level == $old_stock_level) {
                return true; 
            }
            return false;
        }

        // *** FIX: SAFE DELETE STOCK (Prevents Constraint Violation) ***
        public function deleteStock($stock_id) {
            $old_stock_level = $this->getCurrentStockLevel($stock_id);
            $admin_user_id = $_SESSION['user']['user_id'] ?? null;

            try {
                // Attempt Hard Delete
                $sql = "DELETE FROM Stocks WHERE stock_id = :stock_id";
                $this->execute($sql, [':stock_id' => $stock_id]);
                
                // If successful (no orders linked), log deletion
                if ($old_stock_level !== null && $old_stock_level > 0) {
                     $this->addStockMovementLog($stock_id, -$old_stock_level, 0, 'stock_deleted', $admin_user_id, null, "Admin deleted stock entry.");
                }
                return "DELETED";

            } catch (Exception $e) {
                // Check for Foreign Key Constraint (Error 1451)
                if (strpos($e->getMessage(), 'Constraint violation') !== false || strpos($e->getMessage(), '1451') !== false) {
                    
                    // Fallback: Soft Delete by setting stock to 0
                    $updateSql = "UPDATE Stocks SET current_stock = 0 WHERE stock_id = :stock_id";
                    $this->execute($updateSql, [':stock_id' => $stock_id]);

                    if ($old_stock_level !== null && $old_stock_level > 0) {
                        $this->addStockMovementLog($stock_id, -$old_stock_level, 0, 'manual_adjustment', $admin_user_id, null, "Stock zeroed (Cannot delete due to order history).");
                    }
                    return "ZEROED";
                }
                throw $e;
            }
        }

        public function deleteGarment($garment_id) {
            try {
                $garment_details = $this->getGarmentDetails($garment_id);
                if (!$garment_details) {
                    return false;
                }
                $image_url = $garment_details['image_url'];

                $sql = "UPDATE Garment SET is_deleted = 1 WHERE garment_id = :garment_id";
                $stmt = $this->execute($sql, [':garment_id' => $garment_id]);
                
                if ($stmt->rowCount() > 0) {
                    return $image_url;
                }
                return false; 
            } catch (Exception $e) {
                error_log("Delete garment (soft) failed: " . $e->getMessage());
                return false;
            }
        }
        
        // *** UPDATED: Pagination for Orders ***
        public function getOrdersByStatus($status = 'Pending', $search_query = "", $page = 1, $limit = 10) {
            $offset = ($page - 1) * $limit;
            $params = [':status' => $status];
            $conditions = ["o.status = :status"];

            if (!empty($search_query)) {
                $conditions[] = "(u.full_name LIKE :search1 OR u.student_id LIKE :search2)";
                $params[':search1'] = '%' . $search_query . '%';
                $params[':search2'] = '%' . $search_query . '%';
            }

            $where_sql = " WHERE " . implode(" AND ", $conditions);

            // 1. Get Total Count
            $count_sql = "SELECT COUNT(*) as total FROM Orders o JOIN users u ON o.user_id = u.user_id " . $where_sql;
            $count_res = $this->select($count_sql, $params);
            $total_records = $count_res[0]['total'] ?? 0;

            // 2. Get Data
            $sql = "
                SELECT 
                    o.order_id, 
                    o.order_date, 
                    o.total_amount, 
                    o.status,
                    u.full_name,
                    u.student_id
                FROM Orders o
                JOIN users u ON o.user_id = u.user_id
                $where_sql
                ORDER BY o.order_date ASC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $data = $this->select($sql, $params);

            return [
                'data' => $data,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit)
            ];
        }

        public function getOrderDetailsAdmin($order_id) {
            $sql_order = "
                SELECT
                    o.order_id, o.order_date, o.total_amount, o.status,
                    u.student_id, u.full_name, u.contact_number
                FROM Orders o
                JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = :order_id 
                LIMIT 1
            ";
            $order_result = $this->select($sql_order, [':order_id' => $order_id]);

            if (empty($order_result)) {
                return null;
            }

            $order_data = $order_result[0];

            $sql_items = "
                SELECT
                    oi.size, oi.unit_price, oi.quantity, oi.subtotal,
                    g.item_name,
                    oi.stock_id,
                    s.garment_id
                FROM Order_Items oi
                JOIN Stocks s ON oi.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                WHERE oi.order_id = :order_id
            ";
            $items_result = $this->select($sql_items, [':order_id' => $order_id]);
            
            return array_merge($order_data, ['items' => $items_result]);
        }

        public function updateOrderStatus($order_id, $new_status) {
            $valid_statuses = ['Pending', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected', 'Cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                return false;
            }
            
            $order_info_sql = "SELECT o.user_id, u.email_address, u.full_name 
                               FROM Orders o 
                               JOIN users u ON o.user_id = u.user_id 
                               WHERE o.order_id = :order_id LIMIT 1";
            $order_info = $this->select($order_info_sql, [':order_id' => $order_id]);
            
            $user_id = $order_info[0]['user_id'] ?? null;
            $user_email = $order_info[0]['email_address'] ?? null;
            $user_name = $order_info[0]['full_name'] ?? 'Student';

            $items_restored = []; 
            if ($new_status == 'Rejected' || $new_status == 'Cancelled') {
                try {
                    if (!class_exists('Student')) {
                         require_once __DIR__ . "/student.php";
                    }
                    $studentObj = new Student($this->conn); 

                    $sql_items = "SELECT stock_id, quantity FROM Order_Items WHERE order_id = :order_id";
                    $items_to_restock = $this->select($sql_items, [':order_id' => $order_id]);

                    foreach ($items_to_restock as $item) {
                        $old_stock_level = $this->getCurrentStockLevel($item['stock_id']); 
                        
                        if ($studentObj->restoreStock($item['stock_id'], $item['quantity'])) {
                             if ($old_stock_level !== null) {
                                $items_restored[] = [
                                    'stock_id' => $item['stock_id'],
                                    'quantity' => $item['quantity'],
                                    'new_stock_level' => $old_stock_level + $item['quantity'] 
                                ];
                            }
                        } else {
                             error_log("Failed to restore stock for item {$item['stock_id']} in order {$order_id}");
                            
                        }
                    }
                } catch (Exception $e) {
                    error_log("Failed to restock items for {$new_status} order {$order_id}: " . $e->getMessage());
                }
            }

            $sql = "UPDATE Orders SET status = :new_status";
            $params = [
                ':new_status' => $new_status,
                ':order_id' => $order_id
            ];
            
            if ($new_status == 'Completed') { 
                try {
                    $check_col = $this->select("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Orders' AND COLUMN_NAME = 'date_fulfilled'");
                    if ($check_col) {
                         $sql .= ", date_fulfilled = NOW()"; 
                    }
                } catch (Exception $e) { }
            }

            $sql .= " WHERE order_id = :order_id";
            $stmt = $this->execute($sql, $params);
            
            if (!empty($items_restored)) {
                $admin_user_id = $_SESSION['user']['user_id'] ?? null;
                $log_type = ($new_status == 'Rejected') ? 'rejection_restock' : 'cancellation_restock';
                
                foreach ($items_restored as $restored) {
                    $this->addStockMovementLog(
                        $restored['stock_id'],
                        $restored['quantity'],
                        $restored['new_stock_level'],
                        $log_type,
                        $admin_user_id,
                        $order_id,
                        "Stock restored due to order {$new_status}."
                    );
                }
            }
            
            if ($stmt->rowCount() > 0 && $user_id) {
                
                $message = "";
                $link = "order_receipt.php?id={$order_id}"; 
                
                $email_subject = "";
                $email_header = "";
                $body_student = "";
         
                switch ($new_status) {
                    case 'Approved':
                        $message = "Your Order #{$order_id} has been approved and is being prepared.";
                        $email_subject = "Your Order #[{$order_id}] is Approved!";
                        $email_header = "Order Approved";
                        $body_student = "<p>Hi " . htmlspecialchars($user_name) . ",</p>"
                                    . "<p>Your order <strong>#{$order_id}</strong> has been approved and is now being prepared by our team.</p>"
                                    . "<p>We will notify you again when it is ready for pickup.</p>"
                                    . "<p class='button-wrapper'><a href='http://localhost/garment_ordering_system/student/order_receipt.php?id={$order_id}' class='cta-button'>View Order Status</a></p>";
                        break;
                    case 'Ready for Pickup':
                        $message = "Your Order #{$order_id} is now Ready for Pickup!";
                        $email_subject = "Your Order #[{$order_id}] is Ready for Pickup!";
                        $email_header = "Ready for Pickup";
                        $body_student = "<p>Hi " . htmlspecialchars($user_name) . ",</p>"
                                    . "<p>Great news! Your order <strong>#{$order_id}</strong> is now ready for pickup.</p>"
                                    . "<p>Please proceed to the garments distribution area with your digital order slip and payment receipt.</p>"
                                    . "<p class='button-wrapper'><a href='http://localhost/garment_ordering_system/student/order_receipt.php?id={$order_id}' class='cta-button'>View Order Slip</a></p>";
                        break;
                    case 'Completed':
                        $message = "Your Order #{$order_id} has been completed.";
                        $email_subject = "Your Order #[{$order_id}] is Completed";
                        $email_header = "Order Completed";
                        $body_student = "<p>Hi " . htmlspecialchars($user_name) . ",</p>"
                                    . "<p>Your order <strong>#{$order_id}</strong> has been marked as completed. Thank you for shopping with us!</p>";
                        break;
                    case 'Rejected':
                        $message = "Your Order #{$order_id} has been rejected.";
                        $email_subject = "Your Order #[{$order_id}] has been Rejected";
                        $email_header = "Order Rejected";
                        $body_student = "<p>Hi " . htmlspecialchars($user_name) . ",</p>"
                                    . "<p>Unfortunately, your order <strong>#{$order_id}</strong> has been rejected. This may be due to a stock issue or other reason.</p>"
                                    . "<p>The items in this order have been restocked. Please contact the administrator for more details if needed.</p>";
                        break;
                }
                
                if (!empty($message)) {
                    // This sends the system notification ONLY to the STUDENT
                    $this->createNotification($user_id, $message, $link);
                }

                // Send the email ONLY to the STUDENT
                if (!empty($email_subject) && !empty($user_email)) {
                    try {
                        $mailer = new EmailSender();
                        $mailer->sendEmail($user_email, $user_name, $email_subject, $email_header, $body_student);
                    } catch (Exception $e) {
                        error_log("Failed to send status update email for order $order_id: " . $e->getMessage());
                    }
                }
            }

            return $stmt->rowCount() > 0;
        }

        public function getOrderSummaryCounts() {
            $sql = "
                SELECT status, COUNT(*) as count 
                FROM Orders 
                GROUP BY status
            ";
            $results = $this->select($sql);
            $counts = [];
            foreach($results as $row) {
                $counts[$row['status']] = $row['count'];
            }
            return $counts;
        }

        public function getLowStockItems($threshold = 5) {
            $sql = "
                SELECT 
                    g.item_name, 
                    s.size, 
                    s.current_stock
                FROM Stocks s
                JOIN Garment g ON s.garment_id = g.garment_id
                WHERE s.current_stock <= :threshold AND g.is_deleted = 0
                ORDER BY s.current_stock ASC
            ";
            return $this->select($sql, [':threshold' => $threshold]);
        }

        // *** UPDATED: Pagination for Order Items Report ***
        public function getOrderItemsReport($filters = [], $page = 1, $limit = 10) {
            $offset = ($page - 1) * $limit;
            $params = [];
            $conditions = [];
            
            if (!empty($filters['start_date'])) {
                $conditions[] = "o.order_date >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $conditions[] = "o.order_date <= :end_date";
                 $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            if (!empty($filters['status'])) {
                $conditions[] = "o.status = :status";
                $params[':status'] = $filters['status'];
            }
            if (!empty($filters['garment_id'])) {
                $conditions[] = "g.garment_id = :garment_id";
                $params[':garment_id'] = $filters['garment_id'];
            }

            $where_sql = "";
            if (!empty($conditions)) {
                $where_sql = " WHERE " . implode(" AND ", $conditions);
            }

            // 1. Total Count
            $count_sql = "
                SELECT COUNT(*) as total
                FROM Order_Items oi
                JOIN Orders o ON oi.order_id = o.order_id
                JOIN users u ON o.user_id = u.user_id
                JOIN Stocks s ON oi.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                $where_sql
            ";
            $count_res = $this->select($count_sql, $params);
            $total_records = $count_res[0]['total'] ?? 0;

            // 2. Data
            $sql = "
                SELECT 
                    oi.order_id, 
                    o.order_date, 
                    o.status, 
                    u.full_name, 
                    g.item_name, 
                    oi.size, 
                    oi.quantity, 
                    oi.unit_price, 
                    oi.subtotal
                FROM Order_Items oi
                JOIN Orders o ON oi.order_id = o.order_id
                JOIN users u ON o.user_id = u.user_id
                JOIN Stocks s ON oi.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                $where_sql
                ORDER BY o.order_date DESC, oi.order_id DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $data = $this->select($sql, $params);
            
            return [
                'data' => $data,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit)
            ];
        }

        // *** UPDATED: Pagination for Stock Movement Log ***
        public function getStockMovementLog($filters = [], $page = 1, $limit = 10) {
            $offset = ($page - 1) * $limit;
            $params = [];
            $conditions = [];

             if (!empty($filters['start_date'])) {
                $conditions[] = "log.timestamp >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $conditions[] = "log.timestamp <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            if (!empty($filters['movement_type'])) {
                $conditions[] = "log.movement_type = :movement_type";
                $params[':movement_type'] = $filters['movement_type'];
            }
            if (!empty($filters['garment_id'])) {
                $conditions[] = "g.garment_id = :garment_id";
                $params[':garment_id'] = $filters['garment_id'];
            }
            
            $where_sql = "";
            if (!empty($conditions)) {
                $where_sql = " WHERE " . implode(" AND ", $conditions);
            }

            // 1. Total Count
            $count_sql = "
                SELECT COUNT(*) as total
                FROM Stock_Movement_Log log
                JOIN Stocks s ON log.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                $where_sql
            ";
            $count_res = $this->select($count_sql, $params);
            $total_records = $count_res[0]['total'] ?? 0;

            // 2. Data
             $sql = "
                SELECT 
                    log.timestamp,
                    g.item_name,
                    s.size,
                    log.movement_type,
                    log.change_quantity,
                    log.new_stock_level,
                    log.order_id,
                    u.username as admin_username, -- Changed from full_name for clarity
                    log.notes
                FROM Stock_Movement_Log log
                JOIN Stocks s ON log.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                LEFT JOIN users u ON log.user_id = u.user_id -- Left join for system actions (sale, etc)
                $where_sql
                ORDER BY log.timestamp DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $data = $this->select($sql, $params);
            
            return [
                'data' => $data,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit)
            ];
        }

      
         public function getPopularItems($limit = 5, $filters = []) {
            $sql = "
                SELECT 
                    g.item_name, 
                    SUM(oi.quantity) as total_quantity_sold
                FROM Order_Items oi
                JOIN Orders o ON oi.order_id = o.order_id
                JOIN Stocks s ON oi.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                WHERE o.status = 'Completed' -- Only count completed orders
            ";
            
            $params = [];
            $conditions = []; 

            if (!empty($filters['start_date'])) {
                $date_column = 'o.order_date'; 
                 try {
                    $check_col = $this->select("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Orders' AND COLUMN_NAME = 'date_fulfilled'");
                    if ($check_col) {
                         $date_column = 'o.date_fulfilled';
                    }
                } catch (Exception $e) {  }
                
                $conditions[] = "$date_column >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $date_column = 'o.order_date';
                 try {
                    $check_col = $this->select("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Orders' AND COLUMN_NAME = 'date_fulfilled'");
                    if ($check_col) {
                         $date_column = 'o.date_fulfilled';
                    }
                } catch (Exception $e) {  }

                $conditions[] = "$date_column <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }

            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }

            $sql .= " 
                GROUP BY g.garment_id, g.item_name 
                ORDER BY total_quantity_sold DESC 
                LIMIT :limit
            ";
            
            $params[':limit'] = (int)$limit; 
            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                 if ($key === ':limit') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                 } else {
                    $stmt->bindValue($key, $value);
                 }
            }
            $stmt->execute();
            return $stmt->fetchAll();
        }
        
        public function getOrderItemDetails($order_id, $stock_id) {
            $sql = "
                SELECT 
                    oi.quantity, oi.size,
                    s.garment_id,
                    g.item_name
                FROM Order_Items oi
                JOIN Stocks s ON oi.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                WHERE oi.order_id = :order_id AND oi.stock_id = :stock_id
                LIMIT 1
            ";
            $result = $this->select($sql, [':order_id' => $order_id, ':stock_id' => $stock_id]);
            return $result ? $result[0] : null;
        }

        public function getAvailableSizesForGarmentAdmin($garment_id) {
            $sql = "SELECT stock_id, size, current_stock
                    FROM Stocks
                    WHERE garment_id = :garment_id
                    ORDER BY FIELD(size, 'Extra Small', 'Small', 'Medium', 'Large', 'Extra Large')";
            return $this->select($sql, [':garment_id' => $garment_id]);
        }

        
        public function exchangeOrderItemSize($order_id, $old_stock_id, $new_stock_id, $quantity) {
            
            $new_stock_sql = "
                SELECT s.size, s.current_stock, g.unit_price
                FROM Stocks s
                JOIN Garment g ON s.garment_id = g.garment_id
                WHERE s.stock_id = :new_stock_id
                LIMIT 1
            ";
            $new_stock_details = $this->select($new_stock_sql, [':new_stock_id' => $new_stock_id]);

            if (!$new_stock_details) {
                throw new Exception("The selected new size could not be found.");
            }
            $new_stock_details = $new_stock_details[0];

            if ($new_stock_details['current_stock'] < $quantity) {
                throw new Exception("Not enough stock for the selected new size (Available: {$new_stock_details['current_stock']}).");
            }
            
            $admin_user_id = $_SESSION['user']['user_id'] ?? null;

            try {
                $this->conn->beginTransaction();

                $old_stock_level_before = $this->getCurrentStockLevel($old_stock_id);
                $sql_restore = "UPDATE Stocks SET current_stock = current_stock + :qty WHERE stock_id = :old_stock_id";
                $this->execute($sql_restore, [':qty' => $quantity, ':old_stock_id' => $old_stock_id]);
                
                $this->addStockMovementLog(
                    $old_stock_id,
                    $quantity,
                    $old_stock_level_before + $quantity,
                    'exchange_restock',
                    $admin_user_id,
                    $order_id,
                    "Admin exchanged item (return)."
                );

                $new_stock_level_before = $new_stock_details['current_stock'];
                $sql_reduce = "UPDATE Stocks SET current_stock = current_stock - :qty WHERE stock_id = :new_stock_id";
                $this->execute($sql_reduce, [':qty' => $quantity, ':new_stock_id' => $new_stock_id]);

                $this->addStockMovementLog(
                    $new_stock_id,
                    -$quantity, 
                    $new_stock_level_before - $quantity,
                    'exchange_sale',
                    $admin_user_id,
                    $order_id,
                    "Admin exchanged item (issue)."
                );

                $new_size = $new_stock_details['size'];
                $new_price = $new_stock_details['unit_price'];
                $new_subtotal = $new_price * $quantity;

                $sql_update_item = "
                    UPDATE Order_Items
                    SET stock_id = :new_stock_id,
                        size = :new_size,
                        unit_price = :new_price,
                        subtotal = :new_subtotal
                    WHERE order_id = :order_id AND stock_id = :old_stock_id
                ";
                $this->execute($sql_update_item, [
                    ':new_stock_id' => $new_stock_id,
                    ':new_size' => $new_size,
                    ':new_price' => $new_price,
                    ':new_subtotal' => $new_subtotal,
                    ':order_id' => $order_id,
                    ':old_stock_id' => $old_stock_id
                ]);

                $sql_sum = "SELECT SUM(subtotal) as new_total FROM Order_Items WHERE order_id = :order_id";
                $sum_result = $this->select($sql_sum, [':order_id' => $order_id]);
                $new_order_total = $sum_result[0]['new_total'] ?? 0;

                $sql_update_order = "UPDATE Orders SET total_amount = :new_total WHERE order_id = :order_id";
                $this->execute($sql_update_order, [':new_total' => $new_order_total, ':order_id' => $order_id]);

                $this->conn->commit();
                return true;

            } catch (Exception $e) {
                if ($this->conn->inTransaction()) {
                    $this->conn->rollBack();
                }
                throw new Exception("Transaction failed: " . $e->getMessage());
            }
        }
        
        // *** UPDATED: Pagination for Users ***
        public function getAllUsers($search_query = "", $page = 1, $limit = 10) {
            $offset = ($page - 1) * $limit;
            $params = [];
            $conditions = ["role = 'student'"];

            if (!empty($search_query)) {
                $conditions[] = "(full_name LIKE :search1 OR student_id LIKE :search2 OR email_address LIKE :search3)";
                $params[':search1'] = '%' . $search_query . '%';
                $params[':search2'] = '%' . $search_query . '%';
                $params[':search3'] = '%' . $search_query . '%';
            }
            
            $where_sql = " WHERE " . implode(" AND ", $conditions);

            // 1. Total Count
            $count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
            $count_res = $this->select($count_sql, $params);
            $total_records = $count_res[0]['total'] ?? 0;

            // 2. Data
            $sql = "SELECT user_id, student_id, full_name, email_address, contact_number, is_active 
                    FROM users 
                    $where_sql
                    ORDER BY full_name ASC
                    LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
            
            $data = $this->select($sql, $params);

            return [
                'data' => $data,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit)
            ];
        }

        public function updateUserStatus($user_id, $new_status) {
            $is_active = (int)$new_status > 0 ? 1 : 0; 
            
            $sql = "UPDATE users SET is_active = :is_active WHERE user_id = :user_id AND role = 'student'";
            $stmt = $this->execute($sql, [':is_active' => $is_active, ':user_id' => $user_id]);
            
            return $stmt->rowCount() > 0;
        }

        public function adminResetUserPassword($user_id) {
            $user_sql = "SELECT email_address, full_name, first_name FROM users WHERE user_id = :user_id AND role = 'student' LIMIT 1";
            $user = $this->select($user_sql, [':user_id' => $user_id]);
            
            if (empty($user)) {
                return false;
            }
            $user_data = $user[0];

            $new_password = bin2hex(random_bytes(4)); 
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET password_hash = :new_hash WHERE user_id = :user_id AND role = 'student'";
            $stmt = $this->execute($sql, [':new_hash' => $new_hash, ':user_id' => $user_id]);

            if ($stmt->rowCount() > 0) {
                try {
                    $mailer = new EmailSender();
                    $subject = "Your Password Has Been Reset";
                    $header = "Password Reset by Admin";
                    $body = "<p>Hi " . htmlspecialchars($user_data['first_name']) . ",</p>"
                          . "<p>An administrator has reset your password for the WMSU Garment Ordering System.</p>"
                          . "<p><strong>Your new temporary password is:</strong> <span style='font-size: 1.2em; color: #007bff; font-weight: bold;'>" . $new_password . "</span></p>"
                          . "<p>Please log in using this password and change it immediately from your account settings.</p>"
                          . "<p class='button-wrapper'><a href='http://localhost/garment_ordering_system/login.php' class='cta-button'>Log In Now</a></p>";
                    
                    $mailer->sendEmail($user_data['email_address'], $user_data['full_name'], $subject, $header, $body);
                } catch (Exception $e) {
                    error_log("Failed to send admin reset password email: " . $e->getMessage());
                }

                return $new_password; 
            }
            return false;
        }

        public function getSalesOverTime($filters = []) {
            $sql = "
                SELECT 
                    DATE(order_date) as sale_date, 
                    SUM(total_amount) as daily_total
                FROM Orders
                WHERE status = 'Completed'
            ";
            
            $params = [];
            $conditions = []; 

            if (!empty($filters['start_date'])) {
                $conditions[] = "order_date >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $conditions[] = "order_date <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }

            $sql .= " GROUP BY DATE(order_date) ORDER BY sale_date ASC";
            
            $results = $this->select($sql, $params);
            
            $sales_data = [];
            foreach ($results as $row) {
                $sales_data[$row['sale_date']] = (float)$row['daily_total'];
            }
            return $sales_data;
        }
        
        public function getRevenueByCategory($filters = []) {
            $sql = "
                SELECT 
                    g.category, 
                    SUM(oi.subtotal) as category_total
                FROM Order_Items oi
                JOIN Orders o ON oi.order_id = o.order_id
                JOIN Stocks s ON oi.stock_id = s.stock_id
                JOIN Garment g ON s.garment_id = g.garment_id
                WHERE o.status = 'Completed'
            ";
            
            $params = [];
            $conditions = []; 

            if (!empty($filters['start_date'])) {
                $conditions[] = "o.order_date >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $conditions[] = "o.order_date <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }

            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }

            $sql .= " GROUP BY g.category ORDER BY category_total DESC";
            
            $results = $this->select($sql, $params);
            
            $category_data = [];
            foreach ($results as $row) {
                $category_data[$row['category']] = (float)$row['category_total'];
            }
            return $category_data;
        }

        public function getTopSpenders($limit = 5) {
            $sql = "
                SELECT 
                    u.student_id,
                    u.full_name,
                    SUM(o.total_amount) as total_spent
                FROM Orders o
                JOIN users u ON o.user_id = u.user_id
                WHERE o.status = 'Completed'
                AND u.role = 'student'
                GROUP BY u.user_id, u.student_id, u.full_name
                ORDER BY total_spent DESC
                LIMIT :limit
            ";
            
             $params[':limit'] = (int)$limit; 
             $stmt = $this->conn->prepare($sql);
             $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
             $stmt->execute();
             return $stmt->fetchAll();
        }
        
        public function getDashboardStats() {
            $stats = [
                'total_revenue' => 0,
                'monthly_revenue' => 0,
                'total_sales_today' => 0
            ];
            
            $date_column = 'o.order_date';
            try {
                $check_col = $this->select("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'Orders' AND COLUMN_NAME = 'date_fulfilled'");
                if ($check_col) {
                     $date_column = 'o.date_fulfilled';
                }
            } catch (Exception $e) { }
    
            $sql_total = "SELECT SUM(o.total_amount) as total FROM Orders o WHERE o.status = 'Completed'";
            $result_total = $this->select($sql_total);
            $stats['total_revenue'] = $result_total[0]['total'] ?? 0;
    
            $sql_month = "SELECT SUM(o.total_amount) as total FROM Orders o WHERE o.status = 'Completed' AND YEAR($date_column) = YEAR(NOW()) AND MONTH($date_column) = MONTH(NOW())";
            $result_month = $this->select($sql_month);
            $stats['monthly_revenue'] = $result_month[0]['total'] ?? 0;
            
            $sql_today = "SELECT SUM(o.total_amount) as total FROM Orders o WHERE o.status = 'Completed' AND DATE($date_column) = CURDATE()";
            $result_today = $this->select($sql_today);
            $stats['total_sales_today'] = $result_today[0]['total'] ?? 0;
    
            return $stats;
        }

        public function getNotifications($user_id, $limit = 10) {
            $sql = "SELECT *, 
                       CASE 
                           WHEN TIMESTAMPDIFF(MINUTE, created_at, NOW()) < 60 THEN CONCAT(TIMESTAMPDIFF(MINUTE, created_at, NOW()), 'm ago')
                           WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) < 24 THEN CONCAT(TIMESTAMPDIFF(HOUR, created_at, NOW()), 'h ago')
                           ELSE CONCAT(TIMESTAMPDIFF(DAY, created_at, NOW()), 'd ago')
                       END as time_ago
                FROM Notifications
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit";
        
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        public function getUnreadNotificationCount($user_id) {
            $sql = "SELECT COUNT(*) as unread_count FROM Notifications WHERE user_id = :user_id AND is_read = 0";
            $result = $this->select($sql, [':user_id' => $user_id]);
            return $result[0]['unread_count'] ?? 0;
        }

        public function markNotificationsAsRead($user_id) {
            $sql = "UPDATE Notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
            $stmt = $this->execute($sql, [':user_id' => $user_id]);
            return $stmt->rowCount() > 0;
        }

        // *** FIX: ADDED MISSING METHOD & PAGINATION ***
        public function autoCancelOldOrders($days = 5) {
            $cutoff_date = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $sql = "SELECT order_id FROM Orders 
                    WHERE status IN ('Pending', 'Ready for Pickup') 
                    AND order_date < :cutoff";
            
            $orders = $this->select($sql, [':cutoff' => $cutoff_date]);
            $count = 0;

            foreach ($orders as $order) {
                if ($this->updateOrderStatus($order['order_id'], 'Cancelled')) {
                    $count++;
                }
            }
            return $count;
        }

        public function getInventoryValuation() {
            $sql = "SELECT 
                        SUM(s.current_stock * g.unit_price) as total_value,
                        SUM(s.current_stock) as total_items
                    FROM Stocks s
                    JOIN Garment g ON s.garment_id = g.garment_id
                    WHERE g.is_deleted = 0";
            
            $result = $this->select($sql);
            return $result ? $result[0] : ['total_value' => 0, 'total_items' => 0];
        }

        public function getLostRevenueStats($filters = []) {
            $sql = "
                SELECT 
                    status, 
                    COUNT(*) as count, 
                    SUM(total_amount) as total_lost
                FROM Orders
                WHERE status IN ('Cancelled', 'Rejected')
            ";
            
            $params = [];
            $conditions = []; 

            if (!empty($filters['start_date'])) {
                $conditions[] = "order_date >= :start_date";
                $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
            }
            if (!empty($filters['end_date'])) {
                $conditions[] = "order_date <= :end_date";
                $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
            }
            
            if (!empty($conditions)) {
                $sql .= " AND " . implode(" AND ", $conditions);
            }

            $sql .= " GROUP BY status";
            
            return $this->select($sql, $params);
        }

        // --- UPDATED: REVIEW MANAGEMENT with Pagination ---
        public function getAllReviews($search_query = "", $page = 1, $limit = 10) {
            $offset = ($page - 1) * $limit;
            $params = [];
            $conditions = [];

            if (!empty($search_query)) {
                $conditions[] = "(u.full_name LIKE :search1 OR g.item_name LIKE :search2)";
                $params[':search1'] = '%' . $search_query . '%';
                $params[':search2'] = '%' . $search_query . '%';
            }

            $where_sql = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

            // 1. Total Count
            $count_sql = "
                SELECT COUNT(*) as total 
                FROM Garment_Reviews r
                JOIN Users u ON r.user_id = u.user_id
                JOIN Garment g ON r.garment_id = g.garment_id
                $where_sql
            ";
            $count_res = $this->select($count_sql, $params);
            $total_records = $count_res[0]['total'] ?? 0;

            // 2. Data
            $sql = "
                SELECT 
                    r.review_id, 
                    r.rating, 
                    r.review_text, 
                    r.created_at,
                    u.full_name,
                    g.item_name
                FROM Garment_Reviews r
                JOIN Users u ON r.user_id = u.user_id
                JOIN Garment g ON r.garment_id = g.garment_id
                $where_sql
                ORDER BY r.created_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $data = $this->select($sql, $params);

            return [
                'data' => $data,
                'total' => $total_records,
                'pages' => ceil($total_records / $limit)
            ];
        }

        public function deleteReview($review_id) {
            $sql = "DELETE FROM Garment_Reviews WHERE review_id = :review_id";
            $stmt = $this->execute($sql, [':review_id' => $review_id]);
            return $stmt->rowCount() > 0;
        }
    }
?>