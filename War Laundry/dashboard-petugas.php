<?php
// Dashboard Petugas: ringkasan pekerjaan hari ini.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('petugas');
$pdo  = db();

$hari_ini = date('Y-m-d');

// Pesanan masuk hari ini + total berat
$stmt = $pdo->prepare('SELECT COUNT(*) AS jumlah, COALESCE(SUM(berat), 0) AS berat FROM pesanan WHERE DATE(created_at) = ?');
$stmt->execute([$hari_ini]);
$masuk = $stmt->fetch();

// Jumlah per status (untuk kartu dan ringkasan alur)
$per_status = jumlah_per_status($hari_ini);
$aktif = $per_status['diterima'] + $per_status['dicuci'] + $per_status['dikeringkan']
       + $per_status['disetrika'] + $per_status['siap_diambil'];

// Pesanan siap diambil (semua tanggal)
$siap = $pdo->query("SELECT p.kode, pl.nama FROM pesanan p JOIN pelanggan pl ON pl.id = p.pelanggan_id
                     WHERE p.status = 'siap_diambil' ORDER BY p.created_at")->fetchAll();

// Pesanan belum lunas (semua tanggal)
$belum = $pdo->query('SELECT p.kode, pl.nama, p.total - p.dibayar AS sisa FROM pesanan p
                      JOIN pelanggan pl ON pl.id = p.pelanggan_id
                      WHERE p.dibayar < p.total ORDER BY p.created_at')->fetchAll();
$total_sisa = array_sum(array_column($belum, 'sisa'));

// 5 pesanan terbaru hari ini
$stmt = $pdo->prepare('SELECT p.id, p.kode, p.berat, p.total, p.dibayar, p.status, pl.nama AS pelanggan, l.nama AS layanan
                       FROM pesanan p
                       JOIN pelanggan pl ON pl.id = p.pelanggan_id
                       JOIN layanan l ON l.id = p.layanan_id
                       WHERE DATE(p.created_at) = ?
                       ORDER BY p.created_at DESC, p.id DESC LIMIT 5');
$stmt->execute([$hari_ini]);
$terbaru = $stmt->fetchAll();

// Ambil nama depan untuk sapaan
$nama_depan = explode(' ', $user['nama'])[0];

$judul   = 'Dashboard Petugas';
$halaman = 'dashboard-petugas';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Dashboard Petugas</h1>
        <p class="page-sub"><?= e(tanggal_indo($hari_ini, false, true)) ?> &middot; Selamat bekerja, <?= e($nama_depan) ?>.</p>
      </div>
      <a href="pesanan-baru.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Pesanan baru</a>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Pesanan masuk hari ini</div><div class="stat-value"><?= (int) $masuk['jumlah'] ?></div><div class="stat-sub">Total berat <?= e(format_kg($masuk['berat'])) ?> kg</div></div></div></div>
      <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Pekerjaan aktif</div><div class="stat-value"><?= $aktif ?></div><div class="stat-sub">Diterima sampai siap diambil</div></div></div></div>
      <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Siap diambil</div><div class="stat-value"><?= count($siap) ?></div><div class="stat-sub">
        <?php if ($siap): ?><?= e($siap[0]['nama']) ?> &middot; <?= e($siap[0]['kode']) ?><?= count($siap) > 1 ? ' dan ' . (count($siap) - 1) . ' lainnya' : '' ?><?php else: ?>Belum ada<?php endif; ?>
      </div></div></div></div>
      <div class="col-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Belum lunas</div><div class="stat-value"><?= count($belum) ?></div><div class="stat-sub">Sisa tagihan <?= e(rupiah($total_sisa)) ?></div></div></div></div>
    </div>

    <div class="card section-card mb-4">
      <div class="card-body">
        <h2 class="section-title">Pesanan terbaru</h2>
        <p class="section-sub">Pesanan masuk hari ini, diurutkan dari waktu penerimaan terbaru.</p>
        <div class="table-responsive">
          <table class="table table-hover align-middle app-table">
            <thead><tr><th>No. pesanan</th><th>Pelanggan</th><th>Layanan</th><th>Kg</th><th>Total</th><th>Status proses</th><th>Pembayaran</th><th>Aksi</th></tr></thead>
            <tbody>
<?php foreach ($terbaru as $p): ?>
<tr><td class="fw-semibold"><?= e($p['kode']) ?></td><td><?= e($p['pelanggan']) ?></td><td><?= e($p['layanan']) ?></td><td><?= e(format_kg($p['berat'])) ?></td><td><?= e(rupiah($p['total'])) ?></td><td><?= badge_status($p['status']) ?></td><td><?= badge_bayar($p['total'], $p['dibayar']) ?></td><td><a href="detail-pesanan.php?id=<?= (int) $p['id'] ?>" class="link-action">Detail</a></td></tr>
<?php endforeach; ?>
<?php if (!$terbaru): ?>
<tr><td colspan="8" class="text-center text-secondary py-4">Belum ada pesanan hari ini</td></tr>
<?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
          <span class="text-secondary small">Menampilkan <?= count($terbaru) ?> dari <?= (int) $masuk['jumlah'] ?> pesanan hari ini</span>
          <a href="daftar-pesanan.php" class="btn btn-outline-primary btn-sm">Lihat semua pesanan</a>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Perlu ditindaklanjuti</h2>
          <ul class="list-unstyled kv-list">
<?php foreach (array_slice($siap, 0, 3) as $s): ?>
            <li><span>Siap diambil</span><span class="fw-semibold"><?= e($s['kode']) ?> &middot; <?= e(explode(' ', $s['nama'])[0]) ?></span></li>
<?php endforeach; ?>
<?php foreach (array_slice($belum, 0, 3) as $b): ?>
            <li><span>Tagihan <?= e(explode(' ', $b['nama'])[0]) ?> &middot; <?= e($b['kode']) ?></span><span class="fw-semibold"><?= e(rupiah($b['sisa'])) ?></span></li>
<?php endforeach; ?>
<?php if (!$siap && !$belum): ?>
            <li><span>Tidak ada pekerjaan yang perlu ditindaklanjuti</span><span class="fw-semibold">&ndash;</span></li>
<?php endif; ?>
          </ul>
          <div class="note-box">Buka detail pesanan untuk memperbarui proses atau mencatat pelunasan.</div>
        </div></div>
      </div>
      <div class="col-lg-6">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Ringkasan alur pekerjaan</h2>
          <p class="section-sub">Jumlah pesanan pada setiap status saat ini.</p>
          <div class="row g-2 flow-grid">
<?php foreach (URUTAN_GRID_STATUS as $st): ?>
            <div class="col-6"><div class="kv"><span><?= badge_status($st) ?></span><b><?= $per_status[$st] ?></b></div></div>
<?php endforeach; ?>
          </div>
          <p class="text-secondary small mt-3 mb-0"><?= $aktif ?> pekerjaan aktif &middot; <?= $per_status['selesai'] ?> pesanan selesai hari ini</p>
        </div></div>
      </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
