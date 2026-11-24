<?php
// Detail Pesanan: stepper status, ubah status, pembayaran bertahap, dan nota.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('petugas');
$pdo  = db();

$id = (int) input_get('id');

/** Ambil satu pesanan lengkap. $kunci = true untuk mengunci baris (dipakai di transaksi). */
function ambil_pesanan(int $id, bool $kunci = false): ?array
{
    $sql = 'SELECT p.*, pl.nama AS pelanggan, pl.telepon, pl.alamat,
                   l.nama AS layanan, l.tanpa_cuci, u.nama AS petugas
            FROM pesanan p
            JOIN pelanggan pl ON pl.id = p.pelanggan_id
            JOIN layanan l ON l.id = p.layanan_id
            JOIN users u ON u.id = p.user_id
            WHERE p.id = ?' . ($kunci ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Status tujuan yang boleh dipilih: hanya yang ada SETELAH status sekarang (maju),
 * mengikuti alur layanan. Selesai hanya dari "Siap diambil" dan jika sudah lunas.
 * Hasil: [status => alasan_tidak_boleh] (alasan kosong = boleh).
 */
function status_berikutnya(array $p): array
{
    $alur  = alur_status((bool) $p['tanpa_cuci']);
    $posisi = array_search($p['status'], $alur, true);
    $hasil = [];
    foreach (array_slice($alur, $posisi + 1) as $st) {
        $alasan = '';
        if ($st === 'selesai' && $p['status'] !== 'siap_diambil') {
            $alasan = 'harus siap diambil dulu';
        } elseif ($st === 'selesai' && $p['dibayar'] < $p['total']) {
            $alasan = 'belum lunas';
        }
        $hasil[$st] = $alasan;
    }
    return $hasil;
}

/* ----------------------------------------------------------- Proses POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = input_post('aksi');
    $kembali = 'detail-pesanan.php?id=' . $id;

    try {
        $pdo->beginTransaction();
        $p = ambil_pesanan($id, true);   // kunci baris agar tidak bentrok dengan petugas lain
        if (!$p) {
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }

        if ($aksi === 'status') {
            // ---------- Ubah status
            $baru = input_post('status');
            $boleh = status_berikutnya($p);
            if (!array_key_exists($baru, $boleh)) {
                throw new RuntimeException('Status hanya bisa maju ke tahap berikutnya.');
            }
            if ($boleh[$baru] !== '') {
                throw new RuntimeException('Pesanan belum bisa diselesaikan: ' . $boleh[$baru] . '.');
            }
            $pdo->prepare('UPDATE pesanan SET status = ?, selesai_at = ? WHERE id = ?')
                ->execute([$baru, $baru === 'selesai' ? date('Y-m-d H:i:s') : null, $id]);
            $pesan = 'Status ' . $p['kode'] . ' diperbarui menjadi ' . label_status($baru) . '.';

        } elseif ($aksi === 'bayar') {
            // ---------- Catat pembayaran (boleh bertahap, tidak boleh melebihi sisa)
            $nominal = input_post('nominal');
            $metode  = input_post('metode');
            $sisa    = $p['total'] - $p['dibayar'];

            if ($sisa <= 0) {
                throw new RuntimeException('Pesanan ini sudah lunas.');
            }
            if (!ctype_digit($nominal) || (int) $nominal < 1) {
                throw new RuntimeException('Nominal pembayaran minimal Rp1.');
            }
            if ((int) $nominal > $sisa) {
                throw new RuntimeException('Nominal melebihi sisa tagihan (' . rupiah($sisa) . ').');
            }
            if (!in_array($metode, ['tunai', 'transfer'], true)) {
                throw new RuntimeException('Pilih metode pembayaran.');
            }
            $nominal = (int) $nominal;
            $pdo->prepare('INSERT INTO pembayaran (pesanan_id, user_id, nominal, metode, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $user['id'], $nominal, $metode, date('Y-m-d H:i:s')]);
            $pdo->prepare('UPDATE pesanan SET dibayar = dibayar + ? WHERE id = ?')
                ->execute([$nominal, $id]);
            $pesan = 'Pembayaran ' . rupiah($nominal) . ' dicatat.'
                   . ($nominal === $sisa ? ' Pesanan ' . $p['kode'] . ' lunas.' : '');

        } else {
            throw new RuntimeException('Aksi tidak dikenal.');
        }

        $pdo->commit();
        set_flash('success', $pesan);
    } catch (RuntimeException $ex) {
        $pdo->rollBack();
        set_flash('danger', $ex->getMessage());      // pesan validasi
    } catch (PDOException $ex) {
        $pdo->rollBack();
        set_flash('danger', 'Gagal menyimpan ke database. Silakan coba lagi.');
    }
    redirect($kembali);
}

/* ---------------------------------------------------------- Tampilan (GET) */
$p = ambil_pesanan($id);
if (!$p) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    redirect('daftar-pesanan.php');
}

$stmt = $pdo->prepare('SELECT b.*, u.nama AS petugas FROM pembayaran b JOIN users u ON u.id = b.user_id
                       WHERE b.pesanan_id = ? ORDER BY b.created_at, b.id');
$stmt->execute([$id]);
$riwayat = $stmt->fetchAll();

$sisa     = $p['total'] - $p['dibayar'];
$lunas    = $sisa <= 0;
$alur     = alur_status((bool) $p['tanpa_cuci']);
$semua    = array_keys(DAFTAR_STATUS);
$posisi   = array_search($p['status'], $semua, true);
$pilihan  = status_berikutnya($p);
$terakhir = $p['status'] === 'selesai';
$bisa_ubah = in_array('', $pilihan, true);   // ada minimal satu status yang boleh dipilih

$judul      = 'Detail Pesanan ' . $p['kode'];
$halaman    = 'detail-pesanan';
$menu_aktif = 'daftar-pesanan';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3 no-print">
      <div>
        <h1 class="page-title">Detail Pesanan <?= e($p['kode']) ?> <span id="statusBadge" class="st <?= $p['status'] === 'siap_diambil' ? 'st-siap' : 'st-' . e($p['status']) ?> align-middle"><?= e(label_status($p['status'])) ?></span></h1>
        <p class="page-sub">Masuk <?= e(tanggal_indo($p['created_at'], true)) ?> &middot; dicatat oleh <?= e($p['petugas']) ?></p>
      </div>
      <a href="daftar-pesanan.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>

    <div class="row g-4">
      <div class="col-xl-8">

        <!-- Stepper status proses -->
        <div class="card section-card mb-4 no-print"><div class="card-body">
          <h2 class="section-title">Proses laundry</h2>
          <p class="section-sub" id="statusSaatIni">Status saat ini: <?= e(label_status($p['status'])) ?>. <?= $terakhir ? 'Pesanan sudah selesai.' : 'Lanjutkan ke tahap berikutnya setelah pekerjaan selesai.' ?></p>
          <ol class="stepper mb-0" id="stepper">
<?php foreach ($semua as $i => $st):
    $dilewati = !in_array($st, $alur, true);
    if ($i < $posisi) {
        $kelas = 'done';
        $ket = $dilewati ? 'Dilewati' : 'Selesai';
    } elseif ($i === $posisi) {
        $kelas = 'current';
        $ket = $st === 'selesai' ? tanggal_pendek($p['selesai_at']) : 'Sedang diproses';
    } else {
        $kelas = 'pending';
        $ket = $dilewati ? 'Dilewati' : 'Belum diproses';
    }
    if ($i === 0) {
        $ket = tanggal_pendek($p['created_at']);   // tahap pertama menampilkan waktu masuk
    }
?>
            <li class="step <?= $kelas ?>"<?= $kelas === 'current' ? ' aria-current="step"' : '' ?>><span class="step-dot"></span><span class="step-name"><?= e(label_status($st)) ?></span><small><?= e($ket) ?></small></li>
<?php endforeach; ?>
          </ol>
        </div></div>

        <!-- Form ubah status -->
        <div class="card section-card mb-4 no-print"><div class="card-body">
          <h2 class="section-title">Ubah status</h2>
          <p class="section-sub">Status hanya bisa maju. <?= $p['tanpa_cuci'] ? 'Layanan ' . e($p['layanan']) . ' melewati tahap Dicuci dan Dikeringkan. ' : '' ?>Status Selesai hanya bisa dipilih setelah Siap diambil dan lunas.</p>
          <form id="statusForm" method="post" action="detail-pesanan.php?id=<?= (int) $p['id'] ?>" class="row g-3 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="status">
            <div class="col-md-8">
              <label for="statusBerikut" class="form-label">Status berikutnya</label>
              <select class="form-select" id="statusBerikut" name="status"<?= $terakhir ? ' disabled' : '' ?>>
<?php foreach ($pilihan as $st => $alasan): ?>
                <option value="<?= e($st) ?>"<?= $alasan !== '' ? ' disabled' : '' ?>><?= e(label_status($st)) ?><?= $alasan !== '' ? ' (' . e($alasan) . ')' : '' ?></option>
<?php endforeach; ?>
<?php if ($terakhir): ?>
                <option>Pesanan sudah selesai</option>
<?php endif; ?>
              </select>
            </div>
            <div class="col-md-4">
              <button type="submit" class="btn btn-primary w-100" id="simpanStatus"<?= $bisa_ubah ? '' : ' disabled' ?>>Simpan status</button>
            </div>
          </form>
        </div></div>

        <!-- Pembayaran -->
        <div class="card section-card no-print"><div class="card-body">
          <h2 class="section-title">Pembayaran</h2>
          <p class="section-sub">Pembayaran boleh bertahap dan tidak boleh melebihi sisa tagihan.</p>
          <div class="table-responsive mb-3">
            <table class="table align-middle app-table">
              <thead><tr><th>Waktu</th><th>Metode</th><th>Petugas</th><th class="text-end">Nominal</th></tr></thead>
              <tbody>
<?php foreach ($riwayat as $b): ?>
                <tr><td><?= e(tanggal_indo($b['created_at'], true)) ?></td><td><?= e(ucfirst($b['metode'])) ?></td><td><?= e($b['petugas']) ?></td><td class="text-end"><?= e(rupiah($b['nominal'])) ?></td></tr>
<?php endforeach; ?>
<?php if (!$riwayat): ?>
                <tr><td colspan="4" class="text-center text-secondary py-3">Belum ada pembayaran</td></tr>
<?php endif; ?>
              </tbody>
            </table>
          </div>
          <ul class="list-unstyled kv-list">
            <li><span>Total tagihan</span><span class="fw-semibold"><?= e(rupiah($p['total'])) ?></span></li>
            <li><span>Sudah dibayar</span><span class="fw-semibold text-success"><?= e(rupiah($p['dibayar'])) ?></span></li>
            <li><span>Sisa tagihan</span><span class="fw-semibold<?= $lunas ? '' : ' text-danger' ?>"><?= e(rupiah($sisa)) ?></span></li>
          </ul>
<?php if ($lunas): ?>
          <div class="note-box mb-0"><i class="bi bi-check-circle me-1"></i>Pesanan sudah lunas.</div>
<?php else: ?>
          <form id="bayarForm" method="post" action="detail-pesanan.php?id=<?= (int) $p['id'] ?>" class="row g-3 align-items-start needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="bayar">
            <div class="col-md-5">
              <label for="bNominal" class="form-label">Nominal diterima (Rp) *</label>
              <input type="number" class="form-control" id="bNominal" name="nominal" min="1" max="<?= (int) $sisa ?>" step="1" value="<?= (int) $sisa ?>" data-sisa="<?= (int) $sisa ?>" required>
              <div class="invalid-feedback" id="bNominalFb">Nominal 1 sampai <?= e(rupiah($sisa)) ?>.</div>
            </div>
            <div class="col-md-4">
              <label for="bMetode" class="form-label">Metode *</label>
              <select class="form-select" id="bMetode" name="metode" required>
                <option value="">Pilih metode</option>
                <option value="tunai">Tunai</option>
                <option value="transfer">Transfer</option>
              </select>
              <div class="invalid-feedback">Pilih metode pembayaran.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label d-none d-md-block">&nbsp;</label>
              <button type="submit" class="btn btn-primary w-100">Catat bayar</button>
            </div>
          </form>
<?php endif; ?>
        </div></div>
      </div>

      <div class="col-xl-4">
        <!-- Nota (hanya bagian ini yang ikut dicetak) -->
        <div class="card section-card mb-3 print-area"><div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <div>
              <div class="nota-brand"><i class="bi bi-droplet-half me-1"></i>War Laundry</div>
              <div class="text-secondary small">Laundry Kiloan &middot; Nota pesanan</div>
            </div>
            <span class="fw-semibold"><?= e($p['kode']) ?></span>
          </div>
          <ul class="list-unstyled kv-list">
            <li><span>Tanggal masuk</span><span><?= e(tanggal_indo($p['created_at'], true)) ?></span></li>
            <li><span>Estimasi selesai</span><span><?= e(tanggal_indo($p['estimasi_selesai'], true)) ?></span></li>
            <li><span>Pelanggan</span><span class="fw-semibold"><?= e($p['pelanggan']) ?></span></li>
            <li><span>Layanan</span><span><?= e($p['layanan']) ?></span></li>
            <li><span>Perhitungan</span><span><?= e(format_kg($p['berat'])) ?> kg &times; <?= e(rupiah($p['tarif'])) ?></span></li>
          </ul>
          <div class="total-row"><span>Total biaya</span><span><?= e(rupiah($p['total'])) ?></span></div>
          <ul class="list-unstyled kv-list mt-2">
            <li><span>Sudah dibayar</span><span><?= e(rupiah($p['dibayar'])) ?></span></li>
            <li><span>Sisa tagihan</span><span class="fw-semibold"><?= e(rupiah($sisa)) ?></span></li>
            <li><span>Pembayaran</span><span><?= badge_bayar($p['total'], $p['dibayar']) ?></span></li>
          </ul>
          <p class="text-secondary small mb-3">Petugas: <?= e($p['petugas']) ?>. Simpan nota ini untuk pengambilan cucian.</p>
          <button type="button" class="btn btn-outline-primary w-100 no-print" id="cetakNota"><i class="bi bi-printer me-1"></i>Cetak nota</button>
        </div></div>

        <!-- Data pelanggan -->
        <div class="card section-card no-print"><div class="card-body">
          <h2 class="section-title">Data pelanggan</h2>
          <ul class="list-unstyled kv-list mb-0">
            <li><span>Nama</span><span class="fw-semibold"><?= e($p['pelanggan']) ?></span></li>
            <li><span>Telepon</span><span><?= e($p['telepon']) ?></span></li>
            <li><span>Alamat</span><span class="text-end"><?= e($p['alamat']) ?></span></li>
            <li><span>Catatan cucian</span><span class="text-end"><?= $p['catatan'] !== null ? e($p['catatan']) : '&ndash;' ?></span></li>
          </ul>
        </div></div>
      </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
