<?php
// single entry point for html pages
// logged-in users get the LABO application, everyone else the login page
session_start();

require_once __DIR__ . '/inc/basis_functions.php';

if (isset($_GET['page']) && $_GET['page'] === 'deconnexion') {
	$_SESSION = array();
	session_destroy();
	header('Location: ' . labo_url());
	exit;
}

if (labo_is_connected()) {
	require __DIR__ . '/modules/labo_page.php';
} else {
	require __DIR__ . '/account/account_page.php';
}
