# Dokumentasi Proyek AntriV2 — Referensi Gemini

Dokumen ini menjadi referensi sesi mendatang: arsitektur, routing, database, aset wajib, dan riwayat milestone. **Selaraskan dengan codebase terbaru** (bukan rencana lama yang sudah tidak dipakai).

---

## Ringkasan Aplikasi

AntriV2 adalah sistem antrian lokal **PHP 8.1+ + SQLite** untuk banyak loket (contoh operasional: 8 loket) di satu jaringan LAN. Peran utama:

| Peran | URL | Keterangan |
|--------|-----|------------|
| Beranda / admin | `/` atau `/admin` | Panel master (perlu login admin) |
| Layar publik | `/layar` | TV display + audio FIFO |
| Loket petugas | `/loket?loket=N` | Next, panggil ulang, alias, foto profil |
| Login | `/login` | Redirect: admin → `/admin`, loket → `/loket?loket=N` |
| Register | `/register` | Dinonaktifkan (hanya pesan + link login) |
| Logout | `/logout` | Hapus sesi |

**`views/menu.php` tidak dipakai di routing.** Setelah login, pengguna langsung ke admin atau loket; tidak ada URL `/menu`.

Instalasi lengkap: [README.md](README.md).

---

## Struktur Folder Penting

```
antriv2/
├── index.php              # Front controller + routing
├── .htaccess              # Rewrite ke index.php
├── config/database.php    # PDO SQLite + migrasi schema
├── auth/                  # login, logout, register, helpers
├── api/                   # next, recall, status, reset, upload/delete foto, alias
├── views/                 # admin, layar, loket (+ menu.php legacy)
├── assets/
│   ├── css/style.css
│   ├── js/main.js         # Display, admin, loket + audio queue
│   └── vendor/            # bootstrap.min.css, lucide.min.js (wajib lokal)
├── audio/                 # Segmen MP3 + in.wav (wajib)
│   └── custom/            # intro.mp3 / outro.mp3 (upload admin)
├── assets/img/backgrounds/  # loket_uid_{id}.jpg (avatar)
└── database/antrian.sqlite  # Dibuat otomatis saat pertama jalan
```

Verifikasi aset: `powershell -ExecutionPolicy Bypass -File scripts/setup-assets.ps1`

---

## Keputusan Arsitektur Penting

1. **UI SaaS Light Mode (ungu SMKN 4 Surakarta)**  
   Tema terang premium: latar `#fdfaff` / `#fbfdff`, aksen `#7c3aed` / `#6d28d9`, kartu putih, bayangan halus. Bukan tema gelap lama.

2. **Layar display tanpa scroll (TV / Full HD)**  
   Grid loket dari `display_cols` × `display_rows` (default 4×2). Panel floating **Atur Padding Layar** (localStorage, range **0–200 px** per sisi).

3. **Live Log Panggilan**  
   Di bagian bawah papan loket (`#activityLog`), tata letak **horizontal** satu baris. Data dari `call_history` (API mengembalikan **hingga 20** entri terbaru); JS menampilkan seluruh daftar yang dikirim (bukan dibatasi 2 di UI).

4. **Polling 0,5 detik + `peek=true`**  
   `assets/js/main.js`: `setInterval(..., 500)` untuk layar, admin, dan loket. Layar/admin/loket memakai `peek=true` agar flag `panggil` tidak “dimakan” oleh panel yang bukan pemutar audio utama. Layar mendeteksi panggilan baru lewat peningkatan `id` di `call_history`, lalu mengantre audio FIFO.

5. **Penamaan loket**  
   Primer: **Loket N** (`loket_number`, 1-based). Alias kustom sekunder (kurung / baris di bawah).

6. **Slot loket stabil**  
   Kolom `users.loket_number`; penghapusan loket tidak menggeser nomor slot loket lain. Admin kelola akun dari panel.

7. **CSRF**  
   Token sesi; header `X-CSRF-Token` untuk fetch JSON. `antrian_require_csrf()` pada: `api/next.php`, `api/recall.php`, `api/reset.php`, `api/update_loket_alias.php`, upload/hapus foto, POST admin.

8. **Subfolder deployment**  
   `antrian_base_url()` + atribut `data-base-url` di `<body>`; semua path audio/API di JS memakai prefix ini (mis. `http://192.168.x.x/antriv2`).

9. **Foto profil (avatar)**  
   File: `assets/img/backgrounds/loket_uid_{user_id}.jpg` (fallback `loket_{loket_number}.jpg`). Upload GD: resize max 800px, JPEG ~80%.

10. **Audio pemanggilan & paket suara**  
    - Segmen terbilang dari `audio/{voice_pack}/` (`default`, `ardi`, `gadis`). Pilihan di Admin → **Ganti Suara**; disimpan di `app_settings.voice_pack`.  
    - Rangkaian di layar: **intro** (`custom/intro.mp3` atau fallback `in.wav` di paket aktif) → `nomor-urut.MP3` → digit → `loket.MP3` → digit loket.  
    - **Outro tidak diputar** di rangkaian layar (meski admin masih bisa upload `outro.mp3`).  
    - Antrean FIFO; jeda antar antrean **300 ms**.  
    - Konkureksi nomor: `BEGIN IMMEDIATE TRANSACTION` di `api/next.php` dan `api/recall.php`.

---

## Routing (`index.php`)

- Strip `SCRIPT_NAME` base path (subfolder).
- `/` → halaman **admin** (default), bukan menu.
- `/admin`, `/layar`, `/login`, `/register`, `/logout`, `/loket?loket=N`.
- Route tidak dikenal → 404 HTML statis (link beranda masih `/` — perhatikan saat subfolder).

API tidak melalui `index.php`; akses langsung `api/*.php`.

---

## Database (SQLite)

Path: `database/antrian.sqlite`. Mode: WAL, `busy_timeout` 5000 ms.

### Tabel `state`
```sql
CREATE TABLE state (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    antrian INTEGER NOT NULL DEFAULT 0,
    loket INTEGER NOT NULL DEFAULT 0,
    panggil INTEGER NOT NULL DEFAULT 0
);
```

### Tabel `app_settings`
```sql
CREATE TABLE app_settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    intro_text TEXT NOT NULL,
    outro_text TEXT NOT NULL,
    queue_start INTEGER NOT NULL DEFAULT 1,
    display_cols INTEGER NOT NULL DEFAULT 4,
    display_rows INTEGER NOT NULL DEFAULT 2
);
```

### Tabel `loket_last_call`
```sql
CREATE TABLE loket_last_call (
    loket INTEGER PRIMARY KEY,
    antrian INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
);
```

### Tabel `call_history`
Riwayat untuk live log dan deteksi panggilan baru di layar.
```sql
CREATE TABLE call_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    loket INTEGER NOT NULL,
    antrian INTEGER NOT NULL,
    created_at TEXT NOT NULL
);
```

### Tabel `users`
```sql
CREATE TABLE users (
   id INTEGER PRIMARY KEY AUTOINCREMENT,
   username TEXT NOT NULL UNIQUE,
   alias TEXT NOT NULL DEFAULT "",
   loket_number INTEGER NOT NULL DEFAULT 0,
   password_hash TEXT NOT NULL,
   role TEXT NOT NULL CHECK (role IN ("admin", "loket")),
   created_at TEXT NOT NULL
);
```

- `loket_number`: slot tetap; `0` untuk admin / migrasi.
- Index unik: `idx_users_loket_number` untuk role loket.

Seed awal (jika DB kosong): `admin` / `admin123`, `loket` / `loket123` — **ganti setelah instalasi**.

---

## Aset Wajib (harus ikut deploy)

### `assets/vendor/` (offline, tidak dari CDN di runtime)
| File | Sumber setup |
|------|----------------|
| `bootstrap/bootstrap.min.css` | Bootstrap 5.3.x |
| `lucide/lucide.min.js` | Lucide UMD |

### Paket suara (`audio/{paket}/`)

| Folder | Label admin | Status |
|--------|-------------|--------|
| `audio/default/` | Suara Default | Aktif (file rekaman lama dipindah ke sini) |
| `audio/ardi/` | Suara Ardi | Opsi admin; aktif setelah semua file terisi |
| `audio/gadis/` | Suara Gadis | Opsi admin; aktif setelah semua file terisi |

Pengaturan disimpan di `app_settings.voice_pack` (`default` | `ardi` | `gadis`). Admin: **Ganti Suara** (navbar / sidebar).

Per paket, file wajib sama:

`0.MP3`–`9.MP3`, `sepuluh.MP3`, `sebelas.MP3`, `belas.MP3`, `puluh.MP3`, `seratus.MP3`, `ratus.MP3`, `ribu.MP3`, `nomor-urut.MP3`, `loket.MP3`, `in.wav`

Layar memutar dari `{baseUrl}/audio/{voice_pack}/...`; intro kustom tetap `audio/custom/intro.mp3`.

### `audio/custom/`
- `intro.mp3` — di-upload admin (jika ada, dipakai sebagai intro layar).
- `outro.mp3` — bisa di-upload; **tidak** diputar di `playQueueAnnouncement`.

### Lainnya saat pindah komputer
- `database/antrian.sqlite`
- `assets/img/backgrounds/`

---

## API Utama

| Endpoint | Metode | CSRF | Fungsi |
|----------|--------|------|--------|
| `api/status.php` | GET | — | State + loket + history; `?peek=1` tidak reset `panggil` |
| `api/next.php` | POST | Ya | Nomor antrian +1, set `panggil=1` |
| `api/recall.php` | POST | Ya | Panggil ulang nomor terakhir loket |
| `api/reset.php` | POST | Ya | Reset antrian (admin) |
| `api/update_loket_alias.php` | POST | Ya | Ubah alias loket |
| `api/upload_loket_bg.php` | POST | Ya | Upload avatar |
| `api/delete_loket_bg.php` | POST | Ya | Hapus avatar |
| `api/stream.php` | GET | — | SSE alternatif; **layar saat ini memakai polling**, bukan stream |

---

## Milestone (riwayat)

1. **Layar & log** — Log di bawah papan loket; layout display dioptimalkan TV.
2. **Real-time** — Polling 0,5 detik.
3. **Alias loket** — Edit dari konsol loket + sinkron layar.
4. **SaaS light + ungu** — Tema baru, highlight loket aktif, transaksi SQLite, FIFO audio, avatar, padding layar (localStorage).
5. **Audio & recall** — Outro dihapus dari pemutaran layar; tombol panggil ulang; status lengkap semua loket + avatar di API.
6. **Avatar** — Ganti background penuh menjadi foto bulat.
7. **Admin avatar + clean URL** — `loket_uid_{id}.jpg`; URL tanpa `index.php?page=`.
8. **Hardening** — `loket_number`, registrasi mati, CSRF.
9. **Subfolder** — `data-base-url` untuk audio dan API di JS.
10. **Operasional saat ini** — Beranda = admin; menu landing tidak di-route; vendor & audio lokal wajib; skrip `scripts/setup-assets.ps1` untuk cek deploy.
11. **Paket suara** — `audio/default`, `audio/ardi`, `audio/gadis`; pilihan di Admin → Ganti Suara; kolom `app_settings.voice_pack`.

---

## Catatan untuk sesi AI berikutnya

- Jangan mengaktifkan `/menu` kecuali user meminta landing terpisah.
- Setelah login: admin → `/admin`, loket → `/loket?loket={loket_number}`.
- Jangan hardcode path `/audio/...` atau `/api/...` di JS tanpa `dataset.baseUrl`.
- Saat menambah aksi tulis baru, wajib `antrian_require_csrf()`.
