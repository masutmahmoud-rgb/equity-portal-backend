<?php
$table = $argv[1] ?? 'dividends';
$db = new PDO('sqlite:database/database.sqlite');
$res = $db->query("PRAGMA table_info('$table')");
foreach ($res as $r) {
    echo $r['cid']."\t".$r['name']."\t".$r['type']."\t".($r['notnull'] ? 'NOTNULL' : '')."\t".$r['dflt_value']."\n";
}
