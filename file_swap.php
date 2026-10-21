<?php
// single entry point for files (downloads, uploads later), only for logged-in users
session_start();

require_once __DIR__ . '/inc/basis_functions.php';

if (!labo_is_connected()) {
	http_response_code(403);
	exit('ko-not_logged');
}

require __DIR__ . '/modules/file_download.php';
