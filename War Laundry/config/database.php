<?php
// Pengaturan koneksi database MySQL (XAMPP: user root tanpa password)

date_default_timezone_set('Asia/Jakarta');   // Batam memakai WIB

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'war_laundry_php');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Mengembalikan objek PDO. Koneksi hanya dibuat sekali per request
 * (disimpan di variabel static).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // error dilempar sebagai exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // hasil berupa array asosiatif
                PDO::ATTR_EMULATE_PREPARES   => false,                  // prepared statement asli MySQL
            ]);
            $pdo->exec("SET time_zone = '+07:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Gagal terhubung ke database. Pastikan MySQL di XAMPP sudah berjalan '
                . 'dan database war_laundry_php sudah diimpor.');
        }
    }

    return $pdo;
}
