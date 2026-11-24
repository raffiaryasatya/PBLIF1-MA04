<?php
// Daftar Pesanan: pencarian, filter (lewat GET), dan pagination 10 per halaman.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('petugas');
$pdo  = db();

const PER_HALAMAN = 10;

/* ------------------------------------------------ Baca filter dari URL (GET) */
$cari    = input_get('cari');
$tanggal = input_get('tanggal');
$bayar   = input_get('bayar');
$status  = input_get('status');
$hal     = max(1, (int) input_get('hal'));

// Abaikan nilai filter yang tidak dikenal
if (!tanggal_valid($tanggal)) {
    $tanggal = '';
}
if (!in_array($bayar, ['lunas', 'belum'], true)) {
    $bayar = '';
}
if (!isset(DAFTAR_STATUS[$status])) {
    $status = '';
}

/* ----------------------------- Susun WHERE (selain status) + parameternya */
$where  = [];
$params = [];
if ($cari !== '') {
    $where[]  = '(p.kode LIKE ? OR pl.nama LIKE ?)';
    $params[] = '%' . $cari . '%';
    $params[] = '%' . $cari . '%';
}
if ($tanggal !== '') {
    $where[]  = 'DATE(p.created_at) = ?';
    $params[] = $tanggal;
}
if ($bayar === 'lunas') {
    $where[] = 'p.dibayar >= p.total';
} elseif ($bayar === 'belum') {
    $where[] = 'p.dibayar < p.total';
}

$dari = 'FROM pesanan p
         JOIN pelanggan pl ON pl.id = p.pelanggan_id
         JOIN layanan l ON l.id = p.layanan_id';

// Jumlah per status untuk chip (memakai filter lain, tanpa filter status)
$sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$stmt = $pdo->prepare("SELECT p.status, COUNT(*) AS n $dari $sql_where GROUP BY p.status");
$stmt->execute($params);
$jumlah_chip = array_fill_keys(array_keys(DAFTAR_STATUS), 0);
foreach ($stmt->fetchAll() as $r) {
    $jumlah_chip[$r['status']] = (int) $r['n'];
}
$jumlah_semua = array_sum($jumlah_chip);

// Tambahkan filter status untuk tabel
if ($status !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $status;
}
$sql_where = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Ringkasan seluruh hasil filter (bukan hanya halaman ini)
$stmt = $pdo->prepare("SELECT COUNT(*) AS jumlah, COALESCE(SUM(p.berat), 0) AS berat,
                              COALESCE(SUM(p.total), 0) AS total, COALESCE(SUM(p.total - p.dibayar), 0) AS sisa
                       $dari $sql_where");
$stmt->execute($params);
$ringkas = $stmt->fetch();
$jumlah  = (int) $ringkas['jumlah'];

// Pagination
$total_hal = max(1, (int) ceil($jumlah / PER_HALAMAN));
$hal       = min($hal, $total_hal);
$offset    = ($hal - 1) * PER_HALAMAN;

// LIMIT & OFFSET berupa angka hasil hitungan sendiri (int), aman ditulis langsung
$stmt = $pdo->prepare("SELECT p.id, p.kode, p.berat, p.total, p.dibayar, p.status, pl.nama AS pelanggan, l.nama AS layanan
                       $dari $sql_where
                       ORDER BY p.created_at DESC, p.id DESC
                       LIMIT " . PER_HALAMAN . ' OFFSET ' . (int) $offset);
$stmt->execute($params);
$daftar = $stmt->fetchAll();

/** Buat URL halaman ini dengan filter yang sama, hanya nomor halaman yang diganti. */
function url_hal(int $nomor): string
{
    global $cari, $tanggal, $bayar, $status;
    $q = array_filter(['cari' => $cari, 'tanggal' => $tanggal, 'bayar' => $bayar, 'status' => $status, 'hal' => $nomor > 1 ? $nomor : '']);
    return 'daftar-pesanan.php' . ($q ? '?' . http_build_query($q) : '');
}

$judul   = 'Daftar Pesanan';
$halaman = 'daftar-pesanan';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Daftar Pesanan</h1>
        <p class="page-sub">Cari pesanan, pantau proses laundry, dan buka detail untuk mencatat pembayaran.</p>
      </div>
      <a href="pesanan-baru.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Pesanan baru</a>
    </div>
    <div class="card section-card mb-4"><div class="card-body">
      <h2 class="section-title">Pencarian dan filter</h2>
      <!-- Filter dikirim ke server lewat GET; app.js mengirim form saat nilai berubah -->
      <form id="filterForm" method="get" action="daftar-pesanan.php">
      <input type="hidden" name="status" id="statusInput" value="<?= e($status) ?>">
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label for="cari" class="form-label">Cari pesanan</label>
          <input type="search" class="form-control" id="cari" name="cari" placeholder="Nomor pesanan atau nama pelanggan" value="<?= e($cari) ?>">
        </div>
        <div class="col-md-4">
          <label for="tanggal" class="form-label">Tanggal masuk</label>
          <input type="date" class="form-control" id="tanggal" name="tanggal" value="<?= e($tanggal) ?>">
        </div>
        <div class="col-md-3">
          <label for="bayar" class="form-label">Pembayaran</label>
          <select class="form-select" id="bayar" name="bayar">
            <option value="">Semua pembayaran</option>
            <option value="lunas"<?= $bayar === 'lunas' ? ' selected' : '' ?>>Lunas</option>
            <option value="belum"<?= $bayar === 'belum' ? ' selected' : '' ?>>Belum lunas</option>
          </select>
        </div>
      </div>
      <div class="d-flex flex-wrap gap-2" role="group" aria-label="Filter status proses"><button type="button" class="chip<?= $status === '' ? ' active' : '' ?>" data-status-chip="">Semua (<?= $jumlah_semua ?>)</button><?php foreach (DAFTAR_STATUS as $kunci => $label): ?><button type="button" class="chip<?= $status === $kunci ? ' active' : '' ?>" data-status-chip="<?= e($kunci) ?>"><?= e($label) ?> (<?= $jumlah_chip[$kunci] ?>)</button><?php endforeach; ?></div>
      </form>
    </div></div>

    <div class="card section-card mb-4"><div class="card-body">
      <h2 class="section-title">Daftar pesanan</h2>
      <p class="section-sub" id="ringkasanList"><?= $jumlah ?> pesanan &middot; <?= e(format_kg($ringkas['berat'])) ?> kg &middot; Total tagihan <?= e(rupiah($ringkas['total'])) ?> &middot; Sisa pembayaran <?= e(rupiah($ringkas['sisa'])) ?></p>
      <div class="table-responsive">
        <table class="table table-hover align-middle app-table">
          <thead><tr><th>No. pesanan</th><th>Pelanggan</th><th>Layanan</th><th>Kg</th><th>Total</th><th>Status proses</th><th>Pembayaran</th><th>Aksi</th></tr></thead>
          <tbody id="ordersBody">
<?php foreach ($daftar as $p): ?>
<tr><td class="fw-semibold"><?= e($p['kode']) ?></td><td><?= e($p['pelanggan']) ?></td><td><?= e($p['layanan']) ?></td><td><?= e(format_kg($p['berat'])) ?></td><td><?= e(rupiah($p['total'])) ?></td><td><?= badge_status($p['status']) ?></td><td><?= badge_bayar($p['total'], $p['dibayar']) ?></td><td><a href="detail-pesanan.php?id=<?= (int) $p['id'] ?>" class="link-action">Detail</a></td></tr>
<?php endforeach; ?>
<?php if (!$daftar): ?>
            <tr id="emptyRow"><td colspan="8" class="text-center text-secondary py-4">Data tidak ditemukan</td></tr>
<?php endif; ?>
          </tbody>
        </table>
      </div>
      <nav class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3" aria-label="Navigasi halaman">
        <span class="text-secondary small" id="hitungTampil"><?php if ($jumlah === 0): ?>Menampilkan 0 pesanan<?php else: ?>Menampilkan <?= $offset + 1 ?>&ndash;<?= $offset + count($daftar) ?> dari <?= $jumlah ?> pesanan<?php endif; ?></span>
        <ul class="pagination pagination-sm mb-0">
<?php if ($hal > 1): ?>
          <li class="page-item"><a class="page-link" href="<?= e(url_hal($hal - 1)) ?>">Sebelumnya</a></li>
<?php else: ?>
          <li class="page-item disabled"><span class="page-link">Sebelumnya</span></li>
<?php endif; ?>
<?php for ($i = 1; $i <= $total_hal; $i++): ?>
<?php if ($i === $hal): ?>
          <li class="page-item active" aria-current="page"><span class="page-link"><?= $i ?></span></li>
<?php else: ?>
          <li class="page-item"><a class="page-link" href="<?= e(url_hal($i)) ?>"><?= $i ?></a></li>
<?php endif; ?>
<?php endfor; ?>
<?php if ($hal < $total_hal): ?>
          <li class="page-item"><a class="page-link" href="<?= e(url_hal($hal + 1)) ?>">Berikutnya</a></li>
<?php else: ?>
          <li class="page-item disabled"><span class="page-link">Berikutnya</span></li>
<?php endif; ?>
        </ul>
      </nav>
    </div></div>

    <div class="note-box">Alur proses: Diterima &rarr; Dicuci &rarr; Dikeringkan &rarr; Disetrika &rarr; Siap diambil &rarr; Selesai. Untuk layanan Setrika saja, tahap Dicuci dan Dikeringkan dilewati. Pesanan hanya dapat diselesaikan setelah diambil dan lunas.</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
