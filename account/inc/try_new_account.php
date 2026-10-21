<?php
if (
	!isset($_SESSION['state']) ||
	$_SESSION['state'] == 'unconnected'
) {
	// first stage => user want to create an account
	// we need email to send e-mail validation
	if (
		!isset($_POST['username']) ||
		$_POST['username'] == null ||
		$_POST['username'] == ''
	) {
		exit('ko-username_or_pw_missing');
	}

	// send temp_password to user...
	// define 'check-code'
	$temp_password = generate_password();
	$_SESSION['temp_password'] = $temp_password;
	$_SESSION['state'] = 'waiting_auth_code';

	// send mail to check
	if (send_auth_mail('temp_password', $temp_password, $_POST['username'])) {
		exit('ok-secret_code_mail_sended');
	} else {
		session_destroy();
		exit('ko-mail_could_not_be_sent');
	}
} else {
	if (
		$_SESSION['state'] == 'waiting_auth_code' &&
		isset($_SESSION['temp_password']) &&
		isset($_POST['temp_password'])	
	) {
		// we need email to ensure security
		if (
			!isset($_POST['username']) ||
			$_POST['username'] == null ||
			$_POST['username'] == ''
		) {
			exit('ko-username_or_pw_missing');
		}

		// we chek if secret code is OK
		if ($_SESSION['temp_password'] != $_POST['temp_password']) {
			exit('ko-secret_code_not_valid');
		}

		// we can now unset temp_password
		unset($_SESSION['temp_password']);

		// now we need to check if user exists or not...
		if (!$user) {
			$_SESSION['state'] = 'user_can_be_created';
			exit('ok-user_can_be_created');
		} else {
			$_SESSION['state'] = 'user_already_exists';
			exit('ok-user_already_exits');
		}
	} else if (
		$_SESSION['state'] == 'user_can_be_created' &&
		isset($_POST) && 
		// to save is defined by function create_form_from_db
		isset($_SESSION['to_save']) &&
		is_countable($_SESSION['to_save']) &&
		count($_SESSION['to_save']) > 0
	) {
		// validation is OK !
		// check password quality
		if (
			isset($_POST['new_password_1']) &&
			isset($_POST['new_password_2'])
		) {
			if ($_POST['new_password_1'] != $_POST['new_password_2']) {
				exit('ko-pws_not_same');
			}
			if (!check_user_pw_strength($_POST['new_password_1'])) {
				exit('ko-pw_too_weak');
			}
		} else {
			exit('ko-incomplete_request');
		}

		// first opening (inc/first_setup.php): the first user is the super administrator
		$first_setup = !empty($_SESSION['first_setup']);

		// in next time we would have to use a get function for user saved...
		$_SESSION['user']['name'] = htmlspecialchars($_POST['name']);
		$_SESSION['user']['lastname'] = htmlspecialchars($_POST['lastname']);
		$_SESSION['user']['username'] = htmlspecialchars($_POST['username']);

		// define role
		$role = $first_setup ? 'sa' : 'me';
		$level = $first_setup ? 100 : 10;
		$_SESSION['roles'] = array($role, 'max_level' => $level);

		$request_elts = '';
		$values_elts = '';
		$values_tab = array(
			'password' => password_hash($_POST['new_password_1'], PASSWORD_DEFAULT),
			'must_be_validated' => 0,
			'role' => $role,
			'max_level' => $level,
			// two factors authentication off at first opening (see readme.txt)
			'two_factors_authentication' => $first_setup ? 0 : 1
		);
		foreach($_SESSION['to_save'] as $v) {

			if (isset($_POST[$v])) {
				$request_elts .= $v . ', ';
				$values_elts .= ':' . $v . ', ';
				$values_tab[$v] = htmlspecialchars($_POST[$v]);
			} else {
				exit('ko-incomplete_request');
			}
		}

		// account can be registred !
		try {
			if ($first_setup) {
				// data management mode chosen in default/labo_settings.json, fixed from now on
				$data_mode = labo_configured_data_mode();
				labo_create_basis_tables($data_mode);
				labo_log('audit', 'Basis tables created at first opening.', array('data_management_mode' => $data_mode));
			}

			$db->beginTransaction();
			$insert_new_user = $db->prepare('
				INSERT INTO users (' . $request_elts . 'password, must_be_validated, roles, max_level, two_factors_authentication)
				VALUES (' . $values_elts . ':password, :must_be_validated, :role, :max_level, :two_factors_authentication)
			');
			$success = $insert_new_user->execute($values_tab);
			$idNewUser = $db->lastInsertId();
			$db->commit();
		} catch (RuntimeException $e) {
			// data management mode invalid or different from an interrupted first opening
			labo_log('core', 'First opening stopped.', array('error' => $e->getMessage()));
			exit('ko-' . $e->getMessage());
		} catch (PDOException $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			labo_log('database', 'Account could not be created.', array(
				'error' => $e->getMessage()
			));
			exit('ko-writing_in_db_problem');
		}

		$user = get_user();
		connect_user($user);

		$_SESSION['user']['id'] = $idNewUser;
		unset($_SESSION['to_save']);

		if ($success) {
			if ($first_setup) {
				unset($_SESSION['first_setup']);
				labo_log('audit', 'First administrator created.', array('user_id' => $idNewUser));
			} else {
				mail($site_default['contact_mail_address'], "RMR - Nouvelle inscription", "Salut,\n\rUn nouvel utilisateur vient de s'enregistrer (" . $_SESSION['user']['name'] . ' ' . $_SESSION['user']['lastname'] . ").");
			}
			exit('ok-labo_open');
		} else {
			exit('ko-writing_in_db_problem');
		}
	} else if ($_SESSION['state'] == 'user_already_exists') {
		if (
			isset($_POST['new_password_1']) &&
			isset($_POST['new_password_2'])
		) {
			if ($_POST['new_password_1'] == $_POST['new_password_2']) {
				$password_check = check_user_pw_strength($_POST['new_password_1']);
				if (!$password_check) {
					exit('ko-pw_too_weak');
				}
				connect_user($user);
				
				if (update_password($_POST['new_password_1'], true)) {
					exit('ok-labo_open');
				} else {
					exit('ko-writing_in_db_problem');
				}
			}
		} else {
			exit('ko-incomplete_request');
		}

		// check pasword's quality
		$password_check = check_user_pw_strength();

		if ($password_check) {
		}

	} else {
		exit ('ko-unknow_action');
	}
}