<?php
declare(strict_types=1);
// Images are served through this fixed-path route; never execute uploaded contents.
$base=__DIR__.'/../storage/brand-logo.';
$allowed=['png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp'];
foreach($allowed as $ext=>$mime){
 $file=$base.$ext;
 if(!is_file($file)||!is_readable($file))continue;
 header('Content-Type: '.$mime);
 header('Cache-Control: no-store, private');
 header('X-Content-Type-Options: nosniff');
 header("Content-Security-Policy: default-src 'none'; sandbox");
 header('Content-Length: '.filesize($file));
 readfile($file);
 exit;
}
http_response_code(404);
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
exit('No logo uploaded');
