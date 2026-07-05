<?php

echo "=== TESTING /API/DIVIDENDS ENDPOINTS ===\n\n";

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
    
    return ['status' => $code, 'response' => $response, 'success' => $code >= 200 && $code < 300];
}

// Get valid test data
echo "Getting test data...\n";
$companies = test_endpoint('GET', '/api/companies');
$investments = test_endpoint('GET', '/api/investments');

$company_data = json_decode($companies['response'], true)['data'] ?? [];
$investment_data = json_decode($investments['response'], true)['data'] ?? [];

if (empty($company_data)) {
    echo "ERROR: No companies found in database\n";
    exit(1);
}

if (empty($investment_data)) {
    echo "ERROR: No investments found in database\n";
    exit(1);
}

$company_id = $company_data[0]['id'];
$investment_id = $investment_data[0]['id'];

echo "Using Company ID: {$company_id}, Investment ID: {$investment_id}\n\n";

// Test 1: GET /api/dividends (list)
echo "1. GET /api/dividends\n";
$list = test_endpoint('GET', '/api/dividends');
echo "   Status: {$list['status']} " . ($list['success'] ? "✓" : "✗") . "\n";

// Test 2: POST /api/dividends (create)
echo "\n2. POST /api/dividends\n";
$create = test_endpoint('POST', '/api/dividends', [
    'company_id' => $company_id,
    'investment_id' => $investment_id,
    'amount' => 250.50,
    'status' => 'Pending',
    'payment_date' => '2026-07-05',
    'notes' => 'Test dividend',
]);
echo "   Status: {$create['status']} " . ($create['success'] ? "✓" : "✗") . "\n";
if (!$create['success']) {
    echo "   Error: " . $create['response'] . "\n";
}

if ($create['success']) {
    $data = json_decode($create['response'], true);
    if (isset($data['data']['id'])) {
        $dividend_id = $data['data']['id'];
        echo "   Created dividend ID: {$dividend_id}\n";
        
        // Test 3: GET /api/dividends/{id}
        echo "\n3. GET /api/dividends/{$dividend_id}\n";
        $show = test_endpoint('GET', "/api/dividends/{$dividend_id}");
        echo "   Status: {$show['status']} " . ($show['success'] ? "✓" : "✗") . "\n";
        
        // Test 4: PUT /api/dividends/{id}
        echo "\n4. PUT /api/dividends/{$dividend_id}\n";
        $update = test_endpoint('PUT', "/api/dividends/{$dividend_id}", [
            'company_id' => $company_id,
            'investment_id' => $investment_id,
            'amount' => 300,
            'status' => 'Paid',
            'payment_date' => '2026-07-10',
        ]);
        echo "   Status: {$update['status']} " . ($update['success'] ? "✓" : "✗") . "\n";
        if (!$update['success']) {
            echo "   Error: " . $update['response'] . "\n";
        }
        
        // Test 5: DELETE /api/dividends/{id}
        echo "\n5. DELETE /api/dividends/{$dividend_id}\n";
        $delete = test_endpoint('DELETE', "/api/dividends/{$dividend_id}");
        echo "   Status: {$delete['status']} " . ($delete['success'] ? "✓" : "✗") . "\n";
    }
}

echo "\n=== TESTS COMPLETE ===\n";
