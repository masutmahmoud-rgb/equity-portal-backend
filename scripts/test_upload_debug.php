<?php
$ch = curl_init('http://127.0.0.1:8001/api/statement-of-accounts');
curl_setopt($ch, CURLOPT_POST, true);

$temp_file = sys_get_temp_dir() . '/test_upload.txt';
file_put_contents($temp_file, 'Test file content');

$post = [
    'company_id' => 3,
    'investment_id' => 1,
    'investor_id' => 1,
    'transaction_type' => 'Withdrawal',
    'amount' => 500,
    'status' => 'Pending',
    'transaction_date' => '2026-07-05',
    'notes' => 'Test upload',
    'attachments[0]' => new CURLFile($temp_file, 'text/plain', 'test.txt'),
];

curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo "Response:\n";
echo json_encode(json_decode($response, true), JSON_PRETTY_PRINT) . "\n";
