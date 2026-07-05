<?php
$id = $argv[1] ?? 1;
$db = new PDO('sqlite:database/database.sqlite');
$res = $db->query("select attachment_paths from statement_of_accounts where id={$id}");
$row = $res->fetch(PDO::FETCH_ASSOC);
$paths = json_decode($row['attachment_paths'], true);
var_dump($paths);
foreach ($paths as $p) {
    $full = __DIR__ . '/../storage/app/' . $p;
    echo $full . ' => ' . (file_exists($full) ? 'exists' : 'missing') . PHP_EOL;
}
