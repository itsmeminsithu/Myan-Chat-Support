<?php
declare(strict_types=1);
function mcs_public_conv(PDO $db,int $conversationId,string $token): ?array {
 if($conversationId<1||!preg_match('/^[0-9a-f]{64}$/D',$token))return null;
 $s=$db->prepare('SELECT c.*,s.allowed_origins FROM mcs_conversations c INNER JOIN mcs_sites s ON s.id=c.site_id WHERE c.id=? AND c.created_at > UTC_TIMESTAMP() - INTERVAL 30 DAY LIMIT 1');$s->execute([$conversationId]);$c=$s->fetch();
 if(!$c)return null;
 $hash=hash_hmac('sha256',$token,(string)mcs_config()['secret']);
 return hash_equals((string)$c['guest_token_hash'],$hash)?$c:null;
}
/** Bounded keyset paging. After-id provides forward updates; before-id retrieves older history;
 * with neither given, return the latest 100 rather than the oldest 100. */
function mcs_messages(PDO $db,int $conversationId,int $afterId=0,int $beforeId=0,int $limit=100): array {
 $limit=max(1,min(100,$limit));
 $sql='SELECT m.id,m.sender_type,m.body,m.created_at,u.name AS operator_name FROM mcs_messages m LEFT JOIN mcs_users u ON u.id=m.sender_user_id WHERE m.conversation_id=?';
 $args=[$conversationId];
 if($afterId>0){$sql.=' AND m.id>?';$args[]=$afterId;$sql.=' ORDER BY m.id ASC LIMIT '.$limit;}
 else {$sql.=($beforeId>0?' AND m.id<?':'');if($beforeId>0)$args[]=$beforeId;$sql.=' ORDER BY m.id DESC LIMIT '.$limit;}
 $s=$db->prepare($sql);$s->execute($args);$rows=$s->fetchAll();
 return $afterId>0?$rows:array_reverse($rows);
}
function mcs_insert_message(PDO $db,int $conversationId,string $type,string $body,?int $userId=null): int {
 $s=$db->prepare('INSERT INTO mcs_messages(conversation_id,sender_type,sender_user_id,body) VALUES(?,?,?,?)');$s->execute([$conversationId,$type,$userId,$body]);
 $db->prepare('UPDATE mcs_conversations SET last_message_at=UTC_TIMESTAMP() WHERE id=?')->execute([$conversationId]);
 return (int)$db->lastInsertId();
}
function mcs_operator_can_reply(array $user,array $conv): bool {
 return $user['role']==='admin'||(int)($conv['assigned_operator_id']??0)===(int)$user['id'];
}

function mcs_has_older_messages(PDO $db,int $conversationId,int $beforeId=0): bool {
 $s=$db->prepare('SELECT id FROM mcs_messages WHERE conversation_id=? AND id<? ORDER BY id DESC LIMIT 1');
 if($beforeId===0){$latest=$db->prepare('SELECT id FROM mcs_messages WHERE conversation_id=? ORDER BY id DESC LIMIT 100');$latest->execute([$conversationId]);$ids=$latest->fetchAll(PDO::FETCH_COLUMN);if(!$ids)return false;$beforeId=(int)end($ids);}
 $s->execute([$conversationId,$beforeId]);return (bool)$s->fetchColumn();
}
function mcs_site_online(PDO $db,int $siteId): bool {
 $s=$db->prepare("SELECT 1 FROM mcs_users u JOIN mcs_user_sites us ON us.user_id=u.id WHERE us.site_id=? AND u.is_active=1 AND u.availability='online' AND u.last_seen_at > UTC_TIMESTAMP() - INTERVAL 90 SECOND LIMIT 1");
 $s->execute([$siteId]);if($s->fetchColumn())return true;
 // Administrators intentionally cover every site in this one business installation.
 return (bool)$db->query("SELECT 1 FROM mcs_users WHERE role='admin' AND is_active=1 AND availability='online' AND last_seen_at > UTC_TIMESTAMP() - INTERVAL 90 SECOND LIMIT 1")->fetchColumn();
}
