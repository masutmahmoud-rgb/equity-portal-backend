<?php
// Test creating a statement with all required fields
$payload = json_encode([
    'company_id' => 1,
    'investment_id' => 1,
    'investor_id' => 1,
    'transaction_type' => 'Dividend',
    'amount' => 100,
    'status' => 'Pending',
    'transaction_date' => '2026-07-03'
]);

$ch = curl_init('http://127.0.0.1:8000/api/statement-of-accounts');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "✅ Testing with ALL required fields\n";
echo "HTTP Status: $httpCode\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

// Now test WITHOUT investment_id
echo "❌ Testing WITHOUT investment_id (should fail)\n";
$payload2 = json_encode([
    'company_id' => 1,
    'investor_id' => 1,
    'transaction_type' => 'Dividend',
    'amount' => 100,
    'status' => 'Pending',
    'transaction_date' => '2026-07-03'
]);

$ch = curl_init('http://127.0.0.1:8000/api/statement-of-accounts');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload2);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
