<?php
// Bagian bawah halaman: menutup layout, wadah toast, dan script.
// Pesan flash dikirim ke app.js lewat atribut data-flash (JSON yang sudah di-escape).
$flash = ambil_flash();
?>
<?php if ($user): ?>
  </main>
</div>
<?php endif; ?>

<div class="toast-container position-fixed top-0 end-0 p-3 no-print" id="toastWrap" data-flash="<?= e(json_encode($flash)) ?>"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
<?php hapus_old(); // isian lama hanya dipakai sekali ?>
