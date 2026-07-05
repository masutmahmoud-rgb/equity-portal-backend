<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

echo "Companies:\n";
$companies = DB::table('companies')->get(['id', 'name']);
foreach ($companies as $c) {
    echo "  ID: {$c->id}, Name: {$c->name}\n";
}

echo "\nInvestments:\n";
$investments = DB::table('investments')->get(['id', 'company_id', 'investor_id', 'amount']);
foreach ($investments as $i) {
    echo "  ID: {$i->id}, Company: {$i->company_id}, Investor: {$i->investor_id}, Amount: {$i->amount}\n";
}
