<?php
// requests while the users table doesn't exist (included by account_actions.php)
// functions in inc/first_setup.php
// the data management mode must be valid before anything is created
if (labo_configured_data_mode() === null) {
	exit('ko-data_mode_invalid');
}

switch ($_POST['to_do']) {
	case 'connect' :
		if (
			isset($_POST['username']) && $_POST['username'] === LABO_FIRST_SETUP_USERNAME &&
			isset($_POST['password']) && is_string($_POST['password']) &&
			password_verify($_POST['password'], LABO_FIRST_SETUP_PASSWORD_HASH)
		) {
			session_regenerate_id(true);
			$_SESSION['first_setup'] = true;
			labo_log('security', 'First setup opened with the installation account.');
			exit('ok-first_setup');
		}
		exit('ko-first_setup_required');
	case 'get_user_form' :
		if (empty($_SESSION['first_setup'])) { exit('ko-first_setup_required'); }
		// no e-mail check: the installation account is already authenticated
		$_SESSION['state'] = 'user_can_be_created';
		exit(create_form_from_db(labo_first_setup_form(), 'user_form_'));
	case 'create_account' :
		if (empty($_SESSION['first_setup'])) { exit('ko-first_setup_required'); }
		// only the final step (no e-mail check at first opening): get_user_form must come first
		if (!isset($_SESSION['state']) || $_SESSION['state'] !== 'user_can_be_created') { exit('ko-incomplete_request'); }
		$user = false;
		include 'inc/try_new_account.php';
		break;
	default :
		exit('ko-first_setup_required');
}
