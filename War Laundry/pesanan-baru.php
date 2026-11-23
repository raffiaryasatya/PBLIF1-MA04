<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pesanan Baru | Laundry Kiloan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
<link href="style.css" rel="stylesheet">
</head>
<body data-page="pesanan-baru">

<header class="topbar no-print">
  <div class="d-flex align-items-center w-100 px-3 px-lg-4">
    <button class="btn btn-outline-secondary btn-sm d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Buka menu"><i class="bi bi-list"></i></button>
    <a class="brand" href="dashboard-petugas.html"><span class="brand-mark"><i class="bi bi-droplet-half"></i></span><span class="brand-text"><strong>War </strong> Laundry<small class="d-none d-md-block">Aplikasi Pengelolaan Laundry Kiloan Sederhana</small></span></a>
    <div class="ms-auto d-flex align-items-center gap-3">
      <div class="text-end lh-sm d-none d-sm-block"><div class="user-name">Cittra</div><div class="user-role">Petugas Laundry</div></div>
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
      <div class="side-label">PETUGAS LAUNDRY</div>
      <nav class="side-nav">
<a class="side-link" href="dashboard-petugas.html"><i class="bi bi-speedometer2"></i>Dashboard</a>
<a class="side-link active" href="pesanan-baru.html" aria-current="page"><i class="bi bi-plus-circle"></i>Pesanan baru</a>
<a class="side-link" href="daftar-pesanan.html"><i class="bi bi-list-check"></i>Daftar pesanan</a>
      </nav>
      </aside>
  <main class="content">

    <div class="page-head d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <h1 class="page-title">Pesanan Baru</h1>
        <p class="page-sub">Draf LD-027 / 19 Oktober 2026, 11.00 | Belum masuk ke ringkasan atau laporan.</p>
      </div>
      
    </div>
    <form id="orderForm" class="needs-validation" novalidate>
    <div class="row g-4">
      <div class="col-xl-8">

        <div class="card section-card mb-4"><div class="card-body">
          <h2 class="section-title">Data pelanggan</h2>
          <p class="section-sub">Isi data pelanggan yang menyerahkan cucian. Tanda * wajib diisi.</p>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="nama" class="form-label">Nama pelanggan *</label>
              <input type="text" class="form-control" id="nama" name="nama" placeholder="Contoh: Dian Puspita" required>
              <div class="invalid-feedback">Nama pelanggan wajib diisi.</div>
            </div>
            <div class="col-md-6">
              <label for="telepon" class="form-label">Nomor telepon *</label>
              <input type="tel" class="form-control" id="telepon" name="telepon" placeholder="Contoh: 0812 3456 7890" pattern="[0-9+\-\s]{8,16}" required>
              <div class="invalid-feedback">Isi nomor telepon yang valid (8&ndash;16 digit).</div>
            </div>
            <div class="col-12">
              <label for="alamat" class="form-label">Alamat / catatan pelanggan *</label>
              <input type="text" class="form-control" id="alamat" name="alamat" placeholder="Contoh: Jl. Melati No. 12" required>
              <div class="invalid-feedback">Alamat wajib diisi.</div>
            </div>
          </div>
        </div></div>

        <div class="card section-card mb-4"><div class="card-body">
          <h2 class="section-title">Rincian laundry</h2>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="berat" class="form-label">Berat cucian (kg) *</label>
              <input type="number" class="form-control" id="berat" name="berat" min="1" step="0.01" value="4.5" required>
              <div class="form-text">Gunakan hasil timbang; minimal 1 kg.</div>
              <div class="invalid-feedback">Berat minimal 1 kg.</div>
            </div>
            <div class="col-md-6">
              <label for="layanan" class="form-label">Layanan *</label>
              <select class="form-select" id="layanan" name="layanan" required>
                <option value="reguler" selected>Cuci reguler</option>
                <option value="ekspres">Cuci ekspres</option>
                <option value="setrika">Setrika saja</option>
              </select>
              <div class="form-text" id="layananKet">Cuci, kering, dan setrika &middot; 2 hari</div>
            </div>
            <div class="col-md-6">
              <label for="tarif" class="form-label">Tarif per kg</label>
              <input type="text" class="form-control" id="tarif" value="Rp8.000" readonly>
              <div class="form-text">Tarif dikelola oleh Pemilik.</div>
            </div>
            <div class="col-md-6">
              <label for="estimasi" class="form-label">Estimasi selesai *</label>
              <input type="text" class="form-control" id="estimasi" value="21 Oktober 2026, 11.00" readonly>
            </div>
            <div class="col-12">
              <label for="catatan" class="form-label">Catatan cucian</label>
              <input type="text" class="form-control" id="catatan" name="catatan" placeholder="Contoh: Pisahkan pakaian putih.">
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
              <input type="number" class="form-control" id="nominal" name="nominal" min="0" step="1" value="0">
              <div class="invalid-feedback" id="nominalFb">Nominal tidak valid.</div>
            </div>
            <div class="col-md-6">
              <label for="metode" class="form-label">Metode pembayaran</label>
              <select class="form-select" id="metode" name="metode">
                <option value="" selected>Belum ada pembayaran</option>
                <option value="tunai">Tunai</option>
                <option value="transfer">Transfer</option>
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
            <li><span>Nomor pesanan</span><span class="fw-semibold">LD-027 (draf)</span></li>
            <li><span>Status awal</span><span><span class="st st-diterima">Diterima</span></span></li>
            <li><span>Layanan</span><span class="fw-semibold" id="sumLayanan">Cuci reguler</span></li>
            <li><span>Perhitungan</span><span id="sumHitung">4,5 kg &times; Rp8.000</span></li>
            <li><span>Subtotal</span><span id="sumSubtotal">Rp36.000</span></li>
            <li><span>Biaya tambahan</span><span>Rp0</span></li>
          </ul>
          <div class="total-row"><span>Total biaya</span><span id="sumTotal">Rp36.000</span></div>
          <ul class="list-unstyled kv-list mt-2 mb-0">
            <li><span>Sudah dibayar</span><span id="sumBayar">Rp0</span></li>
            <li><span>Sisa tagihan</span><span class="fw-semibold" id="sumSisa">Rp36.000</span></li>
          </ul>
        </div></div>
        <div class="note-box mb-3">Total dihitung dari berat &times; tarif. Setelah disimpan, pesanan berstatus Diterima dan dapat dicetak nota di detail pesanan.</div>
        <button type="submit" class="btn btn-primary w-100 mb-2"><i class="bi bi-check2-circle me-1"></i>Simpan pesanan</button>
        <a href="dashboard-petugas.html" class="btn btn-outline-secondary w-100">Batal</a>
      </div>
    </div>
    </form>
  </main>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3 no-print" id="toastWrap"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="app.js"></script>
</body>
</html>
