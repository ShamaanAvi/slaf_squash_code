# Product Requirements Document (PRD)
## Sri Lanka Squash — National Ranking Points System Web Application

**Version:** 1.0  
**Status:** Active Development  
**Effective Date:** 01 January 2026  
**Stack:** HTML · CSS · PHP (no framework) · MySQL · FTP/SFTP Deployment

---

## 1. Product Overview

Sri Lanka Squash (SLS) requires a web-based ranking management system to administer and publish its revised National Ranking Points System. The application supports two user roles — **Admin** and **Player** — and manages tournament results, ranking calculations, and player profiles across all age categories.

---

## 2. Goals & Objectives

- Automate the rolling 12-month ranking calculation per the SLS policy
- Provide a secure, role-separated interface for admins and players
- Maintain transparent, publicly viewable rankings per age category
- Support bi-monthly ranking updates (February, April, June, August, October, December)
- Replace manual/offline ranking management entirely

---

## 3. User Roles

### 3.1 Admin
- Created internally (no self-registration)
- Full CRUD access over players, tournaments, results, and rankings
- Manages tier classification (Tier A / Tier B) for all tournaments
- Triggers or schedules bi-monthly ranking recalculations
- Views audit logs and system activity

### 3.2 Player
- Account created by Admin upon registration
- Login credentials: NIC/Passport number (username + initial password)
- Forced password change and profile completion on first login
- Read-only access to own profile, scores, results history, and current ranking
- Cannot view other players' personal details (only rankings are public)

---

## 4. Functional Requirements

### 4.1 Authentication & Onboarding

| # | Requirement |
|---|-------------|
| FR-01 | Players log in using their NIC/Passport number as username |
| FR-02 | Initial password equals the NIC/Passport number |
| FR-03 | On first login, players are redirected to a mandatory profile completion page |
| FR-04 | Profile completion requires: Full Name, Date of Birth, Address, Phone Number, and new password |
| FR-05 | Admins log in via a separate admin login route (`/admin/login`) |
| FR-06 | Sessions expire after a configurable idle period |
| FR-07 | Passwords are stored using bcrypt hashing |

### 4.2 Player Management (Admin)

| # | Requirement |
|---|-------------|
| FR-08 | Admin can create, edit, and deactivate player accounts |
| FR-09 | Each player record stores: NIC/Passport, Full Name, DOB, Address, Phone, Email (optional), Active Status |
| FR-10 | Admin can assign a player to up to two (2) age categories |
| FR-11 | Admin can view a full list of all players with filtering by category, status |
| FR-12 | Admin can reset a player's password back to their NIC/Passport number |

### 4.3 Age Categories

The system maintains separate rankings for the following categories:

**Junior:** U9, U11, U13, U15, U17, U19 (Boys & Girls each)  
**Senior:** Men's Open, Women's Open  
**Masters:** Women's Over 35, Men's Over 35, Men's Over 40, Men's Over 45, Men's Masters Over 50, Men's Masters Over 55, Men's Masters Over 60, Men's Masters Over 65

| # | Requirement |
|---|-------------|
| FR-13 | Rankings are maintained independently per category |
| FR-14 | Points are not transferable between categories |
| FR-15 | A player may be enrolled in a maximum of two categories |

### 4.4 Tournament Management (Admin)

| # | Requirement |
|---|-------------|
| FR-16 | Admin can create tournaments with: Name, Date, Tier (A or B), Draw Size, Age Category |
| FR-17 | Tier A = Sri Lanka Junior National & Sri Lanka Senior National Championships only |
| FR-18 | Tier B = all other SLS National Ranking Tournaments |
| FR-19 | Admin can edit or archive past tournaments |
| FR-20 | Tournaments with fewer than four (4) players in the draw do not award points (system enforces this) |
| FR-21 | SLS Admin holds sole authority to set/change the tier of any tournament |

### 4.5 Results Entry (Admin)

| # | Requirement |
|---|-------------|
| FR-22 | Admin enters final finishing positions (1st–16th) per player per tournament |
| FR-23 | System calculates and stores points per the Annex A points table |
| FR-24 | A withdrawal or no-show without a valid medical certificate is recorded as 0 points |
| FR-25 | A 0-point result still counts as one of the player's tournament entries for ranking purposes |
| FR-26 | Results older than 12 months are automatically marked inactive |

### 4.6 Points Allocation Table (Annex A)

| Position | Tier A | Tier B |
|----------|--------|--------|
| 1st | 405.00 | 270.00 |
| 2nd | 270.00 | 180.00 |
| 3rd | 202.50 | 135.00 |
| 4th | 150.75 | 100.50 |
| 5th | 121.50 | 81.00 |
| 6th | 108.00 | 72.00 |
| 7th | 96.75 | 64.50 |
| 8th | 81.00 | 54.00 |
| 9th–16th | 54.00 | 36.00 |

### 4.7 Ranking Calculation Engine

| # | Requirement |
|---|-------------|
| FR-27 | Rankings use a rolling 12-month window |
| FR-28 | From a player's last 6 tournaments, the best 4 scores are selected |
| FR-29 | The ranking average = sum of best 4 scores ÷ 4 (divisor is always 4, even if fewer than 4 tournaments played) |
| FR-30 | SLS Admin retains authority to adjust the divisor via a system setting |
| FR-31 | Rankings are recalculated every two months (Feb, Apr, Jun, Aug, Oct, Dec); admin can also trigger manually |
| FR-32 | Transition period (01 Jan 2025 – 31 Dec 2025): only top 8 players per category receive ranking points |
| FR-33 | Full implementation (from 01 Jan 2026): ranking points awarded to positions 1st–16th |

### 4.8 Tie-Breaking

| # | Requirement |
|---|-------------|
| FR-34 | Tie-break rule 1: higher combined points from the last 2 tournaments wins |
| FR-35 | Tie-break rule 2 (if still tied): lower PSA/WSF/ASF ranking number wins |
| FR-36 | Admin can manually enter a player's PSA/WSF/ASF ranking for tie-breaking |

### 4.9 Player-Facing Views

| # | Requirement |
|---|-------------|
| FR-37 | Player dashboard shows: current ranking position, ranking average, category |
| FR-38 | Player can view their own tournament history (last 12 months) |
| FR-39 | Player can see which 4 results are currently being used in ranking calculation |
| FR-40 | Player can view publicly available rankings for their enrolled categories |
| FR-41 | Player can update their own profile details (name, address, phone, password) after initial setup |

### 4.10 Public Rankings Page

| # | Requirement |
|---|-------------|
| FR-42 | A public (no login required) rankings page displays current standings per age category |
| FR-43 | Public page shows: rank position, player name, ranking average only (no personal details) |
| FR-44 | Rankings are filterable by category |

---

## 5. Non-Functional Requirements

| # | Requirement |
|---|-------------|
| NFR-01 | All pages must be responsive and mobile-friendly |
| NFR-02 | Application deployed via FTP/SFTP; no Docker, nginx, or containerization required |
| NFR-03 | All database queries must use prepared statements (PDO or MySQLi) to prevent SQL injection |
| NFR-04 | Passwords stored with `password_hash()` using `PASSWORD_BCRYPT` |
| NFR-05 | All user input must be validated server-side |
| NFR-06 | CSRF protection on all state-changing forms |
| NFR-07 | Session fixation protection on login |
| NFR-08 | Application must function on a standard shared hosting PHP environment (PHP 7.4+) |
| NFR-09 | Ranking calculation must complete within 5 seconds for up to 500 players |
| NFR-10 | System must maintain an audit log of all admin actions (player edits, result entries, ranking runs) |

---

## 6. Out of Scope (v1.0)

- Online payment or registration fees
- Match scheduling or draw generation
- Email notifications (may be added in v2)
- PSA/WSF live ranking API integration (manual entry only)
- Multi-language support

---

## 7. Success Criteria

- Admins can manage the full tournament and results lifecycle without manual spreadsheet work
- Ranking averages are calculated correctly per policy for all active players
- Players can log in and view their ranking and results within 2 clicks of the dashboard
- Public rankings page loads and is accurate on each bi-monthly update cycle
