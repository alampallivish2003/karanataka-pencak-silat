<?php
// email_config.php

// Load Composer autoloader (this loads PHPMailer automatically)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    die("Error: Composer autoloader not found. Run 'composer require phpmailer/phpmailer' in project root.");
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body, $fromName = 'KSPSA Admin') {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'alampallivishnu@gmail.com';      // Your Gmail
        $mail->Password   =  'hour tkmf impx fchu'; // ← Put your REAL App Password here
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Optional debug during testing
        // $mail->SMTPDebug = 2;  // Uncomment to see SMTP conversation
        // $mail->Debugoutput = 'html';

        // Sender & Recipient
        $mail->setFrom('alampallivishnu@gmail.com', $fromName);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
