<?php
// store username in session
if ($_POST['to_do'] == 'forgotten_password') 
{
	// generate new password
	$new_password = generate_password();

	// save it in session and send
	if (send_auth_mail('new_password', $new_password, $_POST['username'])) {
		$_SESSION['temp_password'] = $new_password;
		$_SESSION['state'] = 'waiting_auth_code';

		// exit and inform user
		exit('ok-new_pw_mail_sended');
	} else {
		exit('ko-mail_could_not_be_sent');
	}

} else if (!check_user_pw_strength($_POST['new_password_1'])) {
	exit ('ko-pw_too_weak');
} else if ($_POST['new_password_1'] != $_POST['new_password_2']) {
	exit ('ko-request_not_valid');
} else if ($_POST['temp_password'] != $_SESSION['temp_password']) {
	exit ('ko-request_not_valid');
} else if ($_POST['to_do'] == 'save_new_password') {
	// save new password and exit to labo.php
	$update_pw = $db->prepare('
		UPDATE users
		SET password = :pw
		WHERE id = :user_id
	');
	$update_pw->execute(array(
		'pw' => password_hash($_POST['new_password_1'], PASSWORD_DEFAULT),
		'user_id' => $user['id']
	));

	unset($_SESSION['temp_password']);

	connect_user($user);

	exit('ok-labo_open');
}