<?php
// Pesanan Baru: petugas mencatat cucian pelanggan.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('petugas');
$pdo  = db();

/* ------------------------------------------------------ Proses simpan (POST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    $nama       = input_post('nama');
    $telepon    = input_post('telepon');
    $alamat     = input_post('alamat');
    $berat_teks = str_replace(',', '.', input_post('berat'));
    $layanan_id = (int) input_post('layanan');
    $catatan    = input_post('catatan');
    $nominal    = input_post('nominal') === '' ? '0' : input_post('nominal');
    $metode     = input_post('metode');

    simpan_old(compact('nama', 'telepon', 'alamat', 'catatan', 'metode') + [
        'berat' => $berat_teks, 'layanan' => $layanan_id, 'nominal' => $nominal,
    ]);

    // Nomor telepon disimpan hanya angka (dan +) agar pelanggan lama mudah dikenali
    $telepon_bersih = preg_replace('/[^0-9+]/', '', $telepon);

    // Ambil layanan dari database (tarif TIDAK diambil dari browser)
    $stmt = $pdo->prepare('SELECT * FROM layanan WHERE id = ? AND aktif = 1');
    $stmt->execute([$layanan_id]);
    $layanan = $stmt->fetch();

    $error = '';
    if ($nama === '' || mb_strlen($nama) > 100) {
        $error = 'Nama pelanggan wajib diisi (maksimal 100 karakter).';
    } elseif (!preg_match('/^[0-9+\-\s]{8,16}$/', $telepon) || strlen($telepon_bersih) < 8) {
        $error = 'Nomor telepon tidak valid (8–16 digit).';
    } elseif ($alamat === '' || mb_strlen($alamat) > 255) {
        $error = 'Alamat wajib diisi (maksimal 255 karakter).';
    } elseif (!is_numeric($berat_teks) || $berat_teks < 1 || $berat_teks > 999) {
        $error = 'Berat cucian minimal 1 kg.';
    } elseif (!$layanan) {
        $error = 'Layanan tidak ditemukan atau sudah nonaktif.';
    } elseif (!ctype_digit($nominal)) {
        $error = 'Nominal pembayaran harus berupa angka bulat.';
    } elseif (!in_array($metode, ['', 'tunai', 'transfer'], true)) {
        $error = 'Metode pembayaran tidak valid.';
    } elseif (mb_strlen($catatan) > 255) {
        $error = 'Catatan maksimal 255 karakter.';
    }

    if ($error === '') {
        $berat   = round((float) $berat_teks, 2);
        $tarif   = (int) $layanan['tarif_per_kg'];
        $total   = (int) round($berat * $tarif);   // total dihitung ulang di server
        $nominal = (int) $nominal;

        if ($nominal > $total) {
            $error = 'Nominal melebihi total biaya (' . rupiah($total) . ').';
        } elseif ($nominal > 0 && $metode === '') {
            $error = 'Pilih metode pembayaran (Tunai atau Transfer).';
        } elseif ($nominal === 0 && $metode !== '') {
            $error = 'Isi nominal yang diterima, atau pilih "Belum ada pembayaran".';
        }
    }

    if ($error !== '') {
        set_flash('danger', $error);
        redirect('pesanan-baru.php');
    }

    // Estimasi selesai = waktu masuk + durasi layanan
    $sekarang = date('Y-m-d H:i:s');
    $estimasi = date('Y-m-d H:i:s', strtotime($sekarang . ' +' . (int) $layanan['durasi_hari'] . ' days'));

    // Semua penyimpanan dalam satu transaksi: berhasil semua atau batal semua
    try {
        $pdo->beginTransaction();

        // 1. Pelanggan lama dikenali dari nomor telepon; nama/alamat diperbarui
        $stmt = $pdo->prepare('SELECT id FROM pelanggan WHERE telepon = ?');
        $stmt->execute([$telepon_bersih]);
        $pelanggan_id = $stmt->fetchColumn();

        if ($pelanggan_id) {
            $pdo->prepare('UPDATE pelanggan SET nama = ?, alamat = ? WHERE id = ?')
                ->execute([$nama, $alamat, $pelanggan_id]);
        } else {
            $pdo->prepare('INSERT INTO pelanggan (nama, telepon, alamat, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$nama, $telepon_bersih, $alamat, $sekarang]);
            $pelanggan_id = $pdo->lastInsertId();
        }

        // 2. Simpan pesanan (status awal: diterima)
        $kode = kode_berikutnya();
        $pdo->prepare('INSERT INTO pesanan (kode, pelanggan_id, layanan_id, user_id, berat, tarif, total, dibayar,
                                            status, estimasi_selesai, catatan, created_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$kode, $pelanggan_id, $layanan['id'], $user['id'], $berat, $tarif, $total, $nominal,
                       'diterima', $estimasi, $catatan !== '' ? $catatan : null, $sekarang]);
        $pesanan_id = $pdo->lastInsertId();

        // 3. Simpan pembayaran awal (jika ada)
        if ($nominal > 0) {
            $pdo->prepare('INSERT INTO pembayaran (pesanan_id, user_id, nominal, metode, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$pesanan_id, $user['id'], $nominal, $metode, $sekarang]);
        }

        $pdo->commit();
    } catch (PDOException $ex) {
        $pdo->rollBack();
        set_flash('danger', 'Pesanan gagal disimpan. Silakan coba lagi.');
        redirect('pesanan-baru.php');
    }

    hapus_old();
    set_flash('success', 'Pesanan ' . $kode . ' berhasil disimpan.');
    redirect('detail-pesanan.php?id=' . $pesanan_id);
}

/* ---------------------------------------------------------- Tampilan (GET) */
$daftar_layanan = $pdo->query('SELECT * FROM layanan WHERE aktif = 1 ORDER BY id')->fetchAll();
$kode_draf = kode_berikutnya();
$sekarang  = time();

// Layanan terpilih: dari isian lama atau layanan pertama
$pilih_id = (int) old('layanan', $daftar_layanan[0]['id'] ?? 0);
$pilih = $daftar_layanan[0] ?? null;
foreach ($daftar_layanan as $l) {
    if ((int) $l['id'] === $pilih_id) {
        $pilih = $l;
    }
}

/** Keterangan singkat layanan, contoh: "Cuci, kering, dan setrika · 2 hari" */
function keterangan_layanan(array $l): string
{
    return ($l['tanpa_cuci'] ? 'Setrika saja' : 'Cuci, kering, dan setrika') . ' · ' . $l['durasi_hari'] . ' hari';
}

$berat_awal   = old('berat', '');
$nominal_awal = old('nominal', '0');
$metode_awal  = old('metode', '');
$total_awal   = $pilih ? (int) round((float) $berat_awal * $pilih['tarif_per_kg']) : 0;

$judul   = 'Pesanan Baru';
$halaman = 'pesanan-baru';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Pesanan Baru</h1>
        <p class="page-sub">Draf <?= e($kode_draf) ?> / <?= e(tanggal_indo(date('Y-m-d H:i', $sekarang), true)) ?> | Belum masuk ke ringkasan atau laporan.</p>
      </div>

    </div>
<?php if (!$daftar_layanan): ?>
    <div class="alert alert-warning">Belum ada layanan aktif. Minta pemilik menambahkan layanan di halaman Pengaturan.</div>
<?php endif; ?>
    <!-- data-waktu: waktu server (milidetik) untuk menghitung estimasi di browser -->
    <form id="orderForm" class="needs-validation" method="post" action="pesanan-baru.php" data-waktu="<?= $sekarang * 1000 ?>" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
      <div class="col-xl-8">

        <div class="card section-card mb-4"><div class="card-body">
          <h2 class="section-title">Data pelanggan</h2>
          <p class="section-sub">Isi data pelanggan yang menyerahkan cucian. Tanda * wajib diisi. Pelanggan lama dikenali dari nomor telepon.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="nama" class="form-label">Nama pelanggan *</label>
              <input type="text" class="form-control" id="nama" name="nama" placeholder="Contoh: Dian Puspita" maxlength="100" value="<?= e(old('nama')) ?>" required>
              <div class="invalid-feedback">Nama pelanggan wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="telepon" class="form-label">Nomor telepon *</label>
              <input type="tel" class="form-control" id="telepon" name="telepon" placeholder="Contoh: 0812 3456 7890" pattern="[0-9+\-\s]{8,16}" value="<?= e(old('telepon')) ?>" required>
              <div class="invalid-feedback">Isi nomor telepon yang valid (8&ndash;16 digit).</div>
            </div>
            <div class="col-12">
              <label for="alamat" class="form-label">Alamat / catatan pelanggan *</label>
              <input type="text" class="form-control" id="alamat" name="alamat" placeholder="Contoh: Jl. Melati No. 12" maxlength="255" value="<?= e(old('alamat')) ?>" required>
              <div class="invalid-feedback">Alamat wajib diisi.</div>
            </div>
          </div>
        </div></div>

        <div class="card section-card mb-4"><div class="card-body">
          <h2 class="section-title">Rincian laundry</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="berat" class="form-label">Berat cucian (kg) *</label>
              <input type="number" class="form-control" id="berat" name="berat" min="1" max="999" step="0.01" value="<?= e($berat_awal) ?>" required>
              <div class="form-text">Gunakan hasil timbang; minimal 1 kg.</div>
              <div class="invalid-feedback">Berat minimal 1 kg.</div>
            </div>
            <div class="col-md-6">
              <label for="layanan" class="form-label">Layanan *</label>
              <select class="form-select" id="layanan" name="layanan" required>
                <!-- tarif & durasi dibaca app.js dari atribut data-* -->
<?php foreach ($daftar_layanan as $l): ?>
                <option value="<?= (int) $l['id'] ?>" data-tarif="<?= (int) $l['tarif_per_kg'] ?>" data-hari="<?= (int) $l['durasi_hari'] ?>" data-ket="<?= e(keterangan_layanan($l)) ?>"<?= $pilih && $l['id'] === $pilih['id'] ? ' selected' : '' ?>><?= e($l['nama']) ?></option>
<?php endforeach; ?>
              </select>
              <div class="form-text" id="layananKet"><?= $pilih ? e(keterangan_layanan($pilih)) : '&ndash;' ?></div>
            </div>
            <div class="col-md-6">
              <label for="tarif" class="form-label">Tarif per kg</label>
              <input type="text" class="form-control" id="tarif" value="<?= $pilih ? e(rupiah($pilih['tarif_per_kg'])) : '' ?>" readonly>
              <div class="form-text">Tarif dikelola oleh Pemilik.</div>
            </div>
            <div class="col-md-6">
              <label for="estimasi" class="form-label">Estimasi selesai *</label>
              <input type="text" class="form-control" id="estimasi" value="<?= $pilih ? e(tanggal_indo(date('Y-m-d H:i', strtotime('+' . $pilih['durasi_hari'] . ' days', $sekarang)), true)) : '' ?>" readonly>
            </div>
            <div class="col-12">
              <label for="catatan" class="form-label">Catatan cucian</label>
              <input type="text" class="form-control" id="catatan" name="catatan" placeholder="Contoh: Pisahkan pakaian putih." maxlength="255" value="<?= e(old('catatan')) ?>">
            </div>
          </div>
        </div></div>

        <div class="card section-card"><div class="card-body">
          <h2 class="section-title">Pembayaran awal</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="statusBayar" class="form-label">Status pembayaran</label>
              <input type="text" class="form-control" id="statusBayar" value="Belum lunas" readonly>
              <div class="form-text">Ditentukan otomatis dari nominal yang diterima.</div>
            </div>
            <div class="col-md-6">
              <label for="nominal" class="form-label">Nominal diterima (Rp)</label>
              <input type="number" class="form-control" id="nominal" name="nominal" min="0" step="1" value="<?= e($nominal_awal) ?>">
              <div class="invalid-feedback" id="nominalFb">Nominal tidak valid.</div>
            </div>
            <div class="col-md-6">
              <label for="metode" class="form-label">Metode pembayaran</label>
              <select class="form-select" id="metode" name="metode">
                <option value=""<?= $metode_awal === '' ? ' selected' : '' ?>>Belum ada pembayaran</option>
                <option value="tunai"<?= $metode_awal === 'tunai' ? ' selected' : '' ?>>Tunai</option>
                <option value="transfer"<?= $metode_awal === 'transfer' ? ' selected' : '' ?>>Transfer</option>
              </select>
              <div class="invalid-feedback" id="metodeFb">Pilih metode pembayaran.</div>
            </div>
            <div class="col-md-6 d-flex align-items-end">
              <p class="text-secondary small mb-2">Jika ada pembayaran, pilih Tunai atau Transfer dan isi nominalnya.</p>
            </div>
          </div>
        </div></div>
      </div>

      <div class="col-xl-4">
        <div class="card section-card summary-card mb-3"><div class="card-body">
          <h2 class="section-title">Ringkasan pesanan</h2>
          <ul class="list-unstyled kv-list">
            <li><span>Nomor pesanan</span><span class="fw-semibold"><?= e($kode_draf) ?> (draf)</span></li>
            <li><span>Status awal</span><span><span class="st st-diterima">Diterima</span></span></li>
            <li><span>Layanan</span><span class="fw-semibold" id="sumLayanan"><?= $pilih ? e($pilih['nama']) : '&ndash;' ?></span></li>
            <li><span>Perhitungan</span><span id="sumHitung"><?= $pilih ? e(format_kg($berat_awal)) . ' kg &times; ' . e(rupiah($pilih['tarif_per_kg'])) : '&ndash;' ?></span></li>
            <li><span>Subtotal</span><span id="sumSubtotal"><?= e(rupiah($total_awal)) ?></span></li>
            <li><span>Biaya tambahan</span><span>Rp0</span></li>
          </ul>
          <div class="total-row"><span>Total biaya</span><span id="sumTotal"><?= e(rupiah($total_awal)) ?></span></div>
          <ul class="list-unstyled kv-list mt-2 mb-0">
            <li><span>Sudah dibayar</span><span id="sumBayar">Rp0</span></li>
            <li><span>Sisa tagihan</span><span class="fw-semibold" id="sumSisa"><?= e(rupiah($total_awal)) ?></span></li>
          </ul>
        </div></div>
        <div class="note-box mb-3">Total dihitung dari berat &times; tarif. Setelah disimpan, pesanan berstatus Diterima dan dapat dicetak nota di detail pesanan.</div>
        <button type="submit" class="btn btn-primary w-100 mb-2"<?= $daftar_layanan ? '' : ' disabled' ?>><i class="bi bi-check2-circle me-1"></i>Simpan pesanan</button>
        <a href="dashboard-petugas.php" class="btn btn-outline-secondary w-100">Batal</a>
      </div>
    </div>
    </form>

<?php require __DIR__ . '/includes/footer.php'; ?>
