<?php
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/EmailSender.php";
if (file_exists(__DIR__ . "/../config.php")) {
    require_once __DIR__ . "/../config.php";
}
date_default_timezone_set('Asia/Manila'); 

class Student extends Database {

    public function __construct($existing_conn = null) {
        parent::__construct($existing_conn);
    }

    public function getStudentProfile($student_id) {
        $sql = "
            SELECT
                student_id,
                first_name,
                last_name,
                middle_name,
                email_address,
                contact_number,
                college
            FROM
                users
            WHERE
                student_id = :student_id
            LIMIT 1
        ";
        $result = $this->select($sql, [':student_id' => $student_id]);

        if ($result) {
            $profile = $result[0];
            $profile['full_name'] = trim($profile['first_name'] . ' ' . (!empty($profile['middle_name']) ? $profile['middle_name'] . ' ' : '') . $profile['last_name']);
            return $profile;
        }

        return null;
    }

    private function getCurrentStockLevel($stock_id) {
        $result = $this->select("SELECT current_stock FROM stocks WHERE stock_id = :stock_id LIMIT 1", [':stock_id' => $stock_id]);
        return $result ? (int)$result[0]['current_stock'] : 0;
    }

    public function getAvailableGarmentsWithStocks($search_query = "", $student_college = null) {
        $sql = "
            SELECT
                g.garment_id, g.item_name, g.category, g.unit_price, g.image_url,
                s.stock_id, s.size, s.current_stock,
                AVG(r.rating) as average_rating,
                COUNT(DISTINCT r.review_id) as review_count
            FROM garment g
            JOIN stocks s ON g.garment_id = s.garment_id
            LEFT JOIN garment_reviews r ON g.garment_id = r.garment_id
            WHERE s.current_stock > 0
            AND g.is_deleted = 0
        ";

        $params = [];

        if (!empty($student_college)) {
            $sql .= " AND (g.item_name NOT LIKE '%College of%' OR g.item_name LIKE :college_search)";
            $params[':college_search'] = '%' . $student_college . '%';
        }
    
        if (!empty($search_query)) {
            $sql .= " AND g.item_name LIKE :search";
            $params[':search'] = '%' . $search_query . '%';
        }

        $sql .= " 
            GROUP BY g.garment_id, s.stock_id
            ORDER BY g.item_name ASC, FIELD(s.size, 'Extra Small', 'Small', 'Medium', 'Large', 'Extra Large')
        "; 

        $results = $this->select($sql, $params);
        $garments = [];

        foreach ($results as $row) {
            $garment_id = $row['garment_id'];

            if (!isset($garments[$garment_id])) {
                $garments[$garment_id] = [
                    'garment_id' => $row['garment_id'],
                    'item_name' => $row['item_name'],
                    'category' => $row['category'],
                    'unit_price' => $row['unit_price'],
                    'image_url' => $row['image_url'],
                    'available_stocks' => [],
                    'average_rating' => $row['average_rating'],
                    'review_count' => $row['review_count']
                ];
            }

            $garments[$garment_id]['available_stocks'][] = [
                'stock_id' => $row['stock_id'],
                'size' => $row['size'],
                'current_stock' => $row['current_stock']
            ];
        }

        $garments = array_filter($garments, function($g) {
             return !empty($g['available_stocks']);
        });

        return array_values($garments);
    }

    public function getStockDetails($stock_id) {
        $sql = "
            SELECT
                s.stock_id, s.garment_id, s.size, s.current_stock,
                g.unit_price, g.item_name, g.image_url, g.is_deleted
            FROM
                stocks s
            JOIN
                garment g ON s.garment_id = g.garment_id
            WHERE
                s.stock_id = :stock_id
            AND g.is_deleted = 0
            LIMIT 1
        ";
        $result = $this->select($sql, [':stock_id' => $stock_id]);
        return $result ? $result[0] : null;
    }

    public function reduceStock($stock_id, $quantity, $expected_stock, $order_id) {
        try {
            $sql = "
                UPDATE stocks
                SET current_stock = current_stock - :qty
                WHERE stock_id = :id
                AND current_stock = :expected_stock
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':qty' => $quantity,
                ':id'  => $stock_id,
                ':expected_stock' => $expected_stock
            ]);

            $affected = $stmt->rowCount();

            if ($affected > 0) {
                 $new_stock_level = $expected_stock - $quantity;

                 $low_stock_threshold = 5;
                 if ($new_stock_level <= $low_stock_threshold) {
                     $details = $this->getStockDetails($stock_id);
                     if ($details) {
                         $notif_msg = "⚠️ Low Stock Alert: {$details['item_name']} ({$details['size']}) is down to {$new_stock_level} units.";
                         $notif_link = "adminpage.php?page=manageGarments&search=" . urlencode($details['item_name']);
                         
                         $this->createNotificationForAdmins($notif_msg, $notif_link);
                     }
                 }

                 $this->addStockMovementLog(
                    $stock_id,
                    -$quantity,
                    $new_stock_level,
                    'sale',
                    null,
                    $order_id, 
                    "Stock reduced due to order completion."
                  );
                  return true;
            } else {
                return false;
            }

        } catch (PDOException $e) {
            error_log("Reduce stock failed: " . $e->getMessage());
            return false;
        }
    }

    public function restoreStock($stock_id, $quantity) {
        try {
            $sql = "UPDATE stocks SET current_stock = current_stock + :qty WHERE stock_id = :id";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([':qty' => $quantity,':id'  => $stock_id]);
        } catch (PDOException $e) {
            error_log("Restore stock failed: " . $e->getMessage());
            return false;
        }
    }

    public function getStudentOrders($student_id, $search_query = "") {
        $currentDate = date('Y-m-d H:i:s'); 

        $sql = "
            SELECT
                o.order_id,
                o.order_date,
                o.total_amount,
                o.status,
                GROUP_CONCAT(DISTINCT g.item_name SEPARATOR ', ') AS item_names,
                GROUP_CONCAT(oi.size SEPARATOR ', ') AS item_sizes
            FROM
                orders o
            JOIN
                users u ON o.user_id = u.user_id
            LEFT JOIN
                order_items oi ON o.order_id = oi.order_id
            LEFT JOIN
                stocks s ON oi.stock_id = s.stock_id
            LEFT JOIN
                garment g ON s.garment_id = g.garment_id
            WHERE
                u.student_id = :student_id
                AND o.order_date <= :currentDate 
        ";

        $params = [
            ':student_id' => $student_id,
            ':currentDate' => $currentDate
        ];

        if (!empty($search_query)) {
             $sql .= " AND o.order_id IN (
                          SELECT oi_sub.order_id
                          FROM order_items oi_sub
                          JOIN stocks s_sub ON oi_sub.stock_id = s_sub.stock_id
                          JOIN garment g_sub ON s_sub.garment_id = g_sub.garment_id
                          WHERE g_sub.item_name LIKE :search
                      )";
            $params[':search'] = '%' . $search_query . '%';
        }

        $sql .= "
            GROUP BY
                o.order_id, o.order_date, o.total_amount, o.status 
            ORDER BY
                o.order_date DESC
        ";

        return $this->select($sql, $params);
    }

    public function getOrderDetails($order_id, $student_id) {
        $currentDate = date('Y-m-d H:i:s');

        $sql_order = "
            SELECT
                o.order_id, o.order_date, o.total_amount, o.status,
                u.student_id, u.first_name, u.last_name, u.middle_name, u.contact_number, u.college, u.email_address 
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            WHERE o.order_id = :order_id
              AND u.student_id = :student_id
              AND o.order_date <= :currentDate
            LIMIT 1
        ";
        $params_order = [
            ':order_id' => $order_id,
            ':student_id' => $student_id,
            ':currentDate' => $currentDate
        ];

        $order_result = $this->select($sql_order, $params_order);

        if (empty($order_result)) {
            return null;
        }

        $order_data = $order_result[0];
        $order_data['full_name'] = trim($order_data['first_name'] . ' ' . (!empty($order_data['middle_name']) ? $order_data['middle_name'] . ' ' : '') . $order_data['last_name']);

        $sql_items = "
            SELECT
                oi.size, oi.unit_price, oi.quantity, oi.subtotal,
                g.item_name
            FROM order_items oi
            JOIN stocks s ON oi.stock_id = s.stock_id
            JOIN garment g ON s.garment_id = g.garment_id
            WHERE oi.order_id = :order_id
        ";
        $params_items = [':order_id' => $order_id];

        $items_result = $this->select($sql_items, $params_items);

        $receipt_data = [
            'profile' => [
                'student_id' => $order_data['student_id'],
                'full_name' => $order_data['full_name'],
                'contact_number' => $order_data['contact_number'],
                'email_address' => $order_data['email_address'],
                'college' => $order_data['college']
            ],
            'items' => $items_result,
            'total' => $order_data['total_amount'],
            'order_id' => $order_data['order_id'],
            'date' => $order_data['order_date'],
            'status' => $order_data['status']
        ];

        return $receipt_data;
    }

    public function deleteOrder($order_id, $student_id) {
        $cancellable_status = 'Pending';
        $items_restored_log_details = []; 

        try {
            $this->conn->beginTransaction();
            
            $sql_check = "
                SELECT o.status, o.user_id, u.email_address, u.full_name 
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = :order_id
                AND u.student_id = :student_id
                LIMIT 1
            ";
            $order_info = $this->select($sql_check, [':order_id' => $order_id, ':student_id' => $student_id]);

            if (empty($order_info)) {
                $this->conn->rollBack(); return false;
            }
            if ($order_info[0]['status'] !== $cancellable_status) {
                $this->conn->rollBack(); return false;
            }
            
            $user_id = $order_info[0]['user_id'];
            $user_email = $order_info[0]['email_address'] ?? null;
            $user_name = $order_info[0]['full_name'] ?? 'Student';

            $sql_items = "SELECT stock_id, quantity FROM order_items WHERE order_id = :order_id";
            $items_to_restock = $this->select($sql_items, [':order_id' => $order_id]);

            foreach ($items_to_restock as $item) {
                $old_stock_level = $this->getCurrentStockLevel($item['stock_id']); 
                $stock_restored = $this->restoreStock($item['stock_id'], $item['quantity']);

                if (!$stock_restored) {
                    $this->conn->rollBack(); return false;
                }
                if ($old_stock_level !== null) {
                    $items_restored_log_details[] = [
                        'stock_id' => $item['stock_id'],
                        'quantity' => $item['quantity'],
                        'new_stock_level' => $old_stock_level + $item['quantity']
                    ];
                }
            }

            $sql_update_order = "UPDATE orders SET status = 'Cancelled' WHERE order_id = :order_id AND status = :cancellable_status";
            $stmt = $this->conn->prepare($sql_update_order);
            $success = $stmt->execute([':order_id' => $order_id, ':cancellable_status' => $cancellable_status]);

            if (!$success || $stmt->rowCount() == 0) {
                 $this->conn->rollBack(); return false;
            }

             foreach ($items_restored_log_details as $restored) {
                 $this->addStockMovementLog(
                    $restored['stock_id'],
                    $restored['quantity'],
                    $restored['new_stock_level'],
                    'cancellation_restock', 
                    null, 
                    $order_id,
                    "Stock restored due to student cancelling order."
                  );
             }
             
             $this->createNotification(
                 $user_id, 
                 "Your Order #{$order_id} has been successfully cancelled.",
                 "order_receipt.php?id={$order_id}"
             );
        
            $this->conn->commit();
            
            return true;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            return false;
        }
    }

    public function getCart($user_id) {
        $sql = "
            SELECT
                ci.cart_item_id, ci.quantity,
                s.stock_id, s.size, s.current_stock as current_stock_level, 
                g.garment_id, g.item_name, g.unit_price, g.image_url
            FROM cart_items ci
            JOIN stocks s ON ci.stock_id = s.stock_id
            JOIN garment g ON s.garment_id = g.garment_id
            WHERE ci.user_id = :user_id
            AND s.current_stock > 0
            AND g.is_deleted = 0
        ";
        return $this->select($sql, [':user_id' => $user_id]);
    }

    public function getCartCount($user_id) {
        $sql = "SELECT COUNT(ci.cart_item_id) as count 
                FROM cart_items ci
                JOIN stocks s ON ci.stock_id = s.stock_id
                WHERE ci.user_id = :user_id AND s.current_stock > 0";
        $result = $this->select($sql, [':user_id' => $user_id]);
        return $result[0]['count'] ?? 0;
    }

    public function addToCart($user_id, $stock_id, $quantity) {
        $stock_details = $this->getStockDetails($stock_id);
        if (!$stock_details) {
            return ['success' => false, 'message' => 'Error: Selected item/size not found or no longer available.'];
        }
        $available_stock = $stock_details['current_stock'];

        $sql_check = "SELECT cart_item_id, quantity FROM cart_items WHERE user_id = :user_id AND stock_id = :stock_id";
        $existing = $this->select($sql_check, [':user_id' => $user_id, ':stock_id' => $stock_id]);

        if ($existing) {
            $new_quantity = $existing[0]['quantity'] + $quantity;

            if ($new_quantity > $available_stock) {
                return [
                    'success' => false, 
                    'error_stock_id' => $stock_id, 
                    'message' => "Error: Adding {$quantity} would exceed stock of {$available_stock}."
                ];
            }

            $sql_update = "UPDATE cart_items SET quantity = :quantity WHERE cart_item_id = :cart_item_id";
            $this->execute($sql_update, [':quantity' => $new_quantity, ':cart_item_id' => $existing[0]['cart_item_id']]);
            return ['success' => true, 'message' => "Cart updated." ];

        } else {
            if ($quantity > $available_stock) {
                 return [
                    'success' => false, 
                    'error_stock_id' => $stock_id, 
                    'message' => "Error: The quantity {$quantity} exceeds available stock."
                ];
            }

            $sql_insert = "INSERT INTO cart_items (user_id, stock_id, quantity) VALUES (:user_id, :stock_id, :quantity)";
            $this->execute($sql_insert, [
                ':user_id' => $user_id,
                ':stock_id' => $stock_id,
                ':quantity' => $quantity
            ]);
            return ['success' => true, 'message' => "Item added to cart."];
        }
    }

    public function updateCartQuantity($user_id, $stock_id, $new_quantity) {
        if ($new_quantity <= 0) {
            $this->removeFromCart($user_id, $stock_id);
            return ['success' => true, 'message' => 'Item removed from cart.'];
        }

        $stock_details = $this->getStockDetails($stock_id);
        if (!$stock_details) {
            $this->removeFromCart($user_id, $stock_id);
            return [
                'success' => false, 
                'error_stock_id' => $stock_id, 
                'message' => 'Item removed (unavailable).'
            ];
        }

        $available_stock = $stock_details['current_stock'];

        if ($new_quantity > $available_stock) {
            return [
                'success' => false, 
                'error_stock_id' => $stock_id, 
                'message' => "Max stock is {$available_stock}."
            ];
        }
        $sql = "UPDATE cart_items SET quantity = :quantity WHERE user_id = :user_id AND stock_id = :stock_id";
        $stmt = $this->execute($sql, [
            ':quantity' => $new_quantity,
            ':user_id' => $user_id,
            ':stock_id' => $stock_id
        ]);
        
        return ['success' => true, 'message' => "Quantity updated successfully."];
    }

    public function removeFromCart($user_id, $stock_id) {
        $sql = "DELETE FROM cart_items WHERE user_id = :user_id AND stock_id = :stock_id";
        return $this->execute($sql, [':user_id' => $user_id, ':stock_id' => $stock_id]);
    }

    public function clearCart($user_id) {
        $sql = "DELETE FROM cart_items WHERE user_id = :user_id";
        return $this->execute($sql, [':user_id' => $user_id]);
    }

    private function getPasswordHash($user_id) {
        $sql = "SELECT password_hash FROM users WHERE user_id = :user_id LIMIT 1";
        $result = $this->select($sql, [':user_id' => $user_id]);
        return $result ? $result[0]['password_hash'] : null;
    }

    public function updateProfile($student_id, $full_name_input, $contact_number, $college) {
        $name_parts = explode(' ', trim($full_name_input));
        $first_name = $name_parts[0] ?? '';
        $last_name = !empty($name_parts) ? array_pop($name_parts) : '';
        $middle_name = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';
        
        $sql = "UPDATE users
                SET first_name = :first_name,
                    last_name = :last_name,
                    middle_name = :middle_name,
                    contact_number = :contact_number,
                    college = :college
                WHERE student_id = :student_id";

        $params = [
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':middle_name' => $middle_name, 
            ':contact_number' => $contact_number,
            ':college' => $college,
            ':student_id' => $student_id
        ];

        try {
             $stmt = $this->execute($sql, $params);
             return true; 
        } catch (Exception $e) {
             error_log("updateProfile Error: " . $e->getMessage());
             return false;
        }
    }

    public function verifyCurrentPassword($user_id, $password) {
        $current_hash = $this->getPasswordHash($user_id);
        if (!$current_hash || !password_verify($password, $current_hash)) {
            return false;
        }
        return true;
    }

    public function requestPasswordChangeOTP($user_id) {
        $sql_user = "SELECT email_address, first_name FROM users WHERE user_id = :user_id LIMIT 1";
        $user = $this->select($sql_user, [':user_id' => $user_id]);
        
        if (empty($user)) return false;
        
        $email = $user[0]['email_address'];
        $first_name = $user[0]['first_name'];

        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $sql_update = "UPDATE users SET reset_token = :otp, reset_token_expiry = :expiry WHERE user_id = :user_id";
        $this->execute($sql_update, [
            ':otp' => $otp_code,
            ':expiry' => $expiry,
            ':user_id' => $user_id
        ]);

        try {
            $mailer = new EmailSender();
            $subject = "Verify Password Change";
            $header = "Verification Code";
            $body = "<p>Hi " . htmlspecialchars($first_name) . ",</p>"
                  . "<p>You have requested to change your password. Please use the following code to verify this action:</p>"
                  . "<div style='text-align:center; margin: 20px 0;'>"
                  . "<span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #007bff; background: #f8f9fa; padding: 10px 20px; border-radius: 5px; border: 1px solid #dee2e6;'>" . $otp_code . "</span>"
                  . "</div>"
                  . "<p>This code expires in 10 minutes.</p>"
                  . "<p>If you did not request this, please change your password immediately.</p>";
            
            return $mailer->sendEmail($email, $first_name, $subject, $header, $body);
        } catch (Exception $e) {
            error_log("Failed to send OTP email: " . $e->getMessage());
            return false;
        }
    }

    public function verifyOTPAndChangePassword($user_id, $code, $new_password) {
        $current_time_php = date('Y-m-d H:i:s');

        $sql = "SELECT user_id FROM users WHERE user_id = :user_id AND reset_token = :otp AND reset_token_expiry > :current_time LIMIT 1";
        $result = $this->select($sql, [
            ':user_id' => $user_id, 
            ':otp' => $code,
            ':current_time' => $current_time_php
        ]);

        if (empty($result)) {
            return false; 
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql_update = "UPDATE users SET password_hash = :hash, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :user_id";
        $this->execute($sql_update, [
            ':hash' => $new_hash,
            ':user_id' => $user_id
        ]);

        $this->createNotification($user_id, "Security Alert: Your password was successfully changed.");
        
        return true;
    }

    public function changePassword($user_id, $old_password, $new_password) {
        if ($this->verifyCurrentPassword($user_id, $old_password)) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET password_hash = :new_hash WHERE user_id = :user_id";
            $stmt = $this->execute($sql, [':new_hash' => $new_hash, ':user_id' => $user_id]);
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    public function getAvailableSizesForGarment($garment_id) {
        $sql = "SELECT stock_id, size, current_stock
                FROM stocks
                WHERE garment_id = :garment_id
                AND current_stock > 0
                ORDER BY FIELD(size, 'Extra Small', 'Small', 'Medium', 'Large', 'Extra Large')"; 
        return $this->select($sql, [':garment_id' => $garment_id]);
    }

    public function getGarmentDetails($garment_id) {
        $sql_garment = "
            SELECT garment_id, item_name, category, unit_price, image_url 
            FROM garment 
            WHERE garment_id = :garment_id AND is_deleted = 0 
            LIMIT 1";
        $garment = $this->select($sql_garment, [':garment_id' => $garment_id]);

        if (empty($garment)) {
            return null;
        }
        
        $sql_stocks = "
            SELECT stock_id, size, current_stock
            FROM stocks
            WHERE garment_id = :garment_id AND current_stock > 0
            ORDER BY FIELD(size, 'Extra Small', 'Small', 'Medium', 'Large', 'Extra Large')";
        $stocks = $this->select($sql_stocks, [':garment_id' => $garment_id]);
        
        $garment[0]['available_stocks'] = $stocks;
        return $garment[0];
    }

    public function getReviewsForGarment($garment_id) {
        $sql = "
            SELECT 
                r.rating, r.review_text, r.created_at,
                u.full_name
            FROM garment_reviews r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.garment_id = :garment_id
            ORDER BY r.created_at DESC
        ";
        $reviews = $this->select($sql, [':garment_id' => $garment_id]);
        
        $sql_avg = "SELECT AVG(rating) as average, COUNT(review_id) as count 
                    FROM garment_reviews 
                    WHERE garment_id = :garment_id";
        $stats = $this->select($sql_avg, [':garment_id' => $garment_id]);

        return [
            'reviews' => $reviews,
            'average' => $stats[0]['average'] ?? 0,
            'count' => $stats[0]['count'] ?? 0
        ];
    }

    public function checkIfStudentCanReview($user_id, $garment_id, $order_id = null) {
        $params = [
            ':user_id' => $user_id,
            ':garment_id' => $garment_id
        ];

        // 1. Find ALL eligible completed orders for this item
        $sql_find_orders = "
            SELECT o.order_id
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN stocks s ON oi.stock_id = s.stock_id
            WHERE o.user_id = :user_id
            AND s.garment_id = :garment_id
            AND o.status = 'Completed'
        ";
        
        if ($order_id) {
            $sql_find_orders .= " AND o.order_id = :order_id";
            $params[':order_id'] = $order_id;
        }
        
        $sql_find_orders .= " ORDER BY o.order_date DESC";
        
        $completed_orders = $this->select($sql_find_orders, $params);

        if (empty($completed_orders)) {
            return ['status' => false, 'message' => 'You must purchase this item in a completed order to review it.'];
        }

        // 2. Iterate through orders to find one that IS NOT reviewed yet
        foreach ($completed_orders as $order) {
            $current_oid = $order['order_id'];
            
            $sql_check_review = "SELECT review_id FROM garment_reviews WHERE user_id = :user_id AND garment_id = :garment_id AND order_id = :order_id LIMIT 1";
            $has_review = $this->select($sql_check_review, [
                ':user_id' => $user_id,
                ':garment_id' => $garment_id,
                ':order_id' => $current_oid
            ]);

            if (empty($has_review)) {
                return ['status' => true, 'order_id' => $current_oid];
            }
        }

        // 3. If loop finishes, all eligible orders have been reviewed
        return ['status' => false, 'message' => 'You have already reviewed this item for all your completed orders.'];
    }
  
    public function submitReview($garment_id, $user_id, $order_id, $rating, $review_text) {
        $sql = "
            INSERT INTO garment_reviews (garment_id, user_id, order_id, rating, review_text)
            VALUES (:garment_id, :user_id, :order_id, :rating, :review_text)
        ";
        
        $params = [
            ':garment_id' => $garment_id,
            ':user_id' => $user_id,
            ':order_id' => $order_id,
            ':rating' => $rating,
            ':review_text' => empty($review_text) ? null : $review_text
        ];
        
        try {
            $stmt = $this->execute($sql, $params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function getNotifications($user_id, $limit = 10) {
        // 1. Select raw data (removed the SQL CASE calculation)
        $sql = "SELECT * FROM notifications 
                WHERE user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Calculate 'Time Ago' using PHP (Manila Time)
        foreach ($notifications as &$notif) {
            $time_db = strtotime($notif['created_at']);
            $time_now = time(); 
            $diff = $time_now - $time_db;

            if ($diff < 10) {
                $notif['time_ago'] = 'Just now';
            } elseif ($diff < 60) {
                $notif['time_ago'] = $diff . 's ago';
            } elseif ($diff < 3600) {
                $notif['time_ago'] = floor($diff / 60) . 'm ago';
            } elseif ($diff < 86400) {
                $notif['time_ago'] = floor($diff / 3600) . 'h ago';
            } else {
                $notif['time_ago'] = floor($diff / 86400) . 'd ago';
            }
        }

        return $notifications;
    }

    public function getUnreadNotificationCount($user_id) {
        $sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $result = $this->select($sql, [':user_id' => $user_id]);
        return $result[0]['unread_count'] ?? 0;
    }

    public function markNotificationsAsRead($user_id) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->execute($sql, [':user_id' => $user_id]);
        return $stmt->rowCount() > 0;
    }

    public function getUnreviewedCompletedItems($user_id) {
        $sql = "
            SELECT
                g.garment_id, g.item_name, g.image_url, o.order_id
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN stocks s ON oi.stock_id = s.stock_id
            JOIN garment g ON s.garment_id = g.garment_id
            LEFT JOIN garment_reviews r ON (g.garment_id = r.garment_id AND r.order_id = o.order_id AND r.user_id = :user_id_join)
            WHERE o.user_id = :user_id_where
            AND o.status = 'Completed'
            AND r.review_id IS NULL
            GROUP BY g.garment_id, o.order_id
            ORDER BY o.order_date DESC
            LIMIT 3
        ";
        return $this->select($sql, [
            ':user_id_join' => $user_id,
            ':user_id_where' => $user_id
        ]);
    }
} 
?>