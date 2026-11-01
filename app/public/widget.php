<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';mcs_require_installed();
$site=mcs_site(mcs_pdo(),(string)($_GET['site']??''));
if(!$site){http_response_code(404);exit('Unknown widget');}
$origins=json_decode((string)$site['allowed_origins'],true)?:[];
// The widget is iframe-embeddable only by the registered websites.
header_remove('X-Frame-Options');
$frame=implode(' ',array_map(static fn($o)=>mcs_origin((string)$o)?:"'none'",$origins));
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; form-action 'none'; object-src 'none'; frame-ancestors 'self' ".$frame."; script-src 'self'; style-src 'self'; connect-src 'self'; img-src 'self' data:; font-src 'self'");
header('Cache-Control: no-store');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Myan Chat Support</title><link rel="icon" href="/brand-logo.php"><link rel="stylesheet" href="/assets/widget.css?v=1.0.2"><script src="/assets/widget-frame.js?v=1.0.2" defer></script></head><body><div id="widget" data-site="<?=mcs_esc((string)$site['site_key'])?>"><div id="widgetRoot"></div></div><p class="visually-hidden">Developed by Min SiThu</p></body></html>
