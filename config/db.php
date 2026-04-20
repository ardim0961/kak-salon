<?php
// File: salon_app/config/db.php

// Koneksi database
$host   = "localhost";
$user   = "root";
$pass   = "";
$dbname = "db_salon";

// Buat koneksi
$conn = mysqli_connect($host, $user, $pass, $dbname);

// Cek koneksi
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Set charset
mysqli_set_charset($conn, "utf8mb4");

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include constants
require_once __DIR__ . "/constants.php";

// Helper function untuk query
function db_query($sql) {
    global $conn;
    return mysqli_query($conn, $sql);
}

function db_fetch_assoc($result) {
    return mysqli_fetch_assoc($result);
}

function db_num_rows($result) {
    return mysqli_num_rows($result);
}

function db_escape_string($string) {
    global $conn;
    return mysqli_real_escape_string($conn, $string);
}

function db_insert_id() {
    global $conn;
    return mysqli_insert_id($conn);
}

function db_affected_rows() {
    global $conn;
    return mysqli_affected_rows($conn);
}

function db_error() {
    global $conn;
    return mysqli_error($conn);
}
?>