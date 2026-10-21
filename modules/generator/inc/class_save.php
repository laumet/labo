<?php
// "see as" (save for another user) only for the levels allowed to preview other users (same rule as get_content.php)
if (isset($_POST['see_as']) && (!isset($_SESSION['roles']['max_level']) || $_SESSION['roles']['max_level'] < 90)) {
	unset($_POST['see_as']);
}

// function to define class_code
function defineClassCode ($cat) {
	if ($cat == '' || $cat == null) {
		return null;
	}

	global $db;
	// get all classes of this category
	$d_all_classes = $db->prepare('SELECT class_code FROM school_classes WHERE category_id = :cat_id AND logical_delete = 0 AND rmr_id = :rmr_id ORDER BY class_code ASC');
	$d_all_classes->execute(array('cat_id' => $cat, 'rmr_id' => $_SESSION['rmr_status']['id']));
	$all_classes = $d_all_classes->fetchAll();
	$code = 1;

	// echo 'ok-';
	// var_dump($all_classes);
	if (is_countable($all_classes) && count($all_classes) > 0) {
		$inc = 0;
		$founded = false;

		// looking for a hole in list...
		while (!$founded && $inc < count($all_classes)) {
			// echo intval(substr($all_classes[$inc]['class_code'], - 2)) . ' -> ' . $inc + 1;
			if (intval(substr($all_classes[$inc]['class_code'], - 2)) > $inc + 1) {
				$founded = true;
				$code = $inc + 1;
			}
			$inc++;
		}

		if (!$founded) {
			$code = count($all_classes) + 1;
		}
	}

	$code = $cat . substr('00' . $code, -2);

	return $code;
}

// define or redefine class code
if (strpos($_POST['row_id'], 'new_row_') !== false) {
	// define class_code
	$_POST['class_code'] = defineClassCode($_POST['category_id']);
	$inputs[] = ['column_name' => 'class_code'];
	$return_values = array('class_code' => $_POST['class_code']);
	$inputs[] = ['column_name' => 'user_id'];
	$_POST['user_id'] = isset($_POST['see_as']) ? $_POST['see_as'] : $_SESSION['user']['id'];
	$inputs[] = ['column_name' => 'rmr_id'];
	$_POST['rmr_id'] = $_SESSION['rmr_status']['id'];
} else {
	// we need to know if category_id has changed
	// get category_id
	$d_cat_id = $db->prepare('SELECT category_id FROM school_classes WHERE id = :id');
	$d_cat_id->execute(array('id' => $_POST['row_id']));
	$cat_id = $d_cat_id->fetch();
	$d_cat_id->closeCursor();

	if ($cat_id['category_id'] != $_POST['category_id']) {
		$_POST['class_code'] = defineClassCode($_POST['category_id']);
		$return_values = array('class_code' => $_POST['class_code']);
		$inputs[] = ['column_name' => 'class_code'];
	}
}

// check if new school is proposed
if (isset($_POST['school_id']) && ($_POST['school_id'] == '-1' || $_POST['school_id'] == '0')) {
	
	if (strlen($_POST['class_comment']) > 0) {
		// check if content is new or has changed
		$mailToWM = false;
		if (strpos($_POST['row_id'], 'new_row_') !== false){
			$mailToWM = true;
		} else {
			// we need to know if class_comment has changed
			// get class_comment
			$d_com = $db->prepare('SELECT class_comment FROM school_classes WHERE id = :id');
			$d_com->execute(array('id' => $_POST['row_id']));
			$com = $d_com->fetch();
			$d_com->closeCursor();

			if ($com && $com['class_comment'] != $_POST['class_comment']) {
				$mailToWM = true;
			}
		}
		if ($mailToWM) {
			$subject = "RMR - Nouvelle école proposée";
			$to = $site_default['contact_mail_address'];
			$content = "Salut,\nLors de l'ajout ou de la modification d'une classe, un utilisateur a fait une proposition d'école à ajouter ou modifier dans la liste.\nUtilisateur : " . $_SESSION['user']['name'] . ' ' . $_SESSION['user']['lastname'] . "\nÉcole :\n" . $_POST['class_comment'];
			if (isset($_POST['see_as'])) {
				$content .= "\nCette requête a été créée pour l'utilisateur avec l'ID " . $_POST['see_as'] . " par la personne mentionnée précédemment.";
			}
			mail($to, $subject, $content);
		}
	}
}


// if necessary add role "pa" to user
if (
	strpos($_POST['row_id'], 'new_row_') !== false &&
	(
		(
			!in_array('pa', $_SESSION['display_roles']) &&
			!isset($_POST['see_as'])
		) || (
			isset($_POST['see_as'])
		)
	)
) {
	$user_id = isset($_POST['see_as']) ? $_POST['see_as'] : $_SESSION['user']['id'];
	// get user infos
	$not_pa = false;
	if (!isset($_POST['see_as'])) {
		$_SESSION['display_roles'][] = 'pa';
		$_SESSION['roles'][] = 'pa';
		$not_pa = true;
	} else {
		// get id for "see_as" user
		$d_sa = $db->prepare('SELECT roles FROM users WHERE id = :id');
	    $d_sa->execute(array('id' => $user_id));
	    $see_as = $d_sa->fetch(PDO::FETCH_ASSOC);
	    $d_sa->closeCursor();
	    if (strpos($see_as['roles'], 'pa') === false) {
	    	$not_pa = true;
	    }
	}

	if ($not_pa) {
		// update user in db
		$update_user = $db->prepare('UPDATE users SET roles = CONCAT(roles, ",pa") WHERE id = :user_id');
		$update_user->execute(array('user_id' => $user_id));
		if (!$update_user) {
			exit('ko-adding_role_failed');
		} else {
			unset($return_values);
			$return_string = 'updatePage';
		}
	}
}

// Prepare the confirmation mail; it is sent by save_row.php after the INSERT succeeds.
if (strpos($_POST['row_id'], 'new_row_') !== false) {
	$class_confirmation_user = $db->prepare('SELECT username FROM users WHERE id = :user_id');
	$class_confirmation_user->execute(['user_id' => $_POST['user_id']]);
	$class_confirmation_user = $class_confirmation_user->fetch(PDO::FETCH_ASSOC);

	$class_confirmation_category = $db->prepare('SELECT category_name FROM categories WHERE id = :category_id');
	$class_confirmation_category->execute(['category_id' => $_POST['category_id']]);
	$class_confirmation_category = $class_confirmation_category->fetch(PDO::FETCH_ASSOC);

	$class_confirmation_rmr = $db->prepare('SELECT number FROM rmr WHERE id = :rmr_id');
	$class_confirmation_rmr->execute(['rmr_id' => $_POST['rmr_id']]);
	$class_confirmation_rmr = $class_confirmation_rmr->fetch(PDO::FETCH_ASSOC);

	if ($class_confirmation_user && $class_confirmation_category && $class_confirmation_rmr) {
		$class_confirmation_email = [
			'to' => $class_confirmation_user['username'],
			'subject' => "RMR - Confirmation d'inscription de votre classe",
			'number' => htmlspecialchars($class_confirmation_rmr['number'], ENT_QUOTES, 'UTF-8'),
			'class_name' => htmlspecialchars($_POST['class_name'] ?? '', ENT_QUOTES, 'UTF-8'),
			'class_code' => htmlspecialchars($_POST['class_code'], ENT_QUOTES, 'UTF-8'),
			'category' => htmlspecialchars($class_confirmation_category['category_name'], ENT_QUOTES, 'UTF-8')
		];
	}
}