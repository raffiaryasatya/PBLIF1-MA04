<?php
// Pengaturan: pemilik mengelola layanan & tarif serta akun petugas.
// Tidak ada fitur hapus; cukup dinonaktifkan agar riwayat pesanan tetap utuh.
require_once __DIR__ . '/includes/auth.php';
$user = wajib_peran('pemilik');
$pdo  = db();

/* ----------------------------------------------------------- Proses POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = input_post('aksi');
    $id   = (int) input_post('id');            // 0 = data baru
    $aktif = input_post('status') === '1' ? 1 : 0;

    if ($aksi === 'layanan') {
        // ---------------- Simpan layanan
        $nama  = input_post('nama');
        $tarif = input_post('tarif');
        $hari  = input_post('hari');
        $tanpa_cuci = input_post('tanpa_cuci') === '1' ? 1 : 0;

        if ($nama === '' || mb_strlen($nama) > 100) {
            set_flash('danger', 'Nama layanan wajib diisi (maksimal 100 karakter).');
        } elseif (!ctype_digit($tarif) || (int) $tarif < 500 || (int) $tarif % 500 !== 0 || (int) $tarif > 1000000) {
            set_flash('danger', 'Tarif minimal Rp500 dan kelipatan Rp500.');
        } elseif (!ctype_digit($hari) || (int) $hari < 1 || (int) $hari > 30) {
            set_flash('danger', 'Durasi harus 1–30 hari.');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare('UPDATE layanan SET nama = ?, tarif_per_kg = ?, durasi_hari = ?, tanpa_cuci = ?, aktif = ? WHERE id = ?');
            $stmt->execute([$nama, (int) $tarif, (int) $hari, $tanpa_cuci, $aktif, $id]);
            set_flash('success', 'Layanan disimpan. Tarif baru berlaku untuk pesanan berikutnya.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO layanan (nama, tarif_per_kg, durasi_hari, tanpa_cuci, aktif) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$nama, (int) $tarif, (int) $hari, $tanpa_cuci, $aktif]);
            $id = (int) $pdo->lastInsertId();
            set_flash('success', 'Layanan baru ditambahkan.');
        }
        redirect('pengaturan.php' . ($id > 0 ? '?layanan=' . $id : ''));
    }

    if ($aksi === 'akun') {
        // ---------------- Simpan akun petugas
        $nama     = input_post('nama');
        $username = input_post('pengguna');
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $error    = '';

        if ($nama === '' || mb_strlen($nama) > 100) {
            $error = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
        } elseif (!preg_match('/^[A-Za-z0-9_.]{3,50}$/', $username)) {
            $error = 'Nama pengguna 3–50 karakter: huruf, angka, titik, atau garis bawah.';
        } elseif ($id === 0 && $password === '') {
            $error = 'Kata sandi wajib diisi untuk akun baru.';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'Kata sandi minimal 8 karakter.';
        }

        // Nama pengguna harus unik
        if ($error === '') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?');
            $stmt->execute([$username, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Nama pengguna "' . $username . '" sudah dipakai.';
            }
        }

        if ($error !== '') {
            set_flash('danger', $error);
            redirect('pengaturan.php' . ($id > 0 ? '?akun=' . $id : ''));
        }

        if ($id > 0) {
            // Hanya akun petugas yang boleh diubah dari sini
            $stmt = $pdo->prepare("UPDATE users SET nama = ?, username = ?, aktif = ? WHERE id = ? AND peran = 'petugas'");
            $stmt->execute([$nama, $username, $aktif, $id]);
            if ($password !== '') {
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND peran = 'petugas'");
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            set_flash('success', 'Akun petugas disimpan.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (nama, username, password, peran, aktif) VALUES (?, ?, ?, 'petugas', ?)");
            $stmt->execute([$nama, $username, password_hash($password, PASSWORD_DEFAULT), $aktif]);
            $id = (int) $pdo->lastInsertId();
            set_flash('success', 'Akun petugas baru ditambahkan.');
        }
        redirect('pengaturan.php?akun=' . $id);
    }

    redirect('pengaturan.php');
}

/* ---------------------------------------------------------- Tampilan (GET) */
$daftar_layanan = $pdo->query('SELECT * FROM layanan ORDER BY id')->fetchAll();
$daftar_akun    = $pdo->query("SELECT id, nama, username, aktif FROM users WHERE peran = 'petugas' ORDER BY nama")->fetchAll();

$judul   = 'Pengaturan';
$halaman = 'pengaturan';
require __DIR__ . '/includes/header.php';
?>

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Pengaturan</h1>
        <p class="page-sub">Kelola layanan, tarif per kilogram, dan akun Petugas Laundry. Hanya dapat diakses Pemilik.</p>
      </div>

    </div>
    <div class="row g-4 mb-4">
      <div class="col-xl-7">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Layanan dan tarif</h2>
          <p class="section-sub">Tarif berlaku per kilogram. Layanan nonaktif tidak muncul di formulir pesanan baru.</p>
          <div class="table-responsive">
            <table class="table table-hover align-middle app-table">
              <thead><tr><th>Layanan</th><th>Tarif / kg</th><th>Durasi</th><th>Status</th><th>Aksi</th></tr></thead>
              <!-- data-pilih: baris yang langsung dipilih setelah disimpan -->
              <tbody id="layananBody" data-pilih="<?= (int) input_get('layanan') ?>">
<?php foreach ($daftar_layanan as $l): ?>
                <tr data-id="<?= (int) $l['id'] ?>" data-nama="<?= e($l['nama']) ?>" data-tarif="<?= (int) $l['tarif_per_kg'] ?>" data-hari="<?= (int) $l['durasi_hari'] ?>" data-tanpa_cuci="<?= (int) $l['tanpa_cuci'] ?>" data-status="<?= (int) $l['aktif'] ?>"><td class="c-nama"><?= e($l['nama']) ?></td><td class="c-tarif"><?= e(rupiah($l['tarif_per_kg'])) ?></td><td class="c-hari"><?= (int) $l['durasi_hari'] ?> hari</td><td class="c-status"><?= badge_aktif($l['aktif']) ?></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="tambahLayanan"><i class="bi bi-plus-lg me-1"></i>Tambah layanan</button>
          <div class="note-box mt-3 mb-0">Cuci reguler dan Cuci ekspres mencakup cuci, kering, dan setrika. Setrika saja melewati tahap Dicuci dan Dikeringkan. Perubahan tarif hanya berlaku untuk pesanan baru; tarif pesanan lama tetap.</div>
        </div></div>
      </div>
      <div class="col-xl-5">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Ubah layanan</h2>
          <p class="section-sub" id="layananLabel">Layanan baru</p>
          <form id="layananForm" class="needs-validation" method="post" action="pengaturan.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="layanan">
            <input type="hidden" name="id" value="">
            <div class="mb-3">
              <label for="lNama" class="form-label">Nama layanan *</label>
              <input type="text" class="form-control" id="lNama" name="nama" maxlength="100" required>
              <div class="invalid-feedback">Nama layanan wajib diisi.</div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label for="lTarif" class="form-label">Tarif per kg (Rp) *</label>
                <input type="number" class="form-control" id="lTarif" name="tarif" min="500" max="1000000" step="500" required>
                <div class="invalid-feedback">Isi tarif minimal Rp500, kelipatan Rp500.</div>
              </div>
              <div class="col-sm-6">
                <label for="lHari" class="form-label">Durasi (hari) *</label>
                <input type="number" class="form-control" id="lHari" name="hari" min="1" max="30" required>
                <div class="invalid-feedback">Durasi 1&ndash;30 hari.</div>
              </div>
            </div>
            <div class="mb-3">
              <label for="lStatus" class="form-label">Status layanan</label>
              <select class="form-select" id="lStatus" name="status"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="lTanpaCuci" name="tanpa_cuci" value="1">
              <label class="form-check-label" for="lTanpaCuci">Tanpa cuci (melewati Dicuci dan Dikeringkan)</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Simpan layanan</button>
          </form>
        </div></div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-xl-7">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Akun petugas</h2>
          <p class="section-sub">Kelola akun operasional. Akun Pemilik tidak dibuat dari formulir ini.</p>
          <div class="table-responsive">
            <table class="table table-hover align-middle app-table">
              <thead><tr><th>Nama</th><th>Pengguna</th><th>Peran</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody id="akunBody" data-pilih="<?= (int) input_get('akun') ?>">
<?php foreach ($daftar_akun as $a): ?>
                <tr data-id="<?= (int) $a['id'] ?>" data-nama="<?= e($a['nama']) ?>" data-pengguna="<?= e($a['username']) ?>" data-status="<?= (int) $a['aktif'] ?>"><td class="c-nama"><?= e($a['nama']) ?></td><td class="c-pengguna"><?= e($a['username']) ?></td><td>Petugas Laundry</td><td class="c-status"><?= badge_aktif($a['aktif']) ?></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
<?php endforeach; ?>
<?php if (!$daftar_akun): ?>
                <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada akun petugas</td></tr>
<?php endif; ?>
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" id="tambahAkun"><i class="bi bi-plus-lg me-1"></i>Tambah petugas</button>
          <div class="note-box mt-3 mb-0">Akun nonaktif tidak dapat masuk, tetapi riwayat pesanan tetap tersimpan. Petugas tidak memiliki akses ke laporan usaha, perubahan tarif, atau pengelolaan akun.</div>
        </div></div>
      </div>
      <div class="col-xl-5">
        <div class="card section-card h-100"><div class="card-body">
          <h2 class="section-title">Ubah akun petugas</h2>
          <p class="section-sub" id="akunLabel">Akun baru</p>
          <form id="akunForm" class="needs-validation" method="post" action="pengaturan.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="akun">
            <input type="hidden" name="id" value="">
            <div class="mb-3">
              <label for="aNama" class="form-label">Nama lengkap *</label>
              <input type="text" class="form-control" id="aNama" name="nama" maxlength="100" required>
              <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label for="aPengguna" class="form-label">Nama pengguna *</label>
                <input type="text" class="form-control" id="aPengguna" name="pengguna" autocomplete="off" pattern="[A-Za-z0-9_.]{3,50}" required>
                <div class="invalid-feedback">3&ndash;50 huruf/angka, tanpa spasi.</div>
              </div>
              <div class="col-sm-6">
                <label for="aPeran" class="form-label">Peran</label>
                <input type="text" class="form-control" id="aPeran" value="Petugas Laundry" readonly>
              </div>
            </div>
            <div class="mb-3">
              <label for="aStatus" class="form-label">Status akun</label>
              <select class="form-select" id="aStatus" name="status"><option value="1">Aktif</option><option value="0">Nonaktif</option></select>
            </div>
            <div class="mb-3">
              <label for="aPassword" class="form-label">Kata sandi baru</label>
              <input type="password" class="form-control" id="aPassword" name="password" autocomplete="new-password" placeholder="Isi untuk mengubah">
              <div class="form-text">Kosongkan jika tidak diubah. Minimal 8 karakter. Wajib untuk akun baru.</div>
              <div class="invalid-feedback" id="aPasswordFb">Kata sandi minimal 8 karakter.</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Simpan akun</button>
          </form>
        </div></div>
      </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
