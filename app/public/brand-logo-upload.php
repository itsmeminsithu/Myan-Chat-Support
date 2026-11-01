<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
mcs_require_installed();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')mcs_json(['error'=>'POST required'],405);
$user=mcs_need_admin();
mcs_check_csrf((string)($_POST['csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')));
$base=__DIR__.'/../storage';
if(!is_dir($base)||!is_writable($base))mcs_json(['error'=>'Storage directory is not writable'],500);
$types=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
if(($_POST['action']??'')==='remove'){
 foreach(['png','jpg','webp'] as $ext)@unlink($base.'/brand-logo.'.$ext);
 mcs_audit(mcs_pdo(),(int)$user['id'],'brand.logo.remove','global');
 mcs_json(['ok'=>true,'removed'=>true]);
}
if(!isset($_FILES['logo'])||!is_array($_FILES['logo']))mcs_json(['error'=>'Choose a logo first'],422);
$file=$_FILES['logo'];
if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)mcs_json(['error'=>'Logo upload failed or exceeds server limit'],422);
$max=4*1024*1024;
$size=(int)($file['size']??0);
if($size<=0||$size>$max)mcs_json(['error'=>'Logo must be 4 MB or smaller'],422);
$tmp=(string)($file['tmp_name']??'');
if(!is_uploaded_file($tmp))mcs_json(['error'=>'Upload validation failed'],422);
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
$info=@getimagesize($tmp);
if(!is_string($mime)||!isset($types[$mime])||!is_array($info)||($info['mime']??'')!==$mime)mcs_json(['error'=>'Supported logos: real PNG, JPG and WebP images only'],422);
$width=(int)($info[0]??0);$height=(int)($info[1]??0);
if($width<16||$height<16||$width>4096||$height>4096)mcs_json(['error'=>'Logo dimensions must be between 16 and 4096 pixels'],422);
$ext=$types[$mime];
$dest=$base.'/brand-logo.'.$ext;
$partial=$base.'/logo-upload-'.bin2hex(random_bytes(12)).'.tmp';
if(!move_uploaded_file($tmp,$partial))mcs_json(['error'=>'Could not save logo'],500);
@chmod($partial,0640);
if(!rename($partial,$dest)){@unlink($partial);mcs_json(['error'=>'Could not finalize logo'],500);}
foreach(['png','jpg','webp'] as $other)if($other!==$ext)@unlink($base.'/brand-logo.'.$other);
mcs_audit(mcs_pdo(),(int)$user['id'],'brand.logo.upload',$width.'x'.$height.' '.strtoupper($ext));
mcs_json(['ok'=>true,'url'=>'/brand-logo.php','width'=>$width,'height'=>$height]);
