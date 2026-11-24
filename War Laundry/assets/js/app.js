/* ==========================================================================
   War Laundry - JavaScript sisi browser
   Tugasnya hanya: validasi form, hitung ringkasan, dan menampilkan toast.
   Semua data disimpan & dihitung ulang oleh server (PHP), jadi form tetap
   dikirim ke server. Submit hanya dicegah jika validasi gagal.
   ========================================================================== */
(function () {
  'use strict';

  var page = document.body.dataset.page;
  var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
    'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  function $(id) { return document.getElementById(id); }
  function rp(n) { return 'Rp' + Math.round(n).toLocaleString('id-ID'); }

  function toast(msg, type) {
    var wrap = $('toastWrap');
    if (!wrap || !window.bootstrap) { return; }
    var el = document.createElement('div');
    el.className = 'toast align-items-center border-0 text-bg-' + (type || 'success');
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.setAttribute('aria-atomic', 'true');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>' +
      '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button></div>';
    el.querySelector('.toast-body').textContent = msg;   // textContent: aman dari injeksi HTML
    wrap.appendChild(el);
    var t = new bootstrap.Toast(el, { delay: type === 'danger' ? 5000 : 3000 });
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    t.show();
  }

  // Tampilkan pesan flash dari server (disimpan PHP di atribut data-flash)
  function showFlash() {
    var wrap = $('toastWrap');
    if (!wrap || !wrap.dataset.flash) { return; }
    try {
      JSON.parse(wrap.dataset.flash).forEach(function (f) { toast(f.pesan, f.tipe); });
    } catch (err) { /* abaikan data rusak */ }
  }

  function validate(form) {
    var ok = form.checkValidity();
    form.classList.add('was-validated');
    if (!ok) {
      var first = form.querySelector(':invalid');
      if (first) { first.focus(); }
    }
    return ok;
  }

  // Cegah tombol submit diklik dua kali (pesanan/pembayaran ganda)
  function lockSubmit(form) {
    var btn = form.querySelector('[type="submit"]');
    if (btn) { btn.disabled = true; }
  }

  var pages = {};

  /* ------------------------------------------------------------ Login */
  pages.login = function () {
    var form = $('loginForm');
    var label = $('loginBtnLabel');
    var names = { petugas: 'Petugas Laundry', pemilik: 'Pemilik' };

    function role() { return form.elements['role'].value; }
    function updateLabel() { label.textContent = 'Masuk sebagai ' + names[role()]; }

    form.querySelectorAll('input[name="role"]').forEach(function (r) {
      r.addEventListener('change', updateLabel);
    });
    updateLabel();

    $('togglePassword').addEventListener('click', function () {
      var pw = $('password');
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      $('toggleIcon').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    form.addEventListener('submit', function (e) {
      if (!validate(form)) { e.preventDefault(); return; }
      lockSubmit(form);   // lanjut kirim ke login.php
    });
  };

  /* ------------------------------------------------------- Pesanan baru */
  pages['pesanan-baru'] = function () {
    var form = $('orderForm');
    var base = new Date(Number(form.dataset.waktu) || Date.now());   // waktu server

    function two(n) { return String(n).padStart(2, '0'); }
    function fmtDate(d) {
      return d.getDate() + ' ' + BULAN[d.getMonth()] + ' ' + d.getFullYear() +
        ', ' + two(d.getHours()) + '.' + two(d.getMinutes());
    }

    // Data layanan dibaca dari atribut data-* pada <option> (diisi PHP dari database)
    function layanan() {
      var opt = $('layanan').selectedOptions[0];
      if (!opt) { return null; }
      return {
        nama: opt.textContent,
        tarif: Number(opt.dataset.tarif),
        hari: Number(opt.dataset.hari),
        ket: opt.dataset.ket
      };
    }

    function update() {
      var s = layanan();
      if (!s) { return; }
      var kg = parseFloat($('berat').value) || 0;
      var subtotal = Math.round(kg * s.tarif);
      var paid = parseInt($('nominal').value, 10) || 0;
      var metode = $('metode').value;

      // reset pesan validasi kustom
      $('nominal').setCustomValidity('');
      $('metode').setCustomValidity('');
      if (paid > subtotal) {
        $('nominalFb').textContent = 'Nominal melebihi total biaya (' + rp(subtotal) + ').';
        $('nominal').setCustomValidity('lebih');
      } else if (metode && paid === 0) {
        $('nominalFb').textContent = 'Isi nominal yang diterima.';
        $('nominal').setCustomValidity('kosong');
      } else if (paid > 0 && !metode) {
        $('metodeFb').textContent = 'Pilih Tunai atau Transfer.';
        $('metode').setCustomValidity('metode');
      }

      var d = new Date(base.getTime());
      d.setDate(d.getDate() + s.hari);

      $('tarif').value = rp(s.tarif);
      $('layananKet').textContent = s.ket;
      $('estimasi').value = fmtDate(d);
      $('statusBayar').value = (subtotal > 0 && paid >= subtotal) ? 'Lunas' : 'Belum lunas';

      $('sumLayanan').textContent = s.nama;
      $('sumHitung').textContent = kg.toLocaleString('id-ID') + ' kg × ' + rp(s.tarif);
      $('sumSubtotal').textContent = rp(subtotal);
      $('sumTotal').textContent = rp(subtotal);
      $('sumBayar').textContent = rp(paid);
      $('sumSisa').textContent = rp(Math.max(subtotal - paid, 0));
    }

    ['berat', 'layanan', 'nominal', 'metode'].forEach(function (id) {
      $(id).addEventListener('input', update);
      $(id).addEventListener('change', update);
    });
    update();

    form.addEventListener('submit', function (e) {
      update();
      if (!validate(form)) { e.preventDefault(); return; }
      lockSubmit(form);   // lanjut kirim ke pesanan-baru.php
    });
  };

  /* ----------------------------------------------------- Daftar pesanan */
  pages['daftar-pesanan'] = function () {
    var form = $('filterForm');

    // Filter dikirim ke server (GET) setiap kali nilainya berubah
    ['tanggal', 'bayar'].forEach(function (id) {
      $(id).addEventListener('change', function () { form.submit(); });
    });
    $('cari').addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); form.submit(); }
    });
    $('cari').addEventListener('search', function () {   // tombol "x" di kotak cari
      if (this.value === '') { form.submit(); }
    });

    // Chip status: isi input tersembunyi lalu kirim form
    document.querySelectorAll('[data-status-chip]').forEach(function (c) {
      c.addEventListener('click', function () {
        $('statusInput').value = c.dataset.statusChip;
        form.submit();
      });
    });
  };

  /* ------------------------------------------------------ Detail pesanan */
  pages['detail-pesanan'] = function () {
    $('cetakNota').addEventListener('click', function () { window.print(); });

    $('statusForm').addEventListener('submit', function (e) {
      var sel = $('statusBerikut');
      if (sel.disabled || !sel.value) { e.preventDefault(); return; }
      lockSubmit(this);
    });

    var bayar = $('bayarForm');
    if (bayar) {
      var nominal = $('bNominal');
      nominal.addEventListener('input', function () { nominal.setCustomValidity(''); });
      bayar.addEventListener('submit', function (e) {
        var sisa = Number(nominal.dataset.sisa);
        var n = Number(nominal.value);
        nominal.setCustomValidity('');
        if (n > sisa) {
          $('bNominalFb').textContent = 'Nominal melebihi sisa tagihan (' + rp(sisa) + ').';
          nominal.setCustomValidity('lebih');
        } else if (!(n >= 1)) {
          $('bNominalFb').textContent = 'Isi nominal minimal Rp1.';
          nominal.setCustomValidity('kosong');
        }
        if (!validate(bayar)) { e.preventDefault(); return; }
        lockSubmit(bayar);
      });
    }
  };

  /* --------------------------------------------------------- Laporan */
  pages.laporan = function () {
    var form = $('periodeForm');
    var mulai = $('tglMulai');
    var akhir = $('tglAkhir');

    form.addEventListener('submit', function (e) {
      akhir.setCustomValidity('');
      if (mulai.value && akhir.value && mulai.value > akhir.value) {
        $('akhirFb').textContent = 'Tanggal akhir tidak boleh sebelum tanggal mulai.';
        akhir.setCustomValidity('urutan');
      }
      if (!validate(form)) { e.preventDefault(); }
      // valid: form GET dikirim ke laporan.php
    });

    // Atur ulang = kembali ke periode hari ini
    $('resetPeriode').addEventListener('click', function () { window.location.href = 'laporan.php'; });
    $('cetakLaporan').addEventListener('click', function () { window.print(); });
  };

  /* ------------------------------------------------------- Pengaturan */
  // Klik "Ubah" mengisi form dari atribut data-* baris tabel; "Tambah" mengosongkan form.
  function editor(o) {
    var tbody = $(o.tbody);
    var form = $(o.form);
    var label = $(o.label);
    var editing = null;

    function select(tr) {
      editing = tr;
      Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (r) {
        r.classList.toggle('table-active', r === tr);
      });
      form.elements['id'].value = tr ? tr.dataset.id : '';
      o.fields.forEach(function (f) {
        form.elements[f].value = tr ? tr.dataset[f] : (o.defaults[f] || '');
      });
      if (o.onSelect) { o.onSelect(form, tr); }
      form.classList.remove('was-validated');
      label.textContent = tr ? o.prefix + ' ' + tr.dataset[o.labelField] : o.newText;
    }

    tbody.addEventListener('click', function (e) {
      var a = e.target.closest('[data-ubah]');
      if (!a) { return; }
      e.preventDefault();
      select(a.closest('tr'));
      form.elements[o.fields[0]].focus();
    });

    $(o.addBtn).addEventListener('click', function () {
      select(null);
      form.elements[o.fields[0]].focus();
    });

    form.addEventListener('submit', function (e) {
      if (o.extraCheck) { o.extraCheck(form, !editing); }
      if (!validate(form)) { e.preventDefault(); return; }
      lockSubmit(form);   // lanjut kirim ke pengaturan.php
    });

    // Pilih baris yang baru disimpan (data-pilih), atau baris pertama
    var pilih = tbody.dataset.pilih && tbody.querySelector('tr[data-id="' + tbody.dataset.pilih + '"]');
    select(pilih || tbody.querySelector('tr[data-id]'));
  }

  pages.pengaturan = function () {
    editor({
      tbody: 'layananBody', form: 'layananForm', label: 'layananLabel', addBtn: 'tambahLayanan',
      fields: ['nama', 'tarif', 'hari', 'status'],
      defaults: { status: '1' },
      prefix: 'Layanan dipilih:', labelField: 'nama', newText: 'Layanan baru',
      onSelect: function (form, tr) {
        form.elements['tanpa_cuci'].checked = !!tr && tr.dataset.tanpa_cuci === '1';
      }
    });

    editor({
      tbody: 'akunBody', form: 'akunForm', label: 'akunLabel', addBtn: 'tambahAkun',
      fields: ['nama', 'pengguna', 'status'],
      defaults: { status: '1' },
      prefix: 'Akun dipilih:', labelField: 'pengguna', newText: 'Akun baru',
      onSelect: function (form) { form.elements['password'].value = ''; },
      extraCheck: function (form, isNew) {
        var pw = form.elements['password'];
        pw.setCustomValidity('');
        if (isNew && !pw.value) {
          $('aPasswordFb').textContent = 'Kata sandi wajib diisi untuk akun baru.';
          pw.setCustomValidity('wajib');
        } else if (pw.value && pw.value.length < 8) {
          $('aPasswordFb').textContent = 'Kata sandi minimal 8 karakter.';
          pw.setCustomValidity('pendek');
        }
      }
    });

    // Hapus pesan kesalahan lama saat pengguna mengetik ulang kata sandi.
    $('aPassword').addEventListener('input', function () { this.setCustomValidity(''); });
  };

  showFlash();
  if (pages[page]) { pages[page](); }
})();
