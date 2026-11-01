<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';mcs_require_installed();
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'self'; object-src 'none'; frame-ancestors 'none'");
if(mcs_user()){header('Location: /admin.php');exit;}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $email=mcs_email((string)($_POST['email']??''));$password=(string)($_POST['password']??'');
 try{
  if(!hash_equals(mcs_csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Invalid login form');
  $db=mcs_pdo();$key='login:'.mcs_ip_hash(mcs_config()['secret']).':'.hash('sha256',$email??'');
  $q=$db->prepare('SELECT * FROM mcs_users WHERE email=? AND is_active=1');$q->execute([$email??'']);$user=$q->fetch();
  if(!$user||!password_verify($password,$user['password_hash'])){
   if(!mcs_rate($db,$key,10,900))throw new RuntimeException('Too many attempts. Try again later.');
   throw new RuntimeException('Invalid email or password.');
  }
  if(password_needs_rehash($user['password_hash'],PASSWORD_DEFAULT)){$db->prepare('UPDATE mcs_users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$user['id']]);}
  session_regenerate_id(true);$_SESSION['uid']=(int)$user['id'];$_SESSION['last_activity']=time();$_SESSION['csrf']=bin2hex(random_bytes(32));
  mcs_audit($db,(int)$user['id'],'user.login',(string)$user['email']);
  header('Location: /admin.php');exit;
 }catch(Throwable $e){$error='Unable to sign in. '.($e->getMessage()==='Too many attempts. Try again later.'?$e->getMessage():'Check your credentials.');}
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Operator Login · Myan Chat Support</title><link rel="icon" href="/brand-logo.php"><link rel="stylesheet" href="/assets/admin.css?v=1.0.2"></head><body class="auth-body"><main class="auth-card"><div class="mark"><?php if(is_file(__DIR__.'/../storage/brand-logo.png')||is_file(__DIR__.'/../storage/brand-logo.jpg')||is_file(__DIR__.'/../storage/brand-logo.webp')):?><img src="/brand-logo.php" alt="Myan Chat Support logo" class="auth-brand-image"><?php else:?>✦<?php endif;?></div><p class="eyebrow">OPERATOR CONSOLE · V1.0.2</p><h1>Welcome back.</h1><p class="muted">Sign in to manage customer conversations.</p><?php if($error):?><p class="notice error"><?=mcs_esc($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=mcs_esc(mcs_csrf())?>"><label>Email<input type="email" name="email" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button class="button primary full">Sign in →</button></form><p class="small-muted">Developed by Min SiThu</p></main></body></html>
