<?php
// includes/mail_helper.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

function sendEmail($to, $subject, $body, $altBody = '') {
    $smtpHost = getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? null);
    $smtpUser = getenv('SMTP_USER') ?: ($_ENV['SMTP_USER'] ?? null);
    $smtpPass = getenv('SMTP_PASS') ?: ($_ENV['SMTP_PASS'] ?? null);
    $smtpPort = getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? 587);

    if (!$smtpHost || !$smtpUser || !$smtpPass) {
        // Log error or handle missing SMTP config
        error_log("SMTP configuration missing.");
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $smtpPort;

        $mail->setFrom($smtpUser, 'NutriDeq Support');
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        if ($altBody) {
            $mail->AltBody = $altBody;
        }

        return $mail->send();
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>
