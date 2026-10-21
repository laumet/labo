<?php
function create_form_from_db ($content, $id_form){
	// creating an empty form don't need security controls...
	$form = '';
	$i = 1;
	foreach($content as $v) {
		$form .= '<div>';
		$form .= '<label for="' . $id_form . $v['column_name'] . '" >' . $v['label'] . '</label>';
		$form .= '<input id="' . $id_form . $v['column_name'] . '" name="' . $v['column_name'] . '" type="' . $v['input']['type'] . '" title="' . $v['input']['placeholder'] . '" placeholder="' . $v['input']['placeholder'] . '" ';
		if (isset($v['input']['attribute'])) {
			$form .= $v['input']['attribute'] . ' ';
		}
		$form .= '/>';
		$form .= '</div>';

		$to_save[] = $v['column_name'];
	}
	$_SESSION['to_save'] = $to_save;
	return $form;
}

function update_password($pw, $reset = false) {
	global $db;

	if (isset($_SESSION['user']) &&
		isset($_SESSION['user']['username']) &&
		strpos($_SESSION['user']['username'], '@') !== false
	) {
		$upw = $db->prepare('UPDATE users SET password = :password WHERE username = :username');
		if ($reset) {
			$upw = $db->prepare('UPDATE users SET password = :password, roles = "me", two_factors_authentication = 0 WHERE username = :username');
		}

		$upw->execute(array(
			'password' => password_hash($pw, PASSWORD_DEFAULT),
			'username' => $_SESSION['user']['username']
		));
		if ($upw) {
			return true;
		}
	}

	return false;
}

function connect_user($user)
{
	global $site_default;

	$_SESSION['user']['id'] = htmlspecialchars($user['id']);
	$_SESSION['user']['name'] = htmlspecialchars($user['name']);
	$_SESSION['user']['lastname'] = htmlspecialchars($user['lastname']);
	$_SESSION['user']['username'] = htmlspecialchars($user['username']);
	$_SESSION['roles'] = explode(',', $user['roles']);
	$_SESSION['roles']['max_level'] = $user['max_level'];

	// language from user settings; if not set, labo_lang() uses the browser language
	unset($_SESSION['lang']);
	if (isset($user['lang']) && in_array($user['lang'], LABO_LANGS)) {
		$_SESSION['lang'] = $user['lang'];
	}

	//var_dump($site_default);
	//var_dump($_SESSION['roles']);

	// check maintenance_mode
	if (isset($site_default) && isset($site_default['maintenance']) && isset($site_default['maintenance']['login']) && $site_default['maintenance']['login'] == 'on' && isset($site_default['maintenance']['login_for']) && $site_default['maintenance']['login_for'] != '' && $site_default['maintenance']['login_for'] != null) {
		$accepted_maintenance_roles = explode(',', $site_default['maintenance']['login_for']);
		$allowed = false;
		if ($_SESSION['roles'][0] == 'sa' || $_SESSION['roles'][0] == 'ad') {
			$allowed = true;
		} else {
			foreach($_SESSION['roles'] as $r) {
				if (in_array($r, $accepted_maintenance_roles)) {
					$allowed = true;
				}
			}
		}

		if (!$allowed) {
			session_start();
			session_destroy();
			// return ok to let js redirect to index page!
			exit('ok-site_in_maintenance');
		}
	}

	return;
}

function check_user_pw_strength ($pw)
{
	if (!preg_match(
	'/^(?=.*\d)(?=.*[a-zA-Z])(?=.*[A-Z])(?=.*[-\#\$\%\&\*])(?=.*[a-zA-Z]).{8,16}$/',
	$pw
	)) {
		return false;
	}
	return true;
}

function get_user()
{
	global $db;

	try {
		$d_user_in_db = $db->prepare('SELECT * FROM users WHERE username = :e');
		if (!$d_user_in_db->execute(array('e' => $_POST['username']))) {
			return false;
		};
	} catch (PDOException $e) {
		// 42S02: users table missing => LABO not installed, only the installation account can start (inc/first_setup.php)
		if ($e->getCode() === '42S02') {
			exit('ko-first_setup_required');
		}
		throw $e;
	}
	$user = $d_user_in_db->fetch();
	$d_user_in_db->closeCursor();
	if (!is_countable($user) || count($user) == 0 || $user['must_be_validated'] == 2) {
		return false;
	}

	return $user;
}

function send_auth_mail($type, $code, $username) {
	// store in session
	$code_text = $type == 'secret_code' ? 'le code' : 'un mot de passe temporaire ';
	$subject = $type == 'secret_code' ? 'Code de validation' : 'Mot de passe temporaire';
	// send mail to check
	return mail(
		$username,
		'RMR - ' . $subject,
		"Bonjour,\r\nVoici " . $code_text . " pour votre connexion au site du rallye mathématique romand : " . $code . "\r\nÀ bientôt.\r\n\r\nNote: Si vous n'êtes pas à l'origine de cette requête, vous pouvez simplement oublier cette information.
		"
	);
}

function generate_secret_code ()
{
	// define 'check-code'
	$secret_code = '';
	for ($i = 0; $i < 5 ; $i++) {
		$secret_code .= chr(rand(1, 26) + 64);
	}
	return $secret_code;
}

function generate_password ()
{
	$temp_pw_tab = ['u', 'u', 'l', 'l', 'n', 'n', 's', 's'];
	$spc_char = '-#$%&*';
	shuffle($temp_pw_tab);
	array_unshift($temp_pw_tab, 'u');
	array_push($temp_pw_tab, 'l');

	$temp_pw = '';
	foreach($temp_pw_tab as $v) {
		switch ($v) {
			case 'u':
				$temp_pw .= chr(rand(65, 90));
				break;
			case 'l':
				$temp_pw .= chr(rand(97, 122));
				break;
			case 'n' :
				$temp_pw .= chr(rand(48, 57));
				break;
			case 's' :
				$temp_pw .= substr($spc_char, rand(0, strlen($spc_char) - 1), 1);
				break;
		}
	}
	return $temp_pw;
}