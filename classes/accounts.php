<?php
require_once __DIR__ . "/database.php";
require_once __DIR__ . "/EmailSender.php"; 

class Accounts extends Database {

    public function isStudentExist($student_id, $email) {
        // Ensure table name 'users' is lowercase
        $sql = "SELECT COUNT(*) AS total
                FROM users
                WHERE student_id = :student_id OR email_address = :email";

        $result = $this->select($sql, [
            ":student_id" => $student_id,
            ":email" => $email
        ]);

        if (!empty($result) && isset($result[0]["total"])) {
            return $result[0]["total"] > 0;
        }

        return false;
    }

    public function registerStudent($student_id, $firstname, $lastname, $contact_number, $email, $password, $college, $middlename = null) {
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $username = $student_id; 
        $email_full_name = trim($firstname . ' ' . (!empty($middlename) ? $middlename . ' ' : '') . $lastname);
        
        // Generate 6-Digit OTP
        $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Ensure table name 'users' is lowercase
        $sql = "INSERT INTO users
                    (student_id, username, first_name, last_name, middle_name, college, 
                     contact_number, email_address, password_hash, role,
                     is_email_verified, email_verification_token, token_expiry)
                VALUES
                    (:student_id, :username, :first_name, :last_name, :middle_name, :college, 
                     :contact_number, :email_address, :password_hash, 'student',
                     0, :token, :token_expiry)"; 

        $stmt = $this->execute($sql, [
            ":student_id" => $student_id,
            ":username" => $username,
            ":first_name" => $firstname,
            ":last_name" => $lastname,
            ":middle_name" => $middlename,
            ":college" => $college,
            ":contact_number" => $contact_number,
            ":email_address" => $email,
            ":password_hash" => $hashed_password,
            ":token" => $verification_code,
            ":token_expiry" => $token_expiry
        ]);

        if ($stmt && $stmt->rowCount() > 0) {
            try {
                $subject = "Verify Your Account - WMSU Garments";
                $header_title = "Welcome!";
                $body = "<p>Hi " . htmlspecialchars($firstname) . ",</p>"
                      . "<p>Thank you for registering. Please use the verification code below to activate your account:</p>"
                      . "<div style='text-align:center; margin: 20px 0;'>"
                      . "<span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #007bff; background: #f8f9fa; padding: 10px 20px; border-radius: 5px; border: 1px solid #dee2e6;'>" . $verification_code . "</span>"
                      . "</div>"
                      . "<p>This code expires in 1 hour.</p>";

                $mailer = new EmailSender();
                $mailer->sendEmail($email, $email_full_name, $subject, $header_title, $body);

            } catch (Exception $e) {
                error_log("Registration successful, but verification email failed to send to $email: " . $e->getMessage());
            }
            return true;
        }
        
        return false;
    }

    // Verify Signup OTP
    public function verifyAccountWithCode($email, $code) {
        $sql = "SELECT user_id, email_address 
                FROM users 
                WHERE email_address = :email 
                AND email_verification_token = :code 
                AND is_email_verified = 0
                AND token_expiry > NOW() 
                LIMIT 1";
        
        $user = $this->select($sql, [
            ':email' => $email, 
            ':code' => $code
        ]);

        if ($user) {
            $user_id = $user[0]['user_id'];
            
            // Activate User
            $sql_update = "UPDATE users 
                           SET is_email_verified = 1,
                               email_verification_token = NULL,
                               token_expiry = NULL,
                               is_active = 1 
                           WHERE user_id = :user_id";
            
            $this->execute($sql_update, [':user_id' => $user_id]);
            return true;
        }
        return false;
    }

    // --- Password Reset Methods ---
    public function requestPasswordReset($email) {
        $sql = "SELECT user_id, first_name, last_name FROM users WHERE email_address = :email LIMIT 1";
        $user = $this->select($sql, [':email' => $email]);

        if (empty($user)) { return false; }

        $user_data = $user[0];
        $full_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
        
        $otp_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $update_sql = "UPDATE users SET reset_token = :code, reset_token_expiry = :expiry WHERE user_id = :id";
        $this->execute($update_sql, [ ':code' => $otp_code, ':expiry' => $expiry, ':id' => $user_data['user_id'] ]);

        try {
            $subject = "Your Password Reset Code";
            $header = "Reset Verification Code";
            $body = "<p>Hi " . htmlspecialchars($user_data['first_name']) . ",</p><p>Your password reset code is:</p><h2 style='color:#007bff;text-align:center;'>".$otp_code."</h2>";
            $mailer = new EmailSender();
            return $mailer->sendEmail($email, $full_name, $subject, $header, $body);
        } catch (Exception $e) { return false; }
    }

    public function validateResetToken($token) {
        $sql = "SELECT user_id FROM users WHERE reset_token = :token AND reset_token_expiry > NOW() LIMIT 1";
        $result = $this->select($sql, [':token' => $token]);
        return !empty($result) ? $result[0]['user_id'] : false;
    }

    public function processPasswordReset($token, $new_password) {
        $user_id = $this->validateResetToken($token);
        if (!$user_id) return false;

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $sql = "UPDATE users SET password_hash = :hash, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :id";
        $stmt = $this->execute($sql, [':hash' => $new_hash, ':id' => $user_id]);

        if ($stmt->rowCount() > 0) {
            // Check if method exists in parent before calling, just to be safe
            if(method_exists($this, 'createNotification')) {
                 $this->createNotification($user_id, "Security Alert: Your password was successfully changed.");
            }
            return true;
        }
        return false;
    }

    public function resetPasswordWithCode($email, $code, $new_password) {
        $sql = "SELECT user_id, first_name FROM users WHERE email_address = :email AND reset_token = :code AND reset_token_expiry > NOW() LIMIT 1";
        $user = $this->select($sql, [':email' => $email, ':code' => $code]);
        if (empty($user)) return false;

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password_hash = :hash, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = :id";
        $stmt = $this->execute($update_sql, [':hash' => $new_hash, ':id' => $user[0]['user_id']]);
        return $stmt->rowCount() > 0;
    }
}
?>