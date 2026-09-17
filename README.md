# 🚒 FW Verwaltung

Einfache Feuerwehr-Einsatzverwaltung – bewusst schlank gehalten.

## Version 1

Ein Einsatz wird angelegt, z. B.:

- **Einsatz:** Ölspur
- **Ort:** Halchterstr. 2
- **Datum / Beginn / Ende**
- **Fahrzeuge:** z. B. LF 8 und MTW
- **Besatzung:** Personal kann je Fahrzeug zugeordnet werden
- **Material:** Freitext
- **Bemerkungen:** Freitext

Keine Kosten, Preise, Statistiken oder komplexes Einsatzmanagement.

## Stammdaten

Über **Stammdaten** können Fahrzeuge und Personal angelegt werden. LF 8 und MTW werden beim ersten Start automatisch angelegt.

## Docker

```bash
git clone https://github.com/TobiasKWF/fw-verwaltung.git
cd fw-verwaltung
docker compose up -d --build
```

Danach ist die Anwendung auf Port **8080** erreichbar:

`http://SERVER-IP:8080`

Die SQLite-Datenbank liegt in einem Docker-Volume und bleibt bei Container-Neustarts erhalten.
