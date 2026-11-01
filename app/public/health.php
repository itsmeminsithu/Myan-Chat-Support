<?php
require __DIR__.'/../src/bootstrap.php';
if(!mcs_installed())mcs_json(['status'=>'not_installed'],503);
try{$db=mcs_pdo();$db->query('SELECT 1');mcs_json(['status'=>'ok','version'=>'1.0.2']);}catch(Throwable $e){mcs_json(['status'=>'unavailable'],503);}
