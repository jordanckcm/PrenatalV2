# MaternalCare Prenatal Health Center

**Web-Based Prenatal Health Center Booking Appointment and Record Management System**

A role-based clinic system for booking prenatal appointments with live time-slot capacity, managing patient prenatal records, and coordinating admin / healthcare-worker / patient workflows.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation (XAMPP)](#installation-xampp)
- [Demo Accounts](#demo-accounts)
- [Project Structure](#project-structure)
- [Features](#features)
- [How It Works](#how-it-works)
- [Database](#database)
- [Security](#security)
- [Known Issues](#known-issues)
- [License](#license)

---

## Overview

MaternalCare is a plain-PHP + MySQL application for a prenatal clinic. It supports three roles:

| Role | Portal | Purpose |
|---|---|---|
| **Admin** | `/admin/` | Manage users, services, schedules, slots, bookings, reports, backups, audit logs |
| **Healthcare Worker** | `/worker/` | Examine patients, record checkups, view records, follow-ups, calendar |
| **Patient** | `/patient/` | Book appointments, view own appointments/vouchers, read prenatal records |

There is no framework and no Composer — every page is self-contained (logic + SQL + HTML) rendered through shared `includes/header.php` and `includes/footer.php`.

---

## Tech Stack

- **Backend:** PHP 8.x (procedural, PDO prepared statements)
- **Database:** MySQL / MariaDB (XAMPP default, port `3306`)
- **Frontend:** HTML5, CSS3, vanilla JavaScript (`fetch`, async/await)
- **Libraries (CDN):** Font Awesome 6, Chart.js
- **Session auth** with CSRF tokens on every POST
- **Password hashing:** bcrypt (`password_hash` / `password_verify`)

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (or any PHP 8 + MySQL environment)
- PHP 8.0+ with `pdo_mysql`, `fileinfo` extensions enabled
- A modern browser

---

## Installation (XAMPP)

1. **Copy the project** into your web root:
   ```
   C:\xampp\htdocs\prenatal
   ```

2. **Start services** in the XAMPP Control Panel:
   - Apache
   - MySQL

3. **Create the database** (any one of these works):
   - **Option A — phpMyAdmin:** open `http://localhost/phpmyadmin`, create a database named `prenatal_db`, then import `database.sql`.
   - **Option B — auto-installer:** visit `http://localhost/prenatal/config/setup_db.php` (creates the DB, tables, and seed data using the credentials in `config/database.php`).

4. **Check DB credentials** in `config/database.php` (defaults to `root` with no password on port `3306`). Adjust if your setup differs.

5. **Open the app:**
   ```
   http://localhost/prenatal/
   ```

6. **Log in** with a demo account below, then change the passwords.

> **Note:** The schema self-heals on first request — `config/config.php` and the slot helper lazily add missing columns/tables (`slot_overrides`, `services.form_fields`, worker acknowledgement columns, etc.). Give the first page load a second to complete.

---

## Demo Accounts

All seed accounts use the password **`password123`**.

| Role | Username | Email |
|---|---|---|
| Admin | `admin` | `admin@maternalcare.local` |
| Healthcare Worker | `staff1` | `staff1@maternalcare.local` |
| Patient | `patient1` | `patient1@maternalcare.local` |

*(Exact seed values live in `database.sql` — see the `users` table.)*

---

## Project Structure

```
prenatal/
├── index.php                # Landing page + login/register handlers
├── login.php / register.php / logout.php
├── account_settings.php     # Avatar, profile, password (all roles)
├── config/
│   ├── config.php           # Globals, CSRF, sanitize, audit, notifications, EDD/GA math
│   ├── database.php         # PDO singleton
│   └── setup_db.php         # Web-based DB installer
├── includes/
│   ├── auth_check.php       # requireLogin() / requireRole()
│   ├── header.php           # Role-based nav + theme bootstrap
│   ├── footer.php           # Theme toggle, modal helpers, global JS
│   ├── slot_helper.php      # Slot generation & availability engine
│   └── staff_schedule_helper.php
├── admin/                   # 12 admin pages (+ includes/slot_helper.php)
├── worker/                  # 11 clinical staff pages
├── patient/                 # 7 patient pages
├── api/                     # JSON/CSV endpoints (see below)
├── assets/
│   ├── css/                 # style.css, dashboard.css, auth.css
│   ├── js/                  # main.js, slot_tracker.js (+ unused wizard/charts/clinic_board)
│   └── avatars/             # Uploaded profile pictures
└── database.sql             # Schema + seed data
```

### API Endpoints

| Endpoint | Access | Purpose |
|---|---|---|
| `api/get_slots.php` | Logged-in | Slot availability for a date |
| `api/clinic_load.php` | Public | Live clinic load *(currently broken — see Known Issues)* |
| `api/update_status.php` | Admin | Confirm / complete / miss / cancel appointments |
| `api/assign_staff.php` | Admin | Assign a healthcare worker |
| `api/get_staff_availability.php` | Admin | Busy/free roster for a date+time |
| `api/manage_slots.php` | Admin | Update hours, capacity, slot times, block/delete slots |
| `api/get_assigned_alert.php` | Worker | Poll new assignments + acknowledge duty |
| `api/export_report.php` | Admin/Worker | CSV export (appointments / records) |
| `api/db_backup.php` | Admin | Download full `.sql` backup |
| `api/upload_avatar.php` / `remove_avatar.php` | Logged-in | Profile picture management |

---

## Features

### Admin
- Dashboard statistics and booking queue (confirm, assign, reassign, mark missed, delete)
- User management: add / edit / reset password / archive / restore / permanently delete
- Clinic services CRUD with a **per-service JSON exam-form editor**
- Weekly clinic hours + 14-day slot picker with a **live slot tracker** (edit time, adjust capacity, block/unblock) — refuses to modify slots that already have bookings
- Clinic settings + system settings (booking window, registration & notification toggles)
- Reports with CSV export, audit-log viewer, one-click database backup

### Healthcare Worker
- Dashboard, appointment list with filters
- **Examine flow:** service-type-specific exam form → inserts `prenatal_records` → marks appointment completed → notifies patient
- Full checkup form that updates the patient's baseline (LMP → EDD → gestational age)
- Patients directory, per-patient prenatal records, follow-up reminders
- Month calendar with workload stats, read-only schedule/slot overview
- Polling "new assigned patient" alert with duty acknowledgement

### Patient
- Dashboard: stats, next appointment, latest checkup snapshot, pregnancy info (LMP / EDD / GA)
- **Booking page:** weekly schedule view, 14-day quick-pick, AJAX slot grid (available / full / blocked), CSRF-protected booking → `APT-YYYYMMDD-NNN` code
- My Appointments with status filters and a **printable appointment-slip voucher**
- Prenatal records timeline (vitals, fetal assessment, labs, notes, next visit)
- Notifications center (mark read / clear all / delete), profile & password settings

### Cross-cutting
- Light/dark theme with per-user preference
- CSRF protection on every form and AJAX mutation
- Audit logging on all sensitive actions
- In-app notifications for every status change

---

## How It Works

### Slot engine (`includes/slot_helper.php`)
1. Look up the active weekly schedule for the requested day of week.
2. Generate 30-minute slots from opening to closing time.
3. Apply `slot_overrides` for that date (blocked slots, per-slot capacity changes).
4. Count `pending`/`confirmed` bookings per time → `available = capacity − booked`.
5. `isSlotAvailable()` re-validates server-side at booking time.

Slot capacity is **global per time slot** (not per service or per staff member).

### Appointment lifecycle
```
pending ──(admin confirms + assigns staff/room)──▶ confirmed
   │                                                   │
   │                                                   ▼
   └──(patient/admin cancels)◀── missed ◀──(no-show)  worker acknowledges
         cancelled                                     (worker_notified=1)
                                                            │
                                                            ▼
                                                    worker examines
                                                            │
                                                            ▼
                                             completed + prenatal_record
                                             + patient notification
```

---

## Database

9 core tables (plus runtime-created `slot_overrides`):

`users`, `patients`, `services`, `schedules`, `slot_overrides`, `staff_duty_schedules`, `appointments`, `prenatal_records`, `notifications`, `audit_logs`, `system_settings`

- `database.sql` contains the baseline schema + seed data.
- Several columns/tables are **added at runtime** by `ensureSchemaUpgrades()` / `ensureSlotOverridesTable()` — the live schema is slightly larger than `database.sql` (e.g. `services.form_fields`, `prenatal_records.temperature`, `appointments.worker_notified`).
- Timezone is fixed to `Asia/Manila` (`APP_TIMEZONE` in `config/config.php`).

---

## Security

**Implemented:**
- Prepared statements (PDO) throughout — no string-concatenated SQL found
- Output escaping via `sanitize()` (htmlspecialchars)
- CSRF tokens on all POST endpoints
- Session regeneration on login, role checks on every page (`requireRole`) and API
- Avatar uploads: MIME sniffed with `finfo`, 5 MB cap, old file deleted
- Bcrypt password hashing
- Slot deletion/editing blocked when bookings exist
- Audit trail (`audit_logs`) on sensitive operations

**Before production, review:**
- `config/database.php` ships `root` with an empty password
- `config/setup_db.php` (public DB installer) and `test_session.php` should be removed or protected
- `fix_passwords.php` prints reset passwords; `admin/users.php` echoes the generated password
- Demo password `password123` and the login hash-fallback weaken default auth
- Settings `enable_patient_registration` / `enable_notifications` are saved but never enforced

See [Known Issues](#known-issues) for the full list.

---

## Known Issues

Documented from a full code review — fixes welcome:

| # | Area | Issue |
|---|---|---|
| 1 | `api/clinic_load.php` | Calls `normalizeYmd()`, `getClinicLoadForDate()`, `getClinicDayStrip()` — these functions don't exist → fatal error |
| 2 | `api/api/`, `api/api/api/` | Nested duplicate endpoints with wrong `require` paths — all broken/dead |
| 3 | `assets/js/wizard.js` | Orphaned: binds to booking-wizard markup that no longer exists (booking page uses inline JS). `charts.js` and `clinic_board.js` are also never loaded |
| 4 | Patient cancel | `patient/my_appointments.php` calls `api/update_status.php`, which is **admin-only** → patients always get "Unauthorized" when cancelling |
| 5 | Concurrency | Booking does check-then-insert with **no transaction/lock** → last-seat race condition; appointment codes (`random_int(1,999)`) can collide; double-submit creates duplicates |
| 6 | `worker/examine.php` | Exam form chosen by **service-name substring match** — renaming a service changes the workflow |
| 7 | Dead files | `includes/sidebar.php` never included (and links to non-existent pages); two divergent `slot_helper.php` copies |
| 8 | Error handling | Most pages `die()` with raw `Exception::getMessage()` — leaks DB details to users |
| 9 | Settings | Registration/notification toggles in system settings are cosmetic (never read) |
| 10 | Notifications | `worker/notifications.php` auto-marks all read on page view |

---

## License

For academic / internal clinic use. Add your preferred license here.
