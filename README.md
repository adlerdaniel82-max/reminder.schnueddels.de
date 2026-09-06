# Reminder

Reminder ist eine PHP-Webanwendung für persönliche Termine, wiederkehrende Erinnerungen, Kalenderansichten sowie optionale E-Mail- und Web-Push-Benachrichtigungen. Die Anwendung nutzt das zentrale Schnüddels-Auth-System; ein eigenständiger Betrieb benötigt eine kompatible Auth-API.

## Projektstruktur

```text
reminder.schnueddels.de/
├── public_html/                 # Web-Root: App, API, Import, Widget und Service Worker
│   ├── backend/sql/             # Datenbankmigrationen
│   └── composer.json            # PHP-Abhängigkeiten
├── private/auth/                # Auth-Client für die zentrale Auth-API
├── bin/send_reminders.php       # CLI-Job für E-Mail, Push und Wiederholungen
├── sql/schema.sql               # Basisschema für neue Datenbanken
├── .env.example                 # Konfigurationsvorlage ohne Zugangsdaten
└── install.sh                   # Installation von Abhängigkeiten und Datenbankstruktur
```

## Voraussetzungen

- PHP 8.3 mit `pdo_mysql`, `curl` und den von Composer geforderten Erweiterungen
- Composer 2
- MariaDB oder MySQL
- Webserver, dessen Dokumentenwurzel auf `public_html/` zeigt
- Für den gehosteten Einsatz: eine kompatible Schnüddels-Auth-API und ein projektspezifisches Shared Secret
- Optional: SMTP-Zugang für E-Mails und ein VAPID-Schlüsselpaar für Web Push

## Neuinstallation

1. Repository auschecken und eine leere Datenbank mit zugehörigem Benutzer anlegen.
2. `cp .env.example private/.env` ausführen und die Werte für Datenbank und Auth-Integration setzen. Für E-Mails zusätzlich `SMTP_*`; für Push beide `VAPID_*`-Werte setzen.
3. `chmod 700 install.sh && ./install.sh` ausführen. Das Skript installiert Composer-Abhängigkeiten und spielt erst `sql/schema.sql`, dann die aktuelle Migration ein.
4. Die Webserver-Dokumentenwurzel auf `public_html/` setzen und den Zugriff auf `private/` sperren.
5. Für die Hintergrundverarbeitung einen Cronjob als Webserver-Benutzer einrichten:

   ```cron
   */5 * * * * /usr/bin/php /absoluter/pfad/reminder.schnueddels.de/bin/send_reminders.php >> /absoluter/pfad/reminder.schnueddels.de/logs/reminder-cron.log 2>&1
   ```

Die Installation speichert keine Zugangsdaten im Repository. Die Datei `private/.env` bleibt lokal und darf nicht eingecheckt werden.

## Betriebshinweise

- Das Widget kann über `public_html/widget.js` eingebunden werden.
- E-Mail- und Push-Zustellung sind optional. Ohne ihre Konfiguration bleibt die Kalender- und Reminder-Verwaltung verfügbar.
- Die App ist ein Organisationswerkzeug und keine Ersatzlösung für rechtlich oder sicherheitskritische Alarmierungswege.
