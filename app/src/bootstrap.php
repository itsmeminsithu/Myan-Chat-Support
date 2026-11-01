<?php
declare(strict_types=1);
const MCS_ROOT = __DIR__ . '/..';
require_once __DIR__.'/security.php';
require_once __DIR__.'/database.php';
require_once __DIR__.'/auth.php';
require_once __DIR__.'/chat.php';
date_default_timezone_set('UTC');
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
if (PHP_SAPI !== 'cli') {
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('MCS_FORCE_HTTPS')==='1';
  session_name('mcs_session');
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$https,'httponly'=>true,'samesite'=>'Lax']);
  $script=basename((string)($_SERVER['SCRIPT_NAME']??''));
  $publicActions=['site','guest_start','guest_poll','guest_send','guest_typing','guest_end','contact'];
  $needsSession=in_array($script,['login.php','logout.php','admin.php','demo.php'],true)
    || ($script==='api.php'&&!in_array((string)($_GET['action']??''),$publicActions,true));
  if ($needsSession&&session_status()!==PHP_SESSION_ACTIVE) { session_start(); }
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Permissions-Policy: microphone=(), camera=(), geolocation=()');
  header('X-Frame-Options: DENY');
}
function mcs_installed(): bool { return is_file(MCS_ROOT.'/storage/config.php'); }
function mcs_config(): array {
  static $config=null;
  if($config===null){
    if (!mcs_installed()) throw new RuntimeException('Not installed');
    $data=require MCS_ROOT.'/storage/config.php';
    if(!is_array($data)) throw new RuntimeException('Invalid configuration');
    $config=$data;
  }
  return $config;
}
function mcs_require_installed(): void { if(!mcs_installed()){header('Location: /install.php');exit;} }
function mcs_json(array $payload, int $status=200): never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function mcs_json_body(): array {
 if((int)($_SERVER['CONTENT_LENGTH']??0)>32768)mcs_json(['error'=>'Request too large'],413);
 if(!str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))mcs_json(['error'=>'JSON content type required'],415);
 $raw=file_get_contents('php://input',false,null,0,32769)?:'';if(strlen($raw)>32768)mcs_json(['error'=>'Request too large'],413);
 $data=json_decode($raw,true);
 if(!is_array($data)||array_is_list($data))mcs_json(['error'=>'Invalid JSON'],400);
 return $data;
}
function mcs_require_method(string $method): void {if(($_SERVER['REQUEST_METHOD']??'')!==$method)mcs_json(['error'=>'Method not allowed'],405);}
function mcs_esc(string $text): string {return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function mcs_public_url(): string {
 $cfg=mcs_config();return rtrim((string)($cfg['public_url']??''),'/');
}
