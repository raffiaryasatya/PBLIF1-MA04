<?php
// Fungsi bantu yang dipakai di banyak halaman

const NAMA_BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
    'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

// Urutan status proses laundry => label yang ditampilkan
const DAFTAR_STATUS = [
    'diterima'     => 'Diterima',
    'dicuci'       => 'Dicuci',
    'dikeringkan'  => 'Dikeringkan',
    'disetrika'    => 'Disetrika',
    'siap_diambil' => 'Siap diambil',
    'selesai'      => 'Selesai',
];

// Status yang dilewati oleh layanan tanpa cuci (Setrika saja)
const STATUS_TANPA_CUCI = ['dicuci', 'dikeringkan'];

/** Escape teks sebelum ditampilkan di HTML (mencegah XSS). */
function e($teks): string
{
    return htmlspecialchars((string) $teks, ENT_QUOTES, 'UTF-8');
}

/** Ambil teks dari $_GET (selalu string, sudah di-trim). */
function input_get(string $kunci): string
{
    $nilai = $_GET[$kunci] ?? '';
    return is_string($nilai) ? trim($nilai) : '';
}

/** Ambil teks dari $_POST (selalu string, sudah di-trim). */
function input_post(string $kunci): string
{
    $nilai = $_POST[$kunci] ?? '';
    return is_string($nilai) ? trim($nilai) : '';
}

/** 32000 => "Rp32.000" */
function rupiah($angka): string
{
    return 'Rp' . number_format((float) $angka, 0, ',', '.');
}

/** 4.50 => "4,5"   4.00 => "4" */
function format_kg($berat): string
{
    $teks = number_format((float) $berat, 2, ',', '.');
    return rtrim(rtrim($teks, '0'), ',');
}

/**
 * Format tanggal Indonesia.
 * tanggal_indo('2026-10-19 11:00:00')             => "19 Oktober 2026"
 * tanggal_indo('2026-10-19 11:00:00', true)       => "19 Oktober 2026, 11.00"
 * tanggal_indo('2026-10-19', false, true)         => "Senin, 19 Oktober 2026"
 */
function tanggal_indo($tanggal, bool $pakaiJam = false, bool $pakaiHari = false): string
{
    if (!$tanggal) {
        return '–';
    }
    $t = strtotime($tanggal);
    $hasil = date('j', $t) . ' ' . NAMA_BULAN[(int) date('n', $t)] . ' ' . date('Y', $t);
    if ($pakaiHari) {
        $hasil = NAMA_HARI[(int) date('w', $t)] . ', ' . $hasil;
    }
    if ($pakaiJam) {
        $hasil .= ', ' . date('H.i', $t);
    }
    return $hasil;
}

/** "2026-10-19 10:00:00" => "19 Okt, 10.00" (versi pendek) */
function tanggal_pendek($tanggal): string
{
    $t = strtotime($tanggal);
    return date('j', $t) . ' ' . substr(NAMA_BULAN[(int) date('n', $t)], 0, 3) . ', ' . date('H.i', $t);
}

/** Cek apakah teks adalah tanggal valid berformat YYYY-MM-DD. */
function tanggal_valid($teks): bool
{
    $d = DateTime::createFromFormat('Y-m-d', (string) $teks);
    return $d !== false && $d->format('Y-m-d') === $teks;
}

/** Label status, contoh: siap_diambil => "Siap diambil" */
function label_status(string $status): string
{
    return DAFTAR_STATUS[$status] ?? $status;
}

/** Badge status proses (class sesuai style.css). */
function badge_status(string $status): string
{
    $kelas = $status === 'siap_diambil' ? 'st-siap' : 'st-' . $status;
    return '<span class="st ' . $kelas . '">' . e(label_status($status)) . '</span>';
}

/** Badge pembayaran lunas / belum lunas. */
function badge_bayar($total, $dibayar): string
{
    return $dibayar >= $total
        ? '<span class="st pay-lunas">Lunas</span>'
        : '<span class="st pay-belum">Belum lunas</span>';
}

/** Badge aktif / nonaktif (dipakai di halaman pengaturan). */
function badge_aktif($aktif): string
{
    return $aktif
        ? '<span class="st pay-lunas">Aktif</span>'
        : '<span class="st pay-belum">Nonaktif</span>';
}

/** Daftar status yang dilalui sebuah layanan, sesuai urutan. */
function alur_status(bool $tanpaCuci): array
{
    $alur = array_keys(DAFTAR_STATUS);
    if ($tanpaCuci) {
        $alur = array_values(array_diff($alur, STATUS_TANPA_CUCI));
    }
    return $alur;
}

/**
 * Jumlah pesanan per status untuk kartu "alur pekerjaan" di dashboard.
 * Status aktif dihitung semuanya; status Selesai hanya yang selesai hari ini.
 */
function jumlah_per_status(string $hariIni): array
{
    $jumlah = array_fill_keys(array_keys(DAFTAR_STATUS), 0);

    $rows = db()->query("SELECT status, COUNT(*) AS n FROM pesanan WHERE status <> 'selesai' GROUP BY status")->fetchAll();
    foreach ($rows as $r) {
        $jumlah[$r['status']] = (int) $r['n'];
    }

    $stmt = db()->prepare("SELECT COUNT(*) FROM pesanan WHERE status = 'selesai' AND DATE(selesai_at) = ?");
    $stmt->execute([$hariIni]);
    $jumlah['selesai'] = (int) $stmt->fetchColumn();

    return $jumlah;
}

/** Kode pesanan berikutnya: LD-026 => LD-027 */
function kode_berikutnya(): string
{
    $terakhir = (int) db()->query('SELECT COALESCE(MAX(CAST(SUBSTRING(kode, 4) AS UNSIGNED)), 0) FROM pesanan')->fetchColumn();
    return 'LD-' . str_pad((string) ($terakhir + 1), 3, '0', STR_PAD_LEFT);
}

// Urutan kotak di grid 2 kolom agar tampil seperti desain
// (kolom kiri: Diterima, Dicuci, Dikeringkan; kolom kanan: Disetrika, Siap diambil, Selesai)
const URUTAN_GRID_STATUS = ['diterima', 'disetrika', 'dicuci', 'siap_diambil', 'dikeringkan', 'selesai'];

/** Pindah halaman lalu hentikan skrip. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/* ---------------------------------------------------------------- Flash */

/** Simpan pesan untuk ditampilkan sekali di halaman berikutnya (toast). */
function set_flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'][] = ['tipe' => $tipe, 'pesan' => $pesan];   // tipe: success | danger
}

/** Ambil semua pesan flash lalu hapus dari session. */
function ambil_flash(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

/* ------------------------------------------------- Input lama (old input) */

/** Simpan isian form agar tidak hilang saat validasi server gagal. */
function simpan_old(array $data): void
{
    $_SESSION['old'] = $data;
}

/** Ambil isian lama untuk satu field. */
function old(string $kunci, $bawaan = '')
{
    return $_SESSION['old'][$kunci] ?? $bawaan;
}

function hapus_old(): void
{
    unset($_SESSION['old']);
}

/* ----------------------------------------------------------------- CSRF */

/** Token CSRF unik per session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Input tersembunyi berisi token, ditaruh di setiap form POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Tolak request POST yang tokennya tidak cocok. */
function cek_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Sesi formulir sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}
