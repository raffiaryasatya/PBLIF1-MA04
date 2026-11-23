<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengaturan | War Laundry</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link href="style.css" rel="stylesheet">
</head>
<body data-page="pengaturan">

<header class="topbar no-print">
  <div class="d-flex align-items-center w-100 px-3 px-lg-4">
    <button class="btn btn-outline-secondary btn-sm d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <a class="brand" href="dashboard-pemilik.html"><span class="brand-mark"><i class="bi bi-droplet-half"></i></span><span class="brand-text"><strong>War</strong> Laundry<small class="d-none d-md-block">Aplikasi Pengelolaan Laundry Kiloan Sederhana</small></span></a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <div class="text-end lh-sm d-none d-sm-block"><div class="user-name">Raffi</div><div class="user-role">Pemilik</div></div>
      <a href="login.html" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Keluar</a>
    </div>
  </div>
</header>
<div class="app-shell">
  <aside class="offcanvas-lg offcanvas-start sidebar no-print" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="sidebarLabel">Menu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body d-block">
      <div class="side-label">PEMILIK</div>
      <nav class="side-nav">
<a class="side-link" href="dashboard-pemilik.html"><i class="bi bi-speedometer2"></i>Dashboard</a>
<a class="side-link" href="laporan.html"><i class="bi bi-bar-chart-line"></i>Laporan</a>
<a class="side-link active" href="pengaturan.html" aria-current="page"><i class="bi bi-gear"></i>Pengaturan</a>
      </nav>
      </aside>
  <main class="content">

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
          <p class="section-sub">Tarif berlaku per kilogram; semua layanan pada contoh ini aktif.</p>
          <div class="table-responsive">
            <table class="table table-hover align-middle app-table">
              <thead><tr><th>Layanan</th><th>Tarif / kg</th><th>Durasi</th><th>Status</th><th>Aksi</th></tr></thead>
              <tbody id="layananBody">
                <tr data-nama="Cuci reguler" data-tarif="8000" data-hari="2" data-status="Aktif"><td class="c-nama">Cuci reguler</td><td class="c-tarif">Rp8.000</td><td class="c-hari">2 hari</td><td class="c-status"><span class="st pay-lunas">Aktif</span></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
                <tr data-nama="Cuci ekspres" data-tarif="12000" data-hari="1" data-status="Aktif"><td class="c-nama">Cuci ekspres</td><td class="c-tarif">Rp12.000</td><td class="c-hari">1 hari</td><td class="c-status"><span class="st pay-lunas">Aktif</span></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
                <tr data-nama="Setrika saja" data-tarif="5000" data-hari="1" data-status="Aktif"><td class="c-nama">Setrika saja</td><td class="c-tarif">Rp5.000</td><td class="c-hari">1 hari</td><td class="c-status"><span class="st pay-lunas">Aktif</span></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
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
          <p class="section-sub" id="layananLabel">Layanan dipilih: Cuci reguler</p>
          <form id="layananForm" class="needs-validation" novalidate>
            <div class="mb-3">
              <label for="lNama" class="form-label">Nama layanan *</label>
              <input type="text" class="form-control" id="lNama" name="nama" required>
              <div class="invalid-feedback">Nama layanan wajib diisi.</div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label for="lTarif" class="form-label">Tarif per kg (Rp) *</label>
                <input type="number" class="form-control" id="lTarif" name="tarif" min="500" step="500" required>
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
              <select class="form-select" id="lStatus" name="status"><option>Aktif</option><option>Nonaktif</option></select>
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
              <tbody id="akunBody">
                <tr data-nama="Nadia Putri" data-pengguna="nadia" data-status="Aktif"><td class="c-nama">Nadia Putri</td><td class="c-pengguna">nadia</td><td>Petugas Laundry</td><td class="c-status"><span class="st pay-lunas">Aktif</span></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
                <tr data-nama="Rudi Saputra" data-pengguna="rudi" data-status="Aktif"><td class="c-nama">Rudi Saputra</td><td class="c-pengguna">rudi</td><td>Petugas Laundry</td><td class="c-status"><span class="st pay-lunas">Aktif</span></td><td><a href="#" class="link-action" data-ubah>Ubah</a></td></tr>
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
          <p class="section-sub" id="akunLabel">Akun dipilih: nadia</p>
          <form id="akunForm" class="needs-validation" novalidate>
            <div class="mb-3">
              <label for="aNama" class="form-label">Nama lengkap *</label>
              <input type="text" class="form-control" id="aNama" name="nama" required>
              <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-sm-6">
                <label for="aPengguna" class="form-label">Nama pengguna *</label>
                <input type="text" class="form-control" id="aPengguna" name="pengguna" autocomplete="off" required>
                <div class="invalid-feedback">Nama pengguna wajib diisi.</div>
              </div>
              <div class="col-sm-6">
                <label for="aPeran" class="form-label">Peran</label>
                <input type="text" class="form-control" id="aPeran" value="Petugas Laundry" readonly>
              </div>
            </div>
            <div class="mb-3">
              <label for="aStatus" class="form-label">Status akun</label>
              <select class="form-select" id="aStatus" name="status"><option>Aktif</option><option>Nonaktif</option></select>
            </div>
            <div class="mb-3">
              <label for="aPassword" class="form-label">Kata sandi baru</label>
              <input type="password" class="form-control" id="aPassword" name="password" autocomplete="new-password" placeholder="Isi untuk mengubah">
              <div class="form-text">Kosongkan jika tidak diubah. Minimal 8 karakter.</div>
              <div class="invalid-feedback" id="aPasswordFb">Kata sandi minimal 8 karakter.</div>
            </div>
            <button type="submit" class="btn btn-primary w-100">Simpan akun</button>
          </form>
        </div></div>
      </div>
    </div>
  </main>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3 no-print" id="toastWrap"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="app.js"></script>
</body>
</html>
