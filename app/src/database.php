<?php
declare(strict_types=1);
function mcs_pdo(?array $config=null): PDO {
 static $pdo=null;
 if($config===null&&$pdo instanceof PDO)return $pdo;
 $c=$config??mcs_config();
 $host=(string)$c['db_host'];$port=(int)($c['db_port']??3306);$name=(string)$c['db_name'];
 if(!preg_match('/^[A-Za-z0-9_]+$/D',$name))throw new RuntimeException('Invalid database name');
 $dsn='mysql:host='.$host.';port='.$port.';dbname='.$name.';charset=utf8mb4';
 $db=new PDO($dsn,(string)$c['db_user'],(string)$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
 $db->exec("SET time_zone = '+00:00'");
 if($config===null)$pdo=$db;
 return $db;
}
function mcs_rate(PDO $db,string $key,int $limit=20,int $seconds=60): bool {
 $key=hash('sha256',$key);
 // Single-statement upsert is atomic under concurrent requests.
 $stmt=$db->prepare('INSERT INTO mcs_rate_limits(rate_key,window_start,requests) VALUES(?,UTC_TIMESTAMP(),1) ON DUPLICATE KEY UPDATE requests=IF(window_start < UTC_TIMESTAMP() - INTERVAL '.(int)$seconds.' SECOND,1,requests+1),window_start=IF(window_start < UTC_TIMESTAMP() - INTERVAL '.(int)$seconds.' SECOND,UTC_TIMESTAMP(),window_start)');
 $stmt->execute([$key]);
 $stmt=$db->prepare('SELECT requests FROM mcs_rate_limits WHERE rate_key=?');$stmt->execute([$key]);
 return (int)$stmt->fetchColumn()<=$limit;
}
function mcs_audit(PDO $db, ?int $userId,string $action,string $subject): void {
 $st=$db->prepare('INSERT INTO mcs_audit(actor_id,action,subject,ip_hash) VALUES(?,?,?,?)');
 $st->execute([$userId,$action,substr($subject,0,160),mcs_ip_hash((string)mcs_config()['secret'])]);
}
function mcs_site(PDO $db,string $key): ?array {
 if(!preg_match('/^[0-9a-f]{48}$/D',$key))return null;
 $stmt=$db->prepare('SELECT * FROM mcs_sites WHERE site_key=? LIMIT 1');$stmt->execute([$key]);
 return $stmt->fetch()?:null;
}
