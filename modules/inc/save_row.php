<?php
if (!isset($_POST['row_id']) ||
	$_POST['row_id'] == null ||
	$_POST['row_id'] == '' ||
	!isset($_POST['index']) ||
	$_POST['index'] == null ||
	$_POST['index'] == ''
) {
	exit ('ko-request_not_valid (save_row 1)');
}

include 'inc/check_hability.php';

require_once 'inc/connect_db.php';

include '../inc/site_default.php';

//require_once 'inc/constructor_swap_functions.php';

// get generator index to check save rights
$d_generator = $db->prepare('SELECT * FROM generator WHERE id = :index');
$d_generator->execute(array('index' => $_POST['index']));
$generator = $d_generator->fetch(PDO::FETCH_ASSOC);
$d_generator->closeCursor();

if (!$generator) {
	exit ('ko-request_not_valid (save_row 2)');
}

// if plugin, break the way and go to plugin file 'save.php'
if (strpos($generator['table_name'], '/plugins/') !== false) {
	include '../plugins/' . substr($generator['table_name'], 9) . '/save.php';
	exit();
}

if ($generator['can_edit'] != 1) {
	exit ('ko-not_authorised (save_row)');
}

// page_with_modules: rights of the user for this module (set by check_hability.php from module_permissions)
if (labo_page_mode()) {
	if (!isset($authorisation) || $authorisation['can_edit'] !== 1) {
		exit ('ko-not_authorised (save_row module)');
	}
	if (strpos($_POST['row_id'], 'new_row') !== false && $authorisation['can_add'] !== 1) {
		exit ('ko-not_authorised (save_row module add)');
	}
}

// "see as" is a preview: the row is saved with the rights and the identity of the logged-in user
if (isset($_POST['see_as']) && $_POST['see_as'] != $_SESSION['user']['id']) {
	labo_log('audit', 'Row saved during a see as preview.', array(
		'actor_user_id' => $_SESSION['user']['id'],
		'see_as_user_id' => is_scalar($_POST['see_as']) ? (string) $_POST['see_as'] : '',
		'generator_id' => $generator['id']
	));
}

if ($generator['display'] == null || $generator['display'] == '') {
	exit('ko-impossible_request');
}


// route the request between insert and update !
$update = false;
if (strpos($_POST['row_id'], 'new_row') === false) {
	// check now if row exists !
	$d_check_row = $db->prepare('SELECT id FROM ' . $generator['table_name'] . ' WHERE id = :id');
	$d_check_row->execute(array('id' => $_POST['row_id']));
	$check_row = $d_check_row->fetch();
	$d_check_row->closeCursor();
	if (!$check_row) {
		exit ('ko-request_not_valid (save_row 3)');
	}
	$update = true;
} 

// first, get all fields from table base
// in the future, we can create something to manage saving with updatable display with inner / outer joing condition
// at this time, all not table base fields are rejected
// to be done (a) update only table base fiels / b) update in two or more tables depending of the display request !!)

// save all inputs from display !!!
$inputs = json_decode($generator['display'], true);

// include if necessary file defined in generator
if ($generator['save_include'] != null &&
	$generator['save_include'] != '') {
	if (is_file($generator['save_include'])
	) {
		include $generator['save_include'];
	} else {
		exit('ko-save_include_file_not_found');
	}
}

if (is_countable($inputs) && count($inputs) > 0) {
	$db_request = $update ? 'UPDATE ' . $generator['table_name'] . ' SET ' : 'INSERT INTO ' . $generator['table_name'] . ' (';
	$db_fields_1 = '';
	$db_fields_2 = '';
	$db_values = [];

	// as some formulars can have to times the same column_name only take the first one...
	$inputs_setted = array();
	foreach($inputs as $i) {
		if (
				isset($i['column_name']) &&
				$i['column_name'] != 'none' &&
				!in_array($i['column_name'], $inputs_setted ) &&
				isset($_POST[$i['column_name']]) &&
				(
					!isset($i['attribute']) ||
					strpos($i['attribute'], 'disabled') === false
				) &&
				(
					!isset($i['input']['type']) ||
					(
						$i['input']['type'] != 'display' &&
						$i['input']['type'] != 'mailto' &&
						!isset($i['input']['attributes']['disabled'])
					)
				)
			) {

			array_push($inputs_setted, $i['column_name']);

			if (strlen($db_fields_1) > 0) {
				$db_fields_1 .= ', ';
				$db_fields_2 .= ', ';
			}

			$db_fields_1 .= $update ? $i['column_name'] . ' = :' . $i['column_name'] : $i['column_name'];
			$db_fields_2 .= $update ? '' : ':' . $i['column_name'];
			
			$db_values[$i['column_name']] = $_POST[$i['column_name']];
		}
	}

	$db_request .= $update ? 
		$db_fields_1 . ' WHERE id = :id' :
		$db_fields_1 . ') VALUES (' . $db_fields_2 . ' )';
	if ($update) {
		$db_values['id'] = $_POST['row_id'];
	}
	
	$db_exec = $db->prepare($db_request);
	if (!$db_exec->execute($db_values)) {
		exit('ko-fail_writing_in_db');
	}

	if (isset($class_confirmation_email)) {
		$mail_content = '<div style="font-family:Arial,sans-serif;font-size:16px;line-height:1.2;color:#06f;">'
			. '<p>Bonjour,</p>'
			. '<p>Vous avez inscrit avec succès une classe pour le <strong>' . $class_confirmation_email['number'] . 'e</strong> Rallye Mathématique Romand<br>'
			. '<strong>Classe :</strong> ' . $class_confirmation_email['class_name'] . '<br>'
			. '<strong>Code RMR :</strong> ' . $class_confirmation_email['class_code'] . '<br>'
			. '<strong>Catégorie :</strong> ' . $class_confirmation_email['category'] . '</p>'
			. '<p>Vous retrouvez sur le site votre code RMR et les informations de votre classe dans l&apos;onglet <strong>Mes classes</strong>.</p>'
			. '<p>Merci d&apos;inscrire le(s) code(s) RMR dans la case <em>Communications</em> lors du paiement de l&apos;inscription.</p>'
			. '<p>Votre code RMR devra être écrit sur chaque résolution de problème envoyée.<br>'
			. 'Aucun point ne sera attribué à une résolution sans code RMR.</p>'
			. '<p>Bonne journée et meilleures salutations,<br>Le comité</p>'
			. '</div>';
		$mail_headers = "MIME-Version: 1.0\r\n";
		$mail_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
		mail($class_confirmation_email['to'], $class_confirmation_email['subject'], $mail_content, $mail_headers);
	}

	if (!empty($generator['reset_session']) && isset($_SESSION[$generator['reset_session']])) {
	    unset($_SESSION[$generator['reset_session']]);
	}
	/*
	echo 'ok-';
	var_dump($db_exec->errorInfo());
	var_dump($db_values);
	echo $db_request;
	*/
	if (isset($return_string) && strpos($return_string, 'updatePage') !== false)  {
		exit('ok-rs-' . $return_string);
	}

	if (isset($return_values)) {
		exit('ok-rv-' . json_encode($return_values));
	}
	if (isset($return_string)) {
		exit('ok-rs-' . $return_string);
	}

	exit('ok-done');
}

exit('ko-no_exiting_before');