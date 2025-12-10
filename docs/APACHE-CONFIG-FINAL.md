# Apache Configuration - Final Setup

## Przegląd architektury Apache w produkcji

```
┌─────────────────────────────────────────────────────────┐
│                    TRAEFIK (Port 443)                   │
│  - Terminates SSL                                       │
│  - Adds X-Forwarded-Proto: https                       │
│  - Adds Security Headers (HSTS, X-Frame, etc)          │
│  - Routes to Apache on port 80                         │
└───────────────────┬─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│           APACHE (Port 80 in container)                 │
│                                                          │
│  Global Config: /etc/apache2/conf-enabled/              │
│  ├─ security.conf ────────────────────────────┐        │
│  │   - ServerTokens Prod                       │        │
│  │   - ServerSignature Off                     │        │
│  │   - TraceEnable Off                         │        │
│  │   - Security Headers (X-Frame, etc)         │        │
│  │   - Block .ht*, .git/ files                 │        │
│  └─────────────────────────────────────────────┘        │
│                                                          │
│  VirtualHost: /etc/apache2/sites-enabled/               │
│  ├─ snipnote.conf ────────────────────────────┐        │
│  │   ServerName: snipnote.pl                   │        │
│  │   DocumentRoot: /var/www/html/public        │        │
│  │                                              │        │
│  │   <Directory /var/www/html/public>          │        │
│  │     - AllowOverride None                    │        │
│  │     - FallbackResource /index.php           │        │
│  │     - LimitExcept (tylko GET/POST/PUT...)   │        │
│  │   </Directory>                               │        │
│  └─────────────────────────────────────────────┘        │
└───────────────────┬─────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────────────────┐
│              SYMFONY (Front Controller)                 │
│  /var/www/html/public/index.php                        │
│  - Receives all requests via FallbackResource          │
│  - Routes to Controllers                               │
│  - Returns Response                                    │
└─────────────────────────────────────────────────────────┘
```

---

## Plik 1: `/etc/apache2/conf-available/security.conf`

**Lokalizacja w projekcie:** `docker/apache/security.conf`

**Cel:** Globalna konfiguracja bezpieczeństwa Apache

**Zawartość:**
```apache
# Hide Apache version and OS
ServerTokens Prod           # Server: Apache (nie Apache/2.4.65)
ServerSignature Off         # Usuwa podpis z error pages

# Disable TRACE/TRACK methods
TraceEnable Off             # Chroni przed XST attacks

# Security headers (defense in depth z Traefik)
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"

# Disable directory listing
<Directory />
    Options -Indexes        # Nie pokazuj zawartości katalogów
</Directory>

# Block .htaccess, .htpasswd, etc
<FilesMatch "^\.ht">
    Require all denied
</FilesMatch>

# Block .git/, .svn/, .hg/
<DirectoryMatch "/\.(git|svn|hg)/">
    Require all denied
</DirectoryMatch>
```

**Włączenie w Dockerfile:**
```dockerfile
COPY docker/apache/security.conf /etc/apache2/conf-available/security.conf
RUN a2enconf security
```

**Testowanie:**
```bash
# 1. Sprawdź czy jest włączone
docker exec snipnote-app-1 ls -la /etc/apache2/conf-enabled/ | grep security

# 2. Test ServerTokens
curl -I http://localhost/ | grep Server
# Oczekiwane: Server: Apache (bez wersji)

# 3. Test TRACE disabled
curl -X TRACE http://localhost/
# Oczekiwane: 403 Forbidden

# 4. Test directory listing
curl http://localhost/
# Oczekiwane: NIE pokazuje list plików (tylko 200 lub 404)

# 5. Test blokowania .git
curl http://localhost/.git/config
# Oczekiwane: 403 Forbidden
```

---

## Plik 2: `/etc/apache2/sites-available/snipnote.conf`

**Lokalizacja w projekcie:** `docker/apache/snipnote.conf`

**Cel:** VirtualHost dla aplikacji Symfony

**Zawartość:**
```apache
<VirtualHost *:80>
    ServerName snipnote.pl
    DocumentRoot /var/www/html/public

    <Directory /var/www/html/public>
        # Disable .htaccess (Symfony doesn't need it)
        AllowOverride None
        Require all granted
        
        # Symfony front controller pattern
        # All requests → index.php (except existing files)
        FallbackResource /index.php
        
        # Only allow safe HTTP methods
        <LimitExcept GET POST PUT DELETE PATCH OPTIONS HEAD>
            Require all denied
        </LimitExcept>
    </Directory>

    ErrorLog /var/log/apache2/error.log
    CustomLog /var/log/apache2/access.log combined
</VirtualHost>
```

**Kluczowe decyzje:**

### 1. `AllowOverride None`

**Dlaczego:**
- Symfony NIE używa `.htaccess`
- Używa Front Controller Pattern (`index.php`)
- Blokuje atak przez malicious `.htaccess`
- Szybsze (brak disk I/O dla każdego requestu)

**Alternatywa (stara metoda):**
```apache
# ❌ STARA METODA (nie używaj):
AllowOverride All  # + .htaccess z RewriteRule
```

### 2. `FallbackResource /index.php`

**Co robi:**
```
Request: GET /notes/123

Apache sprawdza:
1. Czy istnieje plik /var/www/html/public/notes/123? NIE
2. Czy istnieje katalog /var/www/html/public/notes/? NIE
3. FallbackResource → internal redirect to /index.php

Symfony otrzymuje:
- REQUEST_URI = /notes/123
- SCRIPT_NAME = /index.php

Symfony Router:
- Parsuje /notes/123
- Znajduje route: /notes/{id}
- Wywołuje NotesController::show($id=123)
```

**Dla plików statycznych:**
```
Request: GET /assets/app.css

Apache sprawdza:
1. Czy istnieje /var/www/html/public/assets/app.css? TAK!
2. Serwuje bezpośrednio (NIE przez index.php)
   → Szybsze, Symfony nie jest angażowany
```

**Dlaczego nie RewriteRule:**
```apache
# ❌ STARA METODA (wolniejsza):
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

# ✅ NOWA METODA (szybsza):
FallbackResource /index.php
```

### 3. `<LimitExcept>` w `<Directory>`

**Dlaczego w `<Directory>` a nie w `<VirtualHost>`:**
- Apache syntax: `<LimitExcept>` musi być w `<Directory>` lub `<Location>`
- ❌ Nie może być bezpośrednio w `<VirtualHost>`

**Co blokuje:**
```bash
# Dozwolone:
GET /notes/123        ✅
POST /api/login       ✅
PUT /api/notes/123    ✅
DELETE /api/notes/123 ✅
PATCH /api/notes/123  ✅
OPTIONS /api/notes    ✅ (CORS preflight)
HEAD /                ✅

# Zablokowane:
TRACE /               ❌ 403 Forbidden
TRACK /               ❌ 403 Forbidden
PROPFIND /            ❌ 403 Forbidden (WebDAV)
MKCOL /               ❌ 403 Forbidden (WebDAV)
CONNECT /             ❌ 403 Forbidden (proxy)
```

**Test:**
```bash
# Test dozwolonych metod
curl -X GET http://localhost/
curl -X POST http://localhost/api/test

# Test zablokowanych metod
curl -X TRACE http://localhost/
# Oczekiwane: 403 Forbidden lub 405 Method Not Allowed

curl -X PROPFIND http://localhost/
# Oczekiwane: 403 Forbidden
```

---

## Błędy które naprawiliśmy:

### Błąd 1: `ServerTokens cannot occur within <VirtualHost> section`

**Problem:**
```apache
<VirtualHost *:80>
    ServerTokens Prod  # ❌ Nie może być tutaj
</VirtualHost>
```

**Rozwiązanie:**
```apache
# Przeniesiono do security.conf (globalnie)
ServerTokens Prod  # ✅ W global config
```

### Błąd 2: `<LimitExcept not allowed in <VirtualHost> context`

**Problem:**
```apache
<VirtualHost *:80>
    <LimitExcept>  # ❌ Nie może być bezpośrednio tutaj
    </LimitExcept>
</VirtualHost>
```

**Rozwiązanie:**
```apache
<VirtualHost *:80>
    <Directory /var/www/html/public>
        <LimitExcept>  # ✅ W <Directory>
        </LimitExcept>
    </Directory>
</VirtualHost>
```

---

## Weryfikacja całej konfiguracji:

### 1. Sprawdź składnię Apache:

```bash
docker exec snipnote-app-1 apache2ctl configtest

# Oczekiwane: Syntax OK
```

### 2. Sprawdź włączone moduły:

```bash
docker exec snipnote-app-1 apache2ctl -M | grep -E "rewrite|headers"

# Oczekiwane:
# rewrite_module (shared)
# headers_module (shared)
```

### 3. Sprawdź włączone konfiguracje:

```bash
# VirtualHost
docker exec snipnote-app-1 ls -la /etc/apache2/sites-enabled/
# Oczekiwane: snipnote.conf

# Global config
docker exec snipnote-app-1 ls -la /etc/apache2/conf-enabled/
# Oczekiwane: security.conf
```

### 4. Test kompletny:

```bash
# A. ServerTokens (ukryta wersja)
curl -I https://snipnote.pl/ | grep Server
# Oczekiwane: Server: Apache

# B. TRACE disabled
curl -X TRACE https://snipnote.pl/
# Oczekiwane: 403 lub 405

# C. Directory listing disabled
curl https://snipnote.pl/uploads/  # Jeśli katalog istnieje
# Oczekiwane: 403 (nie lista plików)

# D. .git blocked
curl https://snipnote.pl/.git/config
# Oczekiwane: 403

# E. Symfony routing działa
curl https://snipnote.pl/
# Oczekiwane: 200 (landing page)

curl https://snipnote.pl/notes
# Oczekiwane: 302 (redirect to login) lub 200

# F. Static files działają
curl https://snipnote.pl/assets/app.css
# Oczekiwane: 200 (CSS content)

# G. WebDAV methods blocked
curl -X PROPFIND https://snipnote.pl/
# Oczekiwane: 403
```

---

## Porównanie z innymi setupami:

### Symfony + Nginx (dla porównania):

```nginx
server {
    server_name snipnote.pl;
    root /var/www/html/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass php:9000;
        # ...
    }
}
```

**Różnice:**
- Nginx: `try_files` (podobne do `FallbackResource`)
- Apache: `FallbackResource` (prostsze, native)
- Nginx: Wymaga PHP-FPM (osobny proces)
- Apache: Mod_PHP (wbudowane) lub PHP-FPM

**Nasze podejście (Apache + Mod_PHP):**
- ✅ Prostsze (jeden proces)
- ✅ Mniej memory overhead
- ⚠️ Mniej skalowalne (dla bardzo high traffic)

---

## Security Defense in Depth:

### Warstwa 1: Traefik (Edge)
- ✅ SSL/TLS termination
- ✅ Rate limiting (może być dodane)
- ✅ GeoIP blocking (może być dodane)
- ✅ HSTS, X-Frame-Options headers

### Warstwa 2: Apache (Web Server)
- ✅ ServerTokens Prod (ukryta wersja)
- ✅ TraceEnable Off
- ✅ LimitExcept (tylko bezpieczne metody)
- ✅ AllowOverride None (brak .htaccess)
- ✅ Block .git/, .ht* files

### Warstwa 3: PHP (Runtime)
- ✅ disable_functions (exec, system, etc)
- ✅ expose_php Off
- ✅ Session security (httponly, secure, samesite)
- ✅ upload_max_filesize limits

### Warstwa 4: Symfony (Application)
- ✅ CSRF protection
- ✅ XSS escaping (Twig)
- ✅ SQL injection prevention (Doctrine ORM)
- ✅ Authentication & Authorization
- ✅ Input validation

### Warstwa 5: Docker (Container)
- ✅ no-new-privileges
- ✅ Resource limits
- ⏳ read_only filesystem (planned)

### Warstwa 6: Linux (Kernel)
- ⏳ AppArmor/SELinux (future)
- ⏳ Seccomp profiles (future)

---

## Troubleshooting

### Problem: Apache nie startuje

```bash
# 1. Sprawdź składnię
docker exec snipnote-app-1 apache2ctl configtest

# 2. Sprawdź logi
docker logs snipnote-app-1 --tail 50

# 3. Sprawdź czy pliki istnieją
docker exec snipnote-app-1 ls -la /etc/apache2/sites-enabled/
docker exec snipnote-app-1 ls -la /etc/apache2/conf-enabled/
```

### Problem: Symfony routing nie działa

```bash
# Test czy FallbackResource działa
curl -v http://localhost/nonexistent-route

# Powinno być:
# - 404 od Symfony (nie od Apache)
# - Content-Type: text/html; charset=UTF-8
```

### Problem: Static files (CSS/JS) nie ładują się

```bash
# 1. Sprawdź czy pliki istnieją
docker exec snipnote-app-1 ls -la /var/www/html/public/assets/

# 2. Test bezpośrednio
curl -I http://localhost/assets/app.css

# Powinno być:
# - 200 OK
# - Content-Type: text/css
```

---

## Podsumowanie

### Pliki konfiguracyjne:

1. **`docker/apache/security.conf`** (global)
   - ServerTokens, ServerSignature
   - TraceEnable
   - Security headers
   - Block .ht*, .git/

2. **`docker/apache/snipnote.conf`** (vhost)
   - ServerName
   - DocumentRoot
   - FallbackResource
   - LimitExcept

### Sprawdzone best practices:

- ✅ AllowOverride None (brak .htaccess)
- ✅ FallbackResource (Symfony front controller)
- ✅ ServerTokens Prod (ukryta wersja)
- ✅ LimitExcept w <Directory> (tylko bezpieczne metody)
- ✅ Block .git/, .ht* files
- ✅ TraceEnable Off

### Gotowe do produkcji: ✅

Apache jest poprawnie skonfigurowany dla Symfony w architekturze z Traefik.

