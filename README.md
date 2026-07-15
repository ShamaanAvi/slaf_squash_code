<<<<<<< HEAD
# Sri Lanka Squash Scoring System
=======
# SLAF Squash
>>>>>>> 3e95d93299aa7445463bd1d55dfb03fae15b957b

A PHP and MySQL web application for managing squash players, tournaments, results, and rolling rankings.

This project supports:

- Admin player registration and management
- Tournament creation, editing, archiving, and player assignment
- Result entry with automatic point calculation
- Rolling 12-month rankings by age category
- Player dashboards and profile setup
- Public rankings view
- Audit logging for admin actions

## Features

- Role-based access for `admin` and `player`
- First-login flow for players with mandatory profile completion
- Secure password handling with `password_hash()`
- CSRF protection on state-changing forms
<<<<<<< HEAD
- Session timeout handling with a 10-minute idle limit and a maximum of 3 active sessions per account
=======
- Session timeout handling
>>>>>>> 3e95d93299aa7445463bd1d55dfb03fae15b957b
- Public rankings page with category filtering
- Tournament points logic for Tier A and Tier B events
- Ranking calculation based on the best 4 results from a player's last 6 tournaments within a 12-month window
- Admin audit trail for key actions

## Project Structure

- `index.php` - login page
- `home.php` - authenticated dashboard
- `logout.php` - session logout
- `player/` - player setup and profile pages
- `players/` - admin player registration pages
- `tournaments/` - tournament management pages
- `scores/` - score entry, list, edit, delete, and view pages
- `admin/` - admin settings, rankings, and audit pages
- `config/` - database, auth, and helper functions
- `database/` - SQL schema and fresh database scripts
- `docs/` - product and technical reference documents

## Requirements

- PHP 7.4 or later
- MySQL or MariaDB
- A web server such as Apache or Nginx
- Bootstrap 5 internet access via CDN

## Installation

1. Clone or copy this repository into your web server directory.
2. Create a MySQL database.
3. Import `database/fresh_database.sql` to create the tables and seed data.
4. Update `config/db.php` with your database credentials:
   - host
   - username
   - password
   - database name
5. Ensure the project files are reachable from your web root.
6. Open `index.php` in your browser and log in.

## Database Notes

The current database layer uses MySQLi and prepared statements.

The main tables used by the app are:

- `users`
<<<<<<< HEAD
- `user_sessions`
=======
>>>>>>> 3e95d93299aa7445463bd1d55dfb03fae15b957b
- `players`
- `age_categories`
- `player_categories`
- `tournaments`
- `tournament_results`
- `rankings`
- `audit_logs`
- `system_settings`

## Login Flow

- Players log in with their NIC or passport number.
- The initial password is the same as the username.
- On the first login, players are redirected to profile setup.
- Admin users have access to the management pages.

## Ranking Logic

Rankings are calculated from tournament results using these rules:

1. Use results from the last 12 months only.
2. For each player and category, consider the last 6 tournament results.
3. Take the best 4 scores from those 6.
4. Divide the total by the system divisor, which defaults to `4`.
5. Store the calculated ranking average and position.

### Tie Breaks

If players have the same ranking average:

1. Compare the combined points from their last 2 tournaments.
2. If still tied, compare the PSA/WSF ranking number, where a lower number ranks higher.

## Points Table

### Tier A

- 1st: 405.00
- 2nd: 270.00
- 3rd: 202.50
- 4th: 150.75
- 5th: 121.50
- 6th: 108.00
- 7th: 96.75
- 8th: 81.00
- 9th to 16th: 54.00

### Tier B

- 1st: 270.00
- 2nd: 180.00
- 3rd: 135.00
- 4th: 100.50
- 5th: 81.00
- 6th: 72.00
- 7th: 64.50
- 8th: 54.00
- 9th to 16th: 36.00

## Age Categories

The application supports the following categories:

- Boys U9
- Girls U9
- Boys U11
- Girls U11
<<<<<<< HEAD
- Boys U 11 Novice
- Girls U 11 Novice
=======
>>>>>>> 3e95d93299aa7445463bd1d55dfb03fae15b957b
- Boys U13
- Girls U13
- Boys U15
- Girls U15
<<<<<<< HEAD
- Boys U 15 Novice
- Girls U 15 Novice
=======
>>>>>>> 3e95d93299aa7445463bd1d55dfb03fae15b957b
- Boys U17
- Girls U17
- Boys U19
- Girls U19
- Men's Open
- Women's Open
- Women's Over 35
- Men's Over 35
- Men's Over 40
- Men's Over 45
- Men's Masters Over 50
- Men's Masters Over 55
- Men's Masters Over 60
- Men's Masters Over 65

## Security

- Passwords are hashed with bcrypt
- CSRF tokens are required on protected POST requests
<<<<<<< HEAD
- Sessions use secure configuration helpers, enforce a 10-minute idle timeout, and allow up to 3 active sessions per account
=======
- Sessions use secure configuration helpers
>>>>>>> 3e95d93299aa7445463bd1d55dfb03fae15b957b
- Admin actions are logged to the audit trail

## Development Notes

- This is a classic PHP application without a framework.
- The UI uses Bootstrap 5 from a CDN.
- Helper functions and ranking logic live in `config/functions.php`.
- Database connection settings currently live in `config/db.php`.

## Default Entry Points

- `index.php` - sign in
- `home.php` - dashboard after login
- `rankings.php` - public rankings
- `admin/rankings.php` - admin ranking tools
- `admin/audit.php` - audit log

## License

No license has been specified yet.
