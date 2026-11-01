<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(mcs_installed()){header('Location: /login.php');exit;}
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; object-src 'none'; frame-ancestors 'none'");
$setupKey=getenv('MCS_SETUP_KEY')?: (is_file(MCS_ROOT.'/storage/install.key')?trim((string)file_get_contents(MCS_ROOT.'/storage/install.key')):'');
$errors=[];$ok=false;
// The install key is private. Never accept public installation without it.

if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if($setupKey===''||!hash_equals($setupKey,(string)($_POST['setup_key']??'')))throw new RuntimeException('Invalid setup key. Set MCS_SETUP_KEY or create storage/install.key on the server.');
  if(!extension_loaded('pdo_mysql')||!extension_loaded('mbstring'))throw new RuntimeException('PHP pdo_mysql and mbstring extensions required.');
  $name=mcs_clean((string)($_POST['name']??''),100);
  $url=rtrim(trim((string)($_POST['public_url']??'')),'/');
  $host=mcs_clean((string)($_POST['db_host']??''),190);
  $port=(int)($_POST['db_port']??3306);
  $dbName=mcs_clean((string)($_POST['db_name']??''),64);
  $dbUser=mcs_clean((string)($_POST['db_user']??''),120);
  $dbPass=(string)($_POST['db_password']??'');if($dbPass==='')$dbPass=(string)(getenv('MCS_DB_PASSWORD')?:'');
  $email=mcs_email((string)($_POST['email']??''));$adminName=mcs_clean((string)($_POST['admin_name']??''),100);
  $password=(string)($_POST['password']??'');
  $origins=mcs_origins((string)($_POST['origins']??''));
  if($name===''||$adminName===''||!$email||strlen($password)<12||strlen($password)>200||count($origins)===0)throw new RuntimeException('Enter site name, valid email, 12+ character password and at least one allowed origin.');
  if(!preg_match('/^[A-Za-z0-9_]+$/D',$dbName)||$host===''||$dbUser===''||$port<1||$port>65535)throw new RuntimeException('Invalid database settings.');
  if(mcs_origin($url)===null||$url!==mcs_origin($url))throw new RuntimeException('Public URL must be an origin, e.g. https://chat.example.com');
  if(str_starts_with($url,'http://')&&!in_array(parse_url($url,PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new RuntimeException('Production public URL requires HTTPS');
  $storage=MCS_ROOT.'/storage';
  if(!is_dir($storage)||!is_writable($storage))throw new RuntimeException('Private storage directory is not writable; repair permissions before installing.');
  $probe=$storage.'/.preflight.'.bin2hex(random_bytes(8));
  if(file_put_contents($probe,'ok',LOCK_EX)!==2)throw new RuntimeException('Private storage write test failed');
  if(!@rename($probe,$probe.'.renamed'))throw new RuntimeException('Private storage rename test failed');
  @unlink($probe.'.renamed');
  $cfg=['db_host'=>$host,'db_port'=>$port,'db_name'=>$dbName,'db_user'=>$dbUser,'db_password'=>$dbPass,'public_url'=>$url,'secret'=>bin2hex(random_bytes(32)),'installed_at'=>gmdate('c'),'version'=>'1.0.2'];
  $db=mcs_pdo($cfg);
  $sql=file_get_contents(MCS_ROOT.'/sql/schema.sql');
  if(!$sql)throw new RuntimeException('Missing schema');
  foreach(preg_split('/;\s*\n/',$sql) as $query){$query=trim($query);if($query!=='')$db->exec($query);}
  $count=(int)$db->query('SELECT COUNT(*) FROM mcs_users')->fetchColumn();
  if($count!==0)throw new RuntimeException('Database already contains operators; choose a fresh database to avoid takeover.');
  $db->beginTransaction();
  try {
   $s=$db->prepare("INSERT INTO mcs_users(name,email,password_hash,role,is_active) VALUES(?,?,?,'admin',1)");$s->execute([$adminName,$email,password_hash($password,PASSWORD_DEFAULT)]);
   $siteKey=bin2hex(random_bytes(24));
   $s=$db->prepare('INSERT INTO mcs_sites(site_key,name,allowed_origins,contact_email) VALUES(?,?,?,?)');
   $s->execute([$siteKey,$name,json_encode($origins,JSON_UNESCAPED_SLASHES),$email]);
   $path=MCS_ROOT.'/storage/config.php';$tmp=$path.'.tmp.'.bin2hex(random_bytes(4));
   $code="<?php\nreturn ".var_export($cfg,true).";\n";
   if(file_put_contents($tmp,$code,LOCK_EX)===false)throw new RuntimeException('Cannot write private config. Ensure storage directory writable.');
   chmod($tmp,0600);
   if(!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('Cannot finalize configuration');}
   try{$db->commit();}catch(Throwable $commitError){@unlink($path);throw $commitError;}
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  @unlink(MCS_ROOT.'/storage/install.key');
  header('Location: /login.php?installed=1');exit;
 }catch(Throwable $e){error_log('Install failure: '.$e->getMessage());$errors[]='Installation could not finish: '.($e instanceof PDOException?'Database connection/schema error. Check credentials and server logs.':mcs_esc($e->getMessage()));}
}
function v(string $key,string $fallback=''):string {return mcs_esc((string)($_POST[$key]??getenv('MCS_'.strtoupper($key))?:$fallback));}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install Myan Chat Support</title><link rel="stylesheet" href="/assets/admin.css?v=1.0.2"></head><body class="auth-body"><main class="auth-card installer"><div class="mark">✦</div><p class="eyebrow">SELF-HOSTED INSTALLER · V1.0.2</p><h1>Welcome to Myan Chat Support</h1><p class="muted">Configure your MySQL database and create your administrator. Keep the setup key private.</p>
<?php foreach($errors as $err):?><p class="notice error"><?= $err ?></p><?php endforeach;?>
<?php if($setupKey===''):?><p class="notice error">Installer locked. Set MCS_SETUP_KEY on your server, or create a private file at app/storage/install.key containing a random token.</p><?php endif;?>
<form method="post" autocomplete="off">
<label>Setup key<input type="password" name="setup_key" required></label>
<label>Business / website name<input name="name" required maxlength="100" value="<?=v('name','Myan Chat Demo')?>"></label>
<label>Chat server URL (origin only)<input name="public_url" required value="<?=v('public_url','http://localhost:8088')?>"></label>
<label>Allowed website origins (one per line)<textarea name="origins" required rows="3"><?=v('origins','http://localhost:8088')?></textarea></label>
<div class="cols"><label>MySQL host<input name="db_host" required value="<?=v('db_host','db')?>"></label><label>MySQL port<input type="number" name="db_port" required value="<?=v('db_port','3306')?>"></label></div>
<div class="cols"><label>Database name<input name="db_name" required value="<?=v('db_name','myanchat')?>"></label><label>Database user<input name="db_user" required value="<?=v('db_user','myanchat')?>"></label></div>
<label>Database password<input type="password" name="db_password" autocomplete="new-password" placeholder="Leave blank to use server environment"></label>
<label>Administrator name<input name="admin_name" required maxlength="100" value="<?=v('admin_name','Min SiThu')?>"></label>
<label>Administrator email<input type="email" name="email" maxlength="190" required value="<?=v('email')?>"></label>
<label>Administrator password (at least 12 characters)<input type="password" name="password" required minlength="12" maxlength="200"></label>
<button class="button primary full" type="submit">Install Myan Chat Support</button>
</form><p class="small-muted">Developed by Min SiThu · <a href="https://minsithu.org">minsithu.org</a></p></main></body></html>
