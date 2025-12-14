<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailSender {
    
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';     
        $this->mail->SMTPAuth   = true;                   
        $this->mail->Username   = 'urfav.khem09@gmail.com'; 
        $this->mail->Password   = 'wpeikibfdliddayj';     
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $this->mail->Port       = 465;                 

        $this->mail->setFrom('urfav.khem09@gmail.com', 'WMSU Garment System');
    }

   
    private function loadTemplate($subject, $header_title, $body_content) {
        try {
            $template_path = __DIR__ . '/../templates/email_template.html';
            
            if (!file_exists($template_path)) {
                return $body_content;
            }
            
            $template = file_get_contents($template_path);
            
            $template = str_replace('{{subject}}', $subject, $template);
            $template = str_replace('{{header_title}}', $header_title, $template);
            $template = str_replace('{{body_content}}', $body_content, $template);
            $template = str_replace('{{year}}', date('Y'), $template);
            
            return $template;

        } catch (Exception $e) {
            error_log("Error loading email template: " . $e->getMessage());
            return $body_content;
        }
    }

 
    public function sendEmail($to_email, $to_name, $subject, $header_title, $body_content) {
        try {
            
            $this->mail->clearAddresses();
  

            $this->mail->addAddress($to_email, $to_name);

            $html_body = $this->loadTemplate($subject, $header_title, $body_content);

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $html_body;
            $this->mail->AltBody = strip_tags(str_replace("<br>", "\n", $body_content));

            $this->mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}