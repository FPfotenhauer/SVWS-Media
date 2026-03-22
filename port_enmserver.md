# 🔁 1:1 Port – Konzept

Wir orientieren uns stark an der Struktur aus:

* SVWS-Server
* insbesondere dem ENM-Bereich:

  * svws-webclient enmserver

### Was wird ersetzt?

| ENM-Server   | SVWS-Media    |
| ------------ | ------------- |
| Notenliste   | Medienliste   |
| Schülerdaten | Medienobjekte |

---

# 📁 Zielstruktur für dieses Modul

```id="mod1"
public/
    media_list.php

src/
    config/
        database.php
    modules/
        media/
            media_service.php
```

---

# ⚙️ 1. Datenbank vorbereiten (SQLite)

Falls noch nicht vorhanden:

```php
// src/config/database.php
<?php

function getDB(): PDO {
    $db = new PDO('sqlite:' . __DIR__ . '/../../data/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $db;
}
```

---

# 🧠 2. Service (analog ENM-Logik-Schicht)

```php
// src/modules/media/media_service.php
<?php

require_once __DIR__ . '/../../config/database.php';

class MediaService {

    public static function getAll(): array {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM media ORDER BY title");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
```

👉 Das entspricht dem typischen SVWS-Prinzip:

* klare Trennung
* DB-Zugriff zentral
* einfache statische Methoden

---

# 🖥️ 3. UI-Seite (1:1 Port-Stil)

```php
// public/media_list.php
<?php

require_once __DIR__ . '/../src/modules/media/media_service.php';

$mediaList = MediaService::getAll();

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Medienverwaltung</title>
    <style>
        body {
            font-family: Arial;
            margin: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
        }
        th {
            background-color: #eee;
        }
    </style>
</head>
<body>

<h1>Medienliste</h1>

<table>
    <tr>
        <th>ID</th>
        <th>Titel</th>
        <th>Typ</th>
        <th>Inventar-Nr.</th>
        <th>Zustand</th>
        <th>Standort</th>
    </tr>

    <?php foreach ($mediaList as $media): ?>
        <tr>
            <td><?= htmlspecialchars($media['id']) ?></td>
            <td><?= htmlspecialchars($media['title']) ?></td>
            <td><?= htmlspecialchars($media['type']) ?></td>
            <td><?= htmlspecialchars($media['inventory_number']) ?></td>
            <td><?= htmlspecialchars($media['condition']) ?></td>
            <td><?= htmlspecialchars($media['location']) ?></td>
        </tr>
    <?php endforeach; ?>

</table>

</body>
</html>
```

---

# 🧩 Was daran „SVWS-like“ ist

Dieses Mini-Modul folgt bereits den wichtigen Prinzipien aus dem SVWS-Umfeld:

✅ klare Trennung (Service vs. UI)
✅ serverseitiges Rendering
✅ einfache Tabellenansicht
✅ keine Framework-Abhängigkeit
✅ direkt lauffähig

---

# 🚀 Nächste sinnvolle Ports

Wenn du diesen Schritt geschafft hast, gehen wir genau so weiter:

## 1. Detailansicht (wie ENM Detailseite)

* `media_detail.php`

## 2. Formular (Bearbeiten/Anlegen)

* `me---

# 👉 Wenn du willst

Ich kann dir als nächsten Schritt direkt liefern:

✅ fertige **media_detail.php (inkl. Navigation)**
✅ **Formular + Speichern (POST)**
✅ oder ein **komplettes Mini-System (CRUD + Ausleihe)**

Sag einfach, was du als nächstes brauchst.dia_form.php`

## 3. Ausleihe (wichtigster Teil)

* `lending_service.php`
* `lending_list.php`

## 4. Auth übernehmen

* Login aus ENM kopieren und anpassen

---

# 💡 Copilot-Prompt für nächsten Schritt

Nutze direkt im Projekt:

> Erweitere die Medienliste um eine Detailansicht mit Link pro Eintrag. Implementiere dazu eine neue Seite media_detail.php und eine Service-Methode getById().


