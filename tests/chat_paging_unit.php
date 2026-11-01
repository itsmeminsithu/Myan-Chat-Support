<?php
declare(strict_types=1);
require __DIR__.'/../app/src/chat.php';
require __DIR__.'/../app/src/auth.php';
function ok(bool $test,string $description): void {if(!$test){fwrite(STDERR,"FAIL $description\n");exit(1);}echo "PASS $description\n";}
class MockDatabase extends PDO {
 public array $messages=[];
 public array $assignments=[];
 public function __construct(){}
 public function prepare(string $query,array $options=[]): PDOStatement|false {return new MockStatement($this,$query);}
}
class MockStatement extends PDOStatement {
 private array $data=[];
 public function __construct(private MockDatabase $db,private string $query){}
 public function execute(?array $params=null): bool {
  $params=$params??[];$this->data=[];
  if(str_contains($this->query,'FROM mcs_messages')){
   $rows=array_values(array_filter($this->db->messages,static fn($m)=>(int)$m['conversation_id']===(int)$params[0]));
   if(str_contains($this->query,'m.id>?')){$rows=array_values(array_filter($rows,fn($x)=>$x['id']>(int)$params[1]));}
   if(str_contains($this->query,'m.id<?')){$rows=array_values(array_filter($rows,fn($x)=>$x['id']<(int)$params[1]));}
   if(str_contains($this->query,' id<?')){$rows=array_values(array_filter($rows,fn($x)=>$x['id']<(int)$params[1]));}
   usort($rows,fn($a,$b)=>str_contains($this->query,'DESC')?$b['id']<=>$a['id']:$a['id']<=>$b['id']);
   preg_match('/LIMIT (\d+)/',$this->query,$m);$this->data=array_slice($rows,0,(int)($m[1]??100));
  }elseif(str_contains($this->query,'FROM mcs_user_sites')){
   $user=(int)$params[0];$site=(int)($params[1]??0);
   $ids=$this->db->assignments[$user]??[];
   $this->data=isset($params[1])?(in_array($site,$ids,true)?[[1]]:[]):array_map(fn($id)=>['site_id'=>$id],$ids);
  }
  return true;
 }
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {
  if($mode===PDO::FETCH_COLUMN)return array_map(static fn($x)=>reset($x),$this->data);
  return $this->data;
 }
 public function fetchColumn(int $column=0): mixed {return isset($this->data[0])?array_values($this->data[0])[$column]??false:false;}
}
$db=new MockDatabase();foreach(range(1,500) as $id)$db->messages[]=['id'=>$id,'conversation_id'=>42,'sender_type'=>'guest','body'=>'Message '.$id,'created_at'=>'2026-01-01 00:00:00','operator_name'=>null];
$first=mcs_messages($db,42);ok(count($first)===100&&$first[0]['id']===401&&end($first)['id']===500,'latest 100 for operator; not oldest');
$older=mcs_messages($db,42,0,401);ok(count($older)===100&&$older[0]['id']===301&&end($older)['id']===400,'older keyset page');
$newer=mcs_messages($db,42,499);ok(count($newer)===1&&$newer[0]['id']===500,'guest incremental after_id');
$last=mcs_messages($db,42,0,101);ok(count($last)===100&&$last[0]['id']===1,'oldest page accessible');
$none=mcs_messages($db,42,0,1);ok($none===[],'no more history before first id');
$db->assignments=[10=>[3],11=>[4]];
ok(mcs_can_access_site($db,['role'=>'operator','id'=>10],3),'operator can access own website');
ok(!mcs_can_access_site($db,['role'=>'operator','id'=>10],4),'operator denied other website');
ok(mcs_can_access_site($db,['role'=>'admin','id'=>1],4),'admin can access every site');
