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
    var t = new bootstrap.Toast(el, { delay: 2600 });
    el.addEventListener('hidden.bs.toast', function () { el.remove(); });
    t.show();
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

  var pages = {};

  /* ------------------------------------------------------------ 01 Login */
  pages.login = function () {
    var form = $('loginForm');
    var label = $('loginBtnLabel');
    var names = { petugas: 'Petugas Laundry', pemilik: 'Pemilik' };
    var dest = { petugas: 'dashboard-petugas.html', pemilik: 'dashboard-pemilik.html' };

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
      e.preventDefault();
      if (!validate(form)) { return; }
      // TODO (backend): kirim nama pengguna, kata sandi, dan peran ke server,
      // lalu arahkan sesuai peran yang dikembalikan server.
      window.location.href = dest[role()];
    });
  };

  /* ------------------------------------------------------- 03 Pesanan baru */
  pages['pesanan-baru'] = function () {
    var form = $('orderForm');
    var SERVICES = {
      reguler: { nama: 'Cuci reguler', tarif: 8000, hari: 2, ket: 'Cuci, kering, dan setrika \u00b7 2 hari' },
      ekspres: { nama: 'Cuci ekspres', tarif: 12000, hari: 1, ket: 'Cuci, kering, dan setrika \u00b7 1 hari' },
      setrika: { nama: 'Setrika saja', tarif: 5000, hari: 1, ket: 'Setrika saja \u00b7 1 hari' }
    };
    var base = new Date(2026, 9, 19, 11, 0);   // waktu draf contoh: 19 Okt 2026, 11.00

    function two(n) { return String(n).padStart(2, '0'); }
    function fmtDate(d) {
      return d.getDate() + ' ' + BULAN[d.getMonth()] + ' ' + d.getFullYear() +
        ', ' + two(d.getHours()) + '.' + two(d.getMinutes());
    }

    function update() {
      var s = SERVICES[$('layanan').value];
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
      $('sumHitung').textContent = kg.toLocaleString('id-ID') + ' kg \u00d7 ' + rp(s.tarif);
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
      e.preventDefault();
      update();
      if (!validate(form)) { return; }
      // TODO (backend): kirim data form ke server untuk disimpan.
      toast('Pesanan LD-027 berhasil disimpan.');
      setTimeout(function () { window.location.href = 'daftar-pesanan.html'; }, 1200);
    });
  };

  /* ----------------------------------------------------- 04 Daftar pesanan */
  pages['daftar-pesanan'] = function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('#ordersBody tr[data-id]'));
    var chips = Array.prototype.slice.call(document.querySelectorAll('[data-status-chip]'));
    var status = 'semua';

    function apply() {
      var term = $('cari').value.trim().toLowerCase();
      var tgl = $('tanggal').value;
      var bayar = $('bayar').value;
      var n = 0, kg = 0, total = 0, sisa = 0;

      rows.forEach(function (r) {
        var ok = (status === 'semua' || r.dataset.status === status) &&
          (!bayar || r.dataset.bayar === bayar) &&
          (!tgl || r.dataset.tanggal === tgl) &&
          (!term || (r.dataset.id + ' ' + r.dataset.nama).toLowerCase().indexOf(term) !== -1);
        r.hidden = !ok;
        if (ok) {
          n++;
          kg += Number(r.dataset.kg);
          total += Number(r.dataset.total);
          sisa += Number(r.dataset.sisa);
        }
      });

      $('emptyRow').hidden = n !== 0;
      $('ringkasanList').textContent = n + ' pesanan \u00b7 ' + kg + ' kg \u00b7 Total tagihan ' +
        rp(total) + ' \u00b7 Sisa pembayaran ' + rp(sisa);
      $('hitungTampil').textContent = n === 0
        ? 'Menampilkan 0 pesanan'
        : 'Menampilkan 1\u2013' + n + ' dari ' + n + ' pesanan';
    }

    ['cari', 'tanggal', 'bayar'].forEach(function (id) {
      $(id).addEventListener('input', apply);
      $(id).addEventListener('change', apply);
    });
    chips.forEach(function (c) {
      c.addEventListener('click', function () {
        status = c.dataset.statusChip;
        chips.forEach(function (x) { x.classList.toggle('active', x === c); });
        apply();
      });
    });
    apply();
  };

  /* ------------------------------------------------------ 05 Detail pesanan */
  pages['detail-pesanan'] = function () {
    var STEPS = ['Diterima', 'Dicuci', 'Dikeringkan', 'Disetrika', 'Siap diambil', 'Selesai'];
    var CLS = ['st-diterima', 'st-dicuci', 'st-dikeringkan', 'st-disetrika', 'st-siap', 'st-selesai'];
    var current = 1;   // LD-025 sedang "Dicuci"
    var els = Array.prototype.slice.call(document.querySelectorAll('#stepper .step'));
    var sel = $('statusBerikut');
    var btn = $('simpanStatus');

    function render() {
      els.forEach(function (el, i) {
        el.classList.toggle('done', i < current);
        el.classList.toggle('current', i === current);
        el.classList.toggle('pending', i > current);
        el.querySelector('small').textContent =
          i === 0 ? '19 Okt, 10.00' : i < current ? 'Selesai' : i === current ? 'Sedang diproses' : 'Belum diproses';
        if (i === current) { el.setAttribute('aria-current', 'step'); } else { el.removeAttribute('aria-current'); }
      });

      var last = current >= STEPS.length - 1;
      $('statusSaatIni').textContent = 'Status saat ini: ' + STEPS[current] + '. ' +
        (last ? 'Pesanan sudah selesai.' : 'Lanjutkan ke tahap berikutnya setelah pekerjaan selesai.');

      sel.innerHTML = '';
      for (var i = current + 1; i < STEPS.length; i++) {
        var o = document.createElement('option');
        o.value = String(i);
        o.textContent = STEPS[i];
        sel.appendChild(o);
      }
      sel.disabled = last;
      btn.disabled = last;

      var badge = $('statusBadge');
      badge.className = 'st ' + CLS[current];
      badge.textContent = STEPS[current];
    }

    $('statusForm').addEventListener('submit', function (e) {
      e.preventDefault();
      if (sel.disabled || !sel.value) { return; }
      // TODO (backend): simpan perubahan status ke server.
      current = parseInt(sel.value, 10);
      render();
      toast('Status diperbarui menjadi ' + STEPS[current] + '.');
    });

    $('cetakNota').addEventListener('click', function () { window.print(); });
    render();
  };

  /* --------------------------------------------------------- 07 Laporan */
  pages.laporan = function () {
    var form = $('periodeForm');
    var mulai = $('tglMulai');
    var akhir = $('tglAkhir');
    var DATA_DATE = '2026-10-19';   // seluruh data contoh berada di tanggal ini

    function fmt(iso) {
      var p = iso.split('-').map(Number);
      return p[2] + ' ' + BULAN[p[1] - 1] + ' ' + p[0];
    }

    function apply() {
      akhir.setCustomValidity('');
      if (mulai.value && akhir.value && mulai.value > akhir.value) {
        $('akhirFb').textContent = 'Tanggal akhir tidak boleh sebelum tanggal mulai.';
        akhir.setCustomValidity('urutan');
      }
      if (!validate(form)) { return; }
      form.classList.remove('was-validated');
      var inRange = mulai.value <= DATA_DATE && akhir.value >= DATA_DATE;
      $('laporanData').hidden = !inRange;
      $('laporanKosong').hidden = inRange;
      $('periodeLabel').textContent = mulai.value === akhir.value
        ? fmt(mulai.value) : fmt(mulai.value) + ' \u2013 ' + fmt(akhir.value);
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
    $('resetPeriode').addEventListener('click', function () {
      mulai.value = DATA_DATE;
      akhir.value = DATA_DATE;
      form.classList.remove('was-validated');
      apply();
    });
    $('cetakLaporan').addEventListener('click', function () { window.print(); });
  };

  /* ------------------------------------------------------- 08 Pengaturan */
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
      o.fields.forEach(function (f) {
        form.elements[f].value = tr ? tr.dataset[f] : (o.defaults[f] || '');
      });
      if (o.onSelect) { o.onSelect(form); }
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
      e.preventDefault();
      if (o.extraCheck) { o.extraCheck(form, !editing); }
      if (!validate(form)) { return; }
      var data = {};
      o.fields.forEach(function (f) { data[f] = form.elements[f].value.trim(); });
      var tr = editing;
      if (!tr) {
        tr = document.createElement('tr');
        tr.innerHTML = o.rowHtml;
        tbody.appendChild(tr);
      }
      Object.keys(data).forEach(function (k) { tr.dataset[k] = data[k]; });
      o.render(tr, data);
      // TODO (backend): kirim perubahan ke server.
      select(tr);
      if (o.afterSave) { o.afterSave(form); }
      toast(o.okText);
    });

    select(tbody.querySelector('tr'));
  }

  function setStatus(tr, status) {
    var span = tr.querySelector('.c-status span');
    span.textContent = status;
    span.className = 'st ' + (status === 'Aktif' ? 'pay-lunas' : 'pay-belum');
  }

  pages.pengaturan = function () {
    editor({
      tbody: 'layananBody', form: 'layananForm', label: 'layananLabel', addBtn: 'tambahLayanan',
      fields: ['nama', 'tarif', 'hari', 'status'],
      defaults: { status: 'Aktif' },
      prefix: 'Layanan dipilih:', labelField: 'nama', newText: 'Layanan baru',
      okText: 'Layanan disimpan. Tarif baru berlaku untuk pesanan berikutnya.',
      rowHtml: '<td class="c-nama"></td><td class="c-tarif"></td><td class="c-hari"></td>' +
        '<td class="c-status"><span class="st"></span></td>' +
        '<td><a href="#" class="link-action" data-ubah>Ubah</a></td>',
      render: function (tr, d) {
        tr.querySelector('.c-nama').textContent = d.nama;
        tr.querySelector('.c-tarif').textContent = rp(Number(d.tarif));
        tr.querySelector('.c-hari').textContent = d.hari + ' hari';
        setStatus(tr, d.status);
      }
    });

    editor({
      tbody: 'akunBody', form: 'akunForm', label: 'akunLabel', addBtn: 'tambahAkun',
      fields: ['nama', 'pengguna', 'status'],
      defaults: { status: 'Aktif' },
      prefix: 'Akun dipilih:', labelField: 'pengguna', newText: 'Akun baru',
      okText: 'Akun petugas disimpan.',
      rowHtml: '<td class="c-nama"></td><td class="c-pengguna"></td><td>Petugas Laundry</td>' +
        '<td class="c-status"><span class="st"></span></td>' +
        '<td><a href="#" class="link-action" data-ubah>Ubah</a></td>',
      render: function (tr, d) {
        tr.querySelector('.c-nama').textContent = d.nama;
        tr.querySelector('.c-pengguna').textContent = d.pengguna;
        setStatus(tr, d.status);
      },
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
      },
      afterSave: function (form) { form.elements['password'].value = ''; }
    });

    // Hapus pesan kesalahan lama saat pengguna mengetik ulang kata sandi.
    $('aPassword').addEventListener('input', function () { this.setCustomValidity(''); });
  };

  if (pages[page]) { pages[page](); }
})();
