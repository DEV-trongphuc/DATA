<?php
// backend/test_webhook_reg.php
require_once __DIR__ . '/db_connect.php';

$botToken = get_system_setting($conn, 'zalo_bot_token');
$secretToken = get_system_setting($conn, 'zalo_webhook_secret');
$webhookUrl = 'https://open.domation.net/sale_data/zalo_webhook.php';

echo "Bot Token: " . substr($botToken, 0, 15) . "...\n";
echo "Secret Token: " . $secretToken . "\n";
echo "Webhook URL: " . $webhookUrl . "\n";

$url = "https://bot-api.zaloplatforms.com/bot" . $botToken . "/setWebhook";
$data = [
    "url" => $webhookUrl,
    "secret_token" => $secretToken
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";
