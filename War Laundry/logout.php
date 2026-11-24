<?php
// Keluar: hanya menerima POST bertoken CSRF, lalu hapus session.
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
cek_csrf();

$_SESSION = [];
session_regenerate_id(true);   // buang ID session lama
set_flash('success', 'Anda sudah keluar.');
redirect('login.php');
