<?php
// backend/test_smtp_connect.php
// Script kiểm tra kết nối SMTP thô đến Amazon SES và in log chi tiết của PHPMailer

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 1. Kéo cài đặt email từ DB
$settings = [];
$settingRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('email_provider', 'ses_host', 'ses_username', 'ses_password', 'ses_sender_email', 'ses_sender_name')");
if ($settingRes) {
    while ($row = $settingRes->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$provider = $settings['email_provider'] ?? '';
$host = $settings['ses_host'] ?? '';
$username = $settings['ses_username'] ?? '';
$password = $settings['ses_password'] ?? '';
$senderEmail = $settings['ses_sender_email'] ?? '';
$senderName = $settings['ses_sender_name'] ?? 'DOMATION TEAM';

// Cho phép truyền test_email qua URL (?to=email_cua_ban)
$toEmail = $_GET['to'] ?? $senderEmail;

$debugOutput = "";
$isSuccessful = false;

if (empty($host) || empty($username) || empty($password)) {
    $debugOutput = "LỖI: Chưa cấu hình đầy đủ Host, Username hoặc Password của Amazon SES trong hệ thống!";
} else {
    $mail = new PHPMailer(true);
    
    // Capture debug output
    $mail->SMTPDebug = SMTP::DEBUG_CONNECTION; // 4 = Connection and system info
    $mail->Debugoutput = function($str, $level) use (&$debugOutput) {
        $debugOutput .= "[Level $level] " . trim($str) . "\n";
    };

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Timeout = 15;

        $mail->setFrom($senderEmail, $senderName);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'SMTP Test Connection - ' . date('Y-m-d H:i:s');
        $mail->Body    = 'Đây là email kiểm tra kết nối SMTP từ hệ thống phân phối lead đến Amazon SES. Kết nối thành công!';

        $mail->send();
        $isSuccessful = true;
        $debugOutput .= "\n==> THÀNH CÔNG: Đã gửi email thử nghiệm thành công tới $toEmail!\n";
    } catch (Exception $e) {
        $isSuccessful = false;
        $debugOutput .= "\n==> THẤT BẠI: " . $e->getMessage() . "\n";
        $debugOutput .= "PHPMailer Error Info: " . $mail->ErrorInfo . "\n";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Connection Test - DOMATION DATA</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 24px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            margin-bottom: 24px;
        }
        h1 {
            font-size: 20px;
            margin-top: 0;
            margin-bottom: 8px;
        }
        .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
        }
        .status-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .status-failed {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        pre {
            background: #0f172a;
            color: #38bdf8;
            padding: 16px;
            border-radius: 8px;
            overflow-x: auto;
            font-family: monospace;
            font-size: 13px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 600px;
        }
        .setting-info {
            background: #f1f5f9;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 16px;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>SMTP Connection Test - DOMATION DATA</h1>
            <div class="subtitle">Kiểm tra kết nối thô đến máy chủ SMTP Amazon SES và xuất nhật ký giao thức</div>

            <div class="setting-info">
                <strong>SMTP Host:</strong> <?php echo htmlspecialchars($host); ?><br>
                <strong>SMTP Port:</strong> 587 (STARTTLS)<br>
                <strong>SMTP User:</strong> <?php echo htmlspecialchars($username); ?><br>
                <strong>Sender:</strong> <?php echo htmlspecialchars($senderEmail); ?><br>
                <strong>Test Receiver:</strong> <?php echo htmlspecialchars($toEmail); ?>
            </div>

            <div style="margin-bottom: 16px;">
                Trạng thái: 
                <?php if ($isSuccessful): ?>
                    <span class="status-badge status-success">KẾT NỐI THÀNH CÔNG</span>
                <?php else: ?>
                    <span class="status-badge status-failed">LỖI KẾT NỐI / XÁC THỰC</span>
                <?php endif; ?>
            </div>

            <div>
                <form method="GET" style="display:flex; gap: 8px; align-items:center;">
                    <span style="font-size:13px; font-weight:600;">Gửi thử tới email khác:</span>
                    <input type="email" name="to" value="<?php echo htmlspecialchars($toEmail); ?>" style="padding: 6px 10px; border:1px solid #cbd5e1; border-radius:6px; font-size:13px; width: 250px;" required>
                    <button type="submit" style="padding: 6px 12px; background:#4f46e5; color:white; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer;">Chạy lại Test</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h2 style="font-size: 16px; margin-top: 0; margin-bottom: 12px;">Nhật ký kết nối chi tiết (SMTP Protocol Log)</h2>
            <pre><?php echo htmlspecialchars($debugOutput); ?></pre>
        </div>
    </div>
</body>
</html>
