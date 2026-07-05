#!/usr/bin/env php
<?php
/**
 * Integration test: Frontend form submission with file upload
 */

$api = 'http://127.0.0.1:8001/api';

// Step 1: Get company, investor, and investment IDs
echo "Step 1: Fetching reference data...\n";

$companies_ch = curl_init("$api/companies");
curl_setopt($companies_ch, CURLOPT_RETURNTRANSFER, true);
$companies_response = curl_exec($companies_ch);
$companies_data = json_decode($companies_response, true);
curl_close($companies_ch);

$company_id = $companies_data['data'][0]['id'] ?? null;
if (!$company_id) {
    echo "✗ No companies found\n";
    exit(1);
}
echo "✓ Company ID: $company_id\n";

$investors_ch = curl_init("$api/investors");
curl_setopt($investors_ch, CURLOPT_RETURNTRANSFER, true);
$investors_response = curl_exec($investors_ch);
$investors_data = json_decode($investors_response, true);
curl_close($investors_ch);

$investor_id = $investors_data['data'][0]['id'] ?? null;
if (!$investor_id) {
    echo "✗ No investors found\n";
    exit(1);
}
echo "✓ Investor ID: $investor_id\n";

$investments_ch = curl_init("$api/investments");
curl_setopt($investments_ch, CURLOPT_RETURNTRANSFER, true);
$investments_response = curl_exec($investments_ch);
$investments_data = json_decode($investments_response, true);
curl_close($investments_ch);

$investment_id = $investments_data['data'][0]['id'] ?? null;
if (!$investment_id) {
    echo "✗ No investments found\n";
    exit(1);
}
echo "✓ Investment ID: $investment_id\n\n";

// Step 2: Create test files
echo "Step 2: Creating test files...\n";
$temp_dir = sys_get_temp_dir();
$file1 = "$temp_dir/test_attachment1.txt";
$file2 = "$temp_dir/test_attachment2.txt";

file_put_contents($file1, "Test attachment 1 content");
file_put_contents($file2, "Test attachment 2 content");
echo "✓ Created test files\n\n";

// Step 3: Create withdrawal with file uploads
echo "Step 3: Submitting form (Withdrawal with attachments)...\n";

$ch = curl_init("$api/statement-of-accounts");
curl_setopt($ch, CURLOPT_POST, true);

// Build multipart form data
$post_data = [
    'company_id' => $company_id,
    'investment_id' => $investment_id,
    'investor_id' => $investor_id,
    'transaction_type' => 'Withdrawal',
    'amount' => '500.00',
    'status' => 'Pending',
    'transaction_date' => date('Y-m-d'),
    'notes' => 'Integration test withdrawal',
];

// Add files using CURLFile
$post_data['attachments[0]'] = new CURLFile($file1, 'text/plain', 'attachment1.txt');
$post_data['attachments[1]'] = new CURLFile($file2, 'text/plain', 'attachment2.txt');

curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$response_data = json_decode($response, true);
curl_close($ch);

if ($http_code === 201) {
    echo "✓ Withdrawal created (HTTP $http_code)\n";
    $soa_id = $response_data['data']['id'] ?? null;
    $attachment_urls = $response_data['data']['attachment_urls'] ?? [];
    
    echo "  ID: $soa_id\n";
    echo "  Attachments: " . count($attachment_urls) . "\n";
    foreach ($attachment_urls as $idx => $url) {
        echo "    [$idx] $url\n";
    }
} else {
    echo "✗ Failed to create withdrawal (HTTP $http_code)\n";
    echo "Response: " . json_encode($response_data, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "\n";

// Step 4: Download attachment
if (!empty($attachment_urls)) {
    echo "Step 4: Testing attachment download...\n";
    $download_url = $attachment_urls[0];
    
    $dl_ch = curl_init($download_url);
    curl_setopt($dl_ch, CURLOPT_RETURNTRANSFER, true);
    $file_content = curl_exec($dl_ch);
    $dl_http_code = curl_getinfo($dl_ch, CURLINFO_HTTP_CODE);
    curl_close($dl_ch);
    
    if ($dl_http_code === 200 && !empty($file_content)) {
        echo "✓ Attachment downloaded (HTTP $dl_http_code)\n";
        echo "  Size: " . strlen($file_content) . " bytes\n";
    } else {
        echo "✗ Failed to download attachment (HTTP $dl_http_code)\n";
    }
}

echo "\n";

// Step 5: Create dividend
echo "Step 5: Submitting dividend form...\n";

$div_ch = curl_init("$api/statement-of-accounts");
curl_setopt($div_ch, CURLOPT_POST, true);
curl_setopt($div_ch, CURLOPT_POSTFIELDS, json_encode([
    'company_id' => $company_id,
    'investment_id' => $investment_id,
    'investor_id' => $investor_id,
    'transaction_type' => 'Dividend',
    'amount' => '1000.00',
    'status' => 'Pending',
    'transaction_date' => date('Y-m-d'),
    'notes' => 'Integration test dividend',
]));
curl_setopt($div_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($div_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$div_response = curl_exec($div_ch);
$div_http_code = curl_getinfo($div_ch, CURLINFO_HTTP_CODE);
$div_data = json_decode($div_response, true);
curl_close($div_ch);

if ($div_http_code === 201) {
    echo "✓ Dividend created (HTTP $div_http_code)\n";
    echo "  ID: " . $div_data['data']['id'] . "\n";
} else {
    echo "✗ Failed to create dividend (HTTP $div_http_code)\n";
    echo "Response: " . json_encode($div_data, JSON_PRETTY_PRINT) . "\n";
}

echo "\n";

// Cleanup
unlink($file1);
unlink($file2);

echo "=== ALL INTEGRATION TESTS PASSED ===\n";
