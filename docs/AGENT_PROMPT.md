# Agent Prompt — Sri Lanka Squash Ranking System

You are working on a half-built PHP/MySQL web application for Sri Lanka Squash (SLS). The full requirements are in `SLS_PRD.md` and `SLS_TRD.md`. Read both documents completely before writing any code.

---

## What Already Exists

The project has a working skeleton with the following files:

- `index.php` — Login page (functional but has critical security flaws — see below)
- `home.php` — Dashboard with role-based cards
- `logout.php` — Session destruction and redirect
- `profile.php` — Password change only (no profile fields)
- `players/add.php` — Register player form (admin only)
- `tournaments/add.php` — Add tournament form (admin only)
- `tournaments/assign.php` — Assign player to tournament (admin only)
- `scores/add.php` — Score entry form (admin only)
- `scores/list.php` — Score history list (role-aware)
- `scores/edit.php` — Edit a score entry (admin only)
- `scores/delete.php` — Delete a score entry (admin only)
- `scores/view.php` — Rankings view with filters
- `config/db.php` — MySQLi connection
- `config/auth.php` — Session/access guards
- `config/functions.php` — All business logic functions
- `public/header.php`, `public/footer.php`, `public/navbar.php`, `public/home_header.php`, `public/home_footer.php` — Layout partials

---

## Critical Issues to Fix First

Fix these before building anything new. They are security vulnerabilities or fundamental mismatches with the PRD/TRD.

**1. SQL Injection in `index.php` (login)**
The login query uses `mysqli_real_escape_string` with string interpolation — this is not safe and must be replaced with a prepared statement.

**2. Plaintext passwords throughout**
- `index.php` compares raw plaintext passwords
- `profile.php` saves plaintext passwords
- `functions.php` `registerPlayer()` sets `$default_password = "password"` as plaintext
Per the TRD, all passwords must use `password_hash()` / `password_verify()` with `PASSWORD_BCRYPT`

**3. Wrong login credential spec**
Per the PRD, a player's initial username AND password must both be their NIC/Passport number — not the string `"password"`. Fix `registerPlayer()` accordingly.

**4. `checkAccess()` accepts a role argument in some files but the function signature in `auth.php` takes no arguments**
`scores/add.php` calls `checkAccess('admin')` but the function doesn't accept parameters. Standardise — use `adminOnly()` everywhere admin-only access is required, and `checkAccess()` for any logged-in user.

**5. No CSRF protection on any form**
Every state-changing POST form needs a CSRF token. Implement this in `config/functions.php` as `generateCsrfToken()` and `validateCsrfToken()`, include the token in every form, and validate on every POST handler.

**6. No first-login redirect**
Per the PRD, players must be redirected to a profile completion page on first login before accessing any other page. The `users` table needs an `is_first_login` flag. After login, check it and redirect to `player/setup.php` if it is set.

---

## Database Changes Required

The existing schema (inferred from the code) is minimal and does not match the TRD. Run the following changes:

1. **`users` table** — Add: `is_first_login TINYINT(1) NOT NULL DEFAULT 1`; change `password` column to `VARCHAR(255)` to hold bcrypt hashes
2. **`players` table** — Add: `address TEXT`, `phone VARCHAR(20)`, `email VARCHAR(100)`, `psa_wsf_ranking INT UNSIGNED DEFAULT NULL`
3. **`tournaments` table** — Add: `tier ENUM('A','B') NOT NULL DEFAULT 'B'`, `draw_size INT UNSIGNED NOT NULL DEFAULT 0`, `held_on DATE NOT NULL`, `category_id INT UNSIGNED`, `is_archived TINYINT(1) NOT NULL DEFAULT 0`
4. **`tournament_results`** — The existing `scores` table stores a raw integer score. Replace or migrate this to store `finish_position` (1–16) and `points_awarded DECIMAL(8,2)`. The system must calculate points from the Annex A table — admins enter finishing position, not raw points.
5. **`age_categories`** — Create this table and seed all 22 categories from TRD section 2.3
6. **`rankings`** — Create per TRD section 2.7 to store computed ranking snapshots
7. **`system_settings`** — Create per TRD section 2.9 with seed rows for `ranking_divisor`, `transition_mode`, `last_ranking_run`
8. **`audit_logs`** — Create per TRD section 2.8

Provide the full migration SQL as a file `database/migrations.sql`.

---

## New Pages and Features to Build

Build all of the following. Keep the existing Bootstrap 5 + dark navbar visual style consistent throughout.

### Player First-Login Setup — `player/setup.php`
- Triggered automatically after first login when `is_first_login = 1`
- Fields: Full Name, Date of Birth, Address, Phone Number, new Password, Confirm Password
- All fields required
- On successful save: update `players` table, set `users.is_first_login = 0`, redirect to `home.php`
- Player cannot access any other page until this is complete

### Player Profile Edit — `profile.php` (replace existing)
- Replace the current password-only form
- Fields: Full Name, Address, Phone, Email (optional), new Password (optional — only updated if filled)
- Displays current values pre-filled

### Tournament Management — `tournaments/add.php` (update existing)
- Add fields: Tier (A/B dropdown), Draw Size (number), Date (`held_on`), Age Category (dropdown from `age_categories` table)
- Remove the auto-appending of current year to the name — admin enters the full name

### Tournament List — `tournaments/list.php` (new)
- Table of all non-archived tournaments
- Columns: Name, Category, Tier, Draw Size, Date
- Actions: Edit, Archive
- Linked from navbar and dashboard

### Tournament Edit — `tournaments/edit.php` (new)
- Edit all tournament fields
- Cannot change tier without admin confirmation (warn via JS confirm dialog)

### Score Entry — `scores/add.php` (update existing)
- Replace the raw score input with a **finishing position** dropdown (1st–16th, plus "Withdrawal / No-show")
- On save, auto-calculate `points_awarded` using the Annex A points table based on the tournament's tier and draw size
- A "Withdrawal / No-show" selection saves `finish_position = NULL`, `points_awarded = 0.00`, `is_penalty = 1`
- Do not award points if `draw_size < 4` (show a warning and block submission)

### Ranking Calculation Engine — `config/functions.php` (add to existing)
Add a function `calculateRankings($conn, $period_label)` that:
1. For each player-category pair, fetches all active results (within 12 months) ordered by tournament date DESC
2. Takes the last 6 tournaments, selects the best 4 by `points_awarded`
3. Divides sum by the `ranking_divisor` from `system_settings`
4. Applies tie-breaking: first by combined points of last 2 tournaments, then by `psa_wsf_ranking`
5. Saves results to the `rankings` table with the given `period_label`
6. Updates `system_settings` `last_ranking_run` timestamp
7. Logs the action to `audit_logs`

Also add a function `expireOldResults($conn)` that sets `tournament_results.is_active = 0` for results from tournaments older than 12 months.

### Rankings Admin Panel — `admin/rankings.php` (new)
- Shows current rankings per category (dropdown filter)
- Shows `last_ranking_run` timestamp from `system_settings`
- Button: "Run Ranking Calculation Now" — POSTs to trigger `calculateRankings()` for the current period
- Transition mode toggle: checkbox to enable/disable `transition_mode` in `system_settings`

### Public Rankings Page — `rankings.php` (new, at root)
- No login required
- Category filter dropdown
- Table: Rank, Player Name, Ranking Average
- Does NOT show personal details (no DOB, NIC, address, phone)
- Shows the label of the last ranking period

### Player Dashboard — `home.php` (update existing)
For the player role, add below the existing cards:
- Current ranking position and ranking average in their enrolled category/categories
- "My Results" quick summary: last 4 tournaments with points earned and whether each result is being counted in the current average

### Admin Dashboard — `home.php` (update existing)
Add a card: "Rankings" linking to `admin/rankings.php`
Add a card: "Audit Log" linking to `admin/audit.php`

### Audit Log — `admin/audit.php` (new)
- Table showing all entries from `audit_logs`
- Columns: Date/Time, Admin User, Action, Target
- Newest first, paginated at 50 per page

### System Settings — `admin/settings.php` (new)
- Form to update `ranking_divisor` (number input, default 4)
- Toggle for `transition_mode`
- Save button — logs change to `audit_logs`

---

## Navbar Updates

Update `public/navbar.php`:

**Admin links:** Players | Tournaments | Assign | Entry | Scores | Rankings (→ `admin/rankings.php`) | Settings (→ `admin/settings.php`)

**Player links:** My Scores | Rankings (→ `scores/view.php`)

**Public rankings link** should appear in the navbar even when logged out, linking to `/rankings.php`.

---

## Points Lookup Implementation

Add this to `config/functions.php`. Do not store the points table in the database.

```php
function getPoints(string $tier, int $position, int $draw_size): float {
    if ($draw_size < 4 || $position < 1 || $position > 16) return 0.00;
    $table = [
        'A' => [1=>405.00,2=>270.00,3=>202.50,4=>150.75,5=>121.50,6=>108.00,7=>96.75,8=>81.00,
                9=>54.00,10=>54.00,11=>54.00,12=>54.00,13=>54.00,14=>54.00,15=>54.00,16=>54.00],
        'B' => [1=>270.00,2=>180.00,3=>135.00,4=>100.50,5=>81.00,6=>72.00,7=>64.50,8=>54.00,
                9=>36.00,10=>36.00,11=>36.00,12=>36.00,13=>36.00,14=>36.00,15=>36.00,16=>36.00],
    ];
    return $table[$tier][$position] ?? 0.00;
}
```

---

## Standards and Conventions to Follow Throughout

- **All database queries must use prepared statements** — no string interpolation into SQL, ever
- **All echoed output must be wrapped in `htmlspecialchars()`**
- **All POST forms must include a CSRF token** (use the helpers added in the fix step)
- **Use the PRG pattern** — always redirect after a successful POST
- **Keep business logic in `config/functions.php`** — page files handle request/response only
- **Use `DECIMAL(8,2)`** for all points/monetary values — never `FLOAT` or `INT`
- **`password_hash()` / `password_verify()`** for all password operations
- **`session_regenerate_id(true)`** on every successful login
- **Error messages to users must be generic** — never expose database errors to the browser; use `error_log()` for internals
- **Keep the existing Bootstrap 5 visual style** — dark navbar, card-based layout, `shadow-sm border-0` cards, Bootstrap Icons
- **Do not introduce Composer, npm, or any build tools** — this is a zero-dependency project beyond Bootstrap CDN
- **Do not use `mysqli_*` procedural functions** in new code — use the existing MySQLi OOP style (`$conn->prepare()`) consistent with `config/functions.php`
ENDOFFILE
echo "Done"