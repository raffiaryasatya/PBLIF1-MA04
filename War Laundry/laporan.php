<?php
// Laporan Pemilik: filter periode (GET), pemasukan dari tanggal bayar,
// jumlah pesanan dari tanggal masuk.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('pemilik');
$pdo  = db();

$hari_ini = date('Y-m-d');
$mulai    = input_get('mulai') ?: $hari_ini;
$akhir    = input_get('akhir') ?: $hari_ini;

// Validasi periode; jika salah kembali ke hari ini
if (!tanggal_valid($mulai) || !tanggal_valid($akhir) || $mulai > $akhir) {
    set_flash('danger', 'Periode tidak valid. Tanggal akhir tidak boleh sebelum tanggal mulai.');
    redirect('laporan.php');
}

$periode_label = $mulai === $akhir
    ? tanggal_indo($mulai)
    : tanggal_indo($mulai) . ' – ' . tanggal_indo($akhir);

/* ------------------------------------------- Pemasukan (tanggal pembayaran) */
$stmt = $pdo->prepare('SELECT metode, COUNT(*) AS jumlah, SUM(nominal) AS nominal FROM pembayaran
                       WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY metode');
$stmt->execute([$mulai, $akhir]);
$bayar = ['tunai' => 0, 'transfer' => 0];
$jumlah_bayar = 0;
foreach ($stmt->fetchAll() as $r) {
    $bayar[$r['metode']] = (int) $r['nominal'];
    $jumlah_bayar += (int) $r['jumlah'];
}
$pemasukan = $bayar['tunai'] + $bayar['transfer'];

/* -------------------------------------------- Pesanan (tanggal masuk) */
$stmt = $pdo->prepare("SELECT COUNT(*) AS jumlah,
                              COALESCE(SUM(berat), 0) AS berat,
                              COALESCE(SUM(total), 0) AS total,
                              COALESCE(SUM(status <> 'selesai'), 0) AS aktif,
                              COALESCE(SUM(status = 'selesai'), 0) AS selesai,
                              COALESCE(SUM(dibayar >= total), 0) AS lunas,
                              COALESCE(SUM(CASE WHEN dibayar >= total THEN total ELSE 0 END), 0) AS total_lunas,
                              COALESCE(SUM(dibayar < total), 0) AS belum,
                              COALESCE(SUM(CASE WHEN dibayar < total THEN total ELSE 0 END), 0) AS total_belum,
                              COALESCE(SUM(total - dibayar), 0) AS sisa
                       FROM pesanan WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$mulai, $akhir]);
$ps = $stmt->fetch();

/* ------------------------------ Tabel transaksi: pembayaran + pesanan tanpa bayar */
// a) setiap pembayaran dalam periode
$stmt = $pdo->prepare('SELECT p.kode, pl.nama, b.created_at AS waktu, p.total, b.nominal, b.metode, p.dibayar
                       FROM pembayaran b
                       JOIN pesanan p ON p.id = b.pesanan_id
                       JOIN pelanggan pl ON pl.id = p.pelanggan_id
                       WHERE DATE(b.created_at) BETWEEN ? AND ?');
$stmt->execute([$mulai, $akhir]);
$transaksi = $stmt->fetchAll();

// b) pesanan yang masuk dalam periode tetapi belum ada pembayaran di periode ini
$stmt = $pdo->prepare('SELECT p.kode, pl.nama, NULL AS waktu, p.total, 0 AS nominal, NULL AS metode, p.dibayar
                       FROM pesanan p
                       JOIN pelanggan pl ON pl.id = p.pelanggan_id
                       WHERE DATE(p.created_at) BETWEEN ? AND ?
                       AND NOT EXISTS (SELECT 1 FROM pembayaran b WHERE b.pesanan_id = p.id
                                       AND DATE(b.created_at) BETWEEN ? AND ?)');
$stmt->execute([$mulai, $akhir, $mulai, $akhir]);
$transaksi = array_merge($transaksi, $stmt->fetchAll());

// Urutkan: kode terbaru di atas, lalu waktu bayar
usort($transaksi, function ($a, $b) {
    return [$b['kode'], $a['waktu']] <=> [$a['kode'], $b['waktu']];
});

$ada_data = $transaksi !== [];

$judul   = 'Laporan';
$halaman = 'laporan';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Laporan Pemilik</h1>
        <p class="page-sub">Laporan penerimaan dan ringkasan pesanan &middot; Periode <span id="periodeLabel"><?= e($periode_label) ?></span></p>
      </div>
      <button type="button" class="btn btn-primary no-print" id="cetakLaporan"><i class="bi bi-printer me-1"></i>Cetak laporan</button>
    </div>
    <div class="card section-card mb-4 no-print"><div class="card-body">
      <h2 class="section-title">Filter periode</h2>
      <p class="section-sub">Pemasukan mengikuti tanggal pembayaran; jumlah pesanan mengikuti tanggal masuk.</p>
      <form id="periodeForm" class="row g-3 align-items-end needs-validation" method="get" action="laporan.php" novalidate>
        <div class="col-md-4">
          <label for="tglMulai" class="form-label">Tanggal mulai</label>
          <input type="date" class="form-control" id="tglMulai" name="mulai" value="<?= e($mulai) ?>" required>
          <div class="invalid-feedback">Tanggal mulai wajib diisi.</div>
        </div>
        <div class="col-md-4">
          <label for="tglAkhir" class="form-label">Tanggal akhir</label>
          <input type="date" class="form-control" id="tglAkhir" name="akhir" value="<?= e($akhir) ?>" required>
          <div class="invalid-feedback" id="akhirFb">Tanggal akhir wajib diisi.</div>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-primary">Tampilkan</button>
          <button type="button" class="btn btn-outline-secondary" id="resetPeriode">Atur ulang</button>
        </div>
      </form>
    </div></div>

    <div id="laporanKosong" class="alert alert-warning"<?= $ada_data ? ' hidden' : '' ?>>Data tidak ditemukan untuk periode yang dipilih.</div>

    <div id="laporanData" class="print-area"<?= $ada_data ? '' : ' hidden' ?>>
      <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card stat-card stat-highlight h-100"><div class="card-body">
          <div class="stat-label">Total pemasukan</div><div class="stat-value"><?= e(rupiah($pemasukan)) ?></div><div class="stat-sub">Tunai <?= e(rupiah($bayar['tunai'])) ?> + transfer <?= e(rupiah($bayar['transfer'])) ?></div></div></div></div>
        <div class="col-md-4"><div class="card stat-card h-100"><div class="card-body">
          <div class="stat-label">Total tagihan</div><div class="stat-value"><?= e(rupiah($ps['total'])) ?></div><div class="stat-sub"><?= (int) $ps['jumlah'] ?> pesanan diterima dalam periode</div></div></div></div>
        <div class="col-md-4"><div class="card stat-card h-100"><div class="card-body">
          <div class="stat-label">Sisa tagihan</div><div class="stat-value"><?= e(rupiah($ps['sisa'])) ?></div><div class="stat-sub"><?= (int) $ps['belum'] ?> pesanan belum lunas</div></div></div></div>
      </div>

      <div class="card section-card mb-4"><div class="card-body">
        <h2 class="section-title">Transaksi dan pembayaran</h2>
        <p class="section-sub">Semua pesanan masuk dan pembayaran pada <?= e($periode_label) ?>. Nilai Rp0 belum dihitung sebagai pemasukan.</p>
        <div class="table-responsive">
          <table class="table table-hover align-middle app-table">
            <thead><tr><th>No. pesanan</th><th>Pelanggan</th><th>Waktu bayar</th><th>Tagihan</th><th>Diterima</th><th>Metode</th><th>Pembayaran</th></tr></thead>
            <tbody>
<?php foreach ($transaksi as $t): ?>
<tr><td class="fw-semibold"><?= e($t['kode']) ?></td><td><?= e($t['nama']) ?></td><td><?= $t['waktu'] ? e($mulai === $akhir ? date('H.i', strtotime($t['waktu'])) : tanggal_pendek($t['waktu'])) : '&ndash;' ?></td><td><?= e(rupiah($t['total'])) ?></td><td><?= e(rupiah($t['nominal'])) ?></td><td><?= $t['metode'] ? e(ucfirst($t['metode'])) : '&ndash;' ?></td><td><?= badge_bayar($t['total'], $t['dibayar']) ?></td></tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between flex-wrap gap-2 mt-2">
          <span class="text-secondary small"><?= (int) $ps['jumlah'] ?> pesanan &middot; <?= $jumlah_bayar ?> pembayaran diterima</span>
          <span class="fw-semibold">Total pemasukan: <?= e(rupiah($pemasukan)) ?></span>
        </div>
      </div></div>

      <div class="row g-3">
        <div class="col-lg-6"><div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Ringkasan pesanan</h2>
          <ul class="list-unstyled kv-list mb-0">
            <li><span>Pesanan masuk / berat</span><span class="fw-semibold"><?= (int) $ps['jumlah'] ?> pesanan / <?= e(format_kg($ps['berat'])) ?> kg</span></li>
            <li><span>Pekerjaan aktif</span><span class="fw-semibold"><?= (int) $ps['aktif'] ?> pesanan</span></li>
            <li><span>Selesai dan diambil</span><span class="fw-semibold"><?= (int) $ps['selesai'] ?></span></li>
          </ul>
        </div></div></div>
        <div class="col-lg-6"><div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Rekonsiliasi pembayaran</h2>
          <ul class="list-unstyled kv-list">
            <li><span>Lunas</span><span class="fw-semibold"><?= (int) $ps['lunas'] ?> pesanan &middot; <?= e(rupiah($ps['total_lunas'])) ?></span></li>
            <li><span>Belum lunas</span><span class="fw-semibold"><?= (int) $ps['belum'] ?> pesanan &middot; <?= e(rupiah($ps['total_belum'])) ?></span></li>
          </ul>
          <p class="text-secondary small mb-0"><?= e(rupiah($ps['total_lunas'])) ?> + <?= e(rupiah($ps['total_belum'])) ?> = <?= e(rupiah($ps['total'])) ?> total tagihan. Sisa yang belum dibayar <?= e(rupiah($ps['sisa'])) ?>.</p>
        </div></div></div>
      </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
