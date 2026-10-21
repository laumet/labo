<?php
// account actions (login, password recovery, registration)
// included by labo_swap.php for the actions listed in LABO_ACCOUNT_ACTIONS, never called directly
chdir(__DIR__);

// connect db
require_once __DIR__ . '/../modules/inc/connect_db.php';

// load basis functions
require_once __DIR__ . '/../inc/basis_functions.php';

// load account functions
require_once 'inc/account_functions.php';

// get basis content
include __DIR__ . '/../inc/site_default.php';

if (isset($_POST['to_do'])) {
	if ($_POST['to_do'] == 'disconnect') {
		session_destroy();
		header('Location: ' . labo_url());
	}
	
	require_once __DIR__ . '/../modules/inc/connect_db.php';

	// add plugins functions
	requireOncePlugins('account_functions.php');

	// check if site is in maintenance mode
	// get basis content
	include __DIR__ . '/../inc/site_default.php';

	$maintenance_mode = false;
	if (isset($site_default['maintenance']) && isset($site_default['maintenance']['maintenance_mode']) && $site_default['maintenance']['maintenance_mode'] == 'on') {
		$maintenance_mode = true;
		$not_allowed = array('create_account', 'forgotten_password', 'save_new_password', 'get_user_form');
		if (in_array($_POST['to_do'], $not_allowed)) {
			session_start();
			session_destroy();
			header('Location: ' . labo_url());
			exit();
		}
	}

	// first opening: no users table yet => only the installation account can create the first administrator
	// checked only for the installation account (or a first opening already started), not for every request
	require_once 'inc/first_setup.php';
	$first_setup_request = !empty($_SESSION['first_setup']) ||
		($_POST['to_do'] === 'connect' && isset($_POST['username']) && $_POST['username'] === LABO_FIRST_SETUP_USERNAME);
	if ($first_setup_request && !labo_users_table_exists()) {
		include 'inc/first_setup_actions.php';
		exit('ko-no_exiting_before');
	}

	// features switched off in default/features.json
	$feature_of_action = array(
		'create_account' => 'create_account',
		'get_user_form' => 'create_account',
		'forgotten_password' => 'forgotten_password',
		'save_new_password' => 'forgotten_password'
	);
	if (isset($feature_of_action[$_POST['to_do']]) && !labo_feature($feature_of_action[$_POST['to_do']])) {
		exit('ko-feature_disabled');
	}

	$user = false;
	switch ($_POST['to_do']) {
		case 'connect':
			$user = get_user();
			if (!$user || $user == null || !is_countable($user) || count($user) == 0) { exit ('ko-request_not_valid'); };
			include 'inc/try_connect.php';
			break;
		case 'create_account' :
			$user = get_user(); // user used in include
			include 'inc/try_new_account.php';
			break;
		case 'forgotten_password' :
		case 'save_new_password' :
			$user = get_user();
			// security requirement
			// if user don't exists => return 'mail sended' but don't do anything
			if (!$user && $_POST['to_do'] == 'forgotten_password') {
				exit ('ok-new_pw_mail_sended');
			}
			include 'inc/try_new_password.php';
			break;
		case 'get_user_form' :
			include 'inc/get_from_db.php';
			break;
		case 'disconnect' :
			session_destroy();
			header('Location: https://rallye-mathematique-romand.ch');
			break;
		default:
			exit('ko-unknow_action');
	}
} else {
	exit('ko-to_do_not_set');
}
exit('ko-no_exiting_before');