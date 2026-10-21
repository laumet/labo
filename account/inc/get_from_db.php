<?php
switch ($_POST['to_do']) {
	case 'get_user_form' :
		$d_user_form = $db->prepare('
			SELECT display FROM generator
			WHERE user_type = "me" AND page = "account"');
		$d_user_form->execute();
		$user_form = $d_user_form->fetch(PDO::FETCH_ASSOC);
		$d_user_form->closeCursor();

		if ($user_form) {
			$user_form = json_decode($user_form['display'], true);
			echo (create_form_from_db($user_form, 'user_form_'));
		} else {
			exit('ko-problem_with_db');
		}

		exit();
		break;
	default :
		exit('ko-unknow_action');
		break;
}