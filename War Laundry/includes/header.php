<?php
// Bagian atas halaman: <head>, topbar, lalu sidebar.
// Variabel yang perlu disiapkan sebelum include:
//   $judul    : judul tab browser
//   $halaman  : nilai data-page di <body> (dipakai app.js)
//   $user     : pengguna login (null di halaman login)
$user = $user ?? null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> | War Laundry</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body data-page="<?= e($halaman) ?>">

<?php if (!$user): ?>
<!-- Topbar halaman login (tanpa menu) -->
<header class="topbar">
  <div class="d-flex align-items-center w-100 px-3 px-lg-4">
<a class="brand" href="login.php"><span class="brand-mark"><i class="bi bi-droplet-half"></i></span><span class="brand-text"><strong>War</strong> Laundry<small class="d-none d-md-block">Aplikasi Pengelolaan Laundry Kiloan</small></span></a>
  </div>
</header>
<?php else: ?>
<!-- Topbar setelah login -->
<header class="topbar no-print">
  <div class="d-flex align-items-center w-100 px-3 px-lg-4">
    <button class="btn btn-outline-secondary btn-sm d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <a class="brand" href="<?= e(dashboard_url($user['peran'])) ?>"><span class="brand-mark"><i class="bi bi-droplet-half"></i></span><span class="brand-text"><strong>War</strong> Laundry<small class="d-none d-md-block">Aplikasi Pengelolaan Laundry Kiloan Sederhana</small></span></a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <div class="text-end lh-sm d-none d-sm-block"><div class="user-name"><?= e($user['nama']) ?></div><div class="user-role"><?= e(LABEL_PERAN[$user['peran']]) ?></div></div>
      <!-- Keluar memakai POST + token CSRF agar tidak bisa dipicu dari situs lain -->
      <form method="post" action="logout.php" class="m-0">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Keluar</button>
      </form>
    </div>
  </div>
</header>
<div class="app-shell">
<?php require __DIR__ . '/sidebar.php'; ?>
  <main class="content">
<?php endif; ?>
