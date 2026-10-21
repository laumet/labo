<?php
// we need email and password
if (!isset($_POST['password']) ||
	$_POST['password'] == null ||
	$_POST['password'] == '' ||
	!isset($_POST['username']) ||
	$_POST['username'] == null ||
	$_POST['username'] == ''
) {
	exit('ko-username_or_pw_missing');
}

if (!$user || !password_verify($_POST['password'], $user['password'])) {
	exit('ko-connect_not_possible');
}

if ($user['two_factors_authentication'] === 1) {
	if (!isset($_POST['secret_code']) || $_POST['secret_code'] == null || $_POST['secret_code'] == '') {
		$sc = generate_secret_code();
		$_SESSION['secret_code'] = $sc;
		$_SESSION['state'] = 'waiting_auth_code';

		send_auth_mail('secret_code', $sc, $_POST['username']);
		
		exit('ok-secret_code_mail_sended');
	} else if (
		$user['two_factors_authentication'] !== 0 &&
		$_SESSION['state'] == 'waiting_auth_code' &&
		isset($_POST['secret_code']) &&
		$_POST['secret_code'] == $_SESSION['secret_code']
	) {
		connect_user($user);
		$_SESSION['state'] = 'connected';
		unset($_SESSION['secret_code']);

		exit('ok-labo_open');
	} else {
		exit('ko-connect_not_possible');
	}
} else {
	connect_user($user);
	$_SESSION['state'] = 'connected';
	exit('ok-labo_open');
}