<?php
// Session, cek login, dan cek peran. Di-include paling atas di setiap halaman.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

// Cookie session hanya bisa dibaca server (httponly) dan tidak ikut dari situs lain.
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const LABEL_PERAN = ['petugas' => 'Petugas Laundry', 'pemilik' => 'Pemilik'];

/** Data pengguna yang sedang login, atau null. */
function user_login(): ?array
{
    return $_SESSION['user'] ?? null;
}

/** Halaman dashboard sesuai peran. */
function dashboard_url(string $peran): string
{
    return $peran === 'pemilik' ? 'dashboard-pemilik.php' : 'dashboard-petugas.php';
}

/** Wajib login. Akun yang dinonaktifkan pemilik langsung dikeluarkan. */
function wajib_login(): array
{
    $user = user_login();
    if (!$user) {
        set_flash('danger', 'Silakan masuk terlebih dahulu.');
        redirect('login.php');
    }

    // Cek ulang ke database: akun masih ada dan masih aktif?
    $stmt = db()->prepare('SELECT aktif FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    if ((int) $stmt->fetchColumn() !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('danger', 'Akun Anda sudah tidak aktif.');
        redirect('login.php');
    }

    return $user;
}

/** Wajib login DAN peran harus sesuai. Selain itu dialihkan ke dashboard sendiri. */
function wajib_peran(string $peran): array
{
    $user = wajib_login();
    if ($user['peran'] !== $peran) {
        set_flash('danger', 'Halaman itu hanya untuk ' . LABEL_PERAN[$peran] . '.');
        redirect(dashboard_url($user['peran']));
    }
    return $user;
}
