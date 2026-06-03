# AntriV2

AntriV2 adalah aplikasi antrian lokal untuk 8 loket yang bekerja di jaringan yang sama. Aplikasi ini cocok dipakai di sekolah, kantor, atau layanan loket yang butuh pemanggilan nomor secara sederhana, cepat, dan real-time.

## Isi Aplikasi

- Menu utama.
- Panel admin untuk mengatur loket.
- Halaman loket untuk memanggil nomor.
- Layar display publik (fullscreen, tanpa scroll).
- Audio panggilan antrian (file MP3).
- Alias loket dan foto profil loket.
- Database SQLite lokal.

## Yang Dibutuhkan

### Minimal

- Windows 10 atau Windows 11.
- XAMPP atau PHP 8.1 ke atas.
- Browser Chrome atau Edge.
- Tidak butuh internet saat aplikasi dijalankan, karena file tampilan sudah disimpan lokal di project.

### Ekstensi PHP yang Harus Aktif

- `pdo_sqlite`
- `sqlite3`
- `gd`
- `fileinfo`

## Cara Instal Paling Mudah

### Jika memakai XAMPP

1. Unduh XAMPP lalu install.
2. Buka XAMPP Control Panel.
3. Nyalakan `Apache`.
4. Salin folder `antriv2` ke `C:\xampp\htdocs\antriv2`.
5. Buka browser.
6. Akses `http://localhost/antriv2/`.

### Jika memakai PHP biasa

1. Pastikan PHP sudah terpasang.
2. Buka terminal di folder project.
3. Jalankan:

```bash
php -S localhost:8000
```

## Mengakses Antrean via HP / Jaringan Lokal (Universal)

Agar HP atau perangkat loket lain di jaringan WiFi/LAN yang sama dapat mengakses aplikasi ini, tidak perlu konfigurasi rumit (vhost). Gunakan salah satu metode universal berikut:

### Opsi 1: Menggunakan PHP Built-in Server (Paling Gampang & Cepat)
Ini sangat cocok jika Anda hanya butuh menjalankan aplikasi sementara secara instan.
1. Buka terminal di folder project `antriv2`.
2. Jalankan perintah ini agar server mendengarkan di semua IP lokal:
   ```bash
   php -S 0.0.0.0:8000
   ```
3. Di komputer server, buka Admin. URL pada tabel **Daftar Loket** akan otomatis berubah menampilkan IP lokal yang benar (misalnya `http://192.168.1.5:8000/loket?loket=1`).
4. Pastikan **Windows Firewall** Anda mengizinkan koneksi port `8000`.

### Opsi 2: Menggunakan XAMPP / Laragon (Production Lokal)
Jika Anda menggunakan web server seperti XAMPP atau Laragon:
1. Pastikan folder `antriv2` ditempatkan langsung di dalam folder root (misalnya `C:\xampp\htdocs\antriv2` atau `C:\laragon\www\antriv2`).
2. Jangan menggunakan virtual host (misal `antriv2.test`) untuk perangkat lain. Perangkat lain di LAN tidak mengenali alamat `.test` tersebut.
3. Di halaman Admin, tabel **Daftar Loket** akan otomatis menyesuaikan URL berdasarkan letak folder (misalnya `http://192.168.1.5/antriv2/loket?loket=1`).
4. Bagikan URL yang tertera di tabel tersebut kepada petugas loket.
5. Jika HP menampilkan "Site can't be reached", pastikan **Windows Firewall** Anda telah mengizinkan koneksi *inbound* untuk **Apache HTTP** (port 80).

## Langkah untuk Orang Awam

Kalau Anda baru pertama kali pasang, ikuti urutan ini saja:

1. Install XAMPP.
2. Aktifkan Apache.
3. Taruh folder project di `htdocs`.
4. Buka `http://localhost/antriv2/`.
5. Login pakai akun admin bawaan.
6. Tambah atau hapus loket dari panel admin.
7. Buka URL masing-masing loket di komputer petugas.

## Login Awal

- Admin: `admin` / `admin123`
- Loket: `loket` / `loket123`

Setelah masuk sebagai admin, semua akun loket diatur dari panel admin.

## Cara Pakai di 8 Loket

1. Jalankan aplikasi di satu komputer server.
2. Pastikan semua komputer ada di jaringan yang sama.
3. Buka panel admin untuk menambah loket bila dibutuhkan.
4. Berikan nomor loket ke tiap petugas.
5. Contoh URL loket:

```text
http://192.168.1.10/antriv2/loket?loket=1
http://192.168.1.10/antriv2/loket?loket=2
http://192.168.1.10/antriv2/loket?loket=3
```

6. Loket bisa ditambah atau dikurangi tanpa mengubah nomor loket yang sudah ada.

## Paket Suara

Aplikasi mendukung beberapa opsi suara:

| Paket | Keterangan |
|-------|------------|
| Suara Default | File MP3 di `audio/default/` |
| Suara Ardi | File MP3 di `audio/ardi/` |
| Suara Gadis | File MP3 di `audio/gadis/` |

Pilih paket suara dari panel Admin → **Ganti Suara**.

### Paket Suara (File MP3)

Pastikan folder berisi file-file ini:

`0.MP3`–`9.MP3`, `sepuluh.MP3`, `sebelas.MP3`, `belas.MP3`, `puluh.MP3`, `seratus.MP3`, `ratus.MP3`, `ribu.MP3`, `nomor-urut.MP3`, `loket.MP3`, `in.wav`

## File Tampilan yang Disimpan Lokal

Supaya lebih cepat dan aman dipakai di komputer lain, file tampilan utama tidak lagi mengambil dari internet.

- `assets/vendor/bootstrap/bootstrap.min.css`
- `assets/vendor/lucide/lucide.min.js`

Kalau Anda pindah ke komputer baru, pastikan folder `assets/vendor/` ikut disalin bersama project.

### Cek / pasang aset otomatis

Dari folder project, jalankan:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup-assets.ps1
```

Skrip ini mengunduh vendor (jika belum ada), `in.wav`, dan memverifikasi semua file `audio/*.MP3` wajib.

## Kalau CSS Tidak Muncul

Ini masalah yang paling sering terjadi saat pindah ke komputer lain.

### Penyebab paling umum

- Aplikasi dibuka langsung dari file, bukan lewat `localhost`.
- Apache belum aktif.
- File vendor lokal belum ikut tersalin, sehingga Bootstrap atau Lucide tidak termuat.
- Cache browser masih menyimpan tampilan lama.

### Cara memperbaiki

1. Jangan buka file PHP langsung dari folder.
2. Buka lewat browser dengan alamat `http://localhost/...`.
3. Pastikan Apache sudah hidup.
4. Coba refresh paksa dengan `Ctrl + F5`.
5. Jika masih kacau, coba buka di browser lain.

## Command yang Sering Dipakai

### Cek PHP

```bash
php -v
```

### Jalankan server lokal

```bash
php -S localhost:8000
```

### Cek file PHP aman atau tidak

```bash
php -l index.php
php -l config/database.php
php -l auth/helpers.php
php -l auth/login.php
php -l auth/register.php
php -l views/admin.php
php -l views/loket.php
php -l views/menu.php
php -l views/layar.php
php -l api/status.php
php -l api/next.php
php -l api/recall.php
php -l api/reset.php
php -l api/update_loket_alias.php
php -l api/upload_loket_bg.php
php -l api/delete_loket_bg.php
```

## File yang Perlu Disalin Kalau Pindah Komputer

Kalau mau pindah ke komputer baru, simpan juga:

- `database/antrian.sqlite`
- `audio/custom/`
- `assets/img/backgrounds/`

## Catatan Penting

- Registrasi publik sudah dimatikan.
- Admin mengatur semua loket.
- Aksi sensitif sudah memakai CSRF token.
- Untuk penggunaan lokal, sebaiknya akses jaringan dibatasi hanya ke perangkat operasional.

## Lisensi

Gunakan untuk kebutuhan internal sekolah atau organisasi Anda.