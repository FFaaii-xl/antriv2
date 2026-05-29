# Dokumentasi Proyek AntriV2 - Referensi Gemini

Dokumen ini berfungsi sebagai referensi sesi mendatang untuk mendokumentasikan keputusan arsitektur penting, konfigurasi database, dan milestone proyek.

---

## 🏛️ Keputusan Arsitektur Penting

1. **Desain Tanpa Card & Sangat Responsif**
   - Mengikuti preferensi pengguna untuk menyajikan visual yang lebih ramping, datar, dan memanjang ke samping daripada gaya *card* besar yang memakan banyak ruang vertikal.
   - Menghapus kelas `queue-call-card` dan menyatukan elemen ke latar belakang gelap bawaan aplikasi.

2. **Kotak Ringkasan Loket & Live Log Terpadu**
   - Menempatkan **Live Log Panggilan** di bagian *bottom-center* dalam wadah besar Ringkasan Loket, disajikan dengan tata letak minimalis horizontal satu baris yang mepet (jarak vertikal ramping 6px).

3. **Polling 0.5 Detik Real-time & Sinkronisasi Panel Petugas**
   - Meningkatkan kecepatan pengambilan data (*polling rate*) dari 1 detik menjadi **0.5 detik** (500 milidetik) pada client-side Javascript (`assets/js/main.js`) baik untuk monitor TV publik display maupun halaman operasional loket petugas (`views/loket.php`) dengan metode `peek=true` (agar tidak mengganggu trigger audio display).
   - Menghubungkan visual "Antrian saat ini" di halaman operasional loket agar ter-update secara real-time dan instan saat tombol **Next** atau **Panggil Ulang** diklik, serta tersinkronisasi otomatis dengan server setiap 0.5 detik.

4. **Prioritas Penamaan Loket Utama & Alias Tambahan**
   - Menetapkan "Loket N" (1-based index) sebagai penamaan primer yang tampil menonjol (seperti pada *badge* papan display dan halaman loket).
   - Menyajikan *alias* kustom (misal: "Loket Registrasi A") secara sekunder sebagai teks tambahan dalam tanda kurung atau baris tambahan di bawah nama utama untuk menjaga keterbacaan serta konsistensi sistem.

5. **Palet Warna Aksen Putih & Ungu Premium**
   - Mengadopsi tema **SaaS Light Mode Premium** dengan warna latar belakang putih soft (`#fdfaff` / `#fbfdff`), kartu putih bersih, bayangan halus (`0 18px 45px rgba(124, 58, 237, 0.05)`), dan border tipis slate-purple (`rgba(124, 58, 237, 0.08)`).
   - Menggunakan warna **Ungu Royal SMKN 4 Surakarta** (`#7c3aed` / `#6d28d9`) sebagai aksen utama untuk elemen penyorot, tombol utama, ikon Lucide, dan dot live reaktif.
   - Kartu loket aktif (`.loket-card-active`) memiliki glow ungu reaktif yang elegan.

---

## 🗄️ Konfigurasi & Skema Database (SQLite)

Database disimpan pada berkas SQLite lokal di: `database/antrian.sqlite`.

### 1. Tabel `state`
Menyimpan state global panggilan antrian saat ini.
```sql
CREATE TABLE state (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    antrian INTEGER NOT NULL DEFAULT 0,
    loket INTEGER NOT NULL DEFAULT 0,
    panggil INTEGER NOT NULL DEFAULT 0
);
```

### 2. Tabel `app_settings`
Menyimpan pengaturan aplikasi antrian.
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

### 3. Tabel `loket_last_call`
Menyimpan nomor antrian terakhir yang dipanggil oleh masing-masing loket.
```sql
CREATE TABLE loket_last_call (
    loket INTEGER PRIMARY KEY,
    antrian INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL
);
```

### 4. Tabel `users`
Menyimpan akun pengguna (role `admin` dan `loket`). Kolom `alias` digunakan untuk menampilkan nama kustom/panggilan masing-masing loket di papan display utama.
```sql
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    alias TEXT NOT NULL DEFAULT "",
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ("admin", "loket")),
    created_at TEXT NOT NULL
);
```

---

## 🏆 Milestone Proyek

1. **[Milestone 1] Refaktorisasi Tampilan Layar Utama**
   - Menghapus gaya card putih vertikal, merapikan ukuran font angka antrian, memindahkan "Live Log Panggilan" ke bagian bawah di dalam kotak ringkasan loket, serta memperketat jarak spasial atas-bawah.
2. **[Milestone 2] Peningkatan Kinerja Real-time**
   - Mengurangi frekuensi *polling* antrian menjadi setiap 0.5 detik untuk respon layar & trigger suara panggilan instan.
3. **[Milestone 3] Perbaikan API & Fitur Alias Loket**
   - Memperbaiki bug impor helper pada pemanggilan antrian baru (`next.php`).
   - Menyediakan fitur ubah alias loket langsung dari halaman operasional loket yang langsung ter-update secara real-time di layar publik display dan log aktivitas.
4. **[Milestone 4] Overhaul Visual SaaS Light Mode & Aksen Ungu Royal**
   - Mengubah keseluruhan arsitektur UI/UX dari tema gelap luar angkasa menjadi **SaaS Light Mode Premium Style** yang berkelas dan teratur.
   - Mengintegrasikan warna **Ungu Royal SMKN 4 Surakarta** (`#7c3aed`, `#6d28d9`) secara konsisten melalui CSS variables dan bootstrap utility overrides.
   - Menyempurnakan layout dashboard menu utama, konsol loket, monitor publik display, halaman login/register, dan panel admin master tanpa mengganggu fungsionalitas backend sama sekali.
   - Mengoptimalkan ruang luar (padding/margin) dan ukuran grid di layar display publik agar pas secara proporsional dalam satu tampilan layar penuh (TV monitor/Full HD) tanpa memerlukan scroll.
   - Menyediakan panel setelan interaktif floating (menggunakan localStorage) langsung di monitor display publik agar petugas dapat menyesuaikan nilai padding atas, bawah, kiri, dan kanan (range 0px - 100px) secara presisi di layar TV monitor di lapangan.
   - Menambahkan efek sorot reaktif pulsing scale (`.loket-card-highlight`) pada loket yang sedang/baru saja memanggil antrian agar mencolok dan mudah dikenali oleh audiens publik.
   - Mengimplementasikan pengaman transaksi database SQLite tingkat tinggi menggunakan **`BEGIN IMMEDIATE TRANSACTION`** pada file API panggilan (`api/next.php`) guna mengunci database saat ada dua loket memanggil di saat bersamaan, menjamin pembagian nomor antrian yang tertib dan unik tanpa duplikasi nomor.
   - Merancang dan membangun **FIFO Audio Announcement Queue** pada sisi klien (`assets/js/main.js`) agar jika terjadi panggilan beruntun/simultan dari loket berbeda, suara pembacaan antrian akan diputar berurutan satu per satu dengan jeda natural tanpa saling tumpang tindih atau terputus di tengah jalan.
   - Menempatkan **Live Log Panggilan** di bagian *bottom-center* dalam wadah besar Ringkasan Loket, disajikan dengan tata letak minimalis horizontal satu baris yang mepet (jarak vertikal ramping 6px).
   - Menyederhanakan tampilan **Live Log Panggilan** dengan tata letak vertikal yang rapi di bawah judulnya, membatasi riwayat panggilan maksimal 2 entri (`max 2`), didekorasi dengan ikon peluru bulat Lucide ungu royal dan lencana waktu kecil yang sangat estetis.
   - Menyediakan modul unggah Foto Profil (Photo Profil / Avatar) untuk masing-masing loket di sisi operasional petugas, yang otomatis diproses lewat PHP GD library (auto-resize max 800px, auto-flatten alpha, dan auto-compress JPEG 80% quality). Foto profil ini disajikan secara presisi sebagai avatar bulat berbingkai ungu dengan efek bayangan halus pada layar publik TV display dan pojok kanan atas konsol loket untuk mendukung arsitektur SaaS yang sangat modern dan ergonomis.
5. **[Milestone 5] Perbaikan Audio, Panggil Ulang & Sinkronisasi Status**
   - **Percepatan & Pembersihan Audio**: Menghapus audio outro sepenuhnya dari rangkaian suara pemanggilan dan menyisakan hanya audio intro (Airport Bell). Mengurangi interval penyambungan segmen suara dari `120ms` menjadi `50ms` serta interval antar antrian dari `600ms` menjadi `300ms` di client-side JS (`assets/js/main.js`) untuk menghasilkan pengumuman audio yang instan, mengalir cepat, dan natural.
   - **Fitur Panggil Ulang (Recall)**: Menambahkan tombol "Panggil Ulang" dengan layout flex vertikal yang elegan di sisi operasional petugas (`views/loket.php`), dihias dengan gaya *ghost button* beraksen ungu lembut (`rgba(124, 58, 237, 0.04)`) yang responsif, serta dihubungkan secara AJAX asinkron ke backend `/api/recall.php` yang aman dari konflik database.
   - **Sinkronisasi Foto Profil & Status Layar Publik**: Menyempurnakan API status `/api/status.php` untuk memetakan seluruh loket terdaftar dari database (termasuk yang belum pernah memanggil) dan menyematkan `background_url` yang ter-cache-bust (`?v=timestamp`). Menyajikan data tersebut sebagai Foto Profil (Avatar) berbingkai ungu bulat yang responsif dan tersinkronisasi di layar monitor TV publik display.
6. **[Milestone 6] Transisi ke Konsep Foto Profil (Avatar)**
   - Mengubah arsitektur tampilan kartu loket di layar display publik dari yang sebelumnya menggunakan gambar latar belakang penuh (background cover) menjadi arsitektur SaaS modular dengan menyematkan Foto Profil bulat (circular avatar) di atas nomor antrean, menjaga keterbacaan tingkat tinggi secara absolut.
   - Menyempurnakan header operasional petugas di `views/loket.php` dengan tata letak flex-horizontal interaktif yang menampilkan bingkai bulat avatar petugas di sisi kanan panel utama.
7. **[Milestone 7] Manajemen Foto Profil dari Admin & URL Bersih**
   - **Manajemen Foto Profil Terpusat**: Memperkuat panel admin master (`views/admin.php`) agar admin dapat langsung mengunggah, mengubah, dan menghapus foto profil (avatar) masing-masing loket dari dropdown aksi di tabel daftar loket.
   - **Penamaan File Berbasis User ID**: Mentransisikan penamaan file gambar profil dari `loket_{index}.jpg` (rentan tertukar saat loket ditambah/dihapus) menjadi `loket_uid_{id}.jpg` yang diikat langsung ke `id` unik akun pengguna di database. Semua titik akses (API status, upload, delete, views) sudah diperbarui dengan fallback ke file legacy untuk menjaga kompatibilitas mundur.
   - **Migrasi URL Bersih (Clean URLs)**: Menghapus seluruh referensi URL lama `index.php?page=...` di seluruh codebase (admin.php, loket.php, menu.php, login.php, register.php, logout.php, helpers.php, API redirect) dan menggantinya dengan URL bersih (`/admin`, `/menu`, `/layar`, `/login`, `/register`, `/logout`, `/loket&loket=N`).
