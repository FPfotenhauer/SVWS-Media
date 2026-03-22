# 📄 AGENT.md – SVWS-Media Architektur & Entwicklungsrichtlinien

## 1. Projektübersicht

Dieses Projekt implementiert eine **Webbasierte Medienverwaltung für Schulen** als leichtgewichtige PHP-Anwendung.

Es orientiert sich strukturell und visuell an:

* SVWS-Server
* sowie dem Webclient / ENM-Server-Konzept:

  * svws-enmserver

Der SVWS-Server stellt eine zentrale Webarchitektur mit Backend + Webclient bereit und nutzt standardisierte APIs sowie eine relationale Datenbank für Schulverwaltungsdaten ([svws.nrw.de][1]).
Dieses Projekt übernimmt **die Prinzipien**, aber reduziert die Komplexität stark.

---

## 2. Architekturprinzipien

### 2.1 Zielarchitektur (vereinfacht)

```
Browser (UI)
   ↓
PHP Web App (SVWS-Media)
   ↓
SQLite Datenbank
```

### 2.2 Designentscheidungen

| Entscheidung             | Begründung                                  |
| ------------------------ | ------------------------------------------- |
| SQLite statt MariaDB     | einfache Installation, kein Server nötig    |
| PHP ohne Framework       | maximale Nähe zum ENM-Server                |
| serverseitiges Rendering | einfache Wartung, keine Build-Tools         |
| modulare Struktur        | Erweiterbarkeit (z. B. weitere Medienarten) |

👉 SQLite ist besonders geeignet für kleine bis mittlere Anwendungen und lässt sich direkt in PHP nutzen, ohne separate Serverinstallation ([stefanhuber.github.io][2])

---

## 3. Orientierung am SVWS-Projekt

### Wichtige Referenzen

* [SVWS-Server Repository öffnen](https://libraries.io/maven/de.svws-nrw%3Asvws-enmserver?utm_source=chatgpt.com)
* [SVWS Dokumentation](https://doku.svws-nrw.de/?utm_source=chatgpt.com)

### Abgeleitete Konzepte

| SVWS-Komponente      | SVWS-Media Umsetzung |
| -------------------- | -------------------- |
| Webclient            | PHP-Frontend         |
| Server (API + Logik) | direkte PHP-Logik    |
| MariaDB              | SQLite               |
| Schüler-/Notendaten  | Medien + Ausleihe    |

Der SVWS-Webclient stellt eine browserbasierte Oberfläche für Schulverwaltungsdaten dar ([doku.svws-nrw.de][3]) – genau dieses UI-Paradigma wird hier übernommen.

---

## 4. Projektstruktur

```
/public
    index.php
    dashboard.php

/src
    /config
        config.php
        database.php

    /modules
        media/
            media_list.php
            media_form.php
            media_service.php

        lending/
            lending_list.php
            lending_service.php

    /auth
        login.php
        user.php

/templates
    header.php
    footer.php
    layout.php

/data
    database.sqlite
```

---

## 5. Datenbankdesign (SQLite)

### Verbindung

```php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/database.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

### Tabellen

#### Medien

```sql
CREATE TABLE media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    type TEXT,
    isbn TEXT,
    inventory_number TEXT,
    condition TEXT,
    location TEXT
);
```

#### Benutzer

(übernommen / angepasst aus ENM-Server)

```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT,
    role TEXT
);
```

#### Ausleihe

```sql
CREATE TABLE lending (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_id INTEGER,
    user_id INTEGER,
    borrowed_at TEXT,
    returned_at TEXT,
    status TEXT,
    FOREIGN KEY(media_id) REFERENCES media(id),
    FOREIGN KEY(user_id) REFERENCES users(id)
);
```

---https://github.com/SVWS-NRW/

## 6. Backend-Architektur

### 6.1 Service-orientierter Ansatz

Jedes Modul enthält:

* `*_service.php` → Geschäftslogik
* `*_list.php` → UI
* `*_form.php` → Eingabe

### Beispiel

```php
class MediaService {
    public function getAll(PDO $db): array {
        return $db->query("SELECT * FROM media")->fetchAll();
    }
}
```

---

## 7. UI-Richtlinien (SVWS-Stil)

Die Oberfläche soll:

* tabellarisch organisiert sein
* wenig JavaScript enthalten
* funktional statt „modern fancy“ sein

### Layout-Prinzip

```
+------------------------+
| Menü                   |
+------------------------+
| Tabelle / Inhalte      |
+------------------------+
| Formular / Details     |
+------------------------+
```

---

## 8. Authentifizierung

* einfache Session-basierte Auth
* Benutzerstruktur aus ENM-Server übernehmen
* keine externe Auth (kein OAuth etc.)

---

## 9. Coding-Regeln für Agenten

### Allgemein

* Schreibe **einfachen, klaren PHP-Code**
* Verwende **PDO**
* Keine externen Frameworks

### Struktur

* Keine Logik im Template
* Services enthalten DB-Zugriffe
* Wiederverwendbare Includes nutzen

### Beispiel Include

```php
require_once __DIR__ . '/../config/database.php';
```

---

## 10. Typische Aufgaben für Copilot / Agent

### Neue Funktion

> Implementiere eine CRUD-Verwaltung für ein neues Medienfeld.

### Erweiterung

> Füge eine Statuslogik für ausgeliehene Medien hinzu.

### UI

> Erzeuge eine Tabelle im Stil einer klassischen Admin-Oberfläche.

---

## 11. Erweiterbarkeit

Geplante Module:

* Gerätemanagement (Tablets etc.)
* Mahnwesen
* Barcode-Scanner
* CSV-Import

---

## 12. Architektur-Zusammenfassung

Dieses Projekt ist bewusst:

* **klein**
* **einfach deploybar**
* **nah am SVWS-Paradigma**
* aber **technisch reduziert**

👉 Ziel:
Ein System, das sich wie SVWS anfühlt, aber ohne dessen Komplexität.

---

[1]: https://www.svws.nrw.de/svws-server-schild-nrw-3/svws-server-download?utm_source=chatgpt.com "SVWS-Server - Download | Schulverwaltung NRW IT Anwendungen"
[2]: https://stefanhuber.github.io/serverseitige-softwareentwicklung/?utm_source=chatgpt.com "Serverseitige Softwareentwicklung"
[3]: https://doku.svws-nrw.de/?utm_source=chatgpt.com "SVWS Dokumentation"
