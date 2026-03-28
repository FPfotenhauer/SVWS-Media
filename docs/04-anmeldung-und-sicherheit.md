# 4. Anmeldung und Sicherheit

Dieses Kapitel enthält praxisnahe Informationen für Anwender:innen zur Anmeldung, zu Passwortrichtlinien, Sitzungsverwaltung und grundlegenden Sicherheitsregeln.

Benutzerinformationen
- **Anmeldung:** Melden Sie sich über die Login‑Maske an (`/login.php`) mit Ihrem Benutzernamen und Passwort an.
- **Erstlogin:** Nach der Erstinstallation existiert ein Default‑Account (`Admin` / `admin`). Ändern Sie das Passwort beim ersten Login sofort in der Benutzerverwaltung.
- **Passwortrichtlinien:** Verwenden Sie ein sicheres Passwort (mindestens 8 Zeichen, Mischung aus Groß/ Kleinbuchstaben, Zahlen und Sonderzeichen). Verwenden Sie keine Schul‑Passwörter für mehrere Dienste.
- **Passwort zurücksetzen:** Administrator:innen können Passwörter in der Benutzerverwaltung zurücksetzen. Standardmäßig werden keine Passwort‑Mails versendet — richten Sie bei Bedarf ein SMTP‑Setup ein.
- **Sitzungen:** Sitzungen laufen standardmäßig nach einer Inaktivität (z. B. 30 Minuten) ab; melden Sie sich bitte nach der Arbeit ab, insbesondere an gemeinsam genutzten Arbeitsplätzen.

Sicherheitsempfehlungen für Betreiber
- **HTTPS zwingend:** Beim Betrieb im Schulnetzwerk oder auf öffentlichem Webspace sollte die Anwendung ausschließlich über HTTPS erreichbar sein (z. B. Let's Encrypt).
- **Dateiberechtigungen:** Das Verzeichnis `data/` und Datei `data/database.sqlite` müssen vom Webserver beschreibbar sein, aber nicht für alle Nutzer weltweit lesbar/schreibbar (z. B. `750` bzw. `640`).
- **Backups:** Regelmäßige Backups der SQLite‑Datei sind Pflicht — automatisierte Kopien und Offsite‑Aufbewahrung empfohlen.
- **Firewall & Netzwerk:** Beschränken Sie externe Zugriffe auf administrative Endpunkte; nutzen Sie Reverse‑Proxy oder VPN für Internetzugänge.
- **Updates:** Führen Sie Sicherheitsupdates für OS, PHP und Webserver regelmäßig durch.

Internetbetrieb und 2FA
- Für den öffentlichen oder Internetbetrieb ist besondere Vorsicht geboten: stellen Sie TLS, starke Zugangsdaten und ein Monitoring sicher.
- **2‑Faktor‑Authentifizierung (2FA):** Eine optionale 2FA‑Unterstützung ist für den Internetbetrieb geplant und wird in einer späteren Version ergänzt. Bis dahin empfehlen wir für externe Zugänge zusätzliche Schutzmaßnahmen wie VPN, IP‑Beschränkungen oder ein zentrales Auth‑Gateway.

Hinweis für Administrator:innen
- Prüfen Sie nach Installation und Updates die Logins, Sync‑Protokolle und Dateiberechtigungen. Testen Sie Backup‑Wiederherstellungen regelmäßig.

---
Stand: Benutzerhandbuch für SVWS‑Media

[Zurück zu Kapitel 03](03-systemvoraussetzungen.md) | [Zurück zum Inhalt](index.md) | [Weiter zu Kapitel 05](05-aufbau-der-benutzeroberflaeche.md)
