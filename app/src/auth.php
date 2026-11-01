<?php
declare(strict_types=1);
function mcs_csrf(): string {
 if(PHP_SAPI!=='cli'&&session_status()!==PHP_SESSION_ACTIVE)session_start();
 if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));
 return $_SESSION['csrf'];
}
function mcs_check_csrf(?string $token): void {if(!is_string($token)||!hash_equals(mcs_csrf(),$token))mcs_json(['error'=>'Invalid CSRF token'],403);}
function mcs_user(): ?array {
 if(empty($_SESSION['uid']))return null;
 if(time()-(int)($_SESSION['last_activity']??0)>3600){mcs_logout();return null;}
 $_SESSION['last_activity']=time();
 $stmt=mcs_pdo()->prepare('SELECT id,name,email,role,is_active,availability FROM mcs_users WHERE id=? LIMIT 1');$stmt->execute([(int)$_SESSION['uid']]);$u=$stmt->fetch();
 return ($u&&(int)$u['is_active']===1)?$u:null;
}
function mcs_need_user(): array {$u=mcs_user();if(!$u)mcs_json(['error'=>'Authentication required'],401);return $u;}
function mcs_need_admin(): array {$u=mcs_need_user();if($u['role']!=='admin')mcs_json(['error'=>'Admin required'],403);return $u;}
function mcs_logout(): void {
 $_SESSION=[];
 if(session_status()===PHP_SESSION_ACTIVE){
  if(ini_get('session.use_cookies')){
   $p=session_get_cookie_params();
   if(!headers_sent())setcookie(session_name(),'', ['expires'=>time()-42000,'path'=>$p['path'],'domain'=>$p['domain'],'secure'=>$p['secure'],'httponly'=>$p['httponly'],'samesite'=>$p['samesite']??'Lax']);
  }
  session_destroy();
 }
}

function mcs_allowed_site_ids(PDO $db,array $user): array {
 if(($user['role']??'')==='admin')return array_map('intval',$db->query('SELECT id FROM mcs_sites')->fetchAll(PDO::FETCH_COLUMN));
 $s=$db->prepare('SELECT site_id FROM mcs_user_sites WHERE user_id=?');$s->execute([(int)$user['id']]);
 return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
}
function mcs_can_access_site(PDO $db,array $user,int $siteId): bool {
 if($siteId<1)return false;
 if(($user['role']??'')==='admin')return true;
 $s=$db->prepare('SELECT 1 FROM mcs_user_sites WHERE user_id=? AND site_id=? LIMIT 1');
 $s->execute([(int)$user['id'],$siteId]);return (bool)$s->fetchColumn();
}
function mcs_require_site(PDO $db,array $user,int $siteId): void {
 if(!mcs_can_access_site($db,$user,$siteId))mcs_json(['error'=>'Website access denied'],403);
}
function mcs_require_conversation(PDO $db,array $user,int $cid): array {
 $s=$db->prepare('SELECT * FROM mcs_conversations WHERE id=? LIMIT 1');$s->execute([$cid]);$conv=$s->fetch();
 if(!$conv)mcs_json(['error'=>'Conversation not found'],404);
 mcs_require_site($db,$user,(int)$conv['site_id']);return $conv;
}
