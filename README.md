# 🚒 FwDesk Halchter

**FwDesk – Die digitale Einsatzdoku für Feuerwehren**

Eine schlanke, webbasierte Einsatzdokumentation für Feuerwehren. Die Anwendung läuft als Docker-Container mit **PHP 8.3, Apache und SQLite** und ist für eine einfache, schnelle Dokumentation von Feuerwehreinsätzen ausgelegt.

**Aktuelle Version:** 0.9  
**Copyright:** T.König 2026

---

## 📋 Funktionen

### 🚨 Einsatzverwaltung

- Einsätze neu anlegen und bearbeiten
- Einsatznummer als frei verwendbares Feld
- Einsatz / Stichwort
- Einsatzort
- Einsatzdatum
- Alarmierungszeit
- **Kostenbeginn**
- Einsatzende
- Automatische Berechnung der Einsatzdauer
- Berücksichtigung von Einsätzen über Mitternacht
- Einsatzleiter
- Dokument ausgefüllt von
- Bemerkungen
- Material / sonstiges Material
- Einsätze anzeigen und löschen

### 🚒 Fahrzeuge und Besatzung

- Fahrzeuge aus den Stammdaten auswählen
- Mehrere Fahrzeuge pro Einsatz
- Personal je Fahrzeug zuordnen
- Personal kann fahrzeugübergreifend ausgewählt werden
- Separate **VA-Kennzeichnung** (Verantwortlicher / VA) pro Besatzungsmitglied
- Zugeordnete Besatzung wird in der Einsatzansicht und im Ausdruck angezeigt

### 👨‍🚒 Stammdaten

Verwaltung von:

- Personal
- Fahrzeugen
- Funkrufnamen
- DIVERA-Benutzer-ID des Personals
- Material

Personal und Fahrzeuge können angelegt, bearbeitet und – sofern nicht bereits verwendet – gelöscht werden.

---

## 🔗 DIVERA 24/7

FwDesk kann mit **DIVERA 24/7** verbunden werden.

Unter **Stammdaten → DIVERA 24/7** kann der AccessKey hinterlegt werden.

### DIVERA-Funktionen

- Import von Einsätzen
- Import eines einzelnen Einsatzes über die DIVERA EinsatzID
- Import von DIVERA-Benutzern
- Abgleich der DIVERA Benutzer mit dem lokalen Personal
- Übernahme von Einsatzrückmeldungen
- Anzeige des Rückmeldestatus
- Zuordnung der Rückmeldungen zu den lokalen Personen
- Auswahl gemeldeter Personen je Fahrzeug

### Rückmeldestatus

Die Rückmeldungen werden entsprechend ihrem Status dargestellt, unter anderem:

- **sofort**
- **innerhalb 10 min**
- **30 minuten**
- **Nicht Einsatzbereit**

Die Rückmeldungen werden bei der Fahrzeugbesatzung entsprechend vorgeschlagen.

---

## 📷 Einsatzbilder

Zu jedem Einsatz können bis zu **4 Bilder** gespeichert werden.

Unterstützt werden aktuell:

- JPEG
- PNG
- WebP
- HEIC
- HEIF

Die Bilder werden dauerhaft im Docker-Datenvolume gespeichert und dem jeweiligen Einsatz zugeordnet.

Bilder können:

- beim Anlegen eines Einsatzes hochgeladen werden
- beim Bearbeiten ergänzt werden
- aus einem Einsatz wieder gelöscht werden
- in der Einsatzansicht angezeigt werden
- beim Drucken / PDF-Ausdruck berücksichtigt werden

Die Dateiauswahl verwendet `accept="image/*"`, damit insbesondere mobile Geräte wie iPhones ihre verfügbaren Bildformate anbieten können.

---

## 👤 Login / Zugriffsschutz

FwDesk ist über **HTTP Basic Authentication** geschützt.

Die Zugangsdaten werden über die Apache-Konfiguration in:

`public/.htaccess`

und

`public/.htpasswd`

festgelegt.

> Für einen produktiven Zugriff über das Internet sollte zusätzlich HTTPS verwendet werden.

---

## 🖨️ Druck / PDF

Die Einsatzansicht besitzt eine Druckfunktion.

Der Ausdruck enthält unter anderem:

- Einsatzdaten
- Alarmierungszeit
- Kostenbeginn
- Ende
- Einsatzdauer
- Einsatzleiter
- Dokument ausgefüllt von
- Fahrzeuge
- Besatzung
- VA-Kennzeichnung
- Material
- Bemerkungen
- Persondaten
- Einsatzbilder

Die Navigation und Bearbeitungsbuttons werden beim Drucken ausgeblendet.

Im Ausdruck befindet sich die FwDesk-Fußzeile:

**FwDesk - Die digitale Einsatzdoku für Feuerwehren · Version 0.9 · Copyright T.König 2026**

---

## 💾 Datenhaltung

FwDesk verwendet **SQLite**.

Die Datenbank und hochgeladenen Bilder liegen im persistenten Docker-Verzeichnis:

`/var/www/data`

Damit bleiben die Daten bei einem Container-Neustart bzw. beim Neuerstellen des Containers erhalten, solange das Docker-Volume nicht gelöscht wird.

### Wichtige Daten

- Einsätze
- Personal
- Fahrzeuge
- Material
- Einsatz-/Fahrzeugzuordnungen
- Besatzungen
- VA-Zuordnungen
- DIVERA-Rückmeldungen
- Einsatzbilder
- DIVERA AccessKey

---

## 🐳 Docker

### Installation

Repository klonen:

```bash
git clone https://github.com/TobiasKWF/fw-verwaltung.git
cd fw-verwaltung
```

Container bauen und starten:

```bash
docker compose up -d --build
```

Die mitgelieferte `docker-compose.yml` verwendet:

- Container: `fw-verwaltung`
- Port: `8080:80`
- Volume: `fw_data:/var/www/data`
- Restart: `unless-stopped`

Anschließend ist FwDesk erreichbar unter:

```
http://SERVER-IP:8080
```

---

## 🔄 Update

Nach Änderungen am Repository:

```bash
git pull
docker compose up -d --build
```

Das Datenvolume wird dabei weiterverwendet.

**Das Volume mit den Einsatzdaten und Bildern darf bei einem Update nicht gelöscht werden.**

---

## 🗂️ Projektstruktur

```
fw-verwaltung/
├── public/
│   ├── index.php
│   ├── .htaccess
│   └── .htpasswd
├── src/
│   └── db.php
├── Dockerfile
├── docker-compose.yml
└── README.md
```

### `public/index.php`

Enthält die Weboberfläche und die Verarbeitung der Einsatzdaten.

### `src/db.php`

Enthält Datenbankverbindung, Tabellenanlage und notwendige Migrationen.

### `public/.htaccess`

Apache Basic Authentication.

### `public/.htpasswd`

Benutzerkonto für die HTTP-Authentifizierung.

### `Dockerfile`

Erstellt das PHP-8.3-Apache-Image inklusive SQLite-Unterstützung und persistentem Datenverzeichnis.

---

## 🗃️ Datenbankbereiche

Die Anwendung verwendet unter anderem folgende Tabellen:

- `incidents`
- `vehicles`
- `personnel`
- `materials`
- `incident_vehicles`
- `incident_personnel`
- `incident_divera_responses`
- `incident_photos`

Die Datenbank wird beim Start automatisch angelegt bzw. um benötigte Felder erweitert.

---

## 📱 Mobile Nutzung

Die Oberfläche ist responsive und kann auch auf Smartphones und Tablets verwendet werden.

Besonders für die Einsatzdokumentation vor Ort vorgesehen sind:

- mobile Einsatzanlage
- mobile Bildaufnahme
- direkte Bildübertragung
- Besatzungsauswahl
- DIVERA-Rückmeldungen
- Bearbeitung bestehender Einsätze

---

## 🔐 Sicherheitshinweise

FwDesk ist für den Betrieb in einer geschützten Umgebung gedacht.

Empfehlungen:

1. Zugriff nicht ungeschützt ins öffentliche Internet stellen.
2. Für externe Zugriffe HTTPS verwenden.
3. Regelmäßige Backups des Docker-Volumes erstellen.
4. Den DIVERA AccessKey nicht öffentlich weitergeben.
5. Das Docker-Volume `fw_data` nicht versehentlich löschen.

---

## 💾 Backup

Das wichtigste Backup ist das Docker-Volume mit den persistenten Daten.

Beispiel:

```bash
docker run --rm \
  -v fw_data:/data \
  -v $(pwd):/backup \
  alpine \
  tar czf /backup/fwdesk-backup.tar.gz /data
```

Damit werden Datenbank und gespeicherte Einsatzbilder gemeinsam gesichert.

---

## 🧭 Bedienung

### Neuer Einsatz

**Einsätze → + Neuer Einsatz**

1. Einsatzdaten erfassen
2. Fahrzeuge auswählen
3. Besatzung auswählen
4. VA bei Bedarf markieren
5. Material und Bemerkungen erfassen
6. Persondaten erfassen
7. Bis zu 4 Einsatzbilder auswählen
8. **Einsatz speichern**

### Einsatz bearbeiten

In der Einsatzübersicht den gewünschten Einsatz öffnen und:

**✏️ Bearbeiten → Änderungen speichern**

### Einsatz drucken

Einsatz öffnen:

**🖨️ PDF / Drucken**

Anschließend im Browser den Drucker bzw. **Als PDF speichern** auswählen.

---

## 🛠️ Technischer Stack

- PHP 8.3
- Apache 2
- SQLite
- PDO SQLite
- HTML5
- CSS
- JavaScript
- Docker
- DIVERA 24/7 API

---

## 📌 Projektziel

FwDesk soll eine **einfache, übersichtliche und praxistaugliche digitale Einsatzdokumentation** für Feuerwehren bereitstellen.

Der Schwerpunkt liegt auf:

- schneller Erfassung
- einfacher Bedienung
- mobiler Nutzung
- DIVERA-Anbindung
- sauberer Einsatzdokumentation
- persistenten Einsatzbildern
- unkompliziertem Docker-Betrieb

---

## 📄 Lizenz / Copyright

**FwDesk – Die digitale Einsatzdoku für Feuerwehren**

Version 0.9  
Copyright T.König 2026
