<?php
echo "=== COMPREHENSIVE API ENDPOINT VERIFICATION ===\n\n";

function test_endpoint($method, $endpoint, $data = null) {
    $ch = curl_init("http://127.0.0.1:8000{$endpoint}");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'status' => $code,
        'response' => $response,
        'success' => $code >= 200 && $code < 300,
    ];
}

// Get test data
$companies = test_endpoint('GET', '/api/companies');
$company_data = json_decode($companies['response'], true)['data'] ?? [];
$company_id = $company_data[0]['id'] ?? null;

$investors = test_endpoint('GET', '/api/investors');
$investor_data = json_decode($investors['response'], true)['data'] ?? [];
$investor_id = $investor_data[0]['id'] ?? null;

$investments = test_endpoint('GET', '/api/investments');
$investment_data = json_decode($investments['response'], true)['data'] ?? [];
$investment_id = $investment_data[0]['id'] ?? null;

$tests = [
    // Companies endpoints
    ['GET', '/api/companies', null, 'List Companies'],
    ['POST', '/api/companies', ['name' => 'Test Company', 'status' => 'Operating'], 'Create Company'],
    
    // Investors endpoints
    ['GET', '/api/investors', null, 'List Investors'],
    ['POST', '/api/investors', ['name' => 'Test Investor', 'email' => 'test@example.com'], 'Create Investor'],
    
    // Investments endpoints
    ['GET', '/api/investments', null, 'List Investments'],
    ['POST', '/api/investments', ['company_id' => $company_id, 'investor_id' => $investor_id, 'amount' => 1000], 'Create Investment'],
    
    // Dividends endpoints (NEWLY FIXED)
    ['GET', '/api/dividends', null, 'List Dividends'],
    ['POST', '/api/dividends', ['company_id' => $company_id, 'investment_id' => $investment_id, 'amount' => 500, 'status' => 'Pending'], 'Create Dividend'],
    
    // Statement of Accounts endpoints
    ['GET', '/api/statement-of-accounts', null, 'List Statement of Accounts'],
    ['POST', '/api/statement-of-accounts', ['company_id' => $company_id, 'investment_id' => $investment_id, 'investor_id' => $investor_id, 'transaction_type' => 'Dividend', 'amount' => 250, 'status' => 'Pending', 'transaction_date' => '2026-07-05'], 'Create Statement of Account'],
];

$passed = 0;
$failed = 0;

foreach ($tests as [$method, $endpoint, $data, $label]) {
    $result = test_endpoint($method, $endpoint, $data);
    $status = $result['success'] ? '✓' : '✗';
    $http_code = $result['status'];
    
    if ($result['success']) {
        $passed++;
    } else {
        $failed++;
    }
    
    printf("%-40s %s HTTP %d\n", $label, $status, $http_code);
}

echo "\n=== SUMMARY ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total: " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ All API endpoints are working correctly!\n";
    echo "✓ POST /api/dividends endpoint is now available!\n";
} else {
    echo "\n✗ Some endpoints failed\n";
    exit(1);
}
