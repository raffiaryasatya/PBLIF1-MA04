<?php
// Menu samping sesuai peran. $menu_aktif menentukan link yang disorot
// (bawaan sama dengan $halaman; detail pesanan menyorot "Daftar pesanan").
$menu_aktif = $menu_aktif ?? $halaman;

$daftar_menu = [
    'petugas' => [
        'dashboard-petugas' => ['bi-speedometer2', 'Dashboard'],
        'pesanan-baru'      => ['bi-plus-circle', 'Pesanan baru'],
        'daftar-pesanan'    => ['bi-list-check', 'Daftar pesanan'],
    ],
    'pemilik' => [
        'dashboard-pemilik' => ['bi-speedometer2', 'Dashboard'],
        'laporan'           => ['bi-bar-chart-line', 'Laporan'],
        'pengaturan'        => ['bi-gear', 'Pengaturan'],
    ],
];
?>
  <aside class="offcanvas-lg offcanvas-start sidebar no-print" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="sidebarLabel">Menu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body d-block">
      <div class="side-label"><?= e(strtoupper(LABEL_PERAN[$user['peran']])) ?></div>
      <nav class="side-nav">
<?php foreach ($daftar_menu[$user['peran']] as $kunci => [$ikon, $label]): ?>
<?php if ($kunci === $menu_aktif): ?>
<a class="side-link active" href="<?= e($kunci) ?>.php" aria-current="page"><i class="bi <?= e($ikon) ?>"></i><?= e($label) ?></a>
<?php else: ?>
<a class="side-link" href="<?= e($kunci) ?>.php"><i class="bi <?= e($ikon) ?>"></i><?= e($label) ?></a>
<?php endif; ?>
<?php endforeach; ?>
      </nav>
<?php if ($user['peran'] === 'pemilik'): ?>
      <div class="side-access">
        <div class="side-label mb-1">Hak akses</div>
        <p class="mb-0">Pantau usaha, lihat laporan, serta kelola tarif dan akun petugas.</p>
      </div>
<?php endif; ?>
    </div><!-- /.offcanvas-body (tag ini hilang di beberapa HTML asli) -->
  </aside>
