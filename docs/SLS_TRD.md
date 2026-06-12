# Technical Requirements Document (TRD)
## Sri Lanka Squash — National Ranking Points System Web Application

**Version:** 1.0  
**Stack:** HTML · CSS · PHP (no framework) · MySQL · FTP/SFTP Deployment  
**PHP Minimum:** 7.4 (8.x preferred)  
**MySQL Minimum:** 5.7 / MariaDB 10.4

---

## 1. Architecture Overview

The application follows a classic server-rendered MVC-style architecture without a framework. All logic is in PHP, all data in MySQL, and all deployment is via FTP/SFTP to a shared or VPS hosting environment.

```
/
├── public/              # Web root (point domain here)
│   ├── index.php
│   ├── login.php
│   ├── rankings.php     # Public rankings (no auth)
│   └── assets/
│       ├── css/
│       ├── js/
│       └── img/
├── src/
│   ├── config/
│   │   └── database.php
│   ├── controllers/
│   ├── models/
│   ├── views/
│   │   ├── admin/
│   │   └── player/
│   └── helpers/
│       ├── auth.php
│       ├── csrf.php
│       └── validation.php
├── admin/
│   └── index.php        # Admin entry point
└── .htaccess
```

> The agent is free to refactor the existing directory structure to align with the above, provided all existing functionality is preserved or improved.

---

## 2. Database Schema

### 2.1 `users`
```sql
CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nic_passport    VARCHAR(20) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin', 'player') NOT NULL DEFAULT 'player',
    is_first_login  TINYINT(1) NOT NULL DEFAULT 1,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2.2 `players`
```sql
CREATE TABLE players (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL UNIQUE,
    full_name       VARCHAR(150) NOT NULL,
    date_of_birth   DATE,
    address         TEXT,
    phone           VARCHAR(20),
    email           VARCHAR(100),
    psa_wsf_ranking INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 2.3 `age_categories`
```sql
CREATE TABLE age_categories (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(60) NOT NULL UNIQUE,
    gender  ENUM('Male', 'Female', 'Mixed') NOT NULL DEFAULT 'Mixed'
);

-- Seed data
INSERT INTO age_categories (name, gender) VALUES
('Boys U9', 'Male'), ('Girls U9', 'Female'),
('Boys U11', 'Male'), ('Girls U11', 'Female'),
('Boys U13', 'Male'), ('Girls U13', 'Female'),
('Boys U15', 'Male'), ('Girls U15', 'Female'),
('Boys U17', 'Male'), ('Girls U17', 'Female'),
('Boys U19', 'Male'), ('Girls U19', 'Female'),
('Men\'s Open', 'Male'), ('Women\'s Open', 'Female'),
('Women\'s Over 35', 'Female'),
('Men\'s Over 35', 'Male'), ('Men\'s Over 40', 'Male'),
('Men\'s Over 45', 'Male'), ('Men\'s Masters Over 50', 'Male'),
('Men\'s Masters Over 55', 'Male'), ('Men\'s Masters Over 60', 'Male'),
('Men\'s Masters Over 65', 'Male');
```

### 2.4 `player_categories`
```sql
CREATE TABLE player_categories (
    player_id    INT UNSIGNED NOT NULL,
    category_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (player_id, category_id),
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES age_categories(id) ON DELETE CASCADE
);
-- Enforce max 2 categories per player via application logic
```

### 2.5 `tournaments`
```sql
CREATE TABLE tournaments (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    category_id  INT UNSIGNED NOT NULL,
    tier         ENUM('A', 'B') NOT NULL,
    draw_size    INT UNSIGNED NOT NULL,
    held_on      DATE NOT NULL,
    is_archived  TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES age_categories(id)
);
```

### 2.6 `tournament_results`
```sql
CREATE TABLE tournament_results (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tournament_id   INT UNSIGNED NOT NULL,
    player_id       INT UNSIGNED NOT NULL,
    finish_position TINYINT UNSIGNED DEFAULT NULL,  -- NULL for withdrawal/no-show
    points_awarded  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    is_penalty      TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = 0-point penalty result
    is_active       TINYINT(1) NOT NULL DEFAULT 1,  -- 0 = older than 12 months
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tournament_player (tournament_id, player_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
    FOREIGN KEY (player_id) REFERENCES players(id)
);
```

### 2.7 `rankings`
```sql
CREATE TABLE rankings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    player_id       INT UNSIGNED NOT NULL,
    category_id     INT UNSIGNED NOT NULL,
    ranking_average DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    rank_position   INT UNSIGNED NOT NULL,
    calculated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    period_label    VARCHAR(20) NOT NULL,  -- e.g. "2026-02", "2026-04"
    FOREIGN KEY (player_id) REFERENCES players(id),
    FOREIGN KEY (category_id) REFERENCES age_categories(id)
);
```

### 2.8 `audit_logs`
```sql
CREATE TABLE audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id   INT UNSIGNED,
    detail      TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### 2.9 `system_settings`
```sql
CREATE TABLE system_settings (
    setting_key   VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO system_settings (setting_key, setting_value) VALUES
('ranking_divisor', '4'),
('transition_mode', '0'),       -- 1 = transition (top 8 only), 0 = full (top 16)
('last_ranking_run', NULL);
```

---

## 3. Security Requirements

### 3.1 Authentication
- All passwords stored using `password_hash($password, PASSWORD_BCRYPT)`
- Verified using `password_verify()`
- On first login (`is_first_login = 1`), redirect to `/player/setup.php` before any other page
- Session regeneration on login: `session_regenerate_id(true)`
- Sessions destroyed on logout: `session_unset(); session_destroy();`
- Separate login routes: `/login.php` (players), `/admin/login.php` (admins)

### 3.2 Authorization
- Every protected page checks role at the top before any output:

```php
// src/helpers/auth.php
function requireRole(string $role): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        http_response_code(403);
        header('Location: /login.php');
        exit;
    }
}
```

### 3.3 CSRF Protection
- All POST forms include a hidden CSRF token
- Token generated per session and validated on every state-changing request:

```php
// Generate
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// In form
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

// Validate
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    http_response_code(403); exit;
}
```

### 3.4 SQL Injection Prevention
- All database interaction uses PDO with prepared statements exclusively
- No raw string interpolation into SQL queries
- Example pattern:

```php
$stmt = $pdo->prepare('SELECT * FROM players WHERE id = :id');
$stmt->execute([':id' => $id]);
```

### 3.5 XSS Prevention
- All output to HTML uses `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`
- No raw `echo` of user-supplied data

### 3.6 Additional Hardening
- `.htaccess` must deny direct access to `/src/` and `/config/`:

```apache
# .htaccess at project root
Options -Indexes
RewriteEngine On

# Block access to src/ config files
RewriteRule ^src/ - [F,L]
```

- Database credentials stored in a config file outside web root if hosting allows, otherwise in `/src/config/database.php` with `.htaccess` protection
- `display_errors` set to `Off` in production; errors logged to file only

---

## 4. Ranking Calculation Logic

The ranking engine must be implemented as a reusable PHP function/class callable both on schedule and via admin trigger.

### 4.1 Algorithm (per player, per category)

```
1. Fetch all tournament_results for player in category where:
   - is_active = 1 (within 12 months)
   - ORDER BY tournament held_on DESC
2. Take the last 6 results (by tournament date)
3. From those 6, select the 4 with highest points_awarded
4. ranking_average = SUM(top 4 points) / divisor
   where divisor = system_settings['ranking_divisor'] (default 4)
5. Persist to rankings table with period_label (e.g. "2026-06")
```

### 4.2 Transition Mode

When `system_settings['transition_mode'] = 1`:
- Only players finishing in positions 1–8 receive points
- Positions 9–16 receive 0 points
- Applied at the point of result entry, not at calculation time

### 4.3 Tie-Breaking (applied during rank ordering after averages are calculated)

```
1. Compare sum of points_awarded from player's last 2 tournaments
2. If still equal, compare psa_wsf_ranking (lower number = higher ranked)
3. If still equal, maintain existing order (stable sort)
```

### 4.4 Expiry of Results

A scheduled check (or run during each ranking calculation) marks results inactive:

```sql
UPDATE tournament_results tr
JOIN tournaments t ON tr.tournament_id = t.id
SET tr.is_active = 0
WHERE t.held_on < DATE_SUB(CURDATE(), INTERVAL 12 MONTH);
```

---

## 5. Key PHP Conventions

The agent must follow these conventions throughout the codebase:

- **No framework** — no Composer autoloading required (use manual `require_once`)
- **PDO** for all database access (not MySQLi)
- **MVC-style separation**: controllers handle request logic, models handle DB queries, views contain only display logic
- **No business logic in view files**
- **Consistent error handling**: use try/catch around PDO calls; log errors with `error_log()`; show user-friendly error pages
- **HTML escaping**: wrap all echoed user data in `htmlspecialchars()`
- **Relative includes**: use `__DIR__` for reliable paths, e.g. `require_once __DIR__ . '/../src/config/database.php'`
- **HTTP methods**: use `$_SERVER['REQUEST_METHOD'] === 'POST'` checks; never process POST data on GET requests
- **Redirect after POST**: always `header('Location: ...')` + `exit` after a successful form submission (PRG pattern)

---

## 6. Points Lookup Implementation

Store the points table as a PHP array constant (not in the DB, as it is policy-defined):

```php
// src/helpers/points.php
const POINTS_TABLE = [
    'A' => [
        1 => 405.00, 2 => 270.00, 3 => 202.50, 4 => 150.75,
        5 => 121.50, 6 => 108.00, 7 => 96.75,  8 => 81.00,
        // positions 9-16
        9 => 54.00, 10 => 54.00, 11 => 54.00, 12 => 54.00,
        13 => 54.00, 14 => 54.00, 15 => 54.00, 16 => 54.00,
    ],
    'B' => [
        1 => 270.00, 2 => 180.00, 3 => 135.00, 4 => 100.50,
        5 => 81.00,  6 => 72.00,  7 => 64.50,  8 => 54.00,
        9 => 36.00, 10 => 36.00, 11 => 36.00, 12 => 36.00,
        13 => 36.00, 14 => 36.00, 15 => 36.00, 16 => 36.00,
    ],
];

function getPoints(string $tier, int $position, int $draw_size): float {
    if ($draw_size < 4) return 0.00;
    if ($position < 1 || $position > 16) return 0.00;
    return POINTS_TABLE[$tier][$position] ?? 0.00;
}
```

---

## 7. Page Map

### Admin Pages

| Route | Description |
|-------|-------------|
| `/admin/login.php` | Admin login |
| `/admin/index.php` | Dashboard (summary stats) |
| `/admin/players/index.php` | List all players |
| `/admin/players/create.php` | Create player account |
| `/admin/players/edit.php?id=` | Edit player |
| `/admin/tournaments/index.php` | List tournaments |
| `/admin/tournaments/create.php` | Create tournament |
| `/admin/tournaments/edit.php?id=` | Edit tournament |
| `/admin/results/index.php?tournament_id=` | View/enter results for a tournament |
| `/admin/rankings/index.php` | View current rankings per category |
| `/admin/rankings/run.php` | Trigger ranking recalculation |
| `/admin/settings/index.php` | System settings (divisor, transition mode) |
| `/admin/audit/index.php` | Audit log viewer |

### Player Pages

| Route | Description |
|-------|-------------|
| `/login.php` | Player login |
| `/player/setup.php` | First-login profile completion |
| `/player/dashboard.php` | Ranking, average, category overview |
| `/player/results.php` | Own tournament history |
| `/player/profile.php` | Edit own profile details |
| `/logout.php` | Shared logout |

### Public Pages

| Route | Description |
|-------|-------------|
| `/rankings.php` | Public rankings by category (no auth) |

---

## 8. .htaccess Configuration

```apache
Options -Indexes
RewriteEngine On

# Redirect to public/ if deploying with subdirectory structure
# (adjust if web root IS the public folder)

# Block src/ from direct access
RewriteRule ^src/ - [F,L]
RewriteRule ^src\\  - [F,L]

# Force trailing slash canonicalization (optional)
# RewriteCond %{REQUEST_FILENAME} !-f
# RewriteRule ^([^.]+[^/])$ /$1/ [R=301,L]
```

---

## 9. Session Configuration

Set in a central bootstrap file included by every page:

```php
// src/config/bootstrap.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Lax');
// ini_set('session.cookie_secure', 1); // Enable when HTTPS is active
session_start();
```

---

## 10. Deployment Checklist

The agent should ensure the following are in place before any production deployment:

- [ ] `display_errors = Off` in `php.ini` or set via `ini_set` in bootstrap
- [ ] `error_reporting(E_ALL)` with `log_errors = On` pointing to a writable log file
- [ ] Database credentials not hardcoded in web-accessible files
- [ ] `.htaccess` blocks direct access to `/src/`
- [ ] All POST forms have CSRF tokens
- [ ] First-login redirect enforced before dashboard access
- [ ] `session_regenerate_id(true)` called on successful login
- [ ] All DB queries use prepared statements
- [ ] Ranking calculation tested against the Annex A points table with known inputs

---

## 11. Industry Standards & Best Practices

The agent must follow these throughout all code written or modified:

- Follow **PSR-1 and PSR-2** coding style (consistent indentation, naming conventions) even without a framework
- Use **semantic HTML5** elements (`<main>`, `<nav>`, `<section>`, `<header>`, `<footer>`)
- Use **CSS custom properties** (variables) for colors and spacing for maintainability
- Keep **SQL in model files**, not scattered across controllers or views
- **Never trust client input** — validate all inputs server-side regardless of client-side validation
- Use `DECIMAL(8,2)` for all financial/points values — never `FLOAT`
- Add **database indexes** on frequently queried columns (`player_id`, `category_id`, `tournament_id`, `held_on`)
- Write **comments** for non-obvious logic, especially the ranking calculation
- Keep PHP files under ~200 lines; extract helpers and utilities into `/src/helpers/`
- Use **HTTP status codes** correctly: 200 OK, 302 redirect, 403 Forbidden, 404 Not Found, 500 Server Error
