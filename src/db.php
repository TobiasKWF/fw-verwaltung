<?php
declare(strict_types=1);

ob_start(function(string $html): string {
    $html=str_replace('<title>FW Verwaltung</title>','<title>FwDesk Halchter – Die digitale Einsatzdoku für Feuerwehren</title>',$html);
    $html=str_replace('<strong>🚒 FW Verwaltung</strong>','<strong>🚒 FwDesk Halchter</strong><div style="font-size:13px;opacity:.8;margin-top:3px">Die digitale Einsatzdoku für Feuerwehren</div>',$html);
    return $html;
});

$dbPath=__DIR__.'/../data/fw.sqlite';
if(!is_dir(dirname($dbPath)))mkdir(dirname($dbPath),0775,true);
$db=new PDO('sqlite:'.$dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec("CREATE TABLE IF NOT EXISTS vehicles(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,call_sign TEXT DEFAULT '',active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS personnel(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS incidents(id INTEGER PRIMARY KEY AUTOINCREMENT,incident_number TEXT DEFAULT '',title TEXT NOT NULL,location TEXT NOT NULL,incident_date TEXT NOT NULL,alarm_time TEXT DEFAULT '',start_time TEXT DEFAULT '',end_time TEXT DEFAULT '',material TEXT DEFAULT '',notes TEXT DEFAULT '',incident_leader TEXT DEFAULT '',document_filled_by TEXT DEFAULT '',culprit1_name TEXT DEFAULT '',culprit1_birthdate TEXT DEFAULT '',culprit2_name TEXT DEFAULT '',culprit2_birthdate TEXT DEFAULT '',culprit_unknown_name TEXT DEFAULT '',culprit_unknown_birthdate TEXT DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS incident_vehicles(incident_id INTEGER NOT NULL,vehicle_id INTEGER NOT NULL,PRIMARY KEY(incident_id,vehicle_id),FOREIGN KEY(incident_id) REFERENCES incidents(id) ON DELETE CASCADE,FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS incident_personnel(incident_id INTEGER NOT NULL,vehicle_id INTEGER NOT NULL,personnel_id INTEGER NOT NULL,PRIMARY KEY(incident_id,vehicle_id,personnel_id),FOREIGN KEY(incident_id) REFERENCES incidents(id) ON DELETE CASCADE,FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,FOREIGN KEY(personnel_id) REFERENCES personnel(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS materials(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS incident_materials(incident_id INTEGER NOT NULL,material_id INTEGER NOT NULL,PRIMARY KEY(incident_id,material_id),FOREIGN KEY(incident_id) REFERENCES incidents(id) ON DELETE CASCADE,FOREIGN KEY(material_id) REFERENCES materials(id) ON DELETE CASCADE);");
$cols=$db->query('PRAGMA table_info(incidents)')->fetchAll(PDO::FETCH_ASSOC);
$required=['incident_number'=>"TEXT DEFAULT ''",'alarm_time'=>"TEXT DEFAULT ''",'incident_leader'=>"TEXT DEFAULT ''",'document_filled_by'=>"TEXT DEFAULT ''",'culprit1_type'=>"TEXT DEFAULT 'Verursacher'",'culprit1_name'=>"TEXT DEFAULT ''",'culprit1_birthdate'=>"TEXT DEFAULT ''",'culprit1_address'=>"TEXT DEFAULT ''",'culprit2_type'=>"TEXT DEFAULT 'Verursacher 2'",'culprit2_name'=>"TEXT DEFAULT ''",'culprit2_birthdate'=>"TEXT DEFAULT ''",'culprit2_address'=>"TEXT DEFAULT ''",'culprit_unknown_type'=>"TEXT DEFAULT 'Unbekannt'",'culprit_unknown_name'=>"TEXT DEFAULT ''",'culprit_unknown_birthdate'=>"TEXT DEFAULT ''",'culprit_unknown_address'=>"TEXT DEFAULT ''"];
$existing=array_column($cols,'name');
foreach($required as $column=>$definition)if(!in_array($column,$existing,true))$db->exec("ALTER TABLE incidents ADD COLUMN {$column} {$definition}");
$pc=$db->query('PRAGMA table_info(personnel)')->fetchAll(PDO::FETCH_ASSOC);$pe=array_column($pc,'name');
if(!in_array('vehicle_id',$pe,true))$db->exec('ALTER TABLE personnel ADD COLUMN vehicle_id INTEGER DEFAULT NULL');
if(in_array('function',$pe,true))$db->exec('ALTER TABLE personnel DROP COLUMN function');
if((int)$db->query('SELECT COUNT(*) FROM vehicles')->fetchColumn()===0){$s=$db->prepare('INSERT INTO vehicles(name,call_sign) VALUES(?,?)');$s->execute(['LF 8','LF 8']);$s->execute(['MTW','MTW']);}
function h(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
