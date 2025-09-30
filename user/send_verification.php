<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../vendor/autoload.php';

// Load environment variables from config file
$config = require_once '../config.php';

function sendVerificationEmail($email, $verificationCode) {
    // Temporarily disable email verification sending
    return true; // Simulate successful sending without actually sending

    global $config;
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;  // Enable verbose debug output if needed
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['GMAIL_USERNAME'];  // Load from config
        $mail->Password = $config['GMAIL_APP_PASSWORD'];  // Load from config
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom($config['GMAIL_USERNAME'], $config['MAIL_FROM_NAME']);
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - EduVault';
        $mail->Body = getEmailTemplate($verificationCode);
        $mail->AltBody = "Your EduVault verification code is: $verificationCode\nThis code will expire in 15 minutes.";

        $result = $mail->send();
        logVerificationAttempt($email, $result);
        return $result;
    } catch (Exception $e) {
        error_log("Email sending failed for $email: {$mail->ErrorInfo}");
        logVerificationAttempt($email, false, $mail->ErrorInfo);
        return false;
    }
}

function generateVerificationCode() {
    try {
        return bin2hex(random_bytes(3)); // 6 characters hexadecimal
    } catch (Exception $e) {
        // Fallback if random_bytes fails
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

function getEmailTemplate($verificationCode) {
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .container {
                font-family: Arial, sans-serif;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .code {
                background: #f0f0f0;
                padding: 15px;
                text-align: center;
                font-size: 24px;
                letter-spacing: 5px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
            }
            .footer {
                font-size: 12px;
                color: #666;
                text-align: center;
                margin-top: 30px;
                padding-top: 15px;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Email Verification</h2>
            </div>
            
            <p>Thank you for registering with EduVault. Please use the verification code below to verify your email:</p>
            
            <div class="code">{$verificationCode}</div>
            
            <p><strong>This code will expire in 15 minutes.</strong></p>
            
            <p>If you did not register for an EduVault account, please ignore this email.</p>
            
            <div class="footer">
                <p>This is an automated message, please do not reply.</p>
                <p>&copy; 2025 EduVault - Partido State University</p>
            </div>
        </div>
    </body>
    </html>
    HTML;
}

function logVerificationAttempt($email, $success, $error = null) {
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "$timestamp | $email | $status" . ($error ? " | Error: $error" : "") . "\n";
    
    $logFile = __DIR__ . '/../logs/verification.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>
