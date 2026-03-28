# SVWS-Media

Ein schlankes Medienverwaltungsprogramm für Schulen in NRW, orientiert am SVWS‑Paradigma.

## Funktionsumfang

- serverseitig gerenderte PHP‑Webanwendung mit SQLite
- Login/Logout und lokale Benutzerverwaltung (admin/viewer)
- Medienverwaltung mit Titel/Exemplar‑Modell
- Barcode‑Ausleihe und Rückgabe inkl. Zuordnung unbekannter Barcodes
- Ausleiherverwaltung (Memo, Sperren, Kontomigration)
- SVWS‑Synchronisation (Schüler, Lehrkräfte)
- Report‑Hub mit Druckansichten und CSV‑Exporten

## Benutzerhandbuch

https://fpfotenhauer.github.io/SVWS-Media/

## Architektur

Browser → PHP Web App → SQLite

Hinweise zur Architektur und Entscheidungen:

- archtecture/adr001.md
- docs/konzept-entwicklung.md

## Projektstruktur

```text
public/
    index.php
    login.php
    logout.php
    dashboard.php
    media_list.php
    lending.php
    borrowers.php
    reports.php
    report_media.php
    report_borrower.php
    sync_svws.php
    sync_data.php
    users.php

src/
    config/
        config.php
        database.php
    auth/
        user.php
    modules/
        media/
            media_service.php
            media_list.php
            media_form.php
        lending/
            lending_service.php
            lending_list.php
        sync/
            svws_sync_service.php
            svws_data_service.php

templates/
    header.php
    footer.php
    layout.php

data/
    database.sqlite
    schema.sql
    .gitkeep

docs/
    konzept-entwicklung.md
```

## Installation

### Voraussetzungen

- PHP 8.1+ (empfohlen 8.2 oder 8.3)
- PHP-Erweiterungen: pdo_sqlite, sqlite3, curl, zlib, json
- Schreibrechte auf data/

Prüfen:

```bash
php -v
php -m | grep -E 'pdo_sqlite|sqlite3|curl|zlib|json'
```

### Lokaler Start (mit PHP Built-in Server)

```bash
cd /pfad/zu/SVWS-Media
php -S 127.0.0.1:8080 -t public
```

Danach im Browser:

- http://127.0.0.1:8080/login.php

### Alternative ohne lokale PHP‑Installation (Docker)

```bash
cd /pfad/zu/SVWS-Media
docker run --rm -it -p 8080:8080 -v "$PWD":/app -w /app php:8.3-cli \
  php -S 0.0.0.0:8080 -t public
```

### Start mit Docker Compose (empfohlen)

Im Projekt liegt eine fertige `docker-compose.yml`.

Starten:

```bash
cd /pfad/zu/SVWS-Media
docker compose up -d
```

Logs ansehen:

```bash
docker compose logs -f
```

Stoppen:

```bash
docker compose down
```

Danach im Browser:

- http://127.0.0.1:8080/login.php

Hinweis:

- Prüfen Sie Dateiberechtigungen auf dem Host. Die Compose‑Konfiguration enthält Workarounds, damit SQLite auf Linux‑Bind‑Mounts schreiben kann; idealerweise läuft der Container nicht als `root`.

### Erstlogin

- Benutzer: `Admin`
- Passwort: `admin`

Wichtig: Passwort nach dem ersten Login in der Benutzerverwaltung ändern.

## Konfiguration

Optional per Umgebungsvariablen (vor allem für SVWS‑Sync):

- `SVWS_BASE_URL` (Default: https://localhost:8443)
- `SVWS_SCHEMA` (kein Default, muss für Sync gesetzt sein)
- `SVWS_ID_LERNPLATTFORM` (Default: 1)
- `SVWS_ID_SCHULJAHRESABSCHNITT` (Default: 1)
- `SVWS_VERIFY_TLS` (true/false)
- `SVWS_USERNAME` (Default: Admin)
- `SVWS_PASSWORD` (Default: leer)

Beispiel:

```bash
export SVWS_BASE_URL="https://meineIp:8443"
export SVWS_SCHEMA="svwsdb"
export SVWS_ID_LERNPLATTFORM=1
export SVWS_ID_SCHULJAHRESABSCHNITT=1
export SVWS_VERIFY_TLS=false
export SVWS_USERNAME="Admin"
export SVWS_PASSWORD="<passwort>"
php -S 127.0.0.1:8080 -t public
```

Mit Docker Compose können die gleichen Variablen z. B. über eine `.env` im Projektverzeichnis gesetzt werden:

```env
SVWS_BASE_URL=https://meineIp:8443
SVWS_SCHEMA=svwsdb
SVWS_ID_LERNPLATTFORM=1
SVWS_ID_SCHULJAHRESABSCHNITT=1
SVWS_VERIFY_TLS=false
SVWS_USERNAME=Admin
SVWS_PASSWORD=<passwort>
APP_SECRET=<optional-fester-secret-key>
```

## Navigation / Seiten

- `/login.php`
- `/dashboard.php`
- `/media_list.php`
- `/lending.php`
- `/borrowers.php`
- `/reports.php`
- `/sync_svws.php`
- `/sync_data.php`
- `/users.php` (nur Rolle `admin`)

## Datenmodell (Kurzuebersicht)

- media_titles / media_copies
- borrowers
- lending
- users
- svws_students / svws_teachers
- svws_sync_runs

## Aktueller Stand

- Login, Rollen und User‑Administration verfügbar
- Medien‑CRUD inkl. Exemplarverwaltung aktiv
- Lending‑Flows (einzeln) aktiv
- Ausleiherverwaltung inkl. Sperre/Memo/Migration aktiv
- Report‑Hub mit HTML‑Druck und CSV‑Export aktiv
- SVWS‑Sync und Datenansichten aktiv

## SVWS-Synchronisation

SVWS-Media kann Daten vom SVWS-Server ueber den GZIP-Endpunkt importieren:

- /api/external/{schema}/v1/lernplattformen/{idLernplattform}/{idSchuljahresabschnitt}/gzip

Ablauf in der App:

1. `/sync_svws.php` öffnen
2. Base‑URL, Schema und IDs prüfen
3. optional BasicAuth und TLS‑Verifikation setzen
4. Synchronisation starten
5. Ergebnis in `/sync_data.php` prüfen

Persistiert werden:

- Schüler (svws_students)
- Lehrkräfte (svws_teachers)

## Deployment

### Option A: Direkt mit Apache oder Nginx + PHP-FPM

- Document Root auf public/ setzen
- Schreibrechte fuer den Webserver-User auf data/ sicherstellen
- PHP-Erweiterungen wie unter Installation beschrieben aktivieren

Minimaler Ablauf:

1. Projekt nach /var/www/svws-media deployen
2. Virtual Host auf /var/www/svws-media/public zeigen lassen
3. Besitz/Rechte setzen, z. B. fuer www-data
4. Webserver neu laden

### Option B: Reverse Proxy vor PHP Built-in Server

Für kleine interne Umgebungen kann ein Reverse Proxy (Nginx/Traefik/Caddy) vor dem Built‑in Server laufen.

Start der App:

```bash
php -S 127.0.0.1:8080 -t public
```

Beispiel Nginx-Proxy:

```nginx
server {
    listen 80;
    server_name svws-media.local;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

### Betriebshinweise

- Backups regelmaessig von data/database.sqlite erstellen
- Passwort fuer Admin direkt nach Erststart aendern
- Bei Produktivbetrieb HTTPS am Proxy oder Webserver erzwingen
- Nach Updates einmal Login, Sync und Reports kurz smoke-testen

## Troubleshooting

- Fehler "php: command not found": PHP installieren oder Docker‑Start (siehe oben) nutzen.
- Fehler "attempt to write a readonly database" mit Docker: Container mit `docker compose down` stoppen und mit der bereitgestellten Compose‑Datei neu starten (`docker compose up -d`).
- Sync liefert 401/403: Zugangsdaten, Schema und Endpunkt prüfen.
- Frontend wirkt träge: nach großen Syncs Browser neu laden und bei Bedarf Datenbankgröße in `data/database.sqlite` prüfen.

## Naechste Schritte

1. PDF-Generierung serverseitig als Download-Option ergaenzen
2. Rollen-/Rechtekonzept fuer Report- und Adminfunktionen verfeinern
3. Automatisierte Tests fuer Lending- und Migrationsregeln aufbauen
4. Performance-Optimierung fuer groessere Bestandsdaten (Pagination/Serverfilter)
