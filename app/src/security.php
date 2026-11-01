<?php
declare(strict_types=1);
function mcs_origin(string $value): ?string {
 $url=parse_url(trim($value));
 if(!$url||!isset($url['scheme'],$url['host']))return null;
 $scheme=strtolower((string)$url['scheme']);$host=strtolower((string)$url['host']);
 if(!in_array($scheme,['http','https'],true))return null;
 if(!preg_match('/^[a-z0-9.\-]+$/D',$host))return null;
 $port=isset($url['port'])?':'.(int)$url['port']:'';
 return $scheme.'://'.$host.$port;
}
function mcs_origins(string $value): array {
 $origins=[];
 foreach(preg_split('/[,\r\n\s]+/',$value) as $origin){
  if($origin==='')continue;
  $clean=mcs_origin($origin);
  if($clean===null)throw new InvalidArgumentException('Invalid origin: '.$origin);
  if($clean!==mcs_origin('http://localhost')&&str_starts_with($clean,'http://')&&!in_array(parse_url($clean,PHP_URL_HOST),['localhost','127.0.0.1'],true))throw new InvalidArgumentException('Only HTTPS is allowed for public domains');
  $origins[]=$clean;
 }
 return array_values(array_unique($origins));
}
function mcs_hex_color(string $value): string {return preg_match('/^#[0-9a-fA-F]{6}$/D',$value)?strtoupper($value):'#3865D8';}
function mcs_clean(string $value,int $limit=250): string {
 $value=trim(str_replace("\0",'',$value));
 return function_exists('mb_substr')?mb_substr($value,0,$limit,'UTF-8'):substr($value,0,$limit);
}
function mcs_email(string $value): ?string {$value=strtolower(trim($value));return filter_var($value,FILTER_VALIDATE_EMAIL)?substr($value,0,190):null;}
function mcs_page(string $page, array $origins): ?string {
 $page=trim($page);
 if(strlen($page)>4096||!in_array(mcs_origin($page),$origins,true))return null;
 $parsed=parse_url($page);
 if(!$parsed||isset($parsed['user'])||isset($parsed['pass']))return null;
 $path=(string)($parsed['path']??'/');
 // Query strings, fragment identifiers and URL credentials may contain auth tokens or PII.
 // Paths are retained for routing context. Normalize to origin + pathname only.
 if($path===''||$path[0]!=='/')$path='/';
 if(preg_match('~/(?:reset|password-reset|recover|verify|invite|callback|oauth|token)(?:/|$)~i',$path))$path='/[private-page]';
 $safe=mcs_origin($page).$path;
 return strlen($safe)<=1024?$safe:null;
}
function mcs_client_ip(): string {
 $remote=(string)($_SERVER['REMOTE_ADDR']??'unknown');
 $trusted=array_filter(array_map('trim',explode(',',(string)(getenv('MCS_TRUSTED_PROXIES')?:''))));
 if(!in_array($remote,$trusted,true))return $remote;
 // Only explicitly trusted proxies may supply a forwarded address. The deployment
 // must overwrite (not append) these headers at the trusted edge.
 $forwarded=trim(explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??''))[0]);
 return filter_var($forwarded,FILTER_VALIDATE_IP)?$forwarded:$remote;
}
function mcs_ip_hash(string $secret): string {return hash_hmac('sha256',mcs_client_ip(),$secret);}
function mcs_csp_nonce(): string {return base64_encode(random_bytes(18));}
