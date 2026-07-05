<?php
$id = $argv[1] ?? 1;
$db = new PDO('sqlite:database/database.sqlite');
$res = $db->query("select attachment_paths from statement_of_accounts where id={$id}");
$row = $res->fetch(PDO::FETCH_ASSOC);
var_dump($row);
