<?php
// Halaman awal: arahkan ke dashboard jika sudah login, ke login jika belum.
require_once __DIR__ . '/includes/auth.php';

$user = user_login();
redirect($user ? dashboard_url($user['peran']) : 'login.php');
