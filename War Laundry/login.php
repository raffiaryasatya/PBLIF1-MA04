<?php
// Halaman login: cek nama pengguna, kata sandi, dan peran yang dipilih.
require_once __DIR__ . '/includes/auth.php';

// Sudah login? langsung ke dashboard.
if (user_login()) {
    redirect(dashboard_url(user_login()['peran']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();

    $username = input_post('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';   // kata sandi tidak di-trim
    $peran    = input_post('role');
    simpan_old(['username' => $username, 'role' => $peran]);

    if ($username === '' || $password === '' || !isset(LABEL_PERAN[$peran])) {
        set_flash('danger', 'Nama pengguna, kata sandi, dan peran wajib diisi.');
        redirect('login.php');
    }

    $stmt = db()->prepare('SELECT id, nama, username, password, peran, aktif FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $akun = $stmt->fetch();

    // password_verify membandingkan kata sandi dengan hash di database
    if (!$akun || !password_verify($password, $akun['password'])) {
        set_flash('danger', 'Nama pengguna atau kata sandi salah.');
        redirect('login.php');
    }
    if (!$akun['aktif']) {
        set_flash('danger', 'Akun Anda nonaktif. Hubungi pemilik laundry.');
        redirect('login.php');
    }
    if ($akun['peran'] !== $peran) {
        set_flash('danger', 'Akun ini tidak terdaftar sebagai ' . LABEL_PERAN[$peran] . '.');
        redirect('login.php');
    }

    // Login berhasil: ganti ID session (mencegah session fixation)
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $akun['id'],
        'nama'     => $akun['nama'],
        'username' => $akun['username'],
        'peran'    => $akun['peran'],
    ];
    hapus_old();
    set_flash('success', 'Selamat datang, ' . $akun['nama'] . '.');
    redirect(dashboard_url($akun['peran']));
}

$peran_lama = old('role', 'petugas');
$judul   = 'Masuk';
$halaman = 'login';
$user    = null;
require __DIR__ . '/includes/header.php';
?>
<main class="login-wrap">
  <div class="container">
    <div class="row align-items-center g-5">
      <section class="col-lg-6">
        <div class="eyebrow">Selamat datang di War Laundry</div>
        <h1 class="login-title">Aplikasi Pengelolaan Laundry Kiloan</h1>
        <p class="login-lead">Kelola pesanan harian dan pantau usaha dalam satu aplikasi.</p>
        <div class="role-info">
          <h2><i class="bi bi-person-badge"></i> Petugas Laundry</h2>
          <p>Mencatat pesanan, memperbarui proses laundry, menerima pembayaran, dan mencetak nota.</p>
        </div>
        <div class="role-info">
          <h2><i class="bi bi-shop"></i> Pemilik</h2>
          <p>Memantau pendapatan dan pesanan, melihat laporan, serta mengelola layanan, tarif, dan akun petugas.</p>
        </div>
      </section>

      <section class="col-lg-6">
        <div class="card login-card">
          <div class="card-body p-4 p-md-5">
            <h2 class="h3 mb-1">Masuk ke aplikasi</h2>
            <p class="text-secondary mb-4">Silahkan masuk sesuai dengan akun.</p>

            <form id="loginForm" class="needs-validation" method="post" action="login.php" novalidate>
              <?= csrf_field() ?>
              <label class="form-label fw-semibold d-block">Masuk sebagai</label>
              <div class="row g-2 mb-3" role="radiogroup" aria-label="Pilih peran">
                <div class="col-6">
                  <input type="radio" class="btn-check" name="role" id="role-petugas" value="petugas" autocomplete="off" <?= $peran_lama !== 'pemilik' ? 'checked' : '' ?>>
                  <label class="btn role-btn w-100" for="role-petugas"><i class="bi bi-person-badge me-1"></i>Petugas Laundry</label>
                </div>
                <div class="col-6">
                  <input type="radio" class="btn-check" name="role" id="role-pemilik" value="pemilik" autocomplete="off" <?= $peran_lama === 'pemilik' ? 'checked' : '' ?>>
                  <label class="btn role-btn w-100" for="role-pemilik"><i class="bi bi-shop me-1"></i>Pemilik</label>
                </div>
              </div>

              <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Nama pengguna</label>
                <input type="text" class="form-control form-control-lg" id="username" name="username" autocomplete="username" placeholder="Masukkan nama pengguna" value="<?= e(old('username')) ?>" required>
                <div class="invalid-feedback">Nama pengguna wajib diisi.</div>
              </div>

              <div class="mb-4">
                <label for="password" class="form-label fw-semibold">Kata sandi</label>
                <div class="input-group has-validation">
                  <input type="password" class="form-control form-control-lg" id="password" name="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                  <button class="btn btn-outline-secondary" type="button" id="togglePassword" aria-label="Tampilkan atau sembunyikan kata sandi"><i class="bi bi-eye" id="toggleIcon"></i></button>
                  <div class="invalid-feedback">Kata sandi wajib diisi.</div>
                </div>
              </div>

              <!-- Perbaikan: di HTML asli tombol ini berisi link dashboard_petugas.html -->
              <button type="submit" class="btn btn-primary btn-lg w-100" id="loginBtn"><span id="loginBtnLabel">Masuk sebagai Petugas Laundry</span></button>
            </form>

            <div class="note-box mt-4">
              <i class="bi bi-info-circle me-1"></i>
              Harap di isi sesuai dengan akun yang telah terdaftar. Jika belum memiliki akun, silakan hubungi pemilik laundry untuk mendapatkan akses.
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
