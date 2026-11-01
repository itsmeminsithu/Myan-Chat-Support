<?php
declare(strict_types=1);
// Run only from a trusted local shell with a database backup already taken.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../app/src/bootstrap.php';
if(!mcs_installed()){fwrite(STDERR,"Install the server before migrating.\n");exit(1);}
$db=mcs_pdo();
$lockPath=MCS_ROOT.'/storage/migration.lock';
$lock=fopen($lockPath,'c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Migration already running.\n");exit(1);}
try{
 $source=file_get_contents(MCS_ROOT.'/sql/migrations/001_operator_sites.sql');
 if(!$source)throw new RuntimeException('Missing migration SQL');
 // The DDL is idempotent and does not erase existing conversations.
 $db->exec(trim($source));
 $config=mcs_config();
 if(version_compare((string)($config['version']??'0.0.0'),'1.0.1','<')){
  $config['version']='1.0.1';
  $target=MCS_ROOT.'/storage/config.php';
  $temp=$target.'.v101.'.bin2hex(random_bytes(6));
  if(file_put_contents($temp,"<?php\nreturn ".var_export($config,true).";\n",LOCK_EX)===false)throw new RuntimeException('Cannot write migrated config');
  chmod($temp,0600);
  if(!rename($temp,$target)){@unlink($temp);throw new RuntimeException('Cannot replace config safely');}
 }
 echo "Migration v1.0.1 complete. Existing operators are restricted to NO websites until an admin grants explicit access.\n";
} catch(Throwable $e){fwrite(STDERR,'Migration failed: '.$e->getMessage()."\n");exit(1);
} finally {flock($lock,LOCK_UN);fclose($lock);}
