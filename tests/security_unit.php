<?php
declare(strict_types=1);
require __DIR__.'/../app/src/security.php';
require __DIR__.'/../app/src/chat.php';
function eq(mixed $actual,mixed $expected,string $label): void {
 if($actual!==$expected){fwrite(STDERR,"FAIL $label: ".var_export($actual,true)." != ".var_export($expected,true)."\n");exit(1);} echo "PASS $label\n";
}
eq(mcs_origin('https://Example.COM/path?q=1'),'https://example.com','canonical origin');
eq(mcs_origin('https://www.example.org:8443/hello'),'https://www.example.org:8443','origin with port');
eq(mcs_origin('javascript:alert(1)'),null,'JS URI rejected');
eq(mcs_origin('file:///etc/passwd'),null,'file URI rejected');
eq(mcs_origins("https://a.com\nhttps://a.com, https://b.com"),['https://a.com','https://b.com'],'origin dedup');
try{mcs_origins('http://example.com');fwrite(STDERR,"FAIL insecure public origin accepted\n");exit(1);}catch(InvalidArgumentException $e){echo "PASS reject HTTP public origin\n";}
eq(mcs_origins('http://localhost:8088'),['http://localhost:8088'],'allow local HTTP');
eq(mcs_hex_color('#abcdef'),'#ABCDEF','valid HEX color');
eq(mcs_hex_color('red;--x:evil'),'#3865D8','sanitize CSS color');
eq(mcs_page('https://a.com/hi?x=1',['https://a.com']),'https://a.com/hi','same-origin strips query');
eq(mcs_page('https://evil.com/hi',['https://a.com']),null,'reject foreign page');
eq(mcs_email('  Hello@Example.com '),'hello@example.com','email normalization');
eq(mcs_email('a@'),null,'reject invalid email');
eq(mcs_operator_can_reply(['role'=>'operator','id'=>9],['assigned_operator_id'=>9]),true,'assigned can reply');
eq(mcs_operator_can_reply(['role'=>'operator','id'=>8],['assigned_operator_id'=>9]),false,'unassigned cannot reply');
eq(mcs_operator_can_reply(['role'=>'admin','id'=>1],['assigned_operator_id'=>9]),true,'admin override');

eq(mcs_page('https://a.com/orders?q=secret#access_token=123',['https://a.com']),'https://a.com/orders','reject query and hash in page tracking');
eq(mcs_page('https://user:pass@a.com/private',['https://a.com']),null,'reject URL user credentials');
$_SERVER['REMOTE_ADDR']='10.0.0.99';$_SERVER['HTTP_X_FORWARDED_FOR']='203.0.113.5';
putenv('MCS_TRUSTED_PROXIES=');eq(mcs_client_ip(),'10.0.0.99','untrusted forwarded address ignored');
putenv('MCS_TRUSTED_PROXIES=10.0.0.99');eq(mcs_client_ip(),'203.0.113.5','trusted proxy forwarded address accepted');
$_SERVER['HTTP_X_FORWARDED_FOR']='spoofed';eq(mcs_client_ip(),'10.0.0.99','invalid forwarded address ignored');
putenv('MCS_TRUSTED_PROXIES');unset($_SERVER['HTTP_X_FORWARDED_FOR']);
eq(mcs_page('https://a.com/reset/secret-code?x=1',['https://a.com']),'https://a.com/[private-page]','sensitive auth path redaction');
