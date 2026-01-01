# Universal License Server - Konzept

## Übersicht

Ein **generisches, Multi-Produkt-fähiges** WordPress-basiertes Lizenzsystem für den Verkauf und die Verwaltung von:

- WordPress Plugins & Themes
- Standalone PHP Scripts
- Desktop-Anwendungen (Electron, etc.)
- Mobile Apps
- SaaS-Zugänge
- Beliebige digitale Produkte

**Ein Server – Alle Produkte – Alle Projekte**

---

## 1. Architektur

### Systemkomponenten

```
┌─────────────────────────────────────────────────────────────┐
│              UNIVERSAL LICENSE SERVER                       │
│            (z.B. licenses.deine-domain.de)                  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    PRODUKTE                          │   │
│  ├─────────────────────────────────────────────────────┤   │
│  │  📦 Sleek Audio Player                               │   │
│  │     ├── Neon Glow Theme                             │   │
│  │     ├── Retro Wave Theme                            │   │
│  │     └── Pro Version                                 │   │
│  │                                                     │   │
│  │  📦 Anderes WordPress Plugin                        │   │
│  │     ├── Standard License                            │   │
│  │     └── Developer License                           │   │
│  │                                                     │   │
│  │  📦 Desktop App                                     │   │
│  │     └── Vollversion                                 │   │
│  │                                                     │   │
│  │  📦 Zukünftige Produkte...                          │   │
│  └─────────────────────────────────────────────────────┘   │
│                         │                                   │
│              REST API Endpoints                             │
│              /wp-json/license/v1/                           │
└─────────────────────────────────────────────────────────────┘
         ▲              ▲              ▲              ▲
         │              │              │              │
      HTTPS          HTTPS          HTTPS          HTTPS
         │              │              │              │
         ▼              ▼              ▼              ▼
┌─────────────┐ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐
│  WordPress  │ │  WordPress  │ │  Desktop    │ │  PHP        │
│  Plugin A   │ │  Plugin B   │ │  App        │ │  Script     │
└─────────────┘ └─────────────┘ └─────────────┘ └─────────────┘
```

---

## 2. Server-Plugin: Universal License Server

### 2.1 Plugin-Struktur

```
developer-license-server/
├── developer-license-server.php    # Hauptdatei
├── includes/
│   ├── class-license-manager.php   # Lizenzverwaltung
│   ├── class-license-api.php       # REST API
│   ├── class-license-admin.php     # Admin-Oberfläche
│   ├── class-product-manager.php   # Produkt-Verwaltung
│   ├── class-file-delivery.php     # Datei-Auslieferung
│   └── class-webhook-handler.php   # PayPal/Stripe Webhooks
├── admin/
│   ├── views/
│   │   ├── dashboard.php           # Übersicht alle Produkte
│   │   ├── licenses-list.php       # Lizenzübersicht
│   │   ├── license-edit.php        # Lizenz bearbeiten
│   │   ├── products-list.php       # Alle Produkte
│   │   ├── product-edit.php        # Produkt bearbeiten
│   │   └── settings.php            # Einstellungen
│   └── css/
│       └── admin.css
├── downloads/                       # Geschützte Downloads
│   └── .htaccess                   # Direktzugriff blockieren
└── languages/
    └── developer-license-server-de_DE.po
```

### 2.2 Datenbank-Schema

#### Tabelle: `wp_dev_licenses`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT AUTO_INCREMENT | Primary Key |
| `license_key` | VARCHAR(50) UNIQUE | z.B. LIC-XXXX-XXXX-XXXX |
| `customer_email` | VARCHAR(255) | Käufer E-Mail |
| `customer_name` | VARCHAR(255) | Käufer Name |
| `product_id` | VARCHAR(50) | Produkt-Identifier |
| `status` | ENUM | 'active', 'expired', 'revoked', 'pending' |
| `max_activations` | INT | Standard: 3 |
| `created_at` | DATETIME | Erstellungsdatum |
| `expires_at` | DATETIME | NULL = unbegrenzt |
| `order_id` | VARCHAR(100) | PayPal/Stripe Order ID |
| `notes` | TEXT | Admin-Notizen |

#### Tabelle: `wp_dev_license_activations`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT AUTO_INCREMENT | Primary Key |
| `license_id` | INT | Foreign Key |
| `domain` | VARCHAR(255) | Aktivierte Domain |
| `ip_address` | VARCHAR(45) | IP bei Aktivierung |
| `activated_at` | DATETIME | Zeitpunkt |
| `last_check` | DATETIME | Letzte Validierung |
| `is_active` | BOOLEAN | Kann deaktiviert werden |

#### Tabelle: `wp_dev_products`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT AUTO_INCREMENT | Primary Key |
| `product_id` | VARCHAR(50) UNIQUE | z.B. 'sap-neon-glow', 'my-plugin-pro' |
| `name` | VARCHAR(255) | Anzeigename |
| `description` | TEXT | Beschreibung |
| `price` | DECIMAL(10,2) | Preis |
| `file_path` | VARCHAR(255) | Pfad zur Download-Datei (optional) |
| `encryption_key` | VARCHAR(64) | Pro-Produkt Schlüssel |
| `version` | VARCHAR(20) | Produkt-Version |
| `is_active` | BOOLEAN | Im Verkauf? |

#### Tabelle: `wp_dev_customers`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT AUTO_INCREMENT | Primary Key |
| `email` | VARCHAR(255) UNIQUE | Kunden-E-Mail |
| `name` | VARCHAR(255) | Kundenname |
| `company` | VARCHAR(255) | Firma (optional) |
| `total_purchases` | INT | Anzahl Käufe |
| `total_spent` | DECIMAL(10,2) | Gesamtumsatz |
| `first_purchase_at` | DATETIME | Erster Kauf |
| `last_purchase_at` | DATETIME | Letzter Kauf |
| `gdpr_consent` | BOOLEAN | DSGVO-Einwilligung |
| `gdpr_consent_at` | DATETIME | Zeitpunkt Einwilligung |
| `is_deleted` | BOOLEAN | Soft-Delete (DSGVO) |
| `deleted_at` | DATETIME | Löschzeitpunkt |
| `notes` | TEXT | Admin-Notizen |
| `created_at` | DATETIME | Erstellt |

#### Tabelle: `wp_dev_download_tokens`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT AUTO_INCREMENT | Primary Key |
| `token` | VARCHAR(64) UNIQUE | Temporärer Download-Token |
| `license_id` | INT | Foreign Key zur Lizenz |
| `product_id` | VARCHAR(50) | Produkt-ID |
| `ip_address` | VARCHAR(45) | IP des Anfragenden |
| `created_at` | DATETIME | Erstellt |
| `expires_at` | DATETIME | Gültig bis (Standard: 5 Min) |
| `used_at` | DATETIME | Eingelöst um (NULL = ungenutzt) |
| `download_count` | INT | Anzahl Downloads (max 1-3) |

---

### 2.3 REST API Endpoints

#### `POST /wp-json/license/v1/verify`

Validiert eine Lizenz und aktiviert ggf. die Domain.

**Request:**
```json
{
  "license_key": "LIC-XXXX-XXXX-XXXX",
  "domain": "kunde-website.de",
  "product_id": "sap-neon-glow",
  "client_version": "1.0.0"
}
```

**Response (Erfolg):**
```json
{
  "valid": true,
  "status": "active",
  "expires_at": "2026-01-01T00:00:00Z",
  "activations_used": 1,
  "activations_max": 3,
  "product": {
    "name": "Neon Glow Theme",
    "version": "1.2.0"
  },
  "download_token": "temp_abc123xyz",
  "token_expires": 300
}
```

**Response (Fehler):**
```json
{
  "valid": false,
  "error_code": "LICENSE_EXPIRED",
  "error_message": "Die Lizenz ist am 01.01.2025 abgelaufen."
}
```

**Fehler-Codes:**
- `INVALID_LICENSE` - Lizenzschlüssel existiert nicht
- `LICENSE_EXPIRED` - Lizenz abgelaufen
- `LICENSE_REVOKED` - Lizenz widerrufen
- `ACTIVATION_LIMIT` - Maximale Aktivierungen erreicht
- `PRODUCT_MISMATCH` - Lizenz gilt nicht für dieses Produkt
- `DOMAIN_BLOCKED` - Domain ist gesperrt

---

#### `POST /wp-json/license/v1/deactivate`

Deaktiviert eine Domain (z.B. bei Umzug).

**Request:**
```json
{
  "license_key": "LIC-XXXX-XXXX-XXXX",
  "domain": "alte-website.de"
}
```

**Response:**
```json
{
  "success": true,
  "activations_remaining": 2
}
```

---

#### `GET /wp-json/license/v1/download`

Lädt eine Produktdatei herunter (mit temporärem Token).

**Request:**
```
GET /wp-json/license/v1/download?token=temp_abc123xyz
```

**Response:**
- Content-Type: application/octet-stream
- Verschlüsselte Produktdatei

---

#### `POST /wp-json/license/v1/check`

Periodische Re-Validierung (ohne neue Aktivierung).

**Request:**
```json
{
  "license_key": "LIC-XXXX-XXXX-XXXX",
  "domain": "kunde-website.de"
}
```

**Response:**
```json
{
  "valid": true,
  "status": "active"
}
```

---

### 2.4 Admin-Oberfläche

#### Hauptmenü: "License Manager"

**Unterseiten:**
1. **Alle Lizenzen** - Übersicht mit Filter/Suche
2. **Neue Lizenz** - Manuell erstellen
3. **Produkte** - Produkt-Verwaltung
4. **Aktivierungen** - Domain-Übersicht
5. **Einstellungen** - API-Keys, E-Mail-Templates
6. **Logs** - API-Zugriffe, Fehler

#### Lizenz-Übersicht (Dashboard Widget)

```
┌─────────────────────────────────────────────────┐
│  License Manager - Dashboard                    │
├─────────────────────────────────────────────────┤
│  Produkte:                 4                    │
│  Aktive Lizenzen:        47                     │
│  Auslaufend (30 Tage):    3                     │
│  Heute validiert:       124                     │
│  Umsatz (Monat):     €1.420                     │
└─────────────────────────────────────────────────┘
```

---

### 2.5 Sicherheitsmaßnahmen

#### API-Sicherheit
```php
// Rate Limiting
$requests_per_minute = get_transient('devlic_rate_' . $ip);
if ($requests_per_minute > 60) {
    return new WP_Error('rate_limit', 'Zu viele Anfragen', array('status' => 429));
}

// Request Signing (optional, erhöhte Sicherheit)
$signature = hash_hmac('sha256', $request_body, $shared_secret);
if (!hash_equals($signature, $request_signature)) {
    return new WP_Error('invalid_signature', 'Ungültige Signatur', array('status' => 401));
}
```

#### Lizenzschlüssel-Format
```
[PREFIX]-[RANDOM]-[CHECKSUM]
LIC-A7X9-K3M2-8F4D     (Generisch)
SAP-NEON-A7X9-8F4D     (Produkt-spezifisch)
PRO-DEV-K3M2-9X2A      (Developer License)
    │    │        │
    │    │        └── Prüfsumme (Luhn-Algorithmus)
    │    └─────────── 8-12 Zeichen random (Base32)
    └──────────────── Konfigurierbarer Prefix pro Produkt
```

#### Datei-Verschlüsselung
```php
// Verschlüsselung beim Hochladen
$encrypted = openssl_encrypt(
    file_get_contents($product_file),
    'AES-256-GCM',
    $product_encryption_key,
    OPENSSL_RAW_DATA,
    $iv,
    $tag
);

// Speichern als verschlüsselte Datei
file_put_contents($path, $iv . $tag . $encrypted);
```

---

## 3. Client-Integration (Beispiele)

Der License Server kann von verschiedenen Client-Typen angesprochen werden:

### 3.1 WordPress Plugin Client (PHP)

```php
class Dev_License_Client {
    
    private $api_url = 'https://licenses.deine-domain.de/wp-json/license/v1/';
    private $cache_duration = 86400; // 24 Stunden
    
    /**
     * Lizenz validieren
     */
    public function activate_license($license_key, $product_id) {
        $domain = $this->get_current_domain();
        
        $response = wp_remote_post($this->api_url . 'verify', array(
            'timeout' => 15,
            'body' => array(
                'license_key' => $license_key,
                'domain' => $domain,
                'product_id' => $product_id,
                'client_version' => MY_PLUGIN_VERSION,
            ),
        ));
        
        if (is_wp_error($response)) {
            return array('valid' => false, 'error' => 'Verbindung fehlgeschlagen');
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($body['valid']) {
            // Lizenz speichern
            $this->save_license_data($license_key, $product_id, $body);
            
            // Produkt-Datei herunterladen (falls vorhanden)
            if (isset($body['download_token'])) {
                $this->download_file($body['download_token'], $product_id);
            }
        }
        
        return $body;
    }
    
    /**
     * Periodische Re-Validierung
     */
    public function periodic_check() {
        $licenses = get_option('my_plugin_licenses', array());
        
        foreach ($licenses as $product_id => $data) {
            $last_check = $data['last_check'] ?? 0;
            
            // Alle 24-72 Stunden prüfen (randomisiert)
            $check_interval = rand(86400, 259200);
            
            if (time() - $last_check > $check_interval) {
                $this->validate_existing_license($product_id);
            }
        }
    }
}
```

### 3.2 Standalone PHP Script Client

```php
function verify_license($license_key, $product_id) {
    $api_url = 'https://licenses.deine-domain.de/wp-json/license/v1/verify';
    
    $data = array(
        'license_key' => $license_key,
        'domain' => $_SERVER['HTTP_HOST'],
        'product_id' => $product_id,
    );
    
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}
```

### 3.3 Desktop App Client (JavaScript/Electron)

```javascript
async function verifyLicense(licenseKey, productId) {
    const response = await fetch('https://licenses.deine-domain.de/wp-json/license/v1/verify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            license_key: licenseKey,
            domain: getMachineId(), // Oder Hardware-ID
            product_id: productId,
        })
    });
    
    return await response.json();
}
```

### 3.4 Python Client

```python
import requests

def verify_license(license_key, product_id, domain):
    response = requests.post(
        'https://licenses.deine-domain.de/wp-json/license/v1/verify',
        json={
            'license_key': license_key,
            'domain': domain,
            'product_id': product_id,
        }
    )
    return response.json()
```

### 3.5 Client-seitige Admin-UI (Beispiel)

#### Lizenz-Eingabe im Plugin/Tool

```
┌─────────────────────────────────────────────────────────────────┐
│  Lizenz-Aktivierung                                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────┐          │
│  │ LIC-XXXX-XXXX-XXXX                           │          │
│  └──────────────────────────────────────────────┘          │
│  [Aktivieren]                                                   │
│                                                                 │
│  Status: ✅ Lizenz aktiv                                        │
│  Gültig bis: Unbegrenzt                                        │
│  Aktivierungen: 1/3                                             │
│                                                                 │
│  [Deaktivieren]  [Lizenz kaufen →]                              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 4. Verschlüsseltes Datei-Format (Optional)

### 4.1 Struktur

```
Verschlüsselte Produktdatei (Binary)
├── Header (32 Bytes)
│   ├── Magic: "DEVLIC01" (8 Bytes)
│   ├── Version: 1 (4 Bytes)
│   ├── IV: (16 Bytes) - Initialization Vector
│   └── Reserved (4 Bytes)
├── Auth Tag (16 Bytes) - GCM Authentication
└── Encrypted Payload (Variable)
    └── Beliebiger Inhalt (JSON, ZIP, etc.)
```

### 4.2 Beispiel: Theme-Daten (Sleek Audio Player)

```json
{
  "meta": {
    "id": "neon-glow",
    "name": "Neon Glow",
    "version": "1.2.0",
    "product_type": "sap-theme"
  },
  "data": {
    // Produkt-spezifische Daten
  }
}
```

### 4.3 Beispiel: Plugin Pro-Version

```json
{
  "meta": {
    "id": "my-plugin-pro",
    "name": "My Plugin Pro",
    "version": "2.0.0",
    "product_type": "wp-plugin"
  },
  "features": {
    "feature_a": true,
    "feature_b": true,
    "api_access": true
  }
}
```

---

## 5. Verkaufsprozess

### 5.1 Manueller Verkauf (Phase 1)

```
1. Kunde kontaktiert dich / zahlt per PayPal/Überweisung
          ↓
2. Du erstellst Lizenz im Admin:
   - Kunde: max@example.com
   - Produkt: [Beliebiges Produkt]
   - Aktivierungen: 3
          ↓
3. System generiert: LIC-XXXX-XXXX-XXXX
          ↓
4. Du sendest E-Mail mit Lizenzschlüssel
          ↓
5. Kunde gibt Key im Tool ein
          ↓
6. Produkt wird aktiviert
```

### 5.2 Automatischer Verkauf (Phase 2)

#### PayPal Integration

```php
// Webhook Endpoint: /wp-json/license/v1/webhook/paypal

function handle_paypal_webhook($request) {
    $event = $request->get_json_params();
    
    if ($event['event_type'] === 'CHECKOUT.ORDER.COMPLETED') {
        $order = $event['resource'];
        $product_id = $order['purchase_units'][0]['custom_id'];
        $email = $order['payer']['email_address'];
        $name = $order['payer']['name']['given_name'];
        
        // Lizenz erstellen
        $license = Dev_License_Manager::create(array(
            'product_id' => $product_id,
            'customer_email' => $email,
            'customer_name' => $name,
            'order_id' => $order['id'],
        ));
        
        // E-Mail senden
        Dev_License_Mailer::send_license_email($license);
    }
}
```

#### Stripe Integration

```php
// Webhook Endpoint: /wp-json/license/v1/webhook/stripe

function handle_stripe_webhook($request) {
    $payload = $request->get_body();
    $sig_header = $request->get_header('Stripe-Signature');
    
    $event = \Stripe\Webhook::constructEvent(
        $payload, $sig_header, $webhook_secret
    );
    
    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        
        // Lizenz erstellen...
    }
}
```

---

## 6. E-Mail-Templates

### 6.1 Lizenz-Zustellung

```
Betreff: Dein Lizenzschlüssel für {product_name}

Hallo {customer_name},

vielen Dank für deinen Kauf!

Dein Lizenzschlüssel für "{product_name}":

    {license_key}

So aktivierst du das Produkt:
1. Öffne die Lizenz-Einstellungen im Produkt
2. Gib deinen Lizenzschlüssel ein
3. Klicke auf "Aktivieren"
4. Fertig!

Du kannst diese Lizenz auf bis zu {max_activations} Installationen verwenden.

Bei Fragen: {support_email}

Viele Grüße,
{company_name}
```

### 6.2 Lizenz läuft ab (14 Tage vorher)

```
Betreff: Deine Lizenz läuft bald ab

Hallo {customer_name},

deine Lizenz für "{product_name}" läuft am {expires_at} ab.

Nach Ablauf funktioniert das Theme weiterhin, erhält aber 
keine Updates mehr.

[Jetzt verlängern →]

Viele Grüße
```

---

## 7. Preismodelle

### Option A: Einmalzahlung
- Theme für immer
- 1 Jahr Updates
- €19-39 pro Theme

### Option B: Jährliche Lizenz
- Theme + Updates solange aktiv
- €9-19/Jahr pro Theme

### Option C: Bundle
- Alle Themes
- Lebenslange Updates
- €79-149 einmalig

**Empfehlung:** Starte mit **Option A** (Einmalzahlung) - einfacher zu verwalten.

---

## 8. Schnellstart-Übersicht

Für die detaillierte Implementierungs-Roadmap siehe **Kapitel 21**.

**Minimaler Start (MVP):**
1. Datenbank-Tabellen anlegen
2. REST API: `/verify`, `/check`, `/deactivate`
3. Admin-UI: Lizenzen & Produkte verwalten
4. Client-Integration in dein erstes Produkt

**Geschätzter Aufwand mit KI:** ~2-3 Tage

---

## 9. Technische Anforderungen

### Server (Lizenzserver)
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- SSL-Zertifikat (HTTPS Pflicht)
- OpenSSL Extension

### Client (Beliebiges Produkt)
- HTTP/HTTPS Unterstützung
- JSON Parser
- cURL, fetch, requests, etc.

---

## 10. Sicherheits-Checkliste

- [ ] HTTPS für alle API-Calls
- [ ] Rate Limiting implementiert
- [ ] Lizenzschlüssel mit Checksum validieren
- [ ] SQL Injection Prevention (Prepared Statements)
- [ ] Download-Dateien nicht direkt zugreifbar (.htaccess)
- [ ] Webhook-Signaturen validieren (PayPal/Stripe)
- [ ] Admin-Bereich nur für Administratoren
- [ ] Logging für verdächtige Aktivitäten
- [ ] Regelmäßige Backups der Lizenzdatenbank

---

## 11. Support-Szenarien

### "Meine Lizenz funktioniert nicht"
1. Lizenzstatus im Admin prüfen
2. Domain-Aktivierungen prüfen
3. API-Logs auf Fehler prüfen

### "Ich möchte die Domain wechseln"
1. Alte Domain deaktivieren (im Admin oder per API)
2. Neue Domain aktivieren

### "Ich habe meinen Key verloren"
1. Im Admin nach E-Mail suchen
2. Key erneut per E-Mail senden

---

## 12. Produktbeispiele

### Mögliche Produkte für diesen Server

| Produkt | Typ | Beispiel-ID |
|---------|-----|-------------|
| Sleek Audio Player Themes | WP Theme/Add-on | `sap-neon-glow` |
| Sleek Audio Player Pro | WP Plugin | `sap-pro` |
| Anderes WordPress Plugin | WP Plugin | `my-plugin-pro` |
| PHP Script | Standalone | `invoice-generator` |
| Desktop App | Electron/etc. | `desktop-app-pro` |
| API Zugang | SaaS | `api-access-tier-1` |

---

## 13. Backup & Restore System

### 13.1 Automatische Backups

```php
class Dev_License_Backup {
    
    /**
     * Tägliches automatisches Backup (via WP-Cron)
     */
    public function scheduled_backup() {
        $backup_data = array(
            'version' => '1.0',
            'created_at' => current_time('mysql'),
            'site_url' => get_site_url(),
            'licenses' => $this->export_all_licenses(),
            'products' => $this->export_all_products(),
            'activations' => $this->export_all_activations(),
            'settings' => $this->export_settings(),
        );
        
        $filename = 'license-backup-' . date('Y-m-d-His') . '.json';
        $encrypted = $this->encrypt_backup($backup_data);
        
        // Lokal speichern
        $this->save_local($filename, $encrypted);
        
        // Optional: Remote speichern (S3, FTP, etc.)
        if (get_option('devlic_remote_backup_enabled')) {
            $this->save_remote($filename, $encrypted);
        }
        
        // Alte Backups aufräumen (behalte letzte 30)
        $this->cleanup_old_backups(30);
    }
    
    /**
     * Backup verschlüsseln
     */
    private function encrypt_backup($data) {
        $key = get_option('devlic_backup_encryption_key');
        $iv = random_bytes(16);
        
        $encrypted = openssl_encrypt(
            json_encode($data),
            'AES-256-GCM',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );
        
        return base64_encode($iv . $tag . $encrypted);
    }
}
```

### 13.2 Backup-Speicherorte

| Speicherort | Konfiguration | Empfohlen für |
|-------------|---------------|---------------|
| Lokal (wp-content/backups/) | Standard | Kleine Installationen |
| Amazon S3 | AWS Credentials | Produktionsumgebungen |
| Google Cloud Storage | Service Account | Enterprise |
| FTP/SFTP | Server-Credentials | Eigene Infrastruktur |
| Dropbox | OAuth Token | Einfache Cloud-Sicherung |

### 13.3 Restore-Prozess

```
┌─────────────────────────────────────────────────────────────────┐
│  Backup wiederherstellen                                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Verfügbare Backups:                                            │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  ○ 2025-01-15 14:30 - 47 Lizenzen, 4 Produkte          │   │
│  │  ○ 2025-01-14 14:30 - 45 Lizenzen, 4 Produkte          │   │
│  │  ● 2025-01-13 14:30 - 44 Lizenzen, 4 Produkte  ← Aktiv │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Oder Backup-Datei hochladen:                                   │
│  [Datei auswählen...]                                           │
│                                                                 │
│  Optionen:                                                      │
│  ☑ Bestehende Daten überschreiben                              │
│  ☐ Nur fehlende Lizenzen hinzufügen (Merge)                    │
│  ☐ Dry-Run (Vorschau ohne Änderungen)                          │
│                                                                 │
│  [Wiederherstellen]                                             │
└─────────────────────────────────────────────────────────────────┘
```

### 13.4 Datenbank-Tabelle: `wp_dev_backups`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT | Primary Key |
| `filename` | VARCHAR(255) | Backup-Dateiname |
| `file_path` | VARCHAR(500) | Vollständiger Pfad |
| `file_size` | BIGINT | Größe in Bytes |
| `license_count` | INT | Anzahl Lizenzen im Backup |
| `product_count` | INT | Anzahl Produkte |
| `checksum` | VARCHAR(64) | SHA-256 Hash |
| `storage_type` | ENUM | 'local', 's3', 'gcs', 'ftp' |
| `created_at` | DATETIME | Erstellungszeitpunkt |
| `is_encrypted` | BOOLEAN | Verschlüsselt? |

---

## 14. Bulk-Operationen

### 14.1 Massen-Import von Lizenzen

#### CSV-Format für Import

```csv
customer_email,customer_name,product_id,max_activations,expires_at,notes
max@example.com,Max Mustermann,sap-pro,3,2026-01-01,Früher Unterstützer
anna@example.com,Anna Schmidt,sap-neon-glow,1,,Lifetime
firma@company.de,Firma GmbH,enterprise-bundle,unlimited,2025-12-31,Enterprise Kunde
```

#### Import-API

```php
// REST Endpoint: POST /wp-json/license/v1/admin/bulk-import
function bulk_import_licenses($request) {
    $csv_data = $request->get_param('csv_data');
    $options = array(
        'skip_existing' => $request->get_param('skip_existing', true),
        'send_emails' => $request->get_param('send_emails', false),
        'dry_run' => $request->get_param('dry_run', false),
    );
    
    $results = array(
        'created' => 0,
        'skipped' => 0,
        'errors' => array(),
    );
    
    foreach (parse_csv($csv_data) as $row) {
        $result = Dev_License_Manager::create_from_import($row, $options);
        // ... Ergebnisse sammeln
    }
    
    return $results;
}
```

### 14.2 Massen-Export

#### Export-Formate

| Format | Verwendung |
|--------|------------|
| CSV | Excel, Import in andere Systeme |
| JSON | API-Integration, Backup |
| XML | Legacy-Systeme |
| PDF | Dokumentation, Berichte |

#### Admin-UI

```
┌─────────────────────────────────────────────────────────────────┐
│  Bulk-Export                                                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Was exportieren?                                               │
│  ☑ Lizenzen       ☑ Aktivierungen    ☐ Logs                   │
│  ☑ Produkte       ☐ Kunden-Daten                               │
│                                                                 │
│  Filter:                                                        │
│  Produkt:    [Alle Produkte        ▼]                          │
│  Status:     [Alle Status          ▼]                          │
│  Zeitraum:   [01.01.2025] bis [31.12.2025]                     │
│                                                                 │
│  Format:     ○ CSV  ● JSON  ○ XML  ○ PDF                       │
│                                                                 │
│  ☑ Sensible Daten maskieren (E-Mails: m***@example.com)        │
│                                                                 │
│  [Export starten]                                               │
└─────────────────────────────────────────────────────────────────┘
```

### 14.3 Bulk-Aktionen auf Lizenzen

```
┌─────────────────────────────────────────────────────────────────┐
│  Bulk-Aktionen                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  23 Lizenzen ausgewählt                                         │
│                                                                 │
│  Aktion wählen:                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  ▸ Status ändern                                        │   │
│  │      → Aktivieren | Deaktivieren | Widerrufen           │   │
│  │  ▸ Ablaufdatum ändern                                   │   │
│  │      → Verlängern um [30] Tage | Setzen auf [Datum]     │   │
│  │  ▸ Aktivierungen                                        │   │
│  │      → Alle zurücksetzen | Limit ändern auf [3]         │   │
│  │  ▸ E-Mail senden                                        │   │
│  │      → Lizenz-Info | Ablauf-Warnung | Custom Template   │   │
│  │  ▸ Produkt wechseln                                     │   │
│  │      → Upgrade auf [Produkt] | Downgrade auf [Produkt]  │   │
│  │  ▸ Löschen                                              │   │
│  │      → Unwiderruflich löschen (mit Bestätigung)         │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  [Ausführen]  [Abbrechen]                                       │
└─────────────────────────────────────────────────────────────────┘
```

### 14.4 Lizenz-Neuvergabe (Bulk Re-Issue)

Für Fälle wie:
- Produkt-Rebranding
- Lizenzformat-Änderung
- Sicherheitsvorfall (alle Keys kompromittiert)

```php
class Dev_License_Bulk_Reissue {
    
    /**
     * Alle Lizenzen eines Produkts neu vergeben
     */
    public function reissue_product_licenses($product_id, $options = array()) {
        $defaults = array(
            'notify_customers' => true,
            'grace_period_days' => 30,    // Alte Keys funktionieren noch X Tage
            'new_prefix' => null,          // Neues Prefix für Keys
            'reason' => '',                // Grund für Audit-Log
        );
        $options = wp_parse_args($options, $defaults);
        
        $licenses = $this->get_licenses_by_product($product_id);
        $results = array();
        
        foreach ($licenses as $license) {
            // Neuen Key generieren
            $new_key = Dev_License_Generator::generate($options['new_prefix']);
            
            // Alte Lizenz als "migrated" markieren
            $this->mark_as_migrated($license['id'], $options['grace_period_days']);
            
            // Neue Lizenz erstellen (kopiert alle Daten)
            $new_license = $this->create_replacement($license, $new_key);
            
            // Audit-Log
            Dev_Audit_Log::log('license_reissued', array(
                'old_key' => $this->mask_key($license['license_key']),
                'new_key' => $this->mask_key($new_key),
                'reason' => $options['reason'],
            ));
            
            // Kunde benachrichtigen
            if ($options['notify_customers']) {
                Dev_License_Mailer::send_reissue_notification($license, $new_key, $options);
            }
            
            $results[] = array(
                'old_id' => $license['id'],
                'new_id' => $new_license['id'],
                'customer' => $license['customer_email'],
            );
        }
        
        return $results;
    }
}
```

---

## 15. Audit-Logging & Compliance

### 15.1 Audit-Log Tabelle: `wp_dev_audit_log`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | BIGINT | Primary Key |
| `event_type` | VARCHAR(50) | z.B. 'license_created', 'api_verify' |
| `event_category` | ENUM | 'license', 'product', 'api', 'admin', 'security' |
| `severity` | ENUM | 'info', 'warning', 'error', 'critical' |
| `actor_type` | ENUM | 'user', 'api', 'system', 'webhook' |
| `actor_id` | VARCHAR(100) | User-ID, API-Key, oder 'system' |
| `target_type` | VARCHAR(50) | 'license', 'product', etc. |
| `target_id` | VARCHAR(100) | ID des betroffenen Objekts |
| `ip_address` | VARCHAR(45) | IPv4 oder IPv6 |
| `user_agent` | VARCHAR(500) | Browser/Client Info |
| `details` | JSON | Zusätzliche Event-Daten |
| `created_at` | DATETIME | Zeitstempel |

### 15.2 Geloggte Events

| Event | Kategorie | Severity | Beschreibung |
|-------|-----------|----------|--------------|
| `license_created` | license | info | Neue Lizenz erstellt |
| `license_activated` | license | info | Lizenz auf Domain aktiviert |
| `license_deactivated` | license | info | Domain deaktiviert |
| `license_expired` | license | warning | Lizenz abgelaufen |
| `license_revoked` | license | warning | Lizenz widerrufen |
| `license_reissued` | license | warning | Lizenz neu vergeben |
| `api_verify_success` | api | info | Erfolgreiche Validierung |
| `api_verify_failed` | api | warning | Fehlgeschlagene Validierung |
| `api_rate_limited` | security | warning | Rate Limit erreicht |
| `api_invalid_signature` | security | error | Ungültige Request-Signatur |
| `admin_login` | admin | info | Admin eingeloggt |
| `admin_bulk_action` | admin | info | Bulk-Aktion ausgeführt |
| `backup_created` | system | info | Backup erstellt |
| `backup_restored` | system | warning | Backup wiederhergestellt |
| `suspicious_activity` | security | critical | Verdächtige Aktivität erkannt |

### 15.3 Audit-Log Viewer

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Audit Log                                                   [Export]   │
├─────────────────────────────────────────────────────────────────────────┤
│  Filter: [Alle Kategorien ▼] [Alle Severity ▼] [Letzte 7 Tage ▼]       │
├─────────────────────────────────────────────────────────────────────────┤
│  Zeit          │ Event              │ Actor    │ Details                │
├─────────────────────────────────────────────────────────────────────────┤
│  14:32:15      │ 🟢 api_verify      │ API      │ LIC-XXX → example.com │
│  14:31:02      │ 🟡 license_expired │ System   │ LIC-YYY abgelaufen    │
│  14:28:44      │ 🟢 license_created │ admin    │ LIC-ZZZ für max@...   │
│  14:15:33      │ 🔴 rate_limited    │ API      │ 192.168.1.100 blocked │
│  14:02:11      │ 🟢 backup_created  │ System   │ 47 Lizenzen gesichert │
│  ...           │ ...                │ ...      │ ...                   │
├─────────────────────────────────────────────────────────────────────────┤
│  Seite 1 von 234                              [< Zurück] [Weiter >]     │
└─────────────────────────────────────────────────────────────────────────┘
```

### 15.4 Automatische Alerts

```php
// Konfigurierbare Alerts
$alert_rules = array(
    array(
        'name' => 'Hohe Fehlerrate',
        'condition' => 'api_verify_failed > 100 per hour',
        'action' => 'email_admin',
        'severity' => 'warning',
    ),
    array(
        'name' => 'Brute Force Verdacht',
        'condition' => 'api_verify_failed > 10 per minute from same IP',
        'action' => 'block_ip + email_admin',
        'severity' => 'critical',
    ),
    array(
        'name' => 'Ungewöhnliche Aktivierung',
        'condition' => 'license_activated from blacklisted_country',
        'action' => 'flag_for_review',
        'severity' => 'warning',
    ),
);
```

---

## 16. Enterprise-Features (Zukunftssicher)

### 16.1 Multi-Tenant Support

Für Agenturen oder Reseller, die eigene Kunden verwalten:

```
┌─────────────────────────────────────────────────────────────────┐
│  Tenant-Verwaltung (Enterprise)                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Tenants:                                                       │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  🏢 Agentur Alpha                                       │   │
│  │     Lizenzen: 124 | Produkte: 3 | API-Key: ak_alpha_*** │   │
│  │                                                         │   │
│  │  🏢 Reseller Beta                                       │   │
│  │     Lizenzen: 67  | Produkte: 2 | API-Key: ak_beta_***  │   │
│  │                                                         │   │
│  │  🏢 Partner Gamma                                       │   │
│  │     Lizenzen: 23  | Produkte: 1 | API-Key: ak_gamma_*** │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  [+ Neuen Tenant anlegen]                                       │
└─────────────────────────────────────────────────────────────────┘
```

#### Datenbank-Erweiterung: `wp_dev_tenants`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT | Primary Key |
| `name` | VARCHAR(255) | Tenant-Name |
| `api_key` | VARCHAR(64) | Eindeutiger API-Key |
| `api_secret` | VARCHAR(64) | API-Secret für Signing |
| `allowed_products` | JSON | Welche Produkte darf Tenant verkaufen |
| `license_limit` | INT | Max. Lizenzen (-1 = unlimited) |
| `is_active` | BOOLEAN | Aktiv? |
| `created_at` | DATETIME | Erstellt |

### 16.2 API-Key Management

```
┌─────────────────────────────────────────────────────────────────┐
│  API-Keys                                                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  🔑 Production Key                           [Aktiv]    │   │
│  │     ak_live_xxxxxxxxxxxxxxxx                            │   │
│  │     Erstellt: 01.01.2025 | Letzter Zugriff: vor 2 Min  │   │
│  │     Berechtigungen: verify, check, deactivate           │   │
│  │     [Rotieren] [Deaktivieren]                           │   │
│  └─────────────────────────────────────────────────────────┘   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  🔑 Development Key                          [Aktiv]    │   │
│  │     ak_test_xxxxxxxxxxxxxxxx                            │   │
│  │     Erstellt: 15.01.2025 | Nur für Testlizenzen         │   │
│  │     [Rotieren] [Deaktivieren]                           │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  [+ Neuen API-Key erstellen]                                    │
└─────────────────────────────────────────────────────────────────┘
```

### 16.3 Webhook-System (Outgoing)

Benachrichtige externe Systeme bei Events:

```php
// Webhook-Konfiguration
$webhooks = array(
    array(
        'url' => 'https://crm.example.com/api/license-webhook',
        'events' => array('license_created', 'license_expired'),
        'secret' => 'whsec_xxxxx',
        'active' => true,
    ),
    array(
        'url' => 'https://slack.com/api/webhook/xxxxx',
        'events' => array('suspicious_activity'),
        'format' => 'slack',
        'active' => true,
    ),
);

// Webhook-Payload
{
    "event": "license_created",
    "timestamp": "2025-01-15T14:30:00Z",
    "data": {
        "license_id": 123,
        "license_key": "LIC-XXXX-XXXX", // Maskiert
        "product_id": "sap-pro",
        "customer_email": "max@example.com"
    },
    "signature": "sha256=xxxxxx"
}
```

### 16.4 White-Label Option

```
┌─────────────────────────────────────────────────────────────────┐
│  White-Label Einstellungen                                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Branding:                                                      │
│  Firmenname:     [Meine Firma GmbH              ]              │
│  Logo URL:       [https://example.com/logo.png  ]              │
│  Primärfarbe:    [#3B82F6] 🟦                                  │
│                                                                 │
│  E-Mail Absender:                                               │
│  Von Name:       [Meine Firma Lizenzen          ]              │
│  Von E-Mail:     [licenses@meinefirma.de        ]              │
│                                                                 │
│  API-Branding:                                                  │
│  Custom Domain:  [licenses.meinefirma.de        ] (CNAME)      │
│  ☑ "Powered by" Footer ausblenden                              │
│                                                                 │
│  [Speichern]                                                    │
└─────────────────────────────────────────────────────────────────┘
```

### 16.5 Subscription & Recurring Billing (Vorbereitet)

Datenbankstruktur für zukünftige Abo-Unterstützung:

```sql
-- Tabelle: wp_dev_subscriptions
CREATE TABLE wp_dev_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    customer_id INT NOT NULL,
    plan_id VARCHAR(50) NOT NULL,
    status ENUM('active', 'past_due', 'canceled', 'paused') DEFAULT 'active',
    current_period_start DATETIME,
    current_period_end DATETIME,
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    payment_provider ENUM('stripe', 'paypal', 'paddle') NOT NULL,
    provider_subscription_id VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    canceled_at DATETIME NULL,
    FOREIGN KEY (license_id) REFERENCES wp_dev_licenses(id)
);
```

---

## 17. Erweiterte Sicherheitsmaßnahmen

### 17.1 Request Signing (Pflicht für Production)

```php
// Client-Seite: Request signieren
function sign_request($payload, $api_secret) {
    $timestamp = time();
    $nonce = bin2hex(random_bytes(16));
    
    $signature_base = $timestamp . '.' . $nonce . '.' . json_encode($payload);
    $signature = hash_hmac('sha256', $signature_base, $api_secret);
    
    return array(
        'X-Timestamp' => $timestamp,
        'X-Nonce' => $nonce,
        'X-Signature' => $signature,
    );
}

// Server-Seite: Signatur validieren
function validate_signature($request) {
    $timestamp = $request->get_header('X-Timestamp');
    $nonce = $request->get_header('X-Nonce');
    $signature = $request->get_header('X-Signature');
    
    // Zeitfenster prüfen (max 5 Minuten alt)
    if (abs(time() - $timestamp) > 300) {
        return false; // Replay-Attack Prevention
    }
    
    // Nonce prüfen (einmalige Verwendung)
    if ($this->nonce_was_used($nonce)) {
        return false;
    }
    
    // Signatur verifizieren
    $expected = hash_hmac('sha256', 
        $timestamp . '.' . $nonce . '.' . $request->get_body(),
        $api_secret
    );
    
    return hash_equals($expected, $signature);
}
```

### 17.2 IP-Whitelist/Blacklist

```
┌─────────────────────────────────────────────────────────────────┐
│  IP-Verwaltung                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Whitelist (nur diese IPs erlauben):                           │
│  ☐ Aktiviert                                                   │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  192.168.1.0/24     Büro-Netzwerk                       │   │
│  │  10.0.0.5           Server A                            │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Blacklist (automatisch + manuell):                            │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  🚫 103.21.x.x      Auto-blocked: Brute Force          │   │
│  │  🚫 45.33.x.x       Manuell: Spam                       │   │
│  │  🚫 CN (Land)       Geo-Block: China                    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Auto-Block Regeln:                                             │
│  ☑ Nach [5] fehlgeschlagenen Versuchen in [1] Minute          │
│  ☑ Bei verdächtigen User-Agents                                │
│  ☑ Bei bekannten VPN/Proxy IPs                                 │
└─────────────────────────────────────────────────────────────────┘
```

### 17.3 Geo-Blocking & Fraud Detection

```php
class Dev_Fraud_Detection {
    
    public function analyze_request($license, $domain, $ip) {
        $risk_score = 0;
        $flags = array();
        
        // Geo-Check
        $country = $this->get_country($ip);
        if (in_array($country, $this->high_risk_countries)) {
            $risk_score += 30;
            $flags[] = 'high_risk_country';
        }
        
        // Velocity Check (zu schnelle Aktivierungen)
        $recent_activations = $this->count_recent_activations($license['id'], '1 hour');
        if ($recent_activations > 5) {
            $risk_score += 40;
            $flags[] = 'velocity_exceeded';
        }
        
        // Domain-Pattern Check
        if ($this->is_suspicious_domain($domain)) {
            $risk_score += 20;
            $flags[] = 'suspicious_domain';
        }
        
        // VPN/Proxy Detection
        if ($this->is_vpn_or_proxy($ip)) {
            $risk_score += 25;
            $flags[] = 'vpn_detected';
        }
        
        return array(
            'risk_score' => $risk_score,
            'flags' => $flags,
            'action' => $risk_score > 70 ? 'block' : ($risk_score > 40 ? 'review' : 'allow'),
        );
    }
}
```

### 17.4 Verschlüsselung at Rest

```php
// Sensible Daten in der Datenbank verschlüsseln
class Dev_Encryption {
    
    private $key;
    
    public function __construct() {
        // Key aus wp-config.php oder Umgebungsvariable
        $this->key = defined('DEVLIC_ENCRYPTION_KEY') 
            ? DEVLIC_ENCRYPTION_KEY 
            : $this->derive_key_from_auth_keys();
    }
    
    /**
     * Kundendaten verschlüsseln (E-Mail, Name, etc.)
     */
    public function encrypt_pii($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-GCM', $this->key, 0, $iv, $tag);
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Kundendaten entschlüsseln
     */
    public function decrypt_pii($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $ciphertext = substr($data, 32);
        return openssl_decrypt($ciphertext, 'AES-256-GCM', $this->key, 0, $iv, $tag);
    }
}
```

---

## 18. Offline-Fallback & Testlizenzen

### 18.1 Offline-Fallback-Strategie

Was passiert, wenn der Lizenzserver nicht erreichbar ist?

```php
class Dev_License_Offline_Handler {
    
    private $grace_period_days = 7;
    
    public function check_with_fallback($license_key, $product_id) {
        // 1. Versuche Online-Validierung
        $response = $this->try_online_validation($license_key, $product_id);
        
        if ($response !== false) {
            // Erfolg: Speichere für Offline-Nutzung
            $this->cache_valid_license($license_key, $response);
            return $response;
        }
        
        // 2. Server nicht erreichbar → Prüfe Cache
        $cached = $this->get_cached_license($license_key);
        
        if ($cached && $this->is_within_grace_period($cached)) {
            return array(
                'valid' => true,
                'status' => 'offline_grace',
                'grace_expires' => $cached['last_online_check'] + ($this->grace_period_days * 86400),
                'message' => 'Offline-Modus: Bitte innerhalb von ' . $this->remaining_grace_days($cached) . ' Tagen online gehen.',
            );
        }
        
        // 3. Grace Period abgelaufen
        return array(
            'valid' => false,
            'status' => 'offline_expired',
            'error_code' => 'OFFLINE_GRACE_EXPIRED',
            'error_message' => 'Lizenzserver nicht erreichbar. Bitte Internetverbindung prüfen.',
        );
    }
    
    private function cache_valid_license($key, $response) {
        $cache_data = array(
            'license_key' => $key,
            'response' => $response,
            'last_online_check' => time(),
        );
        update_option('devlic_cache_' . md5($key), $cache_data);
    }
}
```

### 18.2 Offline-Verhalten pro Produkt konfigurieren

```
┌─────────────────────────────────────────────────────────────────┐
│  Offline-Einstellungen                                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Standard Grace Period:  [7] Tage                               │
│                                                                 │
│  Verhalten nach Ablauf:                                         │
│  ○ Produkt komplett sperren                                     │
│  ● Nur Premium-Features deaktivieren                            │
│  ○ Nur Warnung anzeigen (weiter nutzbar)                       │
│                                                                 │
│  Pro Produkt überschreiben:                                     │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  SAP Pro:        Grace [14] Tage, Verhalten: [Warnung]  │   │
│  │  Neon Theme:     Grace [7] Tage, Verhalten: [Standard]  │   │
│  │  Enterprise:     Grace [30] Tage, Verhalten: [Warnung]  │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### 18.3 Testlizenz-System

Für Entwickler und Evaluierung:

#### Testlizenz-Typen

| Typ | Prefix | Gültigkeit | Einschränkungen |
|-----|--------|------------|-----------------|
| **Demo** | `DEMO-` | 14 Tage | Nur localhost, Wasserzeichen |
| **Trial** | `TRIAL-` | 30 Tage | Voller Funktionsumfang |
| **Dev** | `DEV-` | Unbegrenzt | Nur localhost/staging |
| **Review** | `REV-` | 7 Tage | Für Blogger/Reviewer |

#### Tabelle: `wp_dev_test_licenses`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| `id` | INT | Primary Key |
| `license_key` | VARCHAR(50) | z.B. DEMO-XXXX-XXXX |
| `type` | ENUM | 'demo', 'trial', 'dev', 'review' |
| `product_id` | VARCHAR(50) | Für welches Produkt |
| `allowed_domains` | JSON | ['localhost', '*.test', 'staging.*'] |
| `created_at` | DATETIME | Erstellt |
| `expires_at` | DATETIME | Ablauf |
| `converted_to_license_id` | INT | Falls zu Vollversion konvertiert |

#### Automatische Testlizenz-Generierung

```
┌─────────────────────────────────────────────────────────────────┐
│  Testlizenz anfordern (öffentliches Formular)                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  E-Mail:     [entwickler@example.com        ]                   │
│  Produkt:    [SAP Pro Theme              ▼]                    │
│  Verwendung: ○ Evaluierung  ● Entwicklung  ○ Review            │
│                                                                 │
│  [Testlizenz anfordern]                                         │
│                                                                 │
│  → Automatisch: DEMO-XXXX wird per E-Mail gesendet              │
│  → Rate Limit: Max 1 Testlizenz pro E-Mail pro Produkt          │
└─────────────────────────────────────────────────────────────────┘
```

#### Konvertierung zu Vollversion

```php
// Testlizenz → Kauflizenz mit Rabatt
function convert_trial_to_full($trial_key, $discount_percent = 20) {
    $trial = $this->get_test_license($trial_key);
    
    if (!$trial || $trial['converted_to_license_id']) {
        return false; // Bereits konvertiert oder ungültig
    }
    
    // Erstelle Vollversion-Lizenz
    $full_license = Dev_License_Manager::create(array(
        'product_id' => $trial['product_id'],
        'customer_email' => $trial['email'],
        'discount_applied' => $discount_percent,
        'source' => 'trial_conversion',
    ));
    
    // Markiere Trial als konvertiert
    $this->mark_trial_converted($trial_key, $full_license['id']);
    
    return $full_license;
}
```

---

## 19. Analytics & Reporting

### 19.1 Dashboard-Widgets

```
┌─────────────────────────────────────────────────────────────────────────┐
│  License Manager Dashboard                                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐           │
│  │  Aktive         │ │  Diesen Monat   │ │  API-Calls      │           │
│  │  Lizenzen       │ │  Verkauft       │ │  Heute          │           │
│  │                 │ │                 │ │                 │           │
│  │     247         │ │      23         │ │    12.4k        │           │
│  │   ↑ 12%         │ │   ↑ 8%          │ │   ↓ 3%          │           │
│  └─────────────────┘ └─────────────────┘ └─────────────────┘           │
│                                                                         │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  Validierungen (letzte 7 Tage)                                  │   │
│  │                                                                 │   │
│  │  1.5k ┤                                         ╭──╮            │   │
│  │  1.0k ┤                    ╭──────────────────╯    ╰───╮       │   │
│  │  0.5k ┤  ╭────────────────╯                            ╰───    │   │
│  │     0 ┼──┴─────┴─────┴─────┴─────┴─────┴─────┴─────             │   │
│  │       Mo    Di    Mi    Do    Fr    Sa    So                    │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─────────────────────────┐ ┌─────────────────────────────────────┐   │
│  │  Top Produkte           │ │  Letzte Aktivitäten                 │   │
│  │                         │ │                                     │   │
│  │  SAP Pro       45%  ███ │ │  • Lizenz aktiviert (vor 2 Min)    │   │
│  │  Neon Theme    30%  ██  │ │  • Neue Lizenz erstellt (vor 5 Min)│   │
│  │  Bundle        25%  █   │ │  • Backup abgeschlossen (vor 1 Std)│   │
│  └─────────────────────────┘ └─────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────┘
```

### 19.2 Berichte

| Bericht | Intervall | Inhalt |
|---------|-----------|--------|
| Sales Report | Wöchentlich/Monatlich | Verkäufe, Umsatz, Top-Produkte |
| Activation Report | Täglich | Aktivierungen, Deaktivierungen |
| Security Report | Wöchentlich | Fehlversuche, Blockierungen |
| Expiration Report | Täglich | Auslaufende Lizenzen |
| Customer Report | Monatlich | Neue Kunden, Churn |

---

## 20. DSGVO / Datenschutz

### 20.1 Gespeicherte personenbezogene Daten

| Datentyp | Tabelle | Zweck | Aufbewahrung |
|----------|---------|-------|--------------|
| E-Mail | `customers`, `licenses` | Lizenzzustellung | Bis Löschung |
| Name | `customers` | Ansprache | Bis Löschung |
| IP-Adresse | `activations`, `audit_log` | Sicherheit, Missbrauch | 90 Tage |
| Domain | `activations` | Lizenzaktivierung | Bis Deaktivierung |

### 20.2 Rechte der Betroffenen (Art. 15-21 DSGVO)

#### Auskunftsrecht (Art. 15)

```php
// API Endpoint: POST /wp-json/license/v1/gdpr/export
function gdpr_export_customer_data($email) {
    $customer = Dev_Customer_Manager::get_by_email($email);
    
    return array(
        'customer' => array(
            'email' => $customer['email'],
            'name' => $customer['name'],
            'created_at' => $customer['created_at'],
        ),
        'licenses' => $this->get_customer_licenses($customer['id']),
        'activations' => $this->get_customer_activations($customer['id']),
        'purchases' => $this->get_customer_purchases($customer['id']),
    );
}
```

#### Recht auf Löschung (Art. 17)

```php
// Soft-Delete mit Anonymisierung
function gdpr_delete_customer($customer_id, $reason = '') {
    global $wpdb;
    
    // 1. Lizenzen widerrufen (aber Audit-Trail behalten)
    $this->revoke_all_licenses($customer_id, 'gdpr_deletion');
    
    // 2. Personenbezogene Daten anonymisieren
    $wpdb->update('wp_dev_customers', array(
        'email' => 'deleted_' . $customer_id . '@anonymized.local',
        'name' => 'Gelöschter Kunde',
        'company' => null,
        'is_deleted' => true,
        'deleted_at' => current_time('mysql'),
        'deletion_reason' => $reason,
    ), array('id' => $customer_id));
    
    // 3. IP-Adressen in Logs anonymisieren
    $this->anonymize_ip_addresses($customer_id);
    
    // 4. Audit-Log (ohne personenbezogene Daten)
    Dev_Audit_Log::log('gdpr_deletion', array(
        'customer_id' => $customer_id,
        'reason' => $reason,
    ));
}
```

### 20.3 Admin-UI für DSGVO

```
┌─────────────────────────────────────────────────────────────────┐
│  Datenschutz / DSGVO                                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Kundenanfrage bearbeiten:                                      │
│  E-Mail: [kunde@example.com                    ] [Suchen]       │
│                                                                 │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │  Kunde gefunden: Max Mustermann                         │   │
│  │  Lizenzen: 3 | Aktivierungen: 5 | Seit: 01.03.2024     │   │
│  │                                                         │   │
│  │  [📥 Daten exportieren (JSON)]                          │   │
│  │  [🗑️ Konto löschen (Anonymisieren)]                     │   │
│  │  [📧 Einwilligung erneut anfordern]                     │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                 │
│  Automatische Datenlöschung:                                    │
│  ☑ IP-Adressen nach [90] Tagen anonymisieren                   │
│  ☑ Audit-Logs nach [365] Tagen archivieren                     │
│  ☐ Inaktive Kunden nach [730] Tagen warnen                     │
└─────────────────────────────────────────────────────────────────┘
```

### 20.4 Einwilligungsverwaltung

```
E-Mail-Footer (Pflicht):
───────────────────────────────────────────────
Du erhältst diese E-Mail, weil du eine Lizenz für {product} erworben hast.
[Einstellungen ändern] | [Alle E-Mails abbestellen]

Gespeicherte Daten: E-Mail, Name, Kaufhistorie
[Meine Daten exportieren] | [Konto löschen]
───────────────────────────────────────────────
```

### 20.5 Auftragsverarbeitung (AVV)

Falls du den Lizenzserver für Dritte betreibst (Multi-Tenant):

- AVV-Template als Download bereitstellen
- Technische & organisatorische Maßnahmen dokumentieren
- Subunternehmer-Liste (Hosting, E-Mail-Dienst)

---

## 21. Implementierungs-Roadmap

### Phase 1: Core (2 Wochen)
- [ ] Basis-Plugin-Struktur
- [ ] Datenbank-Schema (inkl. Audit-Log)
- [ ] REST API (verify, check, deactivate)
- [ ] Admin: Lizenzen CRUD
- [ ] Admin: Produkte CRUD
- [ ] Basis-Sicherheit (Rate Limiting, Prepared Statements)

### Phase 2: Sicherheit & Backup (1 Woche)
- [ ] Backup-System (lokal)
- [ ] Restore-Funktion
- [ ] Request Signing
- [ ] Audit-Logging
- [ ] IP Blacklist (Auto-Block)

### Phase 3: Bulk & Export (1 Woche)
- [ ] CSV Import
- [ ] CSV/JSON Export
- [ ] Bulk-Aktionen UI
- [ ] Lizenz-Neuvergabe (Bulk Re-Issue)

### Phase 4: Automatisierung (1 Woche)
- [ ] PayPal Webhook
- [ ] Stripe Webhook
- [ ] E-Mail-Templates
- [ ] Automatische Backups (Cron)
- [ ] Ablauf-Warnungen

### Phase 5: Analytics & Polish (1 Woche)
- [ ] Dashboard mit Statistiken
- [ ] Berichte (PDF Export)
- [ ] Alert-System
- [ ] Dokumentation

### Phase 6: Enterprise (Optional/Später)
- [ ] Multi-Tenant Support
- [ ] API-Key Management
- [ ] Outgoing Webhooks
- [ ] White-Label
- [ ] Remote Backup (S3)
- [ ] Geo-Blocking
- [ ] Fraud Detection

---

## Notizen

- Dokumentation aktuell: {DATUM}
- Autor: {DEIN NAME}
- Version: 2.1 (Universal License Server - Enterprise Ready + DSGVO)

### Changelog

| Version | Änderungen |
|---------|------------|
| 2.1 | + Kunden-Tabelle, + Download-Token-Tabelle, + DSGVO-Sektion, + Offline-Fallback, + Testlizenzen |
| 2.0 | + Backup/Restore, + Bulk-Operationen, + Audit-Logging, + Enterprise-Features |
| 1.0 | Initiales Konzept |
