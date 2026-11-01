<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../app/src/bootstrap.php';
if(!mcs_installed()){fwrite(STDERR,"Run installer first\n");exit(1);}
$days=(int)($argv[1]??90);
if($days<30||$days>3650){fwrite(STDERR,"Usage: php scripts/cleanup.php [retention-days 30..3650]\n");exit(1);}
$db=mcs_pdo();$cutoff=gmdate('Y-m-d H:i:s',time()-$days*86400);
// Expires abandoned OPEN chats as well as resolved chats, using last-message activity.
// Contacts are removed after retention regardless of review status.
$st=$db->prepare('DELETE FROM mcs_conversations WHERE last_message_at < ?');$st->execute([$cutoff]);$chats=$st->rowCount();
$st=$db->prepare('DELETE FROM mcs_contacts WHERE created_at < ?');$st->execute([$cutoff]);$contacts=$st->rowCount();
$db->exec("DELETE FROM mcs_rate_limits WHERE window_start < UTC_TIMESTAMP() - INTERVAL 2 DAY");
$db->exec("DELETE FROM mcs_audit WHERE created_at < UTC_TIMESTAMP() - INTERVAL 365 DAY");
echo "Deleted $chats old conversations and $contacts old offline messages; retention $days days.\n";
