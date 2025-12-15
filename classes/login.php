<?php
require_once "database.php";
require_once "EmailSender.php"; 

class Login extends Database {
    
    public $email = "";
    public $password = "";
    private $error = "";
    private $user_data = [];

    public function __construct() {
        parent::__construct();
    }

    public function login() {
        $sql = "SELECT user_id, student_id, username, email_address, password_hash, role, is_active, is_email_verified, full_name
                FROM users 
                WHERE email_address = :email OR student_id = :student_id
                LIMIT 1";
        
        $user = $this->select($sql, [
            ':email' => $this->email,
            ':student_id' => $this->email 
        ]);

        if ($user) {
            $this->user_data = $user[0];

            if (password_verify($this->password, $this->user_data['password_hash'])) {
                
                if ($this->user_data['is_active'] == 0) {
                    $this->error = "Your account has been deactivated. Please contact an administrator.";
                    return false;
                }

                if ($this->user_data['role'] == 'student' && $this->user_data['is_email_verified'] == 0) {
                    $this->error = "Your account is not activated. Please check your email (" . htmlspecialchars($this->user_data['email_address']) . ") for a verification link.";
                    return false;
                }

                try {
                    $this->sendLoginAlert();
                } catch (Exception $e) {
                    error_log("Failed to send login alert for user " . $this->user_data['user_id'] . ": " . $e->getMessage());
                }
                return true;
                
            } else {
                $this->error = "Invalid email/Student ID or password.";
                return false;
            }
        } else {
            $this->error = "Invalid email/Student ID or password.";
            return false;
        }
    }

    public function getError() {
        return $this->error;
    }

    public function getUser() {
        return [
            'user_id' => $this->user_data['user_id'],
            'student_id' => $this->user_data['student_id'],
            'username' => $this->user_data['username'],
            'role' => $this->user_data['role']
        ];
    }
    
  
    private function sendLoginAlert() {
        $user_email = $this->user_data['email_address'];
        $user_name = $this->user_data['full_name'] ?? $this->user_data['username'];
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device';
        $login_time = date('F j, Y, g:i a');

        $subject = "Security Alert: New Login to Your Account";
        $header_title = "New Login Alert";
        $body = "<p>Hi " . htmlspecialchars($user_name) . ",</p>"
              . "<p>Your WMSU Garment Ordering System account was just accessed from a new device.</p>"
              . "<p><strong>When:</strong> " . $login_time . "</p>"
              . "<p><strong>IP Address:</strong> " . $ip_address . "</p>"
              . "<p><strong>Device:</strong> " . $user_agent . "</p>"
              . "<br><p>If this was you, you can safely ignore this email.</p>"
              . "<p>If you do not recognize this activity, please change your password immediately and contact an administrator.</p>";

        $mailer = new EmailSender();
        $mailer->sendEmail($user_email, $user_name, $subject, $header_title, $body);
    }
}
?>