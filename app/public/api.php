<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(!mcs_installed())mcs_json(['error'=>'Installation required'],503);
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
$action=(string)($_GET['action']??'');
try{
 $db=mcs_pdo();
 $public=['site','guest_start','guest_poll','guest_send','guest_typing','guest_end','contact'];
 if(in_array($action,$public,true)){
  if($action==='site'){
   mcs_require_method('GET');$site=mcs_site($db,(string)($_GET['site']??''));if(!$site)mcs_json(['error'=>'Website not found'],404);
   mcs_json(['site'=>['key'=>$site['site_key'],'title_en'=>$site['widget_title_en'],'title_mm'=>$site['widget_title_mm'],'welcome_en'=>$site['welcome_en'],'welcome_mm'=>$site['welcome_mm'],'color'=>mcs_hex_color($site['primary_color']),'default_language'=>$site['default_language'],'collect_email'=>(bool)$site['collect_email'],'allowed_origins'=>json_decode((string)$site['allowed_origins'],true)?:[],'online'=>mcs_site_online($db,(int)$site['id'])]]);
  }
  mcs_require_method('POST');$input=mcs_json_body();
  $secret=(string)mcs_config()['secret'];$ip=mcs_ip_hash($secret);
  if($action==='guest_start'||$action==='contact'){
   $site=mcs_site($db,(string)($input['site_key']??''));if(!$site)mcs_json(['error'=>'Unknown website'],404);
   $origins=json_decode((string)$site['allowed_origins'],true)?:[];
   $origin=mcs_origin((string)($input['origin']??''));
   if(!$origin||!in_array($origin,$origins,true))mcs_json(['error'=>'Website origin not allowed'],403);
   $page=mcs_page((string)($input['page_url']??''),$origins);
   if(!mcs_rate($db,'public:'.$action.':'.$ip.':'.$site['id'],8,3600))mcs_json(['error'=>'Please try again later'],429);
   // Honeypot stays empty during legitimate browser interactions.
   if(trim((string)($input['company']??''))!=='')mcs_json(['ok'=>true]);
   $name=mcs_clean((string)($input['name']??''),120);
   $email=mcs_email((string)($input['email']??''));
   if($name===''||($action==='contact'&&!$email))mcs_json(['error'=>'Name and valid email are required'],422);
   if($action==='contact'){
    $body=mcs_clean((string)($input['message']??''),3000);
    if(strlen($body)<4)mcs_json(['error'=>'Please enter a message'],422);
    $db->prepare('INSERT INTO mcs_contacts(site_id,name,email,message,page_url) VALUES(?,?,?,?,?)')->execute([$site['id'],$name,$email,$body,$page]);
    // Optional mail() notification: database is authoritative even if mail is unavailable.
    $to=(string)$site['contact_email'];
    if(filter_var($to,FILTER_VALIDATE_EMAIL)&&getenv('MCS_MAIL_NOTIFICATIONS')==='1'){
      @mail($to,'Myan Chat Support — offline contact',"New offline message. Sign in to your dashboard to review it.","From: no-reply@".preg_replace('/[^a-z0-9.-]/i','',parse_url(mcs_public_url(),PHP_URL_HOST)?:'localhost')."\r\nContent-Type: text/plain; charset=UTF-8");
    }
    mcs_json(['ok'=>true]);
   }
   if((bool)$site['collect_email']&&!$email)mcs_json(['error'=>'A valid email is required'],422);
   $token=bin2hex(random_bytes(32));$hash=hash_hmac('sha256',$token,$secret);
   $db->beginTransaction();
   try{
    $db->prepare('INSERT INTO mcs_conversations(site_id,guest_token_hash,guest_name,guest_email,guest_ip_hash,page_url) VALUES(?,?,?,?,?,?)')->execute([$site['id'],$hash,$name,$email,$ip,$page]);
    $cid=(int)$db->lastInsertId();
    mcs_insert_message($db,$cid,'system',mcs_clean((string)($input['language']??'en')==='mm'?$site['welcome_mm']:$site['welcome_en'],255));
    $db->commit();
   }catch(Throwable $e){$db->rollBack();throw $e;}
   mcs_json(['ok'=>true,'conversation_id'=>$cid,'token'=>$token,'messages'=>mcs_messages($db,$cid)]);
  }
  $cid=(int)($input['conversation_id']??0);$token=(string)($input['token']??'');$conversation=mcs_public_conv($db,$cid,$token);
  if(!$conversation)mcs_json(['error'=>'Session not found'],401);
  if($action==='guest_poll'){
   if(!mcs_rate($db,'poll:'.$ip.':'.$cid,120,60))mcs_json(['error'=>'Too many requests'],429);
   $after=max(0,(int)($input['after_id']??0));
   $typing=$db->prepare("SELECT 1 FROM mcs_typing WHERE conversation_id=? AND sender_type='operator' AND last_typing_at > UTC_TIMESTAMP() - INTERVAL 5 SECOND");$typing->execute([$cid]);
   mcs_json(['messages'=>mcs_messages($db,$cid,$after),'closed'=>$conversation['status']==='closed','typing'=>(bool)$typing->fetchColumn()]);
  }
  if($action==='guest_end'){
   $db->prepare("UPDATE mcs_conversations SET status='closed' WHERE id=? AND status='open'")->execute([$cid]);
   mcs_json(['ok'=>true]);
  }
  if($conversation['status']==='closed')mcs_json(['error'=>'Conversation closed'],409);
  if($action==='guest_send'){
   if(!mcs_rate($db,'guest-send:'.$ip.':'.$cid,20,60))mcs_json(['error'=>'Sending too quickly'],429);
   $body=mcs_clean((string)($input['body']??''),3000);
   if($body==='')mcs_json(['error'=>'Message cannot be empty'],422);
   $db->beginTransaction();
   try{
    $check=$db->prepare('SELECT status FROM mcs_conversations WHERE id=? FOR UPDATE');$check->execute([$cid]);
    if($check->fetchColumn()!=='open'){$db->rollBack();mcs_json(['error'=>'Conversation closed'],409);}
    $id=mcs_insert_message($db,$cid,'guest',$body);$db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
   mcs_json(['ok'=>true,'message_id'=>$id]);
  }
  if($action==='guest_typing'){
   if(!mcs_rate($db,'typing:'.$ip.':'.$cid,25,60))mcs_json(['error'=>'Sending too quickly'],429);
   $db->prepare("INSERT INTO mcs_typing(conversation_id,sender_type,last_typing_at) VALUES(?,'guest',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_typing_at=UTC_TIMESTAMP()")->execute([$cid]);mcs_json(['ok'=>true]);
  }
  mcs_json(['error'=>'Unknown action'],404);
 }
 // The operator API never supports cross-origin credentials or JSONP.
 $user=mcs_need_user();$uid=(int)$user['id'];
 // Site IDs are from the database, then cast to ints; never interpolate client input.
 $allowedSites=mcs_allowed_site_ids($db,$user);
 $scope=implode(',',array_map('intval',$allowedSites))?:'0';

 if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
  $input=mcs_json_body();mcs_check_csrf($_SERVER['HTTP_X_CSRF_TOKEN']??null);
 }else{$input=[];mcs_require_method('GET');}
 switch($action){
 case 'bootstrap':
  $sites=$db->query('SELECT id,site_key,name,allowed_origins,primary_color,widget_title_en,widget_title_mm,welcome_en,welcome_mm,default_language,contact_email,sound,collect_email FROM mcs_sites WHERE id IN ('.$scope.') ORDER BY id')->fetchAll();
  $operators=$user['role']==='admin'
   ?$db->query("SELECT id,name,email,role,CASE WHEN availability='offline' OR last_seen_at IS NULL OR last_seen_at < UTC_TIMESTAMP() - INTERVAL 90 SECOND THEN 'offline' ELSE availability END AS availability,is_active FROM mcs_users ORDER BY role,name")->fetchAll()
   :[['id'=>$uid,'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role'],'availability'=>$user['availability'],'is_active'=>1]];
  mcs_json(['user'=>$user,'sites'=>$sites,'operators'=>$operators,'operator_sites'=>$user['role']==='admin'?$db->query('SELECT user_id,site_id FROM mcs_user_sites')->fetchAll():[],'csrf'=>mcs_csrf(),'public_url'=>mcs_public_url()]);
 case 'alerts':
  $after=max(0,(int)($_GET['after_id']??0));
  if(($_GET['baseline']??'')==='1'){
   $max=$db->prepare("SELECT COALESCE(MAX(m.id),0) FROM mcs_messages m JOIN mcs_conversations c ON c.id=m.conversation_id WHERE m.sender_type='guest' AND c.site_id IN (".$scope.") AND (c.assigned_operator_id IS NULL OR c.assigned_operator_id=? OR ?='admin')");$max->execute([$uid,$user['role']]);mcs_json(['alerts'=>[],'latest_id'=>(int)$max->fetchColumn()]);
  }
  $st=$db->prepare("SELECT m.id,m.conversation_id,c.site_id FROM mcs_messages m JOIN mcs_conversations c ON c.id=m.conversation_id WHERE m.sender_type='guest' AND m.id>? AND c.site_id IN (".$scope.") AND (c.assigned_operator_id IS NULL OR c.assigned_operator_id=? OR ?='admin') ORDER BY m.id ASC LIMIT 50");
  $st->execute([$after,$uid,$user['role']]);mcs_json(['alerts'=>$st->fetchAll()]);
 case 'overview':
  $row=$db->query("SELECT COUNT(*) total,COALESCE(SUM(status='open'),0) open_count,COALESCE(SUM(status='closed'),0) closed_count FROM mcs_conversations WHERE site_id IN (".$scope.")")->fetch();
  $contacts=(int)$db->query("SELECT COUNT(*) FROM mcs_contacts WHERE status='new' AND site_id IN (".$scope.")")->fetchColumn();
  mcs_json(['stats'=>['all'=>(int)$row['total'],'open'=>(int)$row['open_count'],'closed'=>(int)$row['closed_count'],'contacts'=>$contacts]]);
 case 'conversations':
  $status=(string)($_GET['status']??'open');if(!in_array($status,['open','closed'],true))mcs_json(['error'=>'Invalid filter'],422);
  $search=mcs_clean((string)($_GET['q']??''),100);
  $siteId=max(0,(int)($_GET['site_id']??0));
  $sql="SELECT c.id,c.guest_name,c.guest_email,c.page_url,c.status,c.assigned_operator_id,c.created_at,c.last_message_at,s.name AS site_name,(SELECT body FROM mcs_messages m WHERE m.conversation_id=c.id ORDER BY id DESC LIMIT 1) AS last_message FROM mcs_conversations c JOIN mcs_sites s ON s.id=c.site_id WHERE c.status=? AND c.site_id IN (".$scope.")";
  $args=[$status];if($siteId){$sql.=' AND c.site_id=?';$args[]=$siteId;}
  if($search!==''){$sql.=' AND (c.guest_name LIKE ? OR c.guest_email LIKE ?)';$args[]='%'.$search.'%';$args[]='%'.$search.'%';}
  $before=max(0,(int)($_GET['before_id']??0));$beforeAt=(string)($_GET['before_at']??'');
  if($before&&preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$beforeAt)){
   $sql.=' AND (c.last_message_at<? OR (c.last_message_at=? AND c.id<?))';
   $args[]=$beforeAt;$args[]=$beforeAt;$args[]=$before;
  }
  $sql.=' ORDER BY c.last_message_at DESC,c.id DESC LIMIT 100';$st=$db->prepare($sql);$st->execute($args);
  $rows=$st->fetchAll();$last=count($rows)===100?end($rows):null;
  mcs_json(['conversations'=>$rows,'next_before_id'=>$last?(int)$last['id']:null,'next_before_at'=>$last?$last['last_message_at']:null]);
 case 'messages':
  $cid=max(1,(int)($_GET['conversation_id']??0));$conv=mcs_require_conversation($db,$user,$cid);
  $before=max(0,(int)($_GET['before_id']??0));
  if($before===0&&mcs_operator_can_reply($user,$conv))$db->prepare("UPDATE mcs_messages SET read_by_operator_at=UTC_TIMESTAMP() WHERE conversation_id=? AND sender_type='guest' AND read_by_operator_at IS NULL")->execute([$cid]);
  $typing=$db->prepare("SELECT 1 FROM mcs_typing WHERE conversation_id=? AND sender_type='guest' AND last_typing_at > UTC_TIMESTAMP() - INTERVAL 5 SECOND");$typing->execute([$cid]);
  $batch=mcs_messages($db,$cid,0,$before);$oldest=(int)($batch[0]['id']??0);unset($conv['guest_token_hash'],$conv['guest_ip_hash']);mcs_json(['conversation'=>$conv,'messages'=>$batch,'has_older'=>$oldest>0&&mcs_has_older_messages($db,$cid,$oldest),'typing'=>(bool)$typing->fetchColumn()]);
 case 'claim':
  mcs_require_method('POST');$cid=(int)($input['conversation_id']??0);if($cid<1)mcs_json(['error'=>'Invalid chat'],422);mcs_require_conversation($db,$user,$cid);
  $st=$db->prepare("UPDATE mcs_conversations SET assigned_operator_id=? WHERE id=? AND status='open' AND assigned_operator_id IS NULL");$st->execute([$uid,$cid]);
  if($st->rowCount()!==1)mcs_json(['error'=>'Another operator is already handling this conversation'],409);
  mcs_audit($db,$uid,'chat.claim',(string)$cid);mcs_json(['ok'=>true]);
 case 'assign':
  mcs_require_method('POST');mcs_need_admin();$cid=(int)($input['conversation_id']??0);$target=(int)($input['operator_id']??0);
  mcs_require_conversation($db,$user,$cid);
  $check=$db->prepare('SELECT id FROM mcs_users WHERE id=? AND is_active=1');$check->execute([$target]);if(!$check->fetchColumn())mcs_json(['error'=>'Invalid operator'],422);$conv=mcs_require_conversation($db,$user,$cid);if(!mcs_can_access_site($db,['id'=>$target,'role'=>'operator'],(int)$conv['site_id']))mcs_json(['error'=>'Assign operator to this website first'],422);
  $st=$db->prepare("UPDATE mcs_conversations SET assigned_operator_id=? WHERE id=? AND status='open'");$st->execute([$target,$cid]);if($st->rowCount()!==1)mcs_json(['error'=>'Unable to assign chat'],409);
  mcs_audit($db,$uid,'chat.transfer',$cid.':'.$target);mcs_json(['ok'=>true]);
 case 'reply':
  mcs_require_method('POST');$cid=(int)($input['conversation_id']??0);$body=mcs_clean((string)($input['body']??''),3000);
  if($body==='')mcs_json(['error'=>'Empty message'],422);
  if(!mcs_rate($db,'operator-reply:'.$uid,80,60))mcs_json(['error'=>'Too many messages'],429);
  mcs_require_conversation($db,$user,$cid);
  $db->beginTransaction();try{
   $st=$db->prepare('SELECT * FROM mcs_conversations WHERE id=? FOR UPDATE');$st->execute([$cid]);$conv=$st->fetch();
   if(!$conv||$conv['status']!=='open'){ $db->rollBack();mcs_json(['error'=>'Chat is closed or missing'],409); }
   if(!mcs_operator_can_reply($user,$conv)){$db->rollBack();mcs_json(['error'=>'Claim this chat first'],403);}
   $id=mcs_insert_message($db,$cid,'operator',$body,$uid);$db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  mcs_json(['ok'=>true,'message_id'=>$id]);
 case 'close':
  mcs_require_method('POST');$cid=(int)($input['conversation_id']??0);
  mcs_require_conversation($db,$user,$cid);
  $st=$db->prepare("UPDATE mcs_conversations SET status='closed' WHERE id=? AND status='open' AND (assigned_operator_id=? OR ?='admin')");$st->execute([$cid,$uid,$user['role']]);
  if($st->rowCount()!==1)mcs_json(['error'=>'Cannot close this conversation'],403);
  mcs_audit($db,$uid,'chat.close',(string)$cid);mcs_json(['ok'=>true]);
 case 'operator_typing':
  mcs_require_method('POST');$cid=(int)($input['conversation_id']??0);
  $st=$db->prepare('SELECT * FROM mcs_conversations WHERE id=?');$st->execute([$cid]);$conv=$st->fetch();if(!$conv)mcs_json(['error'=>'Forbidden'],403);mcs_require_site($db,$user,(int)$conv['site_id']);if($conv['status']!=='open'||!mcs_operator_can_reply($user,$conv))mcs_json(['error'=>'Forbidden'],403);
  $db->prepare("INSERT INTO mcs_typing(conversation_id,sender_type,last_typing_at) VALUES(?,'operator',UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE last_typing_at=UTC_TIMESTAMP()")->execute([$cid]);mcs_json(['ok'=>true]);
 case 'contacts':
  $before=max(0,(int)($_GET['before_id']??0));$sql='SELECT id,site_id,name,email,message,page_url,status,created_at FROM mcs_contacts WHERE site_id IN ('.$scope.')';$args=[];
  if($before){$sql.=' AND id<?';$args[]=$before;}$sql.=' ORDER BY id DESC LIMIT 100';$st=$db->prepare($sql);$st->execute($args);
  $rows=$st->fetchAll();mcs_json(['contacts'=>$rows,'next_before_id'=>count($rows)===100?(int)end($rows)['id']:null]);
 case 'contact_review':
  mcs_require_method('POST');$id=(int)($input['id']??0);$st=$db->prepare('SELECT site_id FROM mcs_contacts WHERE id=?');$st->execute([$id]);$site=(int)$st->fetchColumn();mcs_require_site($db,$user,$site);$db->prepare("UPDATE mcs_contacts SET status='reviewed' WHERE id=?")->execute([$id]);mcs_audit($db,$uid,'contact.review',(string)$id);mcs_json(['ok'=>true]);
 case 'canned':
  $before=max(0,(int)($_GET['before_id']??0));$sql='SELECT id,site_id,shortcut,body FROM mcs_canned WHERE site_id IN ('.$scope.')';$args=[];
  if($before){$sql.=' AND id<?';$args[]=$before;}$sql.=' ORDER BY id DESC LIMIT 100';$st=$db->prepare($sql);$st->execute($args);
  $rows=$st->fetchAll();mcs_json(['replies'=>$rows,'next_before_id'=>count($rows)===100?(int)end($rows)['id']:null]);
 case 'canned_add':
  mcs_require_method('POST');$site=(int)($input['site_id']??0);$shortcut=mcs_clean((string)($input['shortcut']??''),60);$body=mcs_clean((string)($input['body']??''),3000);
  mcs_require_site($db,$user,$site);if(!preg_match('~^/[a-z0-9_-]{1,40}$~i',$shortcut)||$body==='')mcs_json(['error'=>'Invalid shortcut or body'],422);
  $db->prepare('INSERT INTO mcs_canned(site_id,shortcut,body) VALUES(?,?,?) ON DUPLICATE KEY UPDATE body=VALUES(body)')->execute([$site,$shortcut,$body]);mcs_audit($db,$uid,'canned.save',$shortcut);mcs_json(['ok'=>true]);
 case 'operator_add':
  mcs_require_method('POST');mcs_need_admin();$name=mcs_clean((string)($input['name']??''),100);$email=mcs_email((string)($input['email']??''));$password=(string)($input['password']??'');
  if($name===''||!$email||strlen($password)<12||strlen($password)>200)mcs_json(['error'=>'Name, valid email and 12+ char password required'],422);
  $siteIds=array_values(array_unique(array_map('intval',is_array($input['site_ids']??null)?$input['site_ids']:[])));
  if(!$siteIds)mcs_json(['error'=>'Assign at least one website'],422);
  foreach($siteIds as $siteId){if(!in_array($siteId,$allowedSites,true))mcs_json(['error'=>'Invalid website selection'],422);}
  $db->beginTransaction();try{
   $db->prepare("INSERT INTO mcs_users(name,email,password_hash,role) VALUES(?,?,?,'operator')")->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
   $newUid=(int)$db->lastInsertId();$grant=$db->prepare('INSERT INTO mcs_user_sites(user_id,site_id) VALUES(?,?)');foreach($siteIds as $siteId)$grant->execute([$newUid,$siteId]);$db->commit();
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}mcs_audit($db,$uid,'operator.add',$email);mcs_json(['ok'=>true]);
 case 'operator_sites':
  mcs_require_method('POST');mcs_need_admin();$target=(int)($input['operator_id']??0);
  $check=$db->prepare("SELECT role FROM mcs_users WHERE id=? AND is_active=1");$check->execute([$target]);if($check->fetchColumn()!=='operator')mcs_json(['error'=>'Invalid operator'],422);
  $siteIds=array_values(array_unique(array_map('intval',is_array($input['site_ids']??null)?$input['site_ids']:[])));
  foreach($siteIds as $siteId)if(!in_array($siteId,$allowedSites,true))mcs_json(['error'=>'Invalid website'],422);
  $db->beginTransaction();try{$db->prepare('DELETE FROM mcs_user_sites WHERE user_id=?')->execute([$target]);$grant=$db->prepare('INSERT INTO mcs_user_sites(user_id,site_id) VALUES(?,?)');foreach($siteIds as $siteId)$grant->execute([$target,$siteId]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  mcs_audit($db,$uid,'operator.sites',(string)$target);mcs_json(['ok'=>true]);
 case 'availability':
  mcs_require_method('POST');$status=(string)($input['status']??'');if(!in_array($status,['online','away','offline'],true))mcs_json(['error'=>'Invalid status'],422);
  $db->prepare('UPDATE mcs_users SET availability=?,last_seen_at=UTC_TIMESTAMP() WHERE id=?')->execute([$status,$uid]);mcs_json(['ok'=>true,'availability'=>$status]);
 case 'site_add':
  mcs_require_method('POST');mcs_need_admin();$name=mcs_clean((string)($input['name']??''),120);$email=mcs_email((string)($input['contact_email']??''));
  try{$origins=mcs_origins((string)($input['origins']??''));}catch(InvalidArgumentException $e){mcs_json(['error'=>$e->getMessage()],422);}
  if($name===''||!$email||!$origins)mcs_json(['error'=>'Name, email and domain origins required'],422);
  $key=bin2hex(random_bytes(24));$db->prepare('INSERT INTO mcs_sites(site_key,name,allowed_origins,contact_email) VALUES(?,?,?,?)')->execute([$key,$name,json_encode($origins),$email]);
  mcs_audit($db,$uid,'site.add',$name);mcs_json(['ok'=>true,'site_key'=>$key]);
 case 'site_update':
  mcs_require_method('POST');mcs_need_admin();$id=(int)($input['site_id']??0);
  try{$origins=mcs_origins((string)($input['allowed_origins']??''));}catch(InvalidArgumentException $e){mcs_json(['error'=>$e->getMessage()],422);}
  if(!$origins)mcs_json(['error'=>'At least one origin required'],422);
  $contactEmail=mcs_email((string)($input['contact_email']??''));if(!$contactEmail)mcs_json(['error'=>'Valid contact email required'],422);
  $lang=in_array($input['default_language']??'en',['en','mm'],true)?$input['default_language']:'en';$sound=in_array($input['sound']??'chime',['chime','ping','bell','soft','double','bright','wood','ripple','crystal','pop','pulse','none'],true)?$input['sound']:'chime';
  $stmt=$db->prepare('UPDATE mcs_sites SET allowed_origins=?,contact_email=?,primary_color=?,default_language=?,sound=?,collect_email=?,widget_title_en=?,widget_title_mm=?,welcome_en=?,welcome_mm=? WHERE id=?');
  $stmt->execute([json_encode($origins),$contactEmail,mcs_hex_color((string)($input['primary_color']??'')),$lang,$sound,!empty($input['collect_email'])?1:0,mcs_clean((string)($input['widget_title_en']??''),120),mcs_clean((string)($input['widget_title_mm']??''),120),mcs_clean((string)($input['welcome_en']??''),255),mcs_clean((string)($input['welcome_mm']??''),255),$id]);
  mcs_audit($db,$uid,'site.update',(string)$id);mcs_json(['ok'=>true]);
 default:mcs_json(['error'=>'Unknown action'],404);
 }
}catch(PDOException $e){error_log('Database error: '.$e->getMessage());mcs_json(['error'=>'Database request failed'],500);}catch(Throwable $e){error_log('Application error: '.$e->getMessage());mcs_json(['error'=>'Unexpected server error'],500);}
