# War Laundry (PHP Native + MySQL)

Aplikasi pengelolaan laundry kiloan untuk PBL Semester 1 D3 Informatika Politeknik Negeri Batam.
Dibuat dengan **PHP native** (tanpa framework, tanpa Composer) dan **MySQL**, memakai desain
asli dari folder `frontend-asli/` (Bootstrap 5 + `style.css`).

## Cara memasang di XAMPP

1. Pastikan folder proyek ada di `C:\xampp\htdocs\war-laundry-php`.
2. Buka **XAMPP Control Panel**, jalankan **Apache** dan **MySQL**.
3. Impor database (pilih salah satu):
   - Lewat terminal:
     ```
     C:\xampp\mysql\bin\mysql.exe -u root < C:\xampp\htdocs\war-laundry-php\database\war_laundry.sql
     ```
   - Lewat phpMyAdmin: buka `http://localhost/phpmyadmin` → tab **Import** → pilih
     `database/war_laundry.sql` → **Go**.
4. Buka `http://localhost/war-laundry-php/`.

File SQL bisa diimpor ulang kapan saja untuk mengembalikan data contoh. Tanggal data
contoh memakai tanggal hari impor, jadi dashboard langsung terisi.

Pengaturan koneksi ada di `config/database.php` (host 127.0.0.1, user `root`, tanpa password,
database `war_laundry_php`).

## Daftar akun

| Peran           | Nama pengguna | Kata sandi   |
|-----------------|---------------|--------------|
| Pemilik         | `raffi`       | `laundry123` |
| Petugas Laundry | `cittra`      | `laundry123` |
| Petugas Laundry | `nadia`       | `laundry123` |

Saat login, pilih peran yang sesuai dengan akunnya.

## Struktur folder

```
config/database.php     koneksi PDO
includes/auth.php       session, cek login, cek peran
includes/helpers.php    format rupiah/tanggal, escape, flash, CSRF
includes/header.php     <head> + topbar
includes/sidebar.php    menu sesuai peran
includes/footer.php     penutup layout, toast, script
index.php               arahkan ke login / dashboard
login.php, logout.php
dashboard-petugas.php, pesanan-baru.php, daftar-pesanan.php, detail-pesanan.php   (petugas)
dashboard-pemilik.php, laporan.php, pengaturan.php                                (pemilik)
assets/css/style.css    salinan dari desain asli
assets/js/app.js        validasi & ringkasan di browser, toast
database/war_laundry.sql
```

## Aturan bisnis singkat

- Petugas: dashboard petugas, pesanan baru, daftar & detail pesanan.
  Pemilik: dashboard pemilik, laporan, pengaturan. Halaman lain dialihkan dengan pesan.
- Total pesanan = berat × tarif, **dihitung ulang di server**. Tarif disimpan di pesanan,
  jadi perubahan tarif tidak mengubah pesanan lama.
- Status hanya bisa maju. Layanan "tanpa cuci" (Setrika saja) melewati Dicuci dan Dikeringkan.
  Selesai hanya bisa dari Siap diambil dan jika sudah lunas.
- Pembayaran boleh bertahap, tidak boleh melebihi sisa tagihan.
- Laporan: pemasukan dihitung dari tanggal pembayaran, jumlah pesanan dari tanggal masuk.

## Keamanan

- Semua query memakai prepared statement PDO.
- Semua output di-escape dengan `e()` (htmlspecialchars).
- Semua form POST memakai token CSRF (`csrf_field()` dan `cek_csrf()`).
- Kata sandi disimpan dengan `password_hash()` dan dicek dengan `password_verify()`.
- `session_regenerate_id(true)` dipanggil setelah login.
