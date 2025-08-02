<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

class EmailNotification {
    public function __construct() {
        // Check required extensions
        if (!extension_loaded('openssl')) {
            error_log("OpenSSL extension is not installed");
        }
        
        // Verify SMTP configuration
        if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
            throw new Exception("SMTP configuration is incomplete");
        }
    }
    
    public function sendBookingReceipt($to_email, $receipt_html) {
        try {
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: $to_email");
            }
            
            // Create fresh mailer for this method too
            $mailer = new PHPMailer(true);
            
            $mailer->SMTPDebug = 0;
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Port = 465;
            $mailer->Timeout = 60;
            $mailer->SMTPKeepAlive = false;
            
            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'cafile' => '',
                    'capath' => '',
                    'ciphers' => 'DEFAULT:!DH'
                )
            );
            
            $mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mailer->addAddress($to_email);
            $mailer->isHTML(true);
            
            $mailer->Subject = 'Your Facility Reservation Receipt';
            $mailer->Body = $receipt_html;
            $mailer->AltBody = 'This is your facility reservation receipt. Please enable HTML to view the receipt properly.';
            
            if (!$mailer->send()) {
                throw new Exception($mailer->ErrorInfo);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed for $to_email. Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendBookingConfirmation($to_email, $booking) {
        try {
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: $to_email");
            }
            
            if (empty($booking['user_name']) || empty($booking['facility_name']) || 
                empty($booking['start_time']) || empty($booking['end_time'])) {
                throw new Exception("Missing required booking information");
            }
            
            // Create a fresh mailer instance for each send to avoid connection issues
            $mailer = new PHPMailer(true);
            
            // Configure SMTP settings
            $mailer->SMTPDebug = 0;
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Port = 465;
            $mailer->Timeout = 60;
            $mailer->SMTPKeepAlive = false;
            
            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'cafile' => '',
                    'capath' => '',
                    'ciphers' => 'DEFAULT:!DH'
                )
            );
            
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            
            $mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mailer->addAddress($to_email);
            $mailer->isHTML(true);
            
            $mailer->Subject = 'Booking Confirmation - School Facility Reservation System';
            
            require_once __DIR__ . '/Receipt.php';
            $receipt = new Receipt($GLOBALS['conn']);
            $receipt_html = $receipt->generateBookingReceipt($booking['id']);
            
            $mailer->addStringAttachment(
                $receipt_html,
                'booking_receipt.html',
                'base64',
                'text/html'
            );
            
            $html = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #0d6efd; color: white; padding: 20px; text-align: center;">
                    <h2>Booking Confirmation</h2>
                </div>
                <div style="padding: 20px; background-color: #f8f9fa;">
                    <p>Dear ' . htmlspecialchars($booking['user_name']) . ',</p>
                    <p>Your facility booking has been confirmed. Here are the details:</p>
                    <ul>
                        <li><strong>Facility:</strong> ' . htmlspecialchars($booking['facility_name']) . '</li>
                        <li><strong>Date:</strong> ' . date('F j, Y', strtotime($booking['start_time'])) . '</li>
                        <li><strong>Time:</strong> ' . date('h:i A', strtotime($booking['start_time'])) . ' - ' . date('h:i A', strtotime($booking['end_time'])) . '</li>
                        <li><strong>Purpose:</strong> ' . htmlspecialchars($booking['purpose']) . '</li>
                    </ul>
                    <p>Your booking receipt has been attached to this email as an HTML file.</p>
                </div>
                <div style="text-align: center; padding: 20px; font-size: 12px; color: #6c757d;">
                    <p>This is an automated message, please do not reply.</p>
                    <p>School Facility Reservation System</p>
                </div>
            </div>';
            
            $mailer->Body    = $html;
            $mailer->AltBody = 'Your facility reservation has been confirmed. Please check the attached receipt for details.';
            
            if (!$mailer->send()) {
                throw new Exception($mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed for $to_email. Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendBookingDateChangeNotification($to_email, $user_name, $facility_name, $old_start_time, $old_end_time, $new_start_time, $new_end_time, $reason) {
        try {
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: $to_email");
            }
            
            // Create a fresh mailer instance for each send to avoid connection issues
            $mailer = new PHPMailer(true);
            
            // Configure SMTP settings
            $mailer->SMTPDebug = 0;
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Port = 465;
            $mailer->Timeout = 60;
            $mailer->SMTPKeepAlive = false;
            
            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'cafile' => '',
                    'capath' => '',
                    'ciphers' => 'DEFAULT:!DH'
                )
            );
            
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            
            $mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mailer->addAddress($to_email);
            $mailer->isHTML(true);
            
            $mailer->Subject = 'Important: Your Booking Schedule Has Been Changed';
            
            $html = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <div style="background-color: #ff9800; color: white; padding: 20px; text-align: center;">
                    <h2>Booking Schedule Change</h2>
                </div>
                <div style="padding: 20px; background-color: #f8f9fa;">
                    <p>Dear ' . htmlspecialchars($user_name) . ',</p>
                    <p>This is to inform you that an administrator has changed the schedule of your booking for <strong>' . htmlspecialchars($facility_name) . '</strong>.</p>
                    
                    <div style="background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 15px 0;">
                        <h4 style="margin-top: 0;">Schedule Changes:</h4>
                        <p><strong>Original Schedule:</strong><br>
                        ' . date('F j, Y', strtotime($old_start_time)) . ' from ' . date('h:i A', strtotime($old_start_time)) . ' to ' . date('h:i A', strtotime($old_end_time)) . '</p>
                        
                        <p><strong>New Schedule:</strong><br>
                        ' . date('F j, Y', strtotime($new_start_time)) . ' from ' . date('h:i A', strtotime($new_start_time)) . ' to ' . date('h:i A', strtotime($new_end_time)) . '</p>
                    </div>
                    
                    <p><strong>Reason for the change:</strong> ' . htmlspecialchars($reason) . '</p>
                    
                    <p>If you have any questions regarding this change, please contact the facility administrator. Your understanding is appreciated.</p>
                </div>
                <div style="text-align: center; padding: 20px; font-size: 12px; color: #6c757d;">
                    <p>This is an automated message, please do not reply.</p>
                    <p>School Facility Reservation System</p>
                </div>
            </div>';
            
            $mailer->Body = $html;
            $mailer->AltBody = "Your booking schedule has been changed. Original schedule: " . 
                                    date('F j, Y', strtotime($old_start_time)) . " from " . date('h:i A', strtotime($old_start_time)) . " to " . date('h:i A', strtotime($old_end_time)) . 
                                    ". New schedule: " . 
                                    date('F j, Y', strtotime($new_start_time)) . " from " . date('h:i A', strtotime($new_start_time)) . " to " . date('h:i A', strtotime($new_end_time)) . 
                                    ". Reason: " . $reason;
            
            if (!$mailer->send()) {
                throw new Exception($mailer->ErrorInfo);
            }
            return true;
            
        } catch (Exception $e) {
            error_log("Email sending failed for $to_email. Error: " . $e->getMessage());
            return false;
        }
    }

    public function sendEmail($to_email, $subject, $body, $from_name = null) {
        try {
            if (!filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: $to_email");
            }
            
            // Create a fresh mailer instance for each send to avoid connection issues
            $mailer = new PHPMailer(true);
            
            // More comprehensive SMTP configuration
            $mailer->SMTPDebug = 0; // Disable debug in production
            $mailer->isSMTP();
            $mailer->Host = SMTP_HOST;
            $mailer->SMTPAuth = true;
            $mailer->Username = SMTP_USERNAME;
            $mailer->Password = SMTP_PASSWORD;
            // Try SSL instead of STARTTLS for better compatibility
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mailer->Port = 465; // Use SSL port instead of STARTTLS port
            $mailer->Timeout = 60; // Increase timeout for web context
            $mailer->SMTPKeepAlive = false;
            
            // More permissive SSL options for web context
            $mailer->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'cafile' => '',
                    'capath' => '',
                    'ciphers' => 'DEFAULT:!DH'
                )
            );
            
            // Set additional properties for reliability
            $mailer->CharSet = 'UTF-8';
            $mailer->Encoding = 'base64';
            
            $mailer->setFrom(SMTP_FROM_EMAIL, $from_name ?: SMTP_FROM_NAME);
            $mailer->addAddress($to_email);
            $mailer->isHTML(true);
            
            $mailer->Subject = $subject;
            
            // Convert plain text to HTML format for better display
            $html_body = nl2br(htmlspecialchars($body));
            $html_body = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">' . $html_body . '</div>';
            
            $mailer->Body = $html_body;
            $mailer->AltBody = strip_tags($body);
            
            if (!$mailer->send()) {
                throw new Exception($mailer->ErrorInfo);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Enhanced error logging for debugging
            $error_details = [
                'email' => $to_email,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => date('Y-m-d H:i:s'),
                'smtp_host' => SMTP_HOST,
                'smtp_port' => defined('SMTP_PORT') ? SMTP_PORT : 465
            ];
            error_log("EMAIL SEND FAILED: " . json_encode($error_details));
            return false;
        }
    }
}
?> 