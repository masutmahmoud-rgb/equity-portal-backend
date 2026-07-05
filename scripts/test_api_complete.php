<?php
/**
 * Comprehensive API test for Statement of Accounts backend
 */

$api = 'http://127.0.0.1:8001/api';
$results = [];

function test_endpoint($method, $endpoint, $data = null) {
    global $api, $results;
    
    $url = $api . $endpoint;
    $ch = curl_init($url);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    return [
        'status' => $status,
        'data' => $decoded,
        'success' => $status >= 200 && $status < 300,
    ];
}

echo "=== STATEMENT OF ACCOUNTS API TEST ===\n\n";

// Test 1: List companies
echo "1. GET /companies\n";
$companies = test_endpoint('GET', '/companies');
$results[] = ['List companies', $companies['success']];
echo "   Status: {$companies['status']} " . ($companies['success'] ? "✓" : "✗") . "\n";
$company_id = $companies['data']['data'][0]['id'] ?? null;

// Test 2: List investors
echo "\n2. GET /investors\n";
$investors = test_endpoint('GET', '/investors');
$results[] = ['List investors', $investors['success']];
echo "   Status: {$investors['status']} " . ($investors['success'] ? "✓" : "✗") . "\n";
$investor_id = $investors['data']['data'][0]['id'] ?? null;

// Test 3: List investments
echo "\n3. GET /investments\n";
$investments = test_endpoint('GET', '/investments');
$results[] = ['List investments', $investments['success']];
echo "   Status: {$investments['status']} " . ($investments['success'] ? "✓" : "✗") . "\n";
$investment_id = $investments['data']['data'][0]['id'] ?? null;

// Test 4: List statement of accounts
echo "\n4. GET /statement-of-accounts\n";
$soas = test_endpoint('GET', '/statement-of-accounts');
$results[] = ['List statement of accounts', $soas['success']];
echo "   Status: {$soas['status']} " . ($soas['success'] ? "✓" : "✗") . "\n";
$first_id = $soas['data']['data'][0]['id'] ?? null;

// Test 5: Get single statement of account
if ($first_id) {
    echo "\n5. GET /statement-of-accounts/{$first_id}\n";
    $single = test_endpoint('GET', "/statement-of-accounts/{$first_id}");
    $results[] = ['Get single SOA', $single['success']];
    echo "   Status: {$single['status']} " . ($single['success'] ? "✓" : "✗") . "\n";
}

// Test 6: Create dividend
if ($company_id && $investment_id && $investor_id) {
    echo "\n6. POST /statement-of-accounts (Dividend)\n";
    $create_dividend = test_endpoint('POST', '/statement-of-accounts', [
        'company_id' => $company_id,
        'investment_id' => $investment_id,
        'investor_id' => $investor_id,
        'transaction_type' => 'Dividend',
        'amount' => 500,
        'status' => 'Pending',
        'transaction_date' => '2026-07-05',
        'notes' => 'Test dividend',
    ]);
    $results[] = ['Create dividend', $create_dividend['success']];
    echo "   Status: {$create_dividend['status']} " . ($create_dividend['success'] ? "✓" : "✗") . "\n";
    $new_soa_id = $create_dividend['data']['data']['id'] ?? null;
}

// Test 7: Create withdrawal
if ($company_id && $investment_id && $investor_id) {
    echo "\n7. POST /statement-of-accounts (Withdrawal)\n";
    $create_withdrawal = test_endpoint('POST', '/statement-of-accounts', [
        'company_id' => $company_id,
        'investment_id' => $investment_id,
        'investor_id' => $investor_id,
        'transaction_type' => 'Withdrawal',
        'amount' => 250,
        'status' => 'Pending',
        'transaction_date' => '2026-07-05',
        'notes' => 'Test withdrawal',
    ]);
    $results[] = ['Create withdrawal', $create_withdrawal['success']];
    echo "   Status: {$create_withdrawal['status']} " . ($create_withdrawal['success'] ? "✓" : "✗") . "\n";
    $withdrawal_id = $create_withdrawal['data']['data']['id'] ?? null;
}

// Test 8: Update SOA
if ($new_soa_id) {
    echo "\n8. PUT /statement-of-accounts/{$new_soa_id}\n";
    $update = test_endpoint('PUT', "/statement-of-accounts/{$new_soa_id}", [
        'company_id' => $company_id,
        'investment_id' => $investment_id,
        'investor_id' => $investor_id,
        'transaction_type' => 'Dividend',
        'amount' => 750,
        'status' => 'Paid',
        'transaction_date' => '2026-07-05',
        'notes' => 'Updated dividend',
    ]);
    $results[] = ['Update SOA', $update['success']];
    echo "   Status: {$update['status']} " . ($update['success'] ? "✓" : "✗") . "\n";
}

// Test 9: Declare dividend
echo "\n9. POST /statement-of-accounts/declarations/dividend\n";
$declare = test_endpoint('POST', '/statement-of-accounts/declarations/dividend', [
    'company_id' => $company_id,
    'total_amount' => 10000,
    'transaction_date' => '2026-07-05',
    'notes' => 'Company-wide dividend',
    'status' => 'Pending',
]);
$results[] = ['Declare dividend', $declare['success']];
echo "   Status: {$declare['status']} " . ($declare['success'] ? "✓" : "✗") . "\n";

// Test 10: Delete SOA
if ($new_soa_id) {
    echo "\n10. DELETE /statement-of-accounts/{$new_soa_id}\n";
    $delete = test_endpoint('DELETE', "/statement-of-accounts/{$new_soa_id}");
    $results[] = ['Delete SOA', $delete['success']];
    echo "   Status: {$delete['status']} " . ($delete['success'] ? "✓" : "✗") . "\n";
}

// Summary
echo "\n\n=== TEST SUMMARY ===\n";
$passed = array_sum(array_map(fn($r) => $r[1] ? 1 : 0, $results));
$total = count($results);
echo "Passed: $passed/$total\n";

foreach ($results as [$name, $passed]) {
    echo ($passed ? "✓" : "✗") . " $name\n";
}
