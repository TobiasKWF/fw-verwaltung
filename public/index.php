<?php
require __DIR__.'/../src/db.php';
$vehicles=$db->query("SELECT * FROM vehicles WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$people=$db->query("SELECT * FROM personnel WHERE active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$action=$_GET['action']??'home';
$editId=(int)($_GET['id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
 if(($_POST['form']??'')==='person'){
  $s=$db->prepare('INSERT INTO personnel(name) VALUES(?)');
  $s->execute([trim($_POST['name'])]);
  header('Location:?action=master');exit;
 }
 if(($_POST['form']??'')==='vehicle'){
  $s=$db->prepare('INSERT INTO vehicles(name,call_sign) VALUES(?,?)');
  $s->execute([trim($_POST['name']),trim($_POST['call_sign']??'')]);
  header('Location:?action=master');exit;
 }
 if(($_POST['form']??'')==='incident'){
  $incidentId=(int)($_POST['incident_id']??0);
  $data=[trim($_POST['incident_number']??''),trim($_POST['title']),trim($_POST['location']),$_POST['incident_date'],$_POST['start_time']??'',$_POST['end_time']??'',trim($_POST['material']??''),trim($_POST['notes']??'')];
  if($incidentId>0){
   $s=$db->prepare('UPDATE incidents SET incident_number=?,title=?,location=?,incident_date=?,start_time=?,end_time=?,material=?,notes=? WHERE id=?');
   $s->execute([...$data,$incidentId]);
   $db->prepare('DELETE FROM incident_personnel WHERE incident_id=?')->execute([$incidentId]);
   $db->prepare('DELETE FROM incident_vehicles WHERE incident_id=?')->execute([$incidentId]);
   $id=$incidentId;
  }else{
   $s=$db->prepare('INSERT INTO incidents(incident_number,title,location,incident_date,start_time,end_time,material,notes) VALUES(?,?,?,?,?,?,?,?)');
   $s->execute($data);$id=(int)$db->lastInsertId();
  }
  $iv=$db->prepare('INSERT INTO incident_vehicles(incident_id,vehicle_id) VALUES(?,?)');
  $ip=$db->prepare('INSERT INTO incident_personnel(incident_id,vehicle_id,personnel_id) VALUES(?,?,?)');
  foreach($_POST['vehicles']??[] as $vid){$vid=(int)$vid;$iv->execute([$id,$vid]);foreach($_POST['crew'][$vid]??[] as $pid)$ip->execute([$id,$vid,(int)$pid]);}
  header('Location:?action=view&id='.$id);exit;
 }
}

function headerHtml(string $title='FW Verwaltung'):void{echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.h($title).'</title><style>body{font-family:system-ui;margin:0;background:#f3f5f7;color:#18212b}header{background:#17212b;color:white;padding:18px 24px}nav a{color:white;margin-right:18px;text-decoration:none}main{max-width:1100px;margin:24px auto;padding:0 16px}.card{background:white;border-radius:14px;padding:20px;margin-bottom:18px;box-shadow:0 2px 10px #0001}label{display:block;font-weight:600;margin:12px 0 5px}input,textarea,select{width:100%;box-sizing:border-box;padding:10px;border:1px solid #ccd3da;border-radius:8px}button,.btn{background:#d62828;color:white;border:0;border-radius:8px;padding:10px 16px;cursor:pointer;text-decoration:none;display:inline-block}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:16px}.muted{color:#667}.crew{border:1px solid #ddd;border-radius:10px;padding:12px;margin:10px 0}.check{display:inline-block;margin:5px 12px 5px 0}.check input{width:auto}.danger{background:#eee;color:#222}.actions{display:flex;gap:10px;flex-wrap:wrap}</style></head><body><header><strong>🚒 FW Verwaltung</strong><nav style="float:right"><a href="?">Einsätze</a><a href="?action=new">+ Neuer Einsatz</a><a href="?action=master">Stammdaten</a></nav></header><main>';}
function footerHtml():void{echo '</main></body></html>';}

function incidentForm(PDO $db,array $vehicles,array $people,?array $incident=null):void{
 $isEdit=$incident!==null;
 $assignedVehicles=[];$assignedCrew=[];
 if($isEdit){
  $q=$db->prepare('SELECT vehicle_id FROM incident_vehicles WHERE incident_id=?');$q->execute([(int)$incident['id']]);$assignedVehicles=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
  $q=$db->prepare('SELECT vehicle_id,personnel_id FROM incident_personnel WHERE incident_id=?');$q->execute([(int)$incident['id']]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$assignedCrew[(int)$row['vehicle_id']][]=(int)$row['personnel_id'];
 }
 $val=fn(string $key,string $default='')=>h($incident[$key]??$default);
 echo '<div class="card"><h1>'.($isEdit?'Einsatz bearbeiten':'Neuer Einsatz').'</h1><form method="post"><input type="hidden" name="form" value="incident">'.($isEdit?'<input type="hidden" name="incident_id" value="'.(int)$incident['id'].'">':'');
 echo '<label>Einsatznummer</label><input name="incident_number" value="'.$val('incident_number').'" placeholder="z. B. 2026-123">';
 echo '<label>Einsatz / Stichwort</label><input name="title" required value="'.$val('title').'" placeholder="z. B. Ölspur">';
 echo '<label>Einsatzort</label><input name="location" required value="'.$val('location').'" placeholder="z. B. Halchterstr. 2">';
 echo '<div class="grid"><div><label>Datum</label><input type="date" name="incident_date" value="'.$val('incident_date',date('Y-m-d')).'" required></div><div><label>Beginn <span class="muted">(Ende der kostenlosen gesetzlichen Hilfe)</span></label><input type="time" name="start_time" value="'.$val('start_time').'"></div><div><label>Ende</label><input type="time" name="end_time" value="'.$val('end_time').'"></div></div>';
 echo '<h2>Fahrzeuge und Besatzung</h2>';
 foreach($vehicles as $v){$checked=in_array((int)$v['id'],$assignedVehicles,true);echo '<div class="crew"><label class="check"><input type="checkbox" name="vehicles[]" value="'.$v['id'].'" '.($checked?'checked':'').' onchange="this.closest(\'.crew\').querySelector(\'.crewlist\').hidden=!this.checked"> 🚒 '.h($v['name']).' <span class="muted">'.h($v['call_sign']).'</span></label><div class="crewlist" '.($checked?'':'hidden').'>';if(!$people)echo '<span class="muted">Noch kein Personal angelegt.</span>';foreach($people as $p){$pc=in_array((int)$p['id'],$assignedCrew[(int)$v['id']]??[],true);echo '<label class="check"><input type="checkbox" name="crew['.$v['id'].'][]" value="'.$p['id'].'" '.($pc?'checked':'').'> '.h($p['name']).'</label>';}echo '</div></div>';}
 echo '<label>Material (Freitext)</label><textarea name="material" rows="4" placeholder="z. B. 2x Ölbindemittel, Pylonen, Absperrband">'.$val('material').'</textarea><label>Bemerkungen</label><textarea name="notes" rows="4">'.$val('notes').'</textarea><br><div class="actions"><button type="submit">'.($isEdit?'Änderungen speichern':'Einsatz speichern').'</button>'.($isEdit?'<a class="btn danger" href="?action=view&id='.(int)$incident['id'].'">Abbrechen</a>':'').'</div></form></div>';
}

headerHtml();
if($action==='new'){
 incidentForm($db,$vehicles,$people);
}elseif($action==='edit'){
 $s=$db->prepare('SELECT * FROM incidents WHERE id=?');$s->execute([$editId]);$incident=$s->fetch(PDO::FETCH_ASSOC);
 if(!$incident)echo '<div class="card"><h1>Einsatz nicht gefunden</h1></div>';else incidentForm($db,$vehicles,$people,$incident);
}elseif($action==='master'){
 echo '<div class="grid"><div class="card"><h2>👨‍🚒 Personal</h2><form method="post"><input type="hidden" name="form" value="person"><label>Name</label><input name="name" required><br><button>Person anlegen</button></form><hr>';foreach($people as $p)echo '<div>'.h($p['name']).'</div>';echo '</div><div class="card"><h2>🚒 Fahrzeuge</h2><form method="post"><input type="hidden" name="form" value="vehicle"><label>Bezeichnung</label><input name="name" required placeholder="LF 8"><label>Funkrufname</label><input name="call_sign"><br><button>Fahrzeug anlegen</button></form><hr>';foreach($vehicles as $v)echo '<div>'.h($v['name']).' <span class="muted">'.h($v['call_sign']).'</span></div>';echo '</div></div>';
}elseif($action==='view'){
 $id=$editId;$s=$db->prepare('SELECT * FROM incidents WHERE id=?');$s->execute([$id]);$i=$s->fetch(PDO::FETCH_ASSOC);
 if(!$i)echo '<div class="card"><h1>Einsatz nicht gefunden</h1></div>';else{
  $sv=$db->prepare('SELECT v.* FROM vehicles v JOIN incident_vehicles iv ON iv.vehicle_id=v.id WHERE iv.incident_id=? ORDER BY v.name');$sv->execute([$id]);$used=$sv->fetchAll(PDO::FETCH_ASSOC);
  echo '<div class="card"><div class="actions"><a class="btn" href="?action=edit&id='.$id.'">✏️ Einsatz bearbeiten</a><a class="btn danger" href="?">← Zur Übersicht</a></div><h1>'.($i['incident_number']?'<span class="muted">#'.h($i['incident_number']).'</span> ':'').h($i['title']).'</h1><p><strong>📍 '.h($i['location']).'</strong><br>📅 '.h($i['incident_date']).'<br>🕐 Beginn: '.h($i['start_time']).'<br>🕐 Ende: '.h($i['end_time']).'</p><h2>Fahrzeuge & Besatzung</h2>';
  foreach($used as $v){echo '<div class="crew"><strong>🚒 '.h($v['name']).'</strong><br>';$sp=$db->prepare('SELECT p.name FROM personnel p JOIN incident_personnel ip ON ip.personnel_id=p.id WHERE ip.incident_id=? AND ip.vehicle_id=? ORDER BY p.name');$sp->execute([$id,$v['id']]);$pp=$sp->fetchAll(PDO::FETCH_ASSOC);echo $pp?implode(' · ',array_map(fn($p)=>h($p['name']),$pp)):'Keine Besatzung erfasst';echo '</div>';}
  echo '<h2>Material</h2><p>'.nl2br(h($i['material'])).'</p><h2>Bemerkungen</h2><p>'.nl2br(h($i['notes'])).'</p></div>';
 }
}else{
 $items=$db->query('SELECT * FROM incidents ORDER BY incident_date DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC);echo '<div class="card"><h1>Einsätze</h1><a class="btn" href="?action=new">+ Neuer Einsatz</a></div>';if(!$items)echo '<div class="card"><p>Noch keine Einsätze angelegt.</p></div>';foreach($items as $i)echo '<div class="card"><h2><a href="?action=view&id='.$i['id'].'">'.($i['incident_number']?'<span class="muted">#'.h($i['incident_number']).'</span> ':'').h($i['title']).'</a></h2><p>📍 '.h($i['location']).' · '.h($i['incident_date']).' · '.h($i['start_time']).' – '.h($i['end_time']).'</p><a class="btn danger" href="?action=edit&id='.$i['id'].'">✏️ Bearbeiten</a></div>';
}
footerHtml();
