<?php
// actions of the LABO application, all require a logged-in user
// included by labo_swap.php for every action not listed as public, never called directly
chdir(__DIR__);

/*
ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);
*/

require_once __DIR__ . '/../inc/basis_functions.php';

if (!labo_is_connected()) {
	exit('ko-not_logged');
}

require_once __DIR__ . '/inc/connect_db.php';

// load plugins index functions;
requireOncePlugins('swap_functions.php');

$authorisation = false; // is completed in check_hability.php

if (!isset($_POST['to_do'])) {
	exit('ko-to_do_not_set');
}

switch ($_POST['to_do']) {
	case 'get_content' :
	case 'get_empty_row':
	case 'get_row':
		include 'inc/get_content.php';
		break;
	case 'save_row' :
		include 'inc/save_row.php';
		break;
	case 'delete_row' :
		include 'inc/delete_row.php';
		break;
	case 'restore_row' :
		include 'inc/restore_row.php';
		break;
	case 'plugin_job' :
		if (!isset($_POST['plugin']) || !isset($_POST['plugin_job'])) {
			exit('ko-incomplete_request');
		}
		if (is_dir(__DIR__ . '/../plugins/' . $_POST['plugin']) && is_file(__DIR__ . '/../plugins/' . $_POST['plugin'] . '/plugin_from_labo_swap.php')) {
			include __DIR__ . '/../plugins/' . $_POST['plugin'] . '/plugin_from_labo_swap.php';
		} else {
			exit('ko-incomplete_request_or_file_not_found');
		}
		break;
	case 'get_file_galery_splash':
		include 'inc/file_galery.php';
		break;
	default : 
		exit ('ko-incomplete_request');
		break;
}

exit ('ko-no_exiting_before');