<?php
// single entry point for ajax requests (POST, the action is given by to_do)
// actions allowed without login are listed here, every other action requires a logged-in user
session_start();

require_once __DIR__ . '/inc/basis_functions.php';

if (!isset($_POST['to_do']) || !is_string($_POST['to_do'])) {
	exit('ko-to_do_not_set');
}

// public actions: login, password recovery and registration
const LABO_ACCOUNT_ACTIONS = array('connect', 'forgotten_password', 'save_new_password', 'create_account', 'get_user_form', 'disconnect');

if (in_array($_POST['to_do'], LABO_ACCOUNT_ACTIONS, true)) {
	require __DIR__ . '/account/account_actions.php';
} else if ($_POST['to_do'] === 'set_lang') {
	require __DIR__ . '/inc/set_lang.php';
} else {
	// login checked in modules/labo_actions.php, unknown actions refused there
	require __DIR__ . '/modules/labo_actions.php';
}

exit('ko-no_exiting_before');
