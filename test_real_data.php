<?php
// Get actual company and investment IDs from database
$db = new PDO('sqlite:' . __DIR__ . '/database/database.sqlite');

$companies = $db->query('SELECT id FROM companies LIMIT 1')->fetchAll();
$investments = $db->query('SELECT id, company_id, investor_id FROM investments LIMIT 1')->fetchAll();

if (!$companies || !$investments) {
    echo "No test data found in database\n";
    exit(1);
}

$company_id = $companies[0]['id'];
$investment = $investments[0];

echo "Using: Company ID = {$company_id}, Investment ID = {$investment['id']}, Investor ID = {$investment['investor_id']}\n\n";

// Test WITH investment_id
echo "✅ Creating Statement OF Account WITH investment_id\n";
$payload = json_encode([
    'company_id' => $company_id,
    'investment_id' => $investment['id'],
    'investor_id' => $investment['investor_id'],
    'transaction_type' => 'Dividend',
    'amount' => 50.00,
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

echo "HTTP Status: $httpCode\n";
$decoded = json_decode($response, true);
echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n\n";

// Test WITHOUT investment_id
echo "❌ Creating Statement OF Account WITHOUT investment_id (should fail)\n";
$payload2 = json_encode([
    'company_id' => $company_id,
    'investor_id' => $investment['investor_id'],
    'transaction_type' => 'Dividend',
    'amount' => 50.00,
    'status' => 'Pending',
    'transaction_date' => '2026-07-03'
    // NOTE: investment_id is missing!
]);

$ch = curl_init('http://127.0.0.1:8000/api/statement-of-accounts');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload2);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode (should be 422 Validation Error)\n";
$decoded = json_decode($response, true);
echo json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($httpCode === 422 && isset($decoded['errors']['investment_id'])) {
    echo "\n✓ Backend validation is working correctly!\n";
} else {
    echo "\n✗ Backend validation may have issues\n";
}
?>
