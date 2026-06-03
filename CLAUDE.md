# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AntriV2 is a local queue management system for up to 8 counter windows ("loket") running on a LAN. Built with PHP 8.1+ and SQLite — no external dependencies beyond GD for image uploads.

## Common Commands

### Development Server
```bash
# Listen on all interfaces (for LAN access)
php -S 0.0.0.0:8000

# Listen on localhost only
php -S localhost:8000
```

### Verify PHP Syntax
```bash
php -l index.php
php -l config/database.php
php -l api/next.php
php -l api/recall.php
php -l api/status.php
php -l auth/helpers.php
```

### Setup Assets
```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup-assets.ps1
```

## Architecture

### Routing (`index.php`)
Front controller parses `REQUEST_URI`, strips subdirectory base path, routes to:
- `/` → admin (default)
- `/admin` → admin
- `/loket?loket=N` → loket panel
- `/layar` → public display (TV)
- `/login`, `/logout`, `/register`
- Unknown routes → static 404 HTML

All routing maps through `$pageMap` array — never hardcode view file paths elsewhere.

### Database (`config/database.php`)
- **Path**: `database/antrian.sqlite` (created automatically)
- **Mode**: WAL, `busy_timeout` 5000ms, foreign keys ON
- **Migrations**: Schema changes are idempotent (`CREATE TABLE IF NOT EXISTS`, `ALTER TABLE` with column checks)

Key tables:
- `state` — global counter (antrian, loket, panggil flag)
- `app_settings` — voice pack, display grid, queue start
- `users` — loket accounts with `loket_number` slot (stable on delete)
- `loket_last_call` — last called queue per slot
- `call_history` — for display log (max 20 entries returned via API)

### Helper Functions (`auth/helpers.php`)
All helpers prefixed `antrian_`:
- `antrian_db()` — singleton PDO connection
- `antrian_base_url()` — detects subdirectory deployment
- `antrian_state()`, `antrian_loket_accounts()`, `antrian_app_settings()`
- `antrian_csrf_token()`, `antrian_require_csrf()`
- `antrian_voice_pack_*()` — voice pack utilities

### API Endpoints
| Endpoint | Method | CSRF | Purpose |
|----------|--------|------|---------|
| `api/status.php` | GET | — | State + loket calls + history; `?peek=1` doesn't consume panggil flag |
| `api/next.php` | POST | Yes | Increment queue, set panggil=1 |
| `api/recall.php` | POST | Yes | Re-call last queue for loket |
| `api/reset.php` | POST | Yes | Reset counter (admin) |
| `api/update_loket_alias.php` | POST | Yes | Update alias |
| `api/upload_loket_bg.php` | POST | Yes | Upload avatar |
| `api/delete_loket_bg.php` | POST | Yes | Delete avatar |

All JSON responses: `{"success": bool, "data"?: ..., "message"?: string}`.

### Views
- `views/admin.php` — dashboard with sidebar status + loket table management
- `views/layar.php` — public display with audio FIFO queue
- `views/loket.php` — counter panel (next, recall, alias edit)
- `views/menu.php` — legacy, not routed (keep for reference)

### JavaScript (`assets/js/main.js`)
Single IIFE initializes one of three modes based on `data-role` attribute:

| Role | Init Function | Polling |
|------|---------------|---------|
| `display` | `initDisplayMode()` | 500ms, detects new `call_history` entries, FIFO audio queue |
| `admin` | `initAdminMode()` | 500ms, updates status panel |
| `loket` | `initLoketMode()` | 500ms, refreshes status + call history log |

**Audio playback**: `playQueueAnnouncement()` builds segments from `audio/{voice_pack}/`:
`in.wav` → `nomor-urut.MP3` → number digits → `loket.MP3` → loket digits

Outro (`audio/custom/outro.mp3`) is **not** played on the display screen.

### Voice Packs
Structure: `audio/{slug}/` with required files:
```
0.MP3–9.MP3, sepuluh.MP3, sebelas.MP3, belas.MP3, pulu.MP3,
seratus.MP3, ratus.MP3, ribu.MP3, nomor-urut.MP3, loket.MP3, in.wav
```

Available packs: `default`, `ardi`, `gadis`. Active pack stored in `app_settings.voice_pack`. Admin: **Ganti Suara** in navbar.

### CSRF Protection
- Token generated once per session (`$_SESSION['_csrf_token']`)
- `antrian_require_csrf()` validates `X-CSRF-Token` header or `_csrf` POST field
- All write actions (POST, API mutations) require CSRF
- JSON APIs check `Accept` header to return appropriate error format

### Subdirectory Deployment
- `antrian_base_url()` strips `SCRIPT_NAME` to detect subdirectory
- JS reads `data-base-url` from `<body>` for all relative paths
- Admin detects real LAN IP (skips WSL 172.x) for cross-device URLs

### Avatar Files
Location: `assets/img/backgrounds/loket_uid_{user_id}.jpg`
Fallback: `loket_{loket_number}.jpg` (legacy compatibility)

## Database

Seed credentials:
- Admin: `admin` / `admin123`
- Loket: `loket` / `loket123`

## File Checklist for Migration

When moving to another computer:
- `database/antrian.sqlite`
- `audio/custom/` (intro/outro)
- `assets/img/backgrounds/`

## Key Constraints

- Registrasi publik dinonaktifkan — admin manages all accounts
- Loket slot numbers are stable — deleting a loket doesn't renumber others
- Voice pack is all-or-nothing — pack shows "belum lengkap" badge if any file is missing
- Display screen uses `peek=true` on all status calls so multiple clients don't consume the `panggil` flag
- Audio overlay on display requires user interaction to unlock (browser autoplay policy)

## Reading Order for New Features

1. `index.php` — routing
2. `config/database.php` — schema + helpers
3. `auth/helpers.php` — auth + voice pack functions
4. `api/status.php` — what data is available
5. `assets/js/main.js` — how data drives UI
6. `views/layar.php` — display template