# 5. Aufbau der Benutzeroberfläche

Dieses Kapitel beschreibt die Hauptbereiche der Anwendung, ihre Funktion und das Benutzerverhalten. Die Oberfläche gliedert sich in drei übergeordnete Bereiche: die linke Navigation, den Kopfbereich und die zentrale Arbeitsfläche (Content Area).

**Layout-Übersicht**
- **Linke Navigation:** Vertikale Leiste am linken Rand mit Icon-Buttons für die Hauptmodule (Schule, Medien, Daten, Leihe, Sync, Druck, Benutzer). Sie dient als primäre Navigation und bleibt beim Wechsel zwischen Bereichen erhalten.
- **Kopfbereich:** Horizontale Leiste oben mit Anwendungs- oder Seiten-Titel, Kontext-Informationen zur angemeldeten Rolle/Schule, sowie Hilfs- und Einstellungs-Elementen (z. B. Theme-Umschalter, Hilfe/Logout).
- **Arbeitsfläche (Content Area):** Hauptbereich rechts neben der Navigation für Dashboard, Tabellenansichten, Formulare und Detailmodale. Hier werden die jeweiligen Module in voller Breite dargestellt.

**Dashboard (Startseite)**
Das Dashboard ist die Startseite der Anwendung und bietet einen schnellen Überblick über Kennzahlen und Zugriffe. Eine ausführliche Beschreibung des Dashboards, der Kacheln und der Schnellzugriffe findest du in Kapitel 6.

**Seiten / Module**
- **Listenansichten:** Modulseiten zeigen meist tabellarische Übersichten mit Such-, Sortier- und Filterfunktionen; beispielhafte Seiten sind das Dashboard und die Medienliste.
- **Formulare & Dialoge:** Erstellen und Bearbeiten erfolgen in Formularen, teils als eigene Seite, teils in Modal-Dialogen (z. B. `Media bearbeiten` oder `Ausleihe erfassen`).
- **Spezielle Workflows:** Die Ausleihe nutzt eine Barcode-Eingabe und direkte Zuordnung zu Entleihern; die SVWS-Synchronisation bietet Konfigurations- und Ausführungsseiten mit Protokollausgaben.

**Interaktion & Feedback**
- **Aktionen:** Buttons in Kopf- oder Tabellenleisten für anlegen, bearbeiten, löschen und Export.
- **Bestätigungen & Hinweise:** Modale Bestätigungen, Inline-Validierung in Formularen und kurzlebige Benachrichtigungen (Toasts) nach erfolgreichen Aktionen.
- **Export & Druck:** Druck- und CSV-Export-Funktionen sind über das Menü erreichbar und öffnen Druckansichten oder starten Datei-Downloads.

**Responsiveness & Bedienbarkeit**
- Das Layout ist so gestaltet, dass Navigation + Content auch bei kleineren Auflösungen nutzbar bleibt. Kacheln und Tabellen skalieren, Modale werden zentriert angezeigt.
- Fokus- und Tastaturbedienbarkeit verbessern die Zugänglichkeit (z. B. Tab-Navigation, eindeutige Beschriftungen).


