#!/usr/bin/env php
<?php

echo "\n=== EQUITY BACKEND VERIFICATION ===\n\n";

$checks = [];

// 1. Check Laravel installation
echo "1. Checking Laravel installation...";
if (file_exists('artisan')) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 2. Check database file
echo "2. Checking database connection...";
if (file_exists('database/database.sqlite')) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 3. Check models exist
echo "3. Checking models...";
$models = ['Company', 'Investor', 'Investment', 'StatementOfAccount'];
$all_exist = true;
foreach ($models as $model) {
    if (!file_exists("app/Models/$model.php")) {
        $all_exist = false;
        echo "\n   ✗ Missing: app/Models/$model.php";
    }
}
if ($all_exist) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 4. Check controllers exist
echo "4. Checking controllers...";
$controllers = ['CompanyController', 'InvestorController', 'InvestmentController', 'StatementOfAccountController'];
$all_exist = true;
foreach ($controllers as $controller) {
    if (!file_exists("app/Http/Controllers/Api/$controller.php")) {
        $all_exist = false;
        echo "\n   ✗ Missing: app/Http/Controllers/Api/$controller.php";
    }
}
if ($all_exist) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 5. Check migrations
echo "5. Checking migrations...";
$migrations = [
    '2026_07_03_000001_create_statement_of_accounts_table.php',
    '2026_07_03_100000_rename_dividends_to_statement_of_accounts.php',
    '2026_07_03_110000_add_attachment_paths_to_statement_of_accounts.php'
];
$all_exist = true;
foreach ($migrations as $migration) {
    if (!file_exists("database/migrations/$migration")) {
        $all_exist = false;
        echo "\n   ✗ Missing: database/migrations/$migration";
    }
}
if ($all_exist) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 6. Check routes file
echo "6. Checking routes...";
if (file_exists('routes/api.php')) {
    $routes = file_get_contents('routes/api.php');
    if (strpos($routes, 'statement-of-accounts') !== false) {
        echo " ✓\n";
        $checks[] = true;
    } else {
        echo " ✗\n";
        $checks[] = false;
    }
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 7. Check frontend files
echo "7. Checking frontend...";
$frontend_files = [
    'next-frontend/pages/statement-of-accounts/index.js',
    'next-frontend/pages/statement-of-accounts/create.js',
    'next-frontend/components/StatementOfAccountForm.js',
    'next-frontend/next.config.js'
];
$all_exist = true;
foreach ($frontend_files as $file) {
    if (!file_exists($file)) {
        $all_exist = false;
        echo "\n   ✗ Missing: $file";
    }
}
if ($all_exist) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 8. Check storage directory
echo "8. Checking storage directory...";
if (is_dir('storage/app/private/statement_of_accounts')) {
    echo " ✓\n";
    $checks[] = true;
} else {
    echo " ✗\n";
    $checks[] = false;
}

// 9. Test database connection
echo "9. Testing database connection...";
try {
    require 'vendor/autoload.php';
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Http\Kernel')->handle(
        $request = Illuminate\Http\Request::capture()
    );
    
    $pdo = DB::connection()->getPdo();
    if ($pdo) {
        // Check if tables exist
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
        $table_names = array_map(fn($t) => $t->name, $tables);
        
        if (in_array('statement_of_accounts', $table_names)) {
            echo " ✓\n";
            $checks[] = true;
        } else {
            echo " ✗ (Table not found)\n";
            $checks[] = false;
        }
    } else {
        echo " ✗\n";
        $checks[] = false;
    }
} catch (Exception $e) {
    echo " ✗ (" . $e->getMessage() . ")\n";
    $checks[] = false;
}

// Summary
$passed = count(array_filter($checks));
$total = count($checks);
$percentage = ($passed / $total) * 100;

echo "\n=== VERIFICATION SUMMARY ===\n";
echo "Passed: $passed/$total (" . round($percentage) . "%)\n\n";

if ($percentage === 100) {
    echo "✓ All systems ready!\n";
    echo "\nRun the following commands:\n";
    echo "  Terminal 1: php artisan serve --port=8000\n";
    echo "  Terminal 2: cd next-frontend && npm run dev\n";
    echo "\nThen open: http://localhost:3001\n\n";
} else {
    echo "✗ Some checks failed. Review above.\n\n";
    exit(1);
}
