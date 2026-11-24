<?php
// Dashboard Pemilik: ringkasan usaha hari ini.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('pemilik');
$pdo  = db();

$hari_ini = date('Y-m-d');

// Pembayaran yang diterima hari ini, per metode (pendapatan = uang yang benar-benar masuk)
$stmt = $pdo->prepare('SELECT metode, COUNT(*) AS jumlah, SUM(nominal) AS nominal FROM pembayaran
                       WHERE DATE(created_at) = ? GROUP BY metode');
$stmt->execute([$hari_ini]);
$bayar = ['tunai' => ['jumlah' => 0, 'nominal' => 0], 'transfer' => ['jumlah' => 0, 'nominal' => 0]];
foreach ($stmt->fetchAll() as $r) {
    $bayar[$r['metode']] = ['jumlah' => (int) $r['jumlah'], 'nominal' => (int) $r['nominal']];
}
$pendapatan    = $bayar['tunai']['nominal'] + $bayar['transfer']['nominal'];
$jumlah_bayar  = $bayar['tunai']['jumlah'] + $bayar['transfer']['jumlah'];

// Pesanan yang masuk hari ini
$stmt = $pdo->prepare('SELECT COUNT(*) AS jumlah, COALESCE(SUM(berat), 0) AS berat,
                              COALESCE(SUM(total), 0) AS total, COALESCE(SUM(dibayar), 0) AS dibayar,
                              COALESCE(SUM(dibayar < total), 0) AS belum_lunas
                       FROM pesanan WHERE DATE(created_at) = ?');
$stmt->execute([$hari_ini]);
$masuk = $stmt->fetch();

// Posisi pekerjaan
$per_status = jumlah_per_status($hari_ini);
$aktif = $per_status['diterima'] + $per_status['dicuci'] + $per_status['dikeringkan']
       + $per_status['disetrika'] + $per_status['siap_diambil'];

// Pesanan yang melewati estimasi selesai tetapi belum siap diambil
$terlambat = (int) $pdo->query("SELECT COUNT(*) FROM pesanan
                                WHERE status IN ('diterima','dicuci','dikeringkan','disetrika')
                                AND estimasi_selesai < NOW()")->fetchColumn();

// Ringkasan per layanan untuk pesanan hari ini
$stmt = $pdo->prepare('SELECT l.nama, COUNT(*) AS jumlah, SUM(p.berat) AS berat, SUM(p.total) AS total
                       FROM pesanan p JOIN layanan l ON l.id = p.layanan_id
                       WHERE DATE(p.created_at) = ?
                       GROUP BY l.id, l.nama ORDER BY l.id');
$stmt->execute([$hari_ini]);
$per_layanan = $stmt->fetchAll();

$judul   = 'Dashboard Pemilik';
$halaman = 'dashboard-pemilik';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Dashboard Pemilik</h1>
        <p class="page-sub">Ringkasan usaha &middot; <?= e(tanggal_indo($hari_ini, false, true)) ?> &middot; Data sampai pukul <?= date('H.i') ?></p>
      </div>
      <a href="laporan.php" class="btn btn-primary"><i class="bi bi-bar-chart-line me-1"></i>Lihat laporan</a>
    </div>
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3"><div class="card stat-card stat-highlight h-100"><div class="card-body">
        <div class="stat-label">Pendapatan diterima</div><div class="stat-value"><?= e(rupiah($pendapatan)) ?></div><div class="stat-sub"><?= $jumlah_bayar ?> pembayaran diterima hari ini</div>
      </div></div></div>
      <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Pesanan masuk</div><div class="stat-value"><?= (int) $masuk['jumlah'] ?></div><div class="stat-sub">Total berat <?= e(format_kg($masuk['berat'])) ?> kg</div></div></div></div>
      <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Pekerjaan aktif</div><div class="stat-value"><?= $aktif ?></div><div class="stat-sub">Termasuk <?= $per_status['siap_diambil'] ?> siap diambil</div></div></div></div>
      <div class="col-sm-6 col-xl-3"><div class="card stat-card h-100"><div class="card-body"><div class="stat-label">Pesanan selesai</div><div class="stat-value"><?= $per_status['selesai'] ?></div><div class="stat-sub">Sudah diambil pelanggan hari ini</div></div></div></div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-lg-7">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Ringkasan keuangan</h2>
          <p class="section-sub">Pendapatan dihitung dari pembayaran yang diterima, bukan dari seluruh tagihan.</p>
          <ul class="list-unstyled kv-list">
            <li><span>Total tagihan <?= (int) $masuk['jumlah'] ?> pesanan hari ini</span><span class="fw-semibold"><?= e(rupiah($masuk['total'])) ?></span></li>
            <li><span>Sudah diterima</span><span class="fw-semibold text-success"><?= e(rupiah($masuk['dibayar'])) ?></span></li>
            <li><span>Belum dibayar &middot; <?= (int) $masuk['belum_lunas'] ?> pesanan</span><span class="fw-semibold text-danger"><?= e(rupiah($masuk['total'] - $masuk['dibayar'])) ?></span></li>
          </ul>
          <div class="row g-3 mt-1">
            <div class="col-6"><div class="mini-stat"><span>Tunai &middot; <?= $bayar['tunai']['jumlah'] ?> pembayaran</span><b><?= e(rupiah($bayar['tunai']['nominal'])) ?></b></div></div>
            <div class="col-6"><div class="mini-stat"><span>Transfer &middot; <?= $bayar['transfer']['jumlah'] ?> pembayaran</span><b><?= e(rupiah($bayar['transfer']['nominal'])) ?></b></div></div>
          </div>
        </div></div>
      </div>
      <div class="col-lg-5">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Posisi pekerjaan</h2>
          <p class="section-sub">Pemilik dapat memantau; pembaruan proses dilakukan Petugas Laundry.</p>
          <div class="row g-2 flow-grid">
<?php foreach (URUTAN_GRID_STATUS as $st): ?>
            <div class="col-6"><div class="kv"><span><?= badge_status($st) ?></span><b><?= $per_status[$st] ?></b></div></div>
<?php endforeach; ?>
          </div>
          <div class="note-box mt-3 mb-0"><?= $aktif ?> pesanan aktif, <?= (int) $masuk['jumlah'] ?> pesanan masuk hari ini. <?= $terlambat > 0 ? $terlambat . ' pesanan melewati estimasi selesai.' : 'Tidak ada pesanan yang melewati estimasi selesai.' ?></div>
        </div></div>
      </div>
    </div>

    <div class="card section-card"><div class="card-body">
      <h2 class="section-title">Ringkasan pesanan per layanan</h2>
      <p class="section-sub">Tagihan berdasarkan pesanan yang diterima pada <?= e(tanggal_indo($hari_ini)) ?>.</p>
      <div class="table-responsive">
        <table class="table align-middle app-table">
          <thead><tr><th>Layanan</th><th>Jumlah pesanan</th><th>Berat</th><th class="text-end">Total tagihan</th></tr></thead>
          <tbody>
<?php foreach ($per_layanan as $r): ?>
            <tr><td><?= e($r['nama']) ?></td><td><?= (int) $r['jumlah'] ?></td><td><?= e(format_kg($r['berat'])) ?> kg</td><td class="text-end"><?= e(rupiah($r['total'])) ?></td></tr>
<?php endforeach; ?>
<?php if (!$per_layanan): ?>
            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada pesanan hari ini</td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
        <span class="fw-semibold">Total: <?= (int) $masuk['jumlah'] ?> pesanan &middot; <?= e(format_kg($masuk['berat'])) ?> kg &middot; <?= e(rupiah($masuk['total'])) ?></span>
        <a href="laporan.php" class="btn btn-outline-primary btn-sm">Buka laporan lengkap</a>
      </div>
    </div></div>

<?php require __DIR__ . '/includes/footer.php'; ?>
