<?php
declare(strict_types=1);
$dbPath=__DIR__.'/../data/fw.sqlite';
if(!is_dir(dirname($dbPath))) mkdir(dirname($dbPath),0775,true);
$db=new PDO('sqlite:'.$dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec("CREATE TABLE IF NOT EXISTS vehicles(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,call_sign TEXT DEFAULT '',active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS personnel(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,function TEXT DEFAULT '',active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS incidents(id INTEGER PRIMARY KEY AUTOINCREMENT,incident_number TEXT DEFAULT '',title TEXT NOT NULL,location TEXT NOT NULL,incident_date TEXT NOT NULL,alarm_time TEXT DEFAULT '',start_time TEXT DEFAULT '',end_time TEXT DEFAULT '',material TEXT DEFAULT '',notes TEXT DEFAULT '',incident_leader TEXT DEFAULT '',document_filled_by TEXT DEFAULT '',culprit1_name TEXT DEFAULT '',culprit1_birthdate TEXT DEFAULT '',culprit2_name TEXT DEFAULT '',culprit2_birthdate TEXT DEFAULT '',culprit_unknown_name TEXT DEFAULT '',culprit_unknown_birthdate TEXT DEFAULT '',created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE IF NOT EXISTS incident_vehicles(incident_id INTEGER NOT NULL,vehicle_id INTEGER NOT NULL,PRIMARY KEY(incident_id,vehicle_id),FOREIGN KEY(incident_id) REFERENCES incidents(id) ON DELETE CASCADE,FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS incident_personnel(incident_id INTEGER NOT NULL,vehicle_id INTEGER NOT NULL,personnel_id INTEGER NOT NULL,PRIMARY KEY(incident_id,vehicle_id,personnel_id),FOREIGN KEY(incident_id) REFERENCES incidents(id) ON DELETE CASCADE,FOREIGN KEY(vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,FOREIGN KEY(personnel_id) REFERENCES personnel(id) ON DELETE CASCADE);
CREATE TABLE IF NOT EXISTS materials(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL UNIQUE,active INTEGER NOT NULL DEFAULT 1);
CREATE TABLE IF NOT EXISTS incident_materials(incident_id INTEGER NOT NULL,material_id INTEGER NOT NULL,PRIMARY KEY(incident_id,material_id),FOREIGN KEY(incident_id) REFERENCES incidents(id) ON DELETE CASCADE,FOREIGN KEY(material_id) REFERENCES materials(id) ON DELETE CASCADE);");
$incidentColumns=$db->query('PRAGMA table_info(incidents)')->fetchAll(PDO::FETCH_ASSOC);
$requiredIncidentColumns=['incident_number'=>"TEXT DEFAULT ''",'alarm_time'=>"TEXT DEFAULT ''",'incident_leader'=>"TEXT DEFAULT ''",'document_filled_by'=>"TEXT DEFAULT ''",'culprit1_name'=>"TEXT DEFAULT ''",'culprit1_birthdate'=>"TEXT DEFAULT ''",'culprit2_name'=>"TEXT DEFAULT ''",'culprit2_birthdate'=>"TEXT DEFAULT ''",'culprit_unknown_name'=>"TEXT DEFAULT ''",'culprit_unknown_birthdate'=>"TEXT DEFAULT ''"];
$existing=array_column($incidentColumns,'name');
foreach($requiredIncidentColumns as $column=>$definition){if(!in_array($column,$existing,true)) $db->exec("ALTER TABLE incidents ADD COLUMN {$column} {$definition}");}
$personnelColumns=$db->query('PRAGMA table_info(personnel)')->fetchAll(PDO::FETCH_ASSOC);
$personnelExisting=array_column($personnelColumns,'name');
if(!in_array('vehicle_id',$personnelExisting,true)) $db->exec('ALTER TABLE personnel ADD COLUMN vehicle_id INTEGER DEFAULT NULL');
if((int)$db->query('SELECT COUNT(*) FROM vehicles')->fetchColumn()===0){$s=$db->prepare('INSERT INTO vehicles(name,call_sign) VALUES(?,?)');$s->execute(['LF 8','LF 8']);$s->execute(['MTW','MTW']);}
function h(?string $v):string{return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
