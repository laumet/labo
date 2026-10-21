<?php
// check if index is sent (exit: a return would only end this include and let the request go on)
if (!isset($_POST['index']) || !is_string($_POST['index']) || $_POST['index'] == '') {
	exit('ko-not_authorised (check_hability)');
}

// page_with_modules: only the session authorizations (module_permissions), see modules/inc/page_modules.php
if (labo_page_mode()) {
	require_once __DIR__ . '/page_modules.php';
	$page_access = labo_check_page_index(
		$_POST['index'],
		isset($_POST['to_do']) ? $_POST['to_do'] : '',
		isset($_SESSION['authorized']) ? $_SESSION['authorized'] : array(),
		isset($_SESSION['authorized_pages']) ? $_SESSION['authorized_pages'] : array(),
		isset($_SESSION['display_roles']) ? $_SESSION['display_roles'] : array()
	);
	if ($page_access === null) {
		exit('ko-not_authorised (check_hability_page)');
	}
	if ($page_access['type'] === 'module') {
		$authorisation = $page_access['authorisation'];
	}
	return;
}

// test hability
if (strpos($_POST['index'], 'homepage_') !== false) {
	// define if role is authorized
	$role_id = substr($_POST['index'], 9);

	// parse all roles admitted in session
	foreach ($_SESSION['display_roles'] as $r) {
		if ($role_id == $r) {
			return;
		}
	}
} else if (isset($_SESSION['authorized']) && is_array($_SESSION['authorized'])){
	foreach($_SESSION['authorized'] as $k => $v) {
		if ($k == $_POST['index']) {
			$authorisation = $v;
			return;
		}
	}

	// some request are not linked to labo, so get the generator row and then check if user is authorised
	$d_gen_row = $db->prepare('SELECT * FROM generator WHERE id = :id OR text_id = :id');
	$d_gen_row->execute(array('id' => $_POST['index']));
	$generator_row = $d_gen_row->fetch();
	$d_gen_row->closeCursor();

	if (!$generator_row || !is_countable($generator_row) || count($generator_row) == 0) {
		exit('ko-request_not_valid (check_hability)');
	}
   
	if (intval($generator_row['user_type']) !== 0 ) {
		if ($_SESSION['roles']['max_level'] <= intval($generator_row['user_type'])) {
			exit('ko-not_authorised (check_hability_1)');
		}
		$authorisation = [];
		$authorisation['can_add'] = $generator_row['can_add'];
		return; 
	}	
}
exit('ko-not_authorised (check_hability_2)');