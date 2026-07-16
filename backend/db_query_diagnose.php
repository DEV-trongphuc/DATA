<?php
require_once __DIR__ . '/db_connect.php';

// 1. Fetch system settings
$settingsKeys = ['email_provider', 'appscript_webhook_url', 'ses_host', 'ses_username', 'ses_sender_email', 'ses_sender_name'];
$settings = [];
$settingRes = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('" . implode("','", $settingsKeys) . "')");
if ($settingRes) {
    while ($row = $settingRes->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// 2. Fetch mail_queue stats
$stats = [];
$statsRes = $conn->query("SELECT status, COUNT(*) as count FROM mail_queue GROUP BY status");
if ($statsRes) {
    while ($row = $statsRes->fetch_assoc()) {
        $stats[$row['status']] = $row['count'];
    }
}

// 3. Fetch latest failed emails in mail_queue
$failedEmails = [];
$failedMailRes = $conn->query("SELECT id, to_email, cc_email, subject, attempts, last_error, created_at, sent_at FROM mail_queue WHERE status = 'failed' ORDER BY id DESC LIMIT 20");
if ($failedMailRes) {
    while ($row = $failedMailRes->fetch_assoc()) {
        $failedEmails[] = $row;
    }
}

// 4. Fetch latest failed emails in communication_logs
$failedCommLogs = [];
$failedCommRes = $conn->query("SELECT id, lead_id, recipient, error_message, sent_at FROM communication_logs WHERE type = 'email' AND status = 'failed' ORDER BY id DESC LIMIT 20");
if ($failedCommRes) {
    while ($row = $failedCommRes->fetch_assoc()) {
        $failedCommLogs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chẩn đoán lỗi gửi Mail - DOMATION DATA</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 24px;
            line-height: 1.5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #0f172a;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        @media (min-width: 768px) {
            .grid {
                grid-template-columns: 2fr 1fr;
            }
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 16px;
            color: #0f172a;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }

        .setting-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
            font-size: 14px;
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-label {
            font-weight: 600;
            color: var(--text-muted);
        }

        .setting-value {
            font-family: monospace;
            word-break: break-all;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stat-box {
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .stat-count {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .table-container {
            overflow-x: auto;
            margin-top: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        th {
            background-color: #f1f5f9;
            color: var(--text-muted);
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 2px solid var(--border);
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        tr:hover td {
            background-color: #f8fafc;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #ef4444;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #10b981;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #d97706;
        }

        .error-msg {
            color: var(--danger);
            font-family: monospace;
            background-color: #fef2f2;
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #fca5a5;
            white-space: pre-wrap;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>Chẩn đoán lỗi gửi Mail - DOMATION DATA</h1>
                <p style="margin: 4px 0 0; color: var(--text-muted); font-size: 14px;">Chương trình kiểm tra trạng thái cấu hình và lỗi gửi Amazon SES</p>
            </div>
            <div>
                <a href="cron_mailer.php?run=1" target="_blank" class="btn btn-primary" onclick="alert('Đang kích hoạt gửi mail xếp hàng ngầm. Vui lòng chờ 5-10 giây rồi tải lại trang này.')">Kích hoạt Cron Mailer</a>
            </div>
        </header>

        <div class="grid">
            <!-- Left: Settings & Stats -->
            <div class="card">
                <div class="card-title">Cấu hình Mail Hiện tại</div>
                <div class="setting-row">
                    <span class="setting-label">Email Provider (Nhà cung cấp):</span>
                    <span class="setting-value">
                        <span class="badge <?php echo ($settings['email_provider'] ?? '') === 'ses' ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo strtoupper($settings['email_provider'] ?? 'Chưa cấu hình'); ?>
                        </span>
                    </span>
                </div>
                <div class="setting-row">
                    <span class="setting-label">Amazon SES SMTP Host:</span>
                    <span class="setting-value"><?php echo htmlspecialchars($settings['ses_host'] ?? 'Trống'); ?></span>
                </div>
                <div class="setting-row">
                    <span class="setting-label">Amazon SES Username:</span>
                    <span class="setting-value"><?php echo htmlspecialchars($settings['ses_username'] ?? 'Trống'); ?></span>
                </div>
                <div class="setting-row">
                    <span class="setting-label">Sender Email (Email gửi):</span>
                    <span class="setting-value"><?php echo htmlspecialchars($settings['ses_sender_email'] ?? 'Trống'); ?></span>
                </div>
                <div class="setting-row">
                    <span class="setting-label">Sender Name (Tên người gửi):</span>
                    <span class="setting-value"><?php echo htmlspecialchars($settings['ses_sender_name'] ?? 'Trống'); ?></span>
                </div>
                <div class="setting-row">
                    <span class="setting-label">AppScript Webhook URL:</span>
                    <span class="setting-value" style="font-size:11px;"><?php echo htmlspecialchars($settings['appscript_webhook_url'] ?? 'Trống'); ?></span>
                </div>
            </div>

            <!-- Right: Stats Box -->
            <div class="card">
                <div class="card-title">Thống kê Hàng đợi (mail_queue)</div>
                <div class="stats-grid">
                    <div class="stat-box" style="border-left: 4px solid var(--warning);">
                        <div class="stat-count"><?php echo $stats['pending'] ?? 0; ?></div>
                        <div class="stat-label">Chờ gửi</div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid var(--primary);">
                        <div class="stat-count"><?php echo $stats['processing'] ?? 0; ?></div>
                        <div class="stat-label">Đang gửi</div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid var(--success);">
                        <div class="stat-count"><?php echo $stats['sent'] ?? 0; ?></div>
                        <div class="stat-label">Đã gửi</div>
                    </div>
                    <div class="stat-box" style="border-left: 4px solid var(--danger);">
                        <div class="stat-count"><?php echo $stats['failed'] ?? 0; ?></div>
                        <div class="stat-label">Thất bại</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Failed Emails in Queue -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-title" style="color: var(--danger);">Chi tiết 20 Email thất bại gần đây nhất (mail_queue)</div>
            <div class="table-container">
                <?php if (empty($failedEmails)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">Không tìm thấy email nào bị lỗi trong hàng đợi.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="width: 150px;">Người nhận</th>
                                <th style="width: 150px;">Tiêu đề</th>
                                <th style="width: 60px; text-align: center;">Số lần thử</th>
                                <th>Thông báo lỗi chi tiết từ SES/PHPMailer</th>
                                <th style="width: 130px;">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedEmails as $mail): ?>
                                <tr>
                                    <td><strong>#<?php echo $mail['id']; ?></strong></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($mail['to_email']); ?></div>
                                        <?php if (!empty($mail['cc_email'])): ?>
                                            <div style="font-size: 11px; color: var(--text-muted);">CC: <?php echo htmlspecialchars($mail['cc_email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($mail['subject']); ?></td>
                                    <td style="text-align: center;"><span class="badge badge-warning"><?php echo $mail['attempts']; ?></span></td>
                                    <td>
                                        <div class="error-msg"><?php echo htmlspecialchars($mail['last_error'] ?? 'Không có thông báo lỗi cụ thể.'); ?></div>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 11px;">
                                        Tạo: <?php echo $mail['created_at']; ?><br>
                                        Thử cuối: <?php echo $mail['sent_at'] ?? 'Chưa thử'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Latest Failed Emails in logs -->
        <div class="card">
            <div class="card-title" style="color: var(--danger);">Nhật ký lỗi lưu trữ (communication_logs)</div>
            <div class="table-container">
                <?php if (empty($failedCommLogs)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">Không tìm thấy nhật ký lỗi email nào.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th style="width: 80px;">Lead ID</th>
                                <th style="width: 180px;">Người nhận</th>
                                <th>Chi tiết lỗi</th>
                                <th style="width: 130px;">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failedCommLogs as $log): ?>
                                <tr>
                                    <td>#<?php echo $log['id']; ?></td>
                                    <td>
                                        <?php if ($log['lead_id']): ?>
                                            <span class="badge badge-success">Lead #<?php echo $log['lead_id']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($log['recipient']); ?></td>
                                    <td>
                                        <div class="error-msg"><?php echo htmlspecialchars($log['error_message'] ?? 'Không rõ lỗi.'); ?></div>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 11px;"><?php echo $log['sent_at']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
