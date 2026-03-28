# 3. Systemvoraussetzungen

Dieses Kapitel beschreibt die empfohlenen technischen Voraussetzungen für den Betrieb von SVWS‑Media in verschiedenen Umgebungen.

1) Betrieb auf einem Rechner (Einzelplatz / Testumgebung)
- Betriebssystem: Windows 10/11, macOS (aktuelle Versionen) oder eine moderne Linux‑Distribution (Debian/Ubuntu/Fedora).
- Webserver: Lokaler Webserver wie Apache oder Nginx mit PHP‑Unterstützung oder integrierter PHP‑Webserver für Tests.
- PHP: Version 8.0 oder höher (empfohlen 8.1/8.2). Erforderliche PHP‑Erweiterungen: `pdo`, `pdo_sqlite`, `mbstring`, `json`, `openssl`, `fileinfo`.
- Datenbank: SQLite (Standard: `data/database.sqlite`). SVWS‑Media nutzt SQLite als eingebettetes Datenbanksystem; es ist keine separate DB‑Installation erforderlich. Stellen Sie sicher, dass das Verzeichnis `data/` und die Datei `data/database.sqlite` vom Webserver beschreibbar sind und regelmäßige Backups durchgeführt werden.
- Hardware (Empfehlung für Entwicklung/Einzelplatz): 2 CPU‑Kerne, 4 GB RAM, 2 GB freier Festplattenspeicher.

2) Betrieb unter Docker
- Voraussetzungen: Docker Engine (>= 20.x) und optional `docker-compose` oder Compose V2.
- Vorteile: Einfache Wiederholbarkeit, isolierte Umgebung, schnelle Bereitstellung von Webserver und PHP.
- Beispielkomponenten: PHP‑FPM Container, Nginx Reverse Proxy, SQLite‑Volume für `data/database.sqlite`.
- Hinweise: Volumes für persistente Daten (insbesondere `data/` für die SQLite‑Datenbank und `public/uploads`) konfigurieren; Netzwerk‑Einstellungen (Ports) anpassen; bei produktivem Betrieb Ressourcenlimits setzen. Achten Sie bei SQLite auf passende Mount‑Berechtigungen und setzen Sie ggf. `PRAGMA journal_mode=WAL` für bessere Parallelität.

Docker‑Beispiel: Volume‑Mount und WAL‑Initialisierung

Ein kurzes `docker-compose`‑Beispiel, das das `data/`‑Verzeichnis einbindet und eine einmalige Initialisierung der SQLite‑Datei vornimmt:

```yaml
services:
	app:
		image: php:8.1-fpm
		volumes:
			- ./data:/var/www/data
			- ./public:/var/www/public
		working_dir: /var/www
		ports:
			- "8080:80"

	sqlite-init:
		image: nouchka/sqlite
		depends_on:
			- app
		volumes:
			- ./data:/data
		entrypoint: ["/bin/sh", "-c"]
		command: |
			test -f /data/database.sqlite || sqlite3 /data/database.sqlite 'VACUUM;';
			sqlite3 /data/database.sqlite 'PRAGMA journal_mode=WAL;'
		restart: "no"
```

Hinweis: Das Beispiel verwendet ein kleines SQLite‑Image (`nouchka/sqlite`) für die einmalige Initialisierung. Alternativ können Sie die WAL‑Konfiguration nach dem Start per `docker exec` setzen:

```bash
docker exec -it <app_container> sqlite3 /var/www/data/database.sqlite "PRAGMA journal_mode=WAL;"
```

Wichtig: Stellen Sie sicher, dass das Verzeichnis `data/` auf dem Host dem Webserver‑User gehört bzw. beschreibbar ist, z. B.:

```bash
chown -R 1000:1000 data/    # Beispiel: UID/GID des Container‑Users anpassen
chmod -R 750 data/
```


3) Betrieb auf einem Server (Schulnetz/Hoster)
- Infrastruktur: Linux‑Server (Debian/Ubuntu empfohlen) mit Webserver (Nginx/Apache) und PHP‑FPM.
- PHP: Version 8.1 oder höher empfohlen; gleiche PHP‑Erweiterungen wie oben. Stellen Sie sicher, dass `opcache` aktiviert ist für bessere Performance.
-- Datenbank: SQLite (Standard: `data/database.sqlite`). Für die meisten Schul‑Installationen ist SQLite ausreichend und reduziert den Verwaltungsaufwand. Beachten Sie bei erhöhter Last Dateisystemwahl (z. B. ext4) und Backup‑Strategien. Regelmäßige Backups der Datei `data/database.sqlite` sind verpflichtend.
- Security & Betrieb: TLS/HTTPS (Let's Encrypt oder kommerzielles Zertifikat), Firewall (z. B. UFW), sichere Dateirechte, regelmäßige Updates.
- Ressourcen (empfohlen für kleine Schulumgebung): 2‑4 CPU‑Kerne, 4–8 GB RAM, 10+ GB freier Festplattenspeicher je nach Medienbestand und Uploads.

Allgemeine Hinweise
- Browser: Aktuelle Versionen von Chrome, Firefox, Edge oder Safari werden unterstützt.
- Netzwerkanforderungen: Für lokale Nutzung im Schulnetzwerk genügt LAN; für Synchronisation mit externen SVWS‑Servern ist ausgehender Internetzugang (HTTPS) erforderlich.
- Offline‑Synchronisation: Für Szenarien ohne permanente Internetverbindung ist eine lokale Synchronisationsoption vorgesehen — beachten Sie dazu die Administrationshinweise in Kapitel 11.

---
Stand: Benutzerhandbuch für SVWS‑Media

[Zurück zu Kapitel 02](02-zielgruppe-und-rollen.md) | [Zurück zum Inhalt](index.md) | [Weiter zu Kapitel 04](04-anmeldung-und-sicherheit.md)
