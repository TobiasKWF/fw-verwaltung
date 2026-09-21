<?php
require __DIR__.'/../src/db.php';
$vehicles=$db->query("SELECT * FROM vehicles WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$people=$db->query("SELECT * FROM personnel WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$action=$_GET['action']??'home';
$editId=(int)($_GET['id']??0);
$diveraAccessKey=(string)($db->query("SELECT value FROM settings WHERE key='divera_access_key'")->fetchColumn()??'');

function diveraJson(string $url,string $key):?array{
 $ch=curl_init($url.'?accesskey='.rawurlencode($key));curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'FwDesk-Halchter/1.0']);
 $response=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($response===false||$http<200||$http>=300)return null;
 $payload=json_decode($response,true);
 return is_array($payload)?$payload:null;
}
function diveraStatusName(array $statusMap,int $statusId):string{
 $known=[56001=>'Nicht Einsatzbereit',55475=>'sofort',56004=>'innerhalb 10 min',56005=>'30 minuten'];
 if(isset($known[$statusId]))return $known[$statusId];
 $s=$statusMap[(string)$statusId]??$statusMap[$statusId]??null;
 if(is_array($s))return trim((string)($s['title']??$s['name']??$s['label']??''));
 return is_string($s)?trim($s):'';
}
function diveraCollectResponses(array $alarm,array $statusMap):array{
 $raw=$alarm['ucr_answered']??[];
 $out=[];
 $add=function($ucr,$value)use(&$out,$statusMap){
  $obj=is_array($value)?$value:[];
  $id=trim((string)($obj['ucr_id']??$obj['user_cluster_relation_id']??$obj['user_id']??$ucr??''));
  if($id==='')return;
  $statusId=(int)($obj['status_id']??($obj['status']['id']??0));
  $statusName=trim((string)($obj['status_name']??($obj['status']['title']??($obj['status']['name']??''))));
  if($statusName==='')$statusName=diveraStatusName($statusMap,$statusId);
  $note=trim((string)($obj['note']??($obj['status_note']??'')));
  $ts=(int)($obj['ts']??($obj['timestamp']??($obj['date']??0)));
  $out[$id]=['ucr_id'=>$id,'status_id'=>$statusId,'status_name'=>$statusName,'note'=>$note,'responded_at'=>$ts];
 };
 if(is_array($raw))foreach($raw as $k=>$v){
  if(is_array($v)&&isset($v['ts'])){
   $add($k,$v);
   continue;
  }
  if(is_array($v)){
   foreach($v as $innerId=>$innerValue){
    if(is_array($innerValue))$add($innerId,$innerValue);
    else $add($innerId,[]);
   }
  }else $add($v,[]);
 }
 return array_values($out);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $form=$_POST['form']??'';
 if($form==='delete_photo'){
  $photoId=(int)($_POST['photo_id']??0);$incidentId=(int)($_POST['incident_id']??0);
  if($photoId>0&&$incidentId>0){
   $q=$db->prepare('SELECT filename FROM incident_photos WHERE id=? AND incident_id=?');$q->execute([$photoId,$incidentId]);$filename=$q->fetchColumn();
   if($filename!==false){
    $path='/var/www/data/uploads/incidents/'.basename((string)$filename);if(is_file($path))@unlink($path);
    $db->prepare('DELETE FROM incident_photos WHERE id=? AND incident_id=?')->execute([$photoId,$incidentId]);
   }
  }
  header('Location:?action=edit&id='.$incidentId);exit;
 }
 if($form==='delete_incident'){
  $incidentId=(int)($_POST['id']??0);
  if($incidentId>0){
   $q=$db->prepare('SELECT filename FROM incident_photos WHERE incident_id=?');$q->execute([$incidentId]);$photosToDelete=$q->fetchAll(PDO::FETCH_COLUMN);
   $db->beginTransaction();
   try{
    $db->prepare('DELETE FROM incidents WHERE id=?')->execute([$incidentId]);
    $db->commit();
    foreach($photosToDelete as $filename){$path='/var/www/data/uploads/incidents/'.basename((string)$filename);if(is_file($path))@unlink($path);}
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
  }
  header('Location:?deleted=1');exit;
 }
 if($form==='delete_person'){
  $id=(int)($_POST['id']??0);
  if($id>0){
   $check=$db->prepare('SELECT COUNT(*) FROM incident_personnel WHERE personnel_id=?');$check->execute([$id]);
   if((int)$check->fetchColumn()===0){$db->prepare('DELETE FROM personnel WHERE id=?')->execute([$id]);header('Location:?action=master&person=deleted');exit;}
   header('Location:?action=master&person=in_use');exit;
  }
  header('Location:?action=master');exit;
 }
 if($form==='delete_vehicle'){
  $id=(int)($_POST['id']??0);
  if($id>0){
   $check=$db->prepare('SELECT COUNT(*) FROM incident_vehicles WHERE vehicle_id=?');$check->execute([$id]);
   if((int)$check->fetchColumn()===0){$db->prepare('DELETE FROM vehicles WHERE id=?')->execute([$id]);header('Location:?action=master&vehicle=deleted');exit;}
   header('Location:?action=master&vehicle=in_use');exit;
  }
  header('Location:?action=master');exit;
 }
 if($form==='divera_settings'){
  $key=trim($_POST['divera_access_key']??'');
  $s=$db->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');$s->execute(['divera_access_key',$key]);
  header('Location:?action=master&divera=saved');exit;
 }
 if($form==='divera_import_one'){
  $key=$diveraAccessKey;$foreign=trim((string)($_POST['divera_incident_id']??''));
  if($key===''){header('Location:?divera=no_key');exit;}
  if($foreign===''){header('Location:?divera=single_error&msg='.rawurlencode('Bitte eine Divera EinsatzID eingeben'));exit;}
  $payload=diveraJson('https://divera247.com/api/v2/alarms/'.$foreign,$key);
  if(!is_array($payload)){header('Location:?divera=single_error&msg='.rawurlencode('Divera liefert keine gültige Antwort'));exit;}
  if(isset($payload['success'])&&$payload['success']!==true){$msg=(string)($payload['message']??$payload['error']??'Divera hat den Einsatzimport abgelehnt');header('Location:?divera=single_error&msg='.rawurlencode($msg));exit;}
  $item=$payload['data']??$payload;
  if(isset($item['alarm'])&&is_array($item['alarm']))$item=$item['alarm'];
  if(!is_array($item))$item=[];
  $title=trim((string)($item['title']??$item['name']??''));
  $address=trim((string)($item['address']??''));
  $alarmId=(int)($item['id']??$foreign);
  $foreignId=trim((string)($item['foreign_id']??$item['id']??$foreign));if($foreignId==='')$foreignId=$foreign;
  if($title===''){header('Location:?divera=single_error&msg='.rawurlencode('Divera Einsatz wurde nicht gefunden oder enthält keinen Titel'));exit;}
  $check=$db->prepare('SELECT id FROM incidents WHERE divera_id=? OR incident_number=? LIMIT 1');$check->execute([$foreignId,$foreignId]);
  if($existing=(int)($check->fetchColumn()?:0)){header('Location:?divera=single_exists&id='.$existing);exit;}
  $rawTs=$item['date']??$item['timestamp']??$item['created_at']??time();$ts=is_numeric($rawTs)?(int)$rawTs:strtotime((string)$rawTs);if(!$ts)$ts=time();
  $ins=$db->prepare('INSERT INTO incidents(incident_number,title,location,incident_date,alarm_time,divera_id) VALUES(?,?,?,?,?,?)');
  $ins->execute([$foreignId,$title,$address,date('Y-m-d',$ts),date('H:i',$ts),$foreignId]);$incidentId=(int)$db->lastInsertId();
  $statusMap=[];$pull=diveraJson('https://divera247.com/api/v2/pull/all',$key);if(is_array($pull))$statusMap=$pull['data']['cluster']['status']??[];
  $usersPayload=diveraJson('https://divera247.com/api/users',$key);$userStatusMap=[];
  if(is_array($usersPayload)){ $uitems=$usersPayload['data']['items']??$usersPayload['items']??$usersPayload['data']??[];if(isset($uitems['items'])&&is_array($uitems['items']))$uitems=$uitems['items'];if(is_array($uitems))foreach($uitems as $u){if(!is_array($u))continue;$uid=trim((string)($u['user_cluster_relation_id']??$u['ucr_id']??$u['user_id']??$u['id']??''));if($uid!=='')$userStatusMap[$uid]=(int)($u['status_id']??0);}}
  $responseIns=$db->prepare('INSERT OR REPLACE INTO incident_divera_responses(incident_id,personnel_id,divera_ucr_id,status_id,status_name,note,responded_at,eligible) VALUES(?,?,?,?,?,?,?,?)');$findPerson=$db->prepare('SELECT id FROM personnel WHERE user_id=? LIMIT 1');
  foreach(diveraCollectResponses($item,$statusMap) as $rr){$findPerson->execute([$rr['ucr_id']]);$pid=$findPerson->fetchColumn();if(!$pid)continue;$statusId=(int)$rr['status_id'];if($statusId===0)$statusId=(int)($userStatusMap[$rr['ucr_id']]??0);$statusName=$rr['status_name'];if($statusName===''&&$statusId>0)$statusName=diveraStatusName($statusMap,$statusId);$eligible=stripos($statusName,'nicht einsatzbereit')===false?1:0;$responseIns->execute([$incidentId,(int)$pid,$rr['ucr_id'],$statusId,$statusName,$rr['note'],(int)$rr['responded_at'],$eligible]);}
  header('Location:?action=view&id='.$incidentId.'&divera=single_imported');exit;
 }
 if($form==='divera_import'){
  $key=$diveraAccessKey;
  if($key===''){header('Location:?divera=no_key');exit;}
  $payload=diveraJson('https://divera247.com/api/v2/alarms',$key);
  if(!is_array($payload)){header('Location:?divera=error&msg='.rawurlencode('Divera liefert keine gültige Antwort'));exit;}
  if(isset($payload['success'])&&$payload['success']!==true){$msg=(string)($payload['message']??$payload['error']??'Divera hat den Einsatzimport abgelehnt');header('Location:?divera=error&msg='.rawurlencode($msg));exit;}
  $items=$payload['data']['items']??$payload['items']??[];
  if(!is_array($items))$items=[];
  $statusMap=[];$userStatusMap=[];
  $pull=diveraJson('https://divera247.com/api/v2/pull/all',$key);
  if(is_array($pull))$statusMap=$pull['data']['cluster']['status']??[];
  $usersPayload=diveraJson('https://divera247.com/api/users',$key);
  if(is_array($usersPayload)){
   $uitems=$usersPayload['data']['items']??$usersPayload['items']??$usersPayload['data']??[];
   if(isset($uitems['items'])&&is_array($uitems['items']))$uitems=$uitems['items'];
   if(is_array($uitems))foreach($uitems as $u){if(!is_array($u))continue;$uid=trim((string)($u['user_cluster_relation_id']??$u['ucr_id']??$u['user_id']??$u['id']??''));if($uid!=='')$userStatusMap[$uid]=(int)($u['status_id']??0);}
  }
  $imported=0;$skipped=0;$responsesTotal=0;
  $check=$db->prepare('SELECT id FROM incidents WHERE divera_id=? OR incident_number=? LIMIT 1');
  $ins=$db->prepare('INSERT INTO incidents(incident_number,title,location,incident_date,alarm_time,divera_id) VALUES(?,?,?,?,?,?)');
  $responseIns=$db->prepare('INSERT OR REPLACE INTO incident_divera_responses(incident_id,personnel_id,divera_ucr_id,status_id,status_name,note,responded_at,eligible) VALUES(?,?,?,?,?,?,?,?)');
  $findPerson=$db->prepare('SELECT id FROM personnel WHERE user_id=? LIMIT 1');
  foreach($items as $item){
   if(!is_array($item))continue;
   $foreign=trim((string)($item['foreign_id']??$item['id']??''));
   $title=trim((string)($item['title']??$item['name']??''));
   $address=trim((string)($item['address']??''));
   if($foreign===''||$title===''){$skipped++;continue;}
   $check->execute([$foreign,$foreign]);if($check->fetchColumn()){$skipped++;continue;}
   $rawTs=$item['date']??$item['timestamp']??$item['created_at']??time();$ts=is_numeric($rawTs)?(int)$rawTs:strtotime((string)$rawTs);if(!$ts)$ts=time();
   $ins->execute([$foreign,$title,$address,date('Y-m-d',$ts),date('H:i',$ts),$foreign]);$incidentId=(int)$db->lastInsertId();$imported++;
   $alarm=$item;$alarmId=(int)($item['id']??0);
   if($alarmId>0){$detail=diveraJson('https://divera247.com/api/v2/alarms/'.$alarmId,$key);if(is_array($detail)&&is_array($detail['data']??null))$alarm=$detail['data'];}
   foreach(diveraCollectResponses($alarm,$statusMap) as $r){
    $findPerson->execute([$r['ucr_id']]);$pid=$findPerson->fetchColumn();if(!$pid)continue;
    $statusId=(int)$r['status_id'];if($statusId===0)$statusId=(int)($userStatusMap[$r['ucr_id']]??0);$statusName=$r['status_name'];if($statusName===''&&$statusId>0)$statusName=diveraStatusName($statusMap,$statusId);$eligible=stripos($statusName,'nicht einsatzbereit')===false?1:0;
    $responseIns->execute([$incidentId,(int)$pid,$r['ucr_id'],$statusId,$statusName,$r['note'],(int)$r['responded_at'],$eligible]);
    if($eligible)$responsesTotal++;
   }
  }
  header('Location:?divera=imported&count='.$imported.'&skipped='.$skipped.'&responses='.$responsesTotal);exit;
 }
 if($form==='divera_users_import'){
  $key=$diveraAccessKey;
  if($key===''){header('Location:?action=master&divera=users_no_key');exit;}
  $url='https://www.divera247.com/api/users?accesskey='.rawurlencode($key);
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_HTTPHEADER=>['Accept: application/json'],CURLOPT_USERAGENT=>'FwDesk-Halchter/1.0']);
  $response=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
  if($response===false||$http<200||$http>=300){$msg=$error!==''?$error:'HTTP '.$http;header('Location:?action=master&divera=users_error&msg='.rawurlencode($msg));exit;}
  $payload=json_decode($response,true);
  if(!is_array($payload)){header('Location:?action=master&divera=users_error&msg='.rawurlencode('Divera liefert kein gültiges JSON'));exit;}
  if(isset($payload['success'])&&$payload['success']!==true){$msg=(string)($payload['message']??$payload['error']??'Divera hat den Benutzerimport abgelehnt');header('Location:?action=master&divera=users_error&msg='.rawurlencode($msg));exit;}
  $items=$payload['data']['items']??$payload['items']??$payload['data']??[];
  if(isset($items['items'])&&is_array($items['items']))$items=$items['items'];
  if(!is_array($items))$items=[];
  $resetUsers=($_POST['reset_users']??'')==='1';
  if($resetUsers)$db->beginTransaction();
  if($resetUsers)$db->exec('DELETE FROM personnel');
  $imported=0;$updated=0;$skipped=0;$find=$db->prepare("SELECT id FROM personnel WHERE user_id=? OR (user_id='' AND lower(trim(name))=lower(trim(?))) ORDER BY CASE WHEN user_id=? THEN 0 ELSE 1 END,id LIMIT 1");$ins=$db->prepare('INSERT INTO personnel(name,user_id) VALUES(?,?)');$upd=$db->prepare('UPDATE personnel SET name=?,user_id=?,active=1 WHERE id=?');
  foreach($items as $item){
   if(!is_array($item))continue;
   $userId=trim((string)($item['user_cluster_relation_id']??$item['user_id']??$item['id']??$item['foreign_id']??''));
   $first=trim((string)($item['firstname']??$item['first_name']??$item['firstName']??''));
   $last=trim((string)($item['lastname']??$item['last_name']??$item['lastName']??''));
   $name=trim(preg_replace('/\s+/',' ',trim($first.' '.$last)));
   if($name==='')$name=trim((string)($item['name']??$item['display_name']??''));
   if($userId===''||$name===''){$skipped++;continue;}
   $find->execute([$userId,$name,$userId]);$existingPerson=$find->fetchColumn();
   if($existingPerson){$upd->execute([$name,$userId,(int)$existingPerson]);$updated++;}else{$ins->execute([$name,$userId]);$imported++;}
  }
  if($resetUsers)$db->commit();
  header('Location:?action=master&divera=users_imported&count='.$imported.'&updated='.$updated.'&skipped='.$skipped.'&reset='.($resetUsers?'1':'0'));exit;
 }
 if($form==='person'){
  $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');if($name!==''){if($id>0){$s=$db->prepare('UPDATE personnel SET name=? WHERE id=?');$s->execute([$name,$id]);}else{$s=$db->prepare('INSERT INTO personnel(name) VALUES(?)');$s->execute([$name]);}}header('Location:?action=master');exit;
 }
 if($form==='vehicle'){
  $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$call=trim($_POST['call_sign']??'');if($name!==''){if($id>0){$s=$db->prepare('UPDATE vehicles SET name=?,call_sign=? WHERE id=?');$s->execute([$name,$call,$id]);}else{$s=$db->prepare('INSERT INTO vehicles(name,call_sign) VALUES(?,?)');$s->execute([$name,$call]);}}header('Location:?action=master');exit;
 }
 if($form==='material'){
  $id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');if($name!==''){if($id>0){$s=$db->prepare('UPDATE materials SET name=? WHERE id=?');$s->execute([$name,$id]);}else{$s=$db->prepare('INSERT INTO materials(name) VALUES(?)');$s->execute([$name]);}}header('Location:?action=master');exit;
 }
 if($form==='incident'){
  $incidentId=(int)($_POST['incident_id']??0);$data=[trim($_POST['incident_number']??''),trim($_POST['title']??''),trim($_POST['location']??''),$_POST['incident_date']??date('Y-m-d'),$_POST['alarm_time']??'',$_POST['start_time']??'',$_POST['end_time']??'',trim($_POST['material']??''),trim($_POST['notes']??''),trim($_POST['incident_leader']??''),trim($_POST['document_filled_by']??''),trim($_POST['culprit1_type']??'Verursacher'),trim($_POST['culprit1_name']??''),$_POST['culprit1_birthdate']??'',trim($_POST['culprit1_address']??''),trim($_POST['culprit2_type']??'Verursacher 2'),trim($_POST['culprit2_name']??''),$_POST['culprit2_birthdate']??'',trim($_POST['culprit2_address']??''),trim($_POST['culprit_unknown_type']??'Unbekannt'),trim($_POST['culprit_unknown_name']??''),$_POST['culprit_unknown_birthdate']??'',trim($_POST['culprit_unknown_address']??'')];
  if($incidentId>0){$s=$db->prepare('UPDATE incidents SET incident_number=?,title=?,location=?,incident_date=?,alarm_time=?,start_time=?,end_time=?,material=?,notes=?,incident_leader=?,document_filled_by=?,culprit1_type=?,culprit1_name=?,culprit1_birthdate=?,culprit1_address=?,culprit2_type=?,culprit2_name=?,culprit2_birthdate=?,culprit2_address=?,culprit_unknown_type=?,culprit_unknown_name=?,culprit_unknown_birthdate=?,culprit_unknown_address=? WHERE id=?');$s->execute([...$data,$incidentId]);$db->prepare('DELETE FROM incident_personnel WHERE incident_id=?')->execute([$incidentId]);$db->prepare('DELETE FROM incident_vehicles WHERE incident_id=?')->execute([$incidentId]);$id=$incidentId;}
  else{$s=$db->prepare('INSERT INTO incidents(incident_number,title,location,incident_date,alarm_time,start_time,end_time,material,notes,incident_leader,document_filled_by,culprit1_type,culprit1_name,culprit1_birthdate,culprit1_address,culprit2_type,culprit2_name,culprit2_birthdate,culprit2_address,culprit_unknown_type,culprit_unknown_name,culprit_unknown_birthdate,culprit_unknown_address) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute($data);$id=(int)$db->lastInsertId();}
  $iv=$db->prepare('INSERT INTO incident_vehicles(incident_id,vehicle_id) VALUES(?,?)');$ip=$db->prepare('INSERT INTO incident_personnel(incident_id,vehicle_id,personnel_id,va) VALUES(?,?,?,?)');$usedPeople=[];foreach($_POST['vehicles']??[] as $vid){$vid=(int)$vid;if($vid<=0)continue;$iv->execute([$id,$vid]);foreach($_POST['crew'][$vid]??[] as $pid){$pid=(int)$pid;if($pid<=0||isset($usedPeople[$pid]))continue;$usedPeople[$pid]=true;$va=!empty($_POST['crew_va'][$vid][$pid])?1:0;$ip->execute([$id,$vid,$pid,$va]);}}$photoFiles=$_FILES['incident_photos']??null;if($photoFiles&&isset($photoFiles['name'])&&is_array($photoFiles['name'])){$dir='/var/www/data/uploads/incidents';if(!is_dir($dir))mkdir($dir,0775,true);$photoIns=$db->prepare('INSERT INTO incident_photos(incident_id,filename,original_name) VALUES(?,?,?)');$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$count=min(4,count($photoFiles['name']));for($n=0;$n<$count;$n++){if(($photoFiles['error'][$n]??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)continue;$tmp=$photoFiles['tmp_name'][$n]??'';$mime=(string)(mime_content_type($tmp)?:'');if(!isset($allowed[$mime]))continue;$name=bin2hex(random_bytes(12)).'.'.$allowed[$mime];if(move_uploaded_file($tmp,$dir.'/'.$name))$photoIns->execute([$id,$name,(string)($photoFiles['name'][$n]??$name)]);}}header('Location:?action=view&id='.$id);exit;
 }
}
function headerHtml(string $title='FW Verwaltung'):void{echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><style>body{font-family:system-ui;margin:0;background:#f3f5f7;color:#18212b}header{background:#17212b;color:#fff;padding:16px 24px}.topbar{display:flex;align-items:center;justify-content:space-between;gap:24px;white-space:nowrap}.brand{flex:0 0 auto;white-space:nowrap}.topnav{display:flex;gap:18px;align-items:center;overflow-x:auto;white-space:nowrap}.topnav a{color:#fff;text-decoration:none;white-space:nowrap;display:inline-block}main{max-width:1200px;margin:24px auto;padding:0 16px}.card{background:#fff;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 2px 10px #0001}label{display:block;font-weight:600;margin:10px 0 5px}input,textarea,select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd3da;border-radius:8px}button,.btn{background:#d62828;color:#fff;border:0;border-radius:8px;padding:10px 16px;cursor:pointer;text-decoration:none;display:inline-block}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.time-grid{display:grid;grid-template-columns:120px 150px minmax(180px,1fr) 150px;gap:16px;align-items:end}.time-grid input{min-width:0}.duration-box{font-weight:600}.duration-value{background:#f3f5f7;min-height:20px;padding:10px;border:1px solid #ccd3da;border-radius:8px;white-space:nowrap}.muted{color:#667}.crew,.material-list,.person-block,.master-row{border:1px solid #ddd;border-radius:10px;padding:12px;margin:10px 0}.check{display:inline-block;margin:5px 12px 5px 0}.check input{width:auto}.person-choice.disabled{opacity:.35}.person-choice.divera-suggested{background:#fff4cc;border-radius:6px;padding:4px 8px}.divera-modal{position:fixed;inset:0;background:#0008;display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px}.divera-modal[hidden]{display:none}.divera-modal-box{background:#fff;border-radius:14px;max-width:620px;width:100%;max-height:80vh;overflow:auto;padding:20px;box-shadow:0 10px 40px #0005}.divera-modal-head{display:flex;justify-content:space-between;align-items:center;gap:12px}.divera-response-list{display:grid;gap:6px;margin:16px 0}.divera-response-row{display:flex;gap:10px;align-items:flex-start;padding:10px;border:1px solid #ddd;border-radius:8px;background:#f8f9fa;font-weight:400;margin:0}.divera-response-row.status-sofort{background:#d9f7df;border-color:#8bd39a}.divera-response-row.status-10min{background:#fff3b8;border-color:#e5cf5a}.divera-response-row.status-30min{background:#ffd39a;border-color:#e6a35c}.divera-response-row.status-nicht{background:#ffc7c7;border-color:#e28a8a}.divera-response-row input{width:auto;margin-top:3px}.divera-response-row .muted{font-weight:400}.person-na .person-detail{background:#eee;color:#888;opacity:.65}.photo-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:12px 0}.photo-item{border:1px solid #ddd;border-radius:10px;padding:8px;background:#f8f9fa}.photo-item img{display:block;width:100%;max-height:220px;object-fit:cover;border-radius:8px}.person-na .person-detail::placeholder{color:#999}.danger{background:#eee;color:#222}.actions{display:flex;gap:10px;flex-wrap:wrap}.material-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:4px 16px}.material-list label{margin:5px 0;font-weight:500}.master-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:18px}.person-grid{align-items:start} .print-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:4px 24px}.print-section{margin-top:24px;border-top:1px solid #ddd;padding-top:12px}.person-print{break-inside:avoid}.photo-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.photo-item img{max-width:100%;max-height:420px;object-fit:contain}.muted{color:#66727e}@media(max-width:900px){.topbar{align-items:flex-start}.topnav{gap:12px}}@media(max-width:800px){.time-grid{grid-template-columns:1fr 1fr}.duration-box{grid-column:1/-1}.topbar{display:block}.topnav{margin-top:12px}.person-grid{grid-template-columns:1fr}}@media print{body{background:#fff}.topnav,header{display:none!important}main{max-width:none;margin:0;padding:0}.card{box-shadow:none;border:0}.actions{display:none!important}.person-grid{grid-template-columns:repeat(3,1fr)}}</style></head><body><header><div class="topbar"><div class="brand"><strong>🚒 FwDesk Halchter</strong><div style="font-size:13px;opacity:.8;margin-top:3px">Die digitale Einsatzdoku für Feuerwehren</div></div><nav class="topnav"><a href="?">Einsätze</a><a href="?action=new">+ Neuer Einsatz</a><a href="?action=master">Stammdaten</a></nav></div></header><main>';}
function footerHtml():void{echo '<footer style="text-align:center;padding:24px 0 16px;color:#66727e;font-size:13px">Copyright T.König 2026</footer></main></body></html>';}
function selectedType(string $current,string $value):string{return $current===$value?' selected':'';}
function personDataDisabled(string $type):string{return $type==='n/a'?' person-na':'';}
function incidentForm(PDO $db,array $vehicles,array $people,?array $incident=null):void{
 $incidentPhotos=[];if($incident!==null){$q=$db->prepare('SELECT * FROM incident_photos WHERE incident_id=? ORDER BY id');$q->execute([(int)$incident['id']]);$incidentPhotos=$q->fetchAll(PDO::FETCH_ASSOC);}
 $isEdit=$incident!==null; $durationValue="–";$st=(string)($incident["start_time"]??"");$et=(string)($incident["end_time"]??"");if($st!==""&&$et!==""){[$sh,$sm]=array_map("intval",explode(":",$st));[$eh,$em]=array_map("intval",explode(":",$et));$smTotal=$sh*60+$sm;$emTotal=$eh*60+$em;if($emTotal<$smTotal)$emTotal+=1440;$dm=$emTotal-$smTotal;$durationValue=floor($dm/60)." Std. ".($dm%60)." Min.";}$assignedVehicles=[];$assignedCrew=[];$assignedVA=[];if($isEdit){$q=$db->prepare('SELECT vehicle_id FROM incident_vehicles WHERE incident_id=?');$q->execute([(int)$incident['id']]);$assignedVehicles=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$q=$db->prepare('SELECT vehicle_id,personnel_id,va FROM incident_personnel WHERE incident_id=?');$q->execute([(int)$incident['id']]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$assignedCrew[(int)$row['vehicle_id']][]=(int)$row['personnel_id'];$assignedVA[(int)$row['vehicle_id']][(int)$row['personnel_id']]=(int)$row['va'];}}
 $diveraSuggested=[];$diveraResponses=[];if($isEdit){$q=$db->prepare('SELECT personnel_id FROM incident_divera_responses WHERE incident_id=? AND eligible=1');$q->execute([(int)$incident['id']]);$diveraSuggested=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$q=$db->prepare('SELECT r.personnel_id,r.status_id,r.status_name,r.note,r.responded_at,r.eligible,p.name FROM incident_divera_responses r JOIN personnel p ON p.id=r.personnel_id WHERE r.incident_id=? ORDER BY r.eligible DESC,p.name');$q->execute([(int)$incident['id']]);$diveraResponses=$q->fetchAll(PDO::FETCH_ASSOC);}$diveraResponseHtml='';$diveraResponseTitle='👥 Divera-Rückmeldungen';if($diveraResponses){usort($diveraResponses,function($a,$b){$rank=function($r){$id=(int)($r['status_id']??0);$name=mb_strtolower(trim((string)($r['status_name']??'')));if($id===55475||str_contains($name,'sofort'))return 1;if($id===56004||str_contains($name,'innerhalb 10'))return 2;if($id===56005||str_contains($name,'30 minuten'))return 3;if($id===56001||str_contains($name,'nicht einsatzbereit'))return 4;return 5;};$ra=$rank($a);$rb=$rank($b);if($ra!==$rb)return $ra<=>$rb;return strcasecmp((string)$a['name'],(string)$b['name']);});foreach($diveraResponses as $r){$rn=(string)$r['name'];$rs=trim((string)$r['status_name']);if($rs==='')$rs='Status '.(int)$r['status_id'];$rt=trim((string)$r['note']);$rid=(int)$r['personnel_id'];$statusId=(int)($r['status_id']??0);$statusClass='';if($statusId===55475||stripos($rs,'sofort')!==false)$statusClass=' status-sofort';elseif($statusId===56004||stripos($rs,'innerhalb 10')!==false)$statusClass=' status-10min';elseif($statusId===56005||stripos($rs,'30 minuten')!==false)$statusClass=' status-30min';elseif($statusId===56001||stripos($rs,'nicht einsatzbereit')!==false)$statusClass=' status-nicht';$diveraResponseHtml.='<label class="divera-response-row'.$statusClass.'"><input type="checkbox" class="divera-response-person" value="'.$rid.'" '.($r['eligible']?'checked':'').' data-person-id="'.$rid.'"><span><strong>'.h($rn).'</strong><br><span class="muted">'.h($rs).($rt!==''?' · '.h($rt):'').'</span></span></label>';}}else{$diveraResponseTitle='👥 Keine Rückmeldung gefunden';foreach($people as $p){$rid=(int)$p['id'];$diveraResponseHtml.='<label class="divera-response-row"><input type="checkbox" class="divera-response-person" value="'.$rid.'" data-person-id="'.$rid.'"><span><strong>'.h($p['name']).'</strong><br><span class="muted">Keine DIVERA-Rückmeldung – manuell auswählbar</span></span></label>';}} $val=fn(string $key,string $default='')=>h($incident[$key]??$default);
 $crewPeople=$people;
 usort($crewPeople,function($a,$b)use($diveraSuggested){
  $as=in_array((int)$a['id'],$diveraSuggested,true)?0:1;
  $bs=in_array((int)$b['id'],$diveraSuggested,true)?0:1;
  if($as!==$bs)return $as<=>$bs;
  return strcasecmp((string)$a['name'],(string)$b['name']);
 });
 echo '<div class="card"><h1>'.($isEdit?'Einsatz bearbeiten':'Neuer Einsatz').'</h1><form method="post" enctype="multipart/form-data"><input type="hidden" name="form" value="incident">'.($isEdit?'<input type="hidden" name="incident_id" value="'.(int)$incident['id'].'">':'');echo '<label>Einsatznummer</label><input name="incident_number" value="'.$val('incident_number').'" placeholder="z. B. 2026-123"><label>Einsatz / Stichwort</label><input name="title" required value="'.$val('title').'" placeholder="z. B. Ölspur"><label>Einsatzort</label><input name="location" required value="'.$val('location').'" placeholder="z. B. Halchterstr. 2"><div class="time-grid"><div><label>Datum</label><input type="date" name="incident_date" value="'.$val('incident_date',date('Y-m-d')).'" required></div><div><label>Alarmierung</label><input type="time" name="alarm_time" value="'.$val('alarm_time').'" title="Zeitpunkt der Alarmierung"></div><div><label>Kostenbeginn <span class="muted">(Ende der kostenlosen gesetzlichen Hilfe)</span></label><input type="time" id="start_time" name="start_time" value="'.$val('start_time').'" title="Kostenbeginn des Einsatzes" oninput="updateDuration()"></div><div><label>Ende</label><input type="time" id="end_time" name="end_time" value="'.$val('end_time').'" title="Ende des Einsatzes" oninput="updateDuration()"></div><div class="duration-box"><label>Dauer</label><div id="duration_value" class="duration-value">'.$durationValue.'</div></div></div><div class="grid"><div><label>Einsatzleiter</label><input name="incident_leader" value="'.$val('incident_leader').'"></div><div><label>Dokument ausgefüllt von</label><input name="document_filled_by" value="'.$val('document_filled_by').'"></div></div><h2>Fahrzeuge und Besatzung</h2>';foreach($vehicles as $v){$vid=(int)$v['id'];$checked=in_array($vid,$assignedVehicles,true);echo '<div class="crew"><label class="check"><input type="checkbox" name="vehicles[]" value="'.$vid.'" '.($checked?'checked':'').' onchange="toggleVehicle(this)"> 🚒 '.h($v['name']).' <span class="muted">'.h($v['call_sign']).'</span></label><div class="actions"><a class="btn" href="#" onclick="var m=document.getElementById(&quot;divera-response-modal&quot;),crew=this.closest(&quot;.crew&quot;),v=crew?crew.querySelector(&quot;input[name=\&quot;vehicles[]\&quot;]&quot;):null;if(m&&crew&&v){m.dataset.crewVehicleId=v.value;m.hidden=false;m.querySelectorAll(&quot;.divera-response-person&quot;).forEach(function(cb){var target=crew.querySelector(&quot;.crew-person[value=\&quot;&quot;+cb.dataset.personId+&quot;\&quot;]&quot;);cb.checked=!!target&&target.checked;});}return false;">👥 Gemeldete hier übernehmen</a></div><div class="crewlist" '.($checked?'':'hidden').'>';if(!$people)echo '<span class="muted">Noch kein Personal angelegt.</span>';foreach($crewPeople as $p){$pid=(int)$p['id'];$pc=in_array($pid,$assignedCrew[$vid]??[],true);echo '<label class="check person-choice '.(in_array($pid,$diveraSuggested,true)?'divera-suggested':'').'" data-person-id="'.$pid.'"><input type="checkbox" class="crew-person" name="crew['.$vid.'][]" value="'.$pid.'" '.($pc?'checked':'').' onchange="syncPersonSelection('.$pid.',this)"> '.h($p['name']).' <span class="va-option"><input type="checkbox" class="crew-va" name="crew_va['.$vid.']['.$pid.']" value="1" '.(!empty($assignedVA[$vid][$pid])?'checked':'').'> VA</span></label>';}echo '</div></div>';}$val('material');echo '<label>Sonstiges Material (Freitext)</label><textarea name="material" rows="3">'.$val('material').'</textarea><label>Bemerkungen</label><textarea name="notes" rows="4">'.$val('notes').'</textarea><h2>Persondaten</h2><div class="grid person-grid">';foreach([['1','culprit1','Verursacher'],['2','culprit2','n/a'],['3','culprit_unknown','n/a']] as $p){$key=$p[1];$default=$p[2];$type=$incident[$key.'_type']??$default;echo '<div class="person-block '.($type==='n/a'?'person-na':'').'"><h3>Person '.$p[0].'</h3><label>Zuordnung</label><select name="'.$key.'_type" onchange="togglePersonData(this)"><option value="Verursacher"'.selectedType($type,'Verursacher').'>Verursacher</option><option value="Verursacher 2"'.selectedType($type,'Verursacher 2').'>Verursacher 2</option><option value="Unbekannt"'.selectedType($type,'Unbekannt').'>Unbekannt</option><option value="n/a"'.selectedType($type,'n/a').'>n/a</option></select><label>Name</label><input class="person-detail" name="'.$key.'_name" '.($type==='n/a'?'disabled':'').' value="'.$val($key.'_name').'"><label>Geburtsdatum</label><input class="person-detail" type="date" name="'.$key.'_birthdate" '.($type==='n/a'?'disabled':'').' value="'.$val($key.'_birthdate').'"><label>Adresse</label><textarea class="person-detail" name="'.$key.'_address" '.($type==='n/a'?'disabled':'').' rows="2">'.$val($key.'_address').'</textarea></div>'; }echo '</div><h2>📷 Einsatzbilder</h2><p class="muted">Bis zu 4 Bilder können mit diesem Einsatz verknüpft werden.</p><input type="file" name="incident_photos[]" accept="image/jpeg,image/png,image/webp" multiple><div class="photo-grid">'.implode('',array_map(fn($p)=>'<div class="photo-item"><img src="uploads/incidents/'.h($p['filename']).'" alt="Einsatzbild"><form method="post" class="actions" style="margin-top:8px"><input type="hidden" name="form" value="delete_photo"><input type="hidden" name="photo_id" value="'.(int)$p['id'].'"><input type="hidden" name="incident_id" value="'.(int)$incident['id'].'"><button type="submit" class="danger" onclick="return confirm(\'Bild wirklich löschen?\')">🗑️ Löschen</button></form></div>',$incidentPhotos)).'</div><div class="actions"><button type="submit">'.($isEdit?'Änderungen speichern':'Einsatz speichern').'</button></div></form><div id="divera-response-modal" class="divera-modal" hidden><div class="divera-modal-box"><div class="divera-modal-head"><h2>'.$diveraResponseTitle.'</h2><button type="button" class="danger" onclick="var m=document.getElementById(&quot;divera-response-modal&quot;);if(m){m.hidden=true;m.dataset.crewVehicleId=&quot;&quot;;}">✕</button></div><p class="muted">Wähle aus, welche gemeldeten Personen für dieses Fahrzeug übernommen werden sollen.</p><div class="divera-response-list">'.$diveraResponseHtml.'</div><div class="actions"><button type="button" onclick="var m=document.getElementById(&quot;divera-response-modal&quot;),crew=null;if(m&&m.dataset.crewVehicleId){var v=document.querySelector(&quot;input[name=\&quot;vehicles[]\&quot;][value=\&quot;&quot;+m.dataset.crewVehicleId+&quot;\&quot;]&quot;);if(v)crew=v.closest(&quot;.crew&quot;);}if(m&&crew){crew.querySelectorAll(&quot;.person-choice&quot;).forEach(function(label){label.hidden=true;});m.querySelectorAll(&quot;.divera-response-person&quot;).forEach(function(cb){var pid=String(cb.dataset.personId);crew.querySelectorAll(&quot;.crew-person&quot;).forEach(function(target){if(String(target.value)===pid){target.checked=cb.checked;target.disabled=false;var label=target.closest(&quot;.person-choice&quot;);if(label){label.classList.remove(&quot;disabled&quot;);label.hidden=!cb.checked;}}});});var list=crew.querySelector(&quot;.crewlist&quot;);if(list)list.hidden=false;}if(m){m.hidden=true;m.dataset.crewVehicleId=&quot;&quot;;}">Auswahl übernehmen</button><button type="button" class="danger" onclick="document.getElementById(&quot;divera-response-modal&quot;).hidden=true">Abbrechen</button></div></div></div></div>';echo '<script>function updateDuration(){var s=document.getElementById("start_time"),e=document.getElementById("end_time"),d=document.getElementById("duration_value");if(!s||!e||!d)return;var sv=s.value,ev=e.value;if(!sv||!ev){d.textContent="–";return;}var sp=sv.split(":").map(Number),ep=ev.split(":").map(Number);if(sp.length<2||ep.length<2||sp.some(isNaN)||ep.some(isNaN)){d.textContent="–";return;}var start=sp[0]*60+sp[1],end=ep[0]*60+ep[1];if(end<start)end+=1440;var minutes=end-start;d.textContent=Math.floor(minutes/60)+" Std. "+(minutes%60)+" Min.";}function syncPersonSelection(id,changed){document.querySelectorAll(".crew-person").forEach(function(cb){if(cb.value!==String(id))return;var label=cb.closest(".person-choice");if(changed===cb)return;cb.disabled=changed.checked;label.classList.toggle("disabled",changed.checked);});}function toggleVehicle(v){var crew=v.closest(".crew").querySelector(".crewlist");crew.hidden=!v.checked;}document.addEventListener("DOMContentLoaded",function(){var s=document.getElementById("start_time"),e=document.getElementById("end_time");if(s)s.addEventListener("change",updateDuration);if(e)e.addEventListener("change",updateDuration);updateDuration();document.querySelectorAll("select[name$=_type]").forEach(togglePersonData);var f=document.querySelector("input[name=\\\"incident_photos[]\\\"]");if(f)f.addEventListener("change",function(){if(this.files.length>4){alert("Maximal 4 Bilder erlaubt.");this.value="";}});});function togglePersonData(sel){var block=sel.closest(".person-block"),off=sel.value==="n/a";block.classList.toggle("person-na",off);block.querySelectorAll(".person-detail").forEach(function(el){el.disabled=off;});}function selectDiveraResponses(){document.querySelectorAll(".divera-suggested .crew-person").forEach(function(cb){cb.checked=true;cb.disabled=false;cb.closest(".person-choice").classList.remove("disabled");});}var activeDiveraCrew=null;function openDiveraResponseModal(btn){activeDiveraCrew=btn.closest(".crew");if(!activeDiveraCrew)return;document.querySelectorAll(".divera-response-person").forEach(function(cb){var target=activeDiveraCrew.querySelector(".crew-person[value=\""+cb.dataset.personId+"\"]");cb.checked=!!target&&target.checked;});var modal=document.getElementById("divera-response-modal");if(modal)modal.hidden=false;}function closeDiveraResponseModal(){if(window.location.hash==="#divera-response-modal")history.replaceState(null,"",window.location.pathname+window.location.search);var modal=document.getElementById("divera-response-modal");if(modal)modal.hidden=true;activeDiveraCrew=null;}function applyDiveraResponses(){if(!activeDiveraCrew)return;document.querySelectorAll(".divera-response-person").forEach(function(cb){var target=activeDiveraCrew.querySelector(".crew-person[value=\""+cb.dataset.personId+"\"]");if(!target)return;target.checked=cb.checked;target.disabled=false;target.closest(".person-choice").classList.remove("disabled");});closeDiveraResponseModal();}function diveraCloseModal(){var m=document.getElementById("divera-response-modal");if(m){m.hidden=true;m._activeCrew=null;}if(window.location.hash==="#divera-response-modal")history.replaceState(null,"",window.location.pathname+window.location.search);}function applyDiveraModal(){var m=document.getElementById("divera-response-modal"),crew=m?m._activeCrew:null;if(!m||!crew)return;var selected={};m.querySelectorAll(".divera-response-person").forEach(function(cb){selected[cb.dataset.personId]=cb.checked;});crew.querySelectorAll(".crew-person").forEach(function(cb){if(Object.prototype.hasOwnProperty.call(selected,cb.value)){cb.checked=selected[cb.value];cb.disabled=false;var label=cb.closest(".person-choice");if(label)label.classList.remove("disabled");}});diveraCloseModal();}</script>';
}
headerHtml();
$diveraStatus=$_GET['divera']??'';$diveraMsg=$_GET['msg']??'';$diveraCount=(int)($_GET['count']??0);$diveraUpdated=(int)($_GET['updated']??0);$diveraSkipped=(int)($_GET['skipped']??0);$diveraResponses=(int)($_GET['responses']??0);
if($action==='new'){incidentForm($db,$vehicles,$people);}
elseif($action==='edit'){$s=$db->prepare('SELECT * FROM incidents WHERE id=?');$s->execute([$editId]);$incident=$s->fetch(PDO::FETCH_ASSOC);if(!$incident)echo '<div class="card"><h1>Einsatz nicht gefunden</h1></div>';else incidentForm($db,$vehicles,$people,$incident);}
elseif($action==='master'){
 $personStatus=$_GET['person']??'';$vehicleStatus=$_GET['vehicle']??'';
 echo '<div class="card"><h1>Stammdaten</h1>'.($diveraStatus==='users_imported'?'<p><strong>✅ Divera Benutzerimport:</strong> '.$diveraCount.' neu, '.$diveraUpdated.' aktualisiert, '.$diveraSkipped.' übersprungen.</p>':($diveraStatus==='users_no_key'?'<p><strong>⚠️ Kein Divera AccessKey gespeichert.</strong></p>':($diveraStatus==='users_error'?'<p><strong>❌ Divera Benutzerimport fehlgeschlagen:</strong> '.h($diveraMsg).'</p>':''))).'<p class="muted">Personal und Fahrzeuge können hier direkt bearbeitet werden. Eine feste Zuordnung von Personal zu einem Fahrzeug gibt es nicht.</p><h2>🔗 Divera 24/7</h2><form method="post"><input type="hidden" name="form" value="divera_settings"><label>AccessKey</label><input type="password" name="divera_access_key" value="'.h($diveraAccessKey).'"><br><button>AccessKey speichern</button></form></div><div class="master-grid">';
 echo '<div class="card"><h2>👨‍🚒 Personal</h2><form method="post"><input type="hidden" name="form" value="person"><label>Name</label><input name="name" required><br><button>Person anlegen</button></form><hr>';if($personStatus==='deleted')echo '<p class="muted">Person gelöscht.</p>';elseif($personStatus==='in_use')echo '<p class="muted">Person kann nicht gelöscht werden, da sie bereits einem Einsatz zugeordnet ist.</p>';foreach($people as $p)echo '<div class="master-row"><form method="post"><input type="hidden" name="form" value="person"><input type="hidden" name="id" value="'.$p['id'].'"><label>Name</label><input name="name" value="'.h($p['name']).'" required><div class="muted">Divera User-ID: '.h($p['user_id']??'').'</div><br><div class="actions"><button type="submit">Speichern</button></div></form><form method="post" style="margin-top:8px"><input type="hidden" name="form" value="delete_person"><input type="hidden" name="id" value="'.$p['id'].'"><button type="submit" class="danger" onclick="return confirm(\'Person wirklich löschen?\')">🗑️ Löschen</button></form></div>';echo '<div class="actions"><form method="post"><input type="hidden" name="form" value="divera_users_import"><button type="submit">📥 Divera User importieren</button></form><form method="post" onsubmit="return confirm(\"ACHTUNG: Alle vorhandenen Personal-Datensätze und deren bisherigen Einsatz-Zuordnungen werden gelöscht und anschließend vollständig aus Divera neu importiert. Wirklich fortfahren?\")"><input type="hidden" name="form" value="divera_users_import"><input type="hidden" name="reset_users" value="1"><button type="submit" class="danger">🔄 Alle User löschen &amp; neu importieren</button></form></div></div>';
 echo '<div class="card"><h2>🚒 Fahrzeuge</h2><form method="post"><input type="hidden" name="form" value="vehicle"><div class="inline"><div><label>Bezeichnung</label><input name="name" required placeholder="LF 8"></div><div><label>Funkrufname</label><input name="call_sign" placeholder="LF 8"></div></div><br><button>Fahrzeug anlegen</button></form><hr>';if($vehicleStatus==='deleted')echo '<p class="muted">Fahrzeug gelöscht.</p>';elseif($vehicleStatus==='in_use')echo '<p class="muted">Fahrzeug kann nicht gelöscht werden, da es bereits einem Einsatz zugeordnet ist.</p>';foreach($vehicles as $v)echo '<div class="master-row"><form method="post"><input type="hidden" name="form" value="vehicle"><input type="hidden" name="id" value="'.$v['id'].'"><div class="inline"><div><label>Bezeichnung</label><input name="name" value="'.h($v['name']).'" required></div><div><label>Funkrufname</label><input name="call_sign" value="'.h($v['call_sign']).'"></div></div><br><div class="actions"><button type="submit">Speichern</button><button type="submit" class="danger" name="form" value="delete_vehicle" onclick="return confirm(\'Fahrzeug wirklich löschen?\')">🗑️ Löschen</button></div></form></div>';echo '</div>';
}
elseif($action==='view'){
 $s=$db->prepare('SELECT * FROM incidents WHERE id=?');$s->execute([$editId]);$i=$s->fetch(PDO::FETCH_ASSOC);
 if(!$i)echo '<div class="card"><h1>Einsatz nicht gefunden</h1></div>';
 else{
  $q=$db->prepare('SELECT v.name,v.call_sign,p.name AS person_name,ip.va AS person_va FROM incident_vehicles iv JOIN vehicles v ON v.id=iv.vehicle_id LEFT JOIN incident_personnel ip ON ip.incident_id=iv.incident_id AND ip.vehicle_id=iv.vehicle_id LEFT JOIN personnel p ON p.id=ip.personnel_id WHERE iv.incident_id=? ORDER BY v.name,p.name');$q->execute([$editId]);$vehicleRows=$q->fetchAll(PDO::FETCH_ASSOC);
  $vehicleHtml='';$currentVehicle='';
  foreach($vehicleRows as $vr){if($currentVehicle!==$vr['name']){$currentVehicle=$vr['name'];$vehicleHtml.='<div class="print-section"><h3>🚒 '.h($vr['name']).' <span class="muted">'.h($vr['call_sign']).'</span></h3><ul>';}$vehicleHtml.=($vr['person_name']!==null?'<li>'.h($vr['person_name']).($vr['person_va']?' <strong>(VA)</strong>':'').'</li>':'');}
  foreach($vehicleRows as $idx=>$vr){$next=$vehicleRows[$idx+1]['name']??'';if($next!==$vr['name'])$vehicleHtml.='</ul></div>';}
  if($vehicleHtml==='')$vehicleHtml='<p class="muted">Keine Fahrzeuge zugeordnet.</p>';
  $photoQ=$db->prepare('SELECT * FROM incident_photos WHERE incident_id=? ORDER BY id');$photoQ->execute([$editId]);$photos=$photoQ->fetchAll(PDO::FETCH_ASSOC);
  $photoHtml='';foreach($photos as $photo)$photoHtml.='<div class="photo-item"><img src="uploads/incidents/'.h($photo['filename']).'" alt="Einsatzbild"><div class="muted">'.h($photo['original_name']).'</div><form method="post" class="actions" style="margin-top:8px"><input type="hidden" name="form" value="delete_photo"><input type="hidden" name="photo_id" value="'.(int)$photo['id'].'"><input type="hidden" name="incident_id" value="'.(int)$editId.'"><button type="submit" class="danger" onclick="return confirm(\'Bild wirklich löschen?\')">🗑️ Bild löschen</button></form></div>';
  $duration='–';if($i['start_time']!==''&&$i['end_time']!==''){[$sh,$sm]=array_map('intval',explode(':',$i['start_time']));[$eh,$em]=array_map('intval',explode(':',$i['end_time']));$start=$sh*60+$sm;$end=$eh*60+$em;if($end<$start)$end+=1440;$mins=$end-$start;$duration=floor($mins/60).' Std. '.($mins%60).' Min.';}
  echo '<div class="card"><div class="actions"><a class="btn" href="?action=edit&id='.(int)$i['id'].'">✏️ Bearbeiten</a><button onclick="window.print()">🖨️ PDF / Drucken</button><form method="post" style="display:inline"><input type="hidden" name="form" value="delete_incident"><input type="hidden" name="id" value="'.(int)$i['id'].'"><button type="submit" class="danger" onclick="return confirm(\'ACHTUNG: Einsatz und alle zugehörigen Bilder wirklich löschen?\')">🗑️ Einsatz löschen</button></form></div><h1>'.h($i['title']).'</h1><div class="print-grid"><p><strong>Einsatznummer:</strong> '.h($i['incident_number']).'</p><p><strong>Einsatzort:</strong> '.h($i['location']).'</p><p><strong>Datum:</strong> '.h($i['incident_date']).'</p><p><strong>Alarmierung:</strong> '.h($i['alarm_time']).'</p><p><strong>Kostenbeginn:</strong> '.h($i['start_time']).'</p><p><strong>Ende:</strong> '.h($i['end_time']).'</p><p><strong>Dauer:</strong> '.h($duration).'</p><p><strong>Einsatzleiter:</strong> '.h($i['incident_leader']).'</p><p><strong>Dokument ausgefüllt von:</strong> '.h($i['document_filled_by']).'</p></div><div class="print-section"><h2>Fahrzeuge und Besatzung</h2>'.$vehicleHtml.'</div><div class="print-section"><h2>Material</h2><p>'.nl2br(h($i['material'])).'</p></div><div class="print-section"><h2>Bemerkungen</h2><p>'.nl2br(h($i['notes'])).'</p></div><div class="print-section"><h2>Persondaten</h2><div class="print-grid">';
  foreach([['culprit1','Person 1'],['culprit2','Person 2'],['culprit_unknown','Person 3']] as $person){$type=$i[$person[0].'_type']??'';$name=$i[$person[0].'_name']??'';$birth=$i[$person[0].'_birthdate']??'';$address=$i[$person[0].'_address']??'';echo '<div class="person-print"><h3>'.h($person[1]).'</h3><p><strong>Zuordnung:</strong> '.h($type).'</p><p><strong>Name:</strong> '.h($name).'</p><p><strong>Geburtsdatum:</strong> '.h($birth).'</p><p><strong>Adresse:</strong> '.nl2br(h($address)).'</p></div>';}
  echo '</div></div>'.($photoHtml!==''?'<div class="print-section"><h2>Einsatzbilder</h2><div class="photo-grid">'.$photoHtml.'</div></div>':'').'</div>';
 }
}
else{
 $homeStatus='';
 if($diveraStatus==='single_imported')$homeStatus='<p><strong>✅ Divera-Einsatz wurde importiert.</strong></p>';
 elseif($diveraStatus==='single_exists')$homeStatus='<p><strong>ℹ️ Einsatz ist bereits vorhanden.</strong></p>';
 elseif($diveraStatus==='single_error')$homeStatus='<p><strong>❌ Einzelimport fehlgeschlagen:</strong> '.h($diveraMsg).'</p>';
 elseif($diveraStatus==='imported')$homeStatus='<p><strong>✅ Divera Einsatzimport:</strong> '.$diveraCount.' neu, '.$diveraSkipped.' übersprungen, '.$diveraResponses.' passende Rückmeldungen.</p>';
 elseif($diveraStatus==='no_key')$homeStatus='<p><strong>⚠️ Kein Divera AccessKey gespeichert.</strong> Bitte unter Stammdaten hinterlegen.</p>';
 elseif($diveraStatus==='error')$homeStatus='<p><strong>❌ Divera Einsatzimport fehlgeschlagen:</strong> '.h($diveraMsg).'</p>';
 echo '<div class="card"><h1>Einsätze</h1>'.$homeStatus.'<div class="actions"><a class="btn" href="?action=new">+ Neuer Einsatz</a><form method="post" style="display:inline"><input type="hidden" name="form" value="divera_import"><button type="submit">📥 Divera Import</button></form><form method="post" style="display:flex;gap:8px;align-items:center"><input type="hidden" name="form" value="divera_import_one"><input name="divera_incident_id" placeholder="Divera EinsatzID" style="width:170px" required><button type="submit">📥 Einsatz importieren</button></form></div>';
 $rows=$db->query('SELECT * FROM incidents ORDER BY incident_date DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as $r)echo '<div class="master-row"><a href="?action=view&id='.(int)$r['id'].'"><strong>'.h($r['incident_number']).' – '.h($r['title']).'</strong></a><div class="muted">'.h($r['incident_date']).' · '.h($r['location']).'</div></div>';
 echo '</div>';
}
footerHtml();
?>