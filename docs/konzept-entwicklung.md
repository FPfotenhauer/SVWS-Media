# Erstes Entwicklungskonzept fuer SVWS-Media

## 1. Zielbild
SVWS-Media ist eine schlanke, serverseitig gerenderte PHP-Webanwendung zur Verwaltung von Schulmedien.
Die Loesung orientiert sich an den Prinzipien aus agent.md und port_enmserver.md:

- klare Trennung von UI, Logik und Datenzugriff
- keine externen PHP-Frameworks
- SQLite als lokale, einfache Datenbasis
- funktionale, tabellarische Oberflaechen im Admin-Stil

## 2. Zielarchitektur

Browser (UI)
-> PHP Web App (Routing, Views, Services)
-> SQLite (persistente Daten)

## 3. Modulzuschnitt

- media: Medienstammdaten und CRUD
- lending: Ausleihe, Rueckgabe, Status
- auth: Session-Login und Rollen

## 4. Technische Leitplanken

- PDO fuer alle DB-Zugriffe
- SQL nur in Service-Schicht
- keine Geschaeftslogik in Templates
- defensive Ausgabe im HTML via htmlspecialchars
- einfache Includes statt Build-Tooling

## 5. Datenmodell (MVP)

- media(id, title, type, isbn, inventory_number, condition, location)
- users(id, username, role)
- lending(id, media_id, user_id, borrowed_at, returned_at, status)

## 6. Umsetzungsphasen

1. Basisstruktur und Laufzeitpfad
2. Datenbank und Schema-Initialisierung
3. Medienliste, Detail, Anlegen/Bearbeiten
4. Ausleihe und Rueckgabe
5. Login und Rollenrechte
6. Stabilisierung, Tests, Dokumentation

## 7. Definition of Done fuer den MVP

- Medienliste ist im Browser sichtbar
- Neues Medium kann angelegt werden
- Ausleihe kann erzeugt und abgeschlossen werden
- Einfache Login-Session ist vorhanden
- Daten bleiben in SQLite persistent erhalten
