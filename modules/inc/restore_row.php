<?php
if (!isset($_POST['row'])) {
	exit('ko-row_not_defined (restore_row 1)');
}

require 'inc/check_hability.php';

require_once 'inc/connect_db.php';

require_once 'inc/constructor_swap_functions.php';

switch ($_POST['to_do']) {
	case 'restore_row' :
		if ($authorisation && $authorisation['can_delete'] !== 0) {
			restoreRow($authorisation['table_name'], $_POST['row']);
		} else {
			exit('ko-not_authorised (restore_row 2)');
		}
	break;
}
exit('ko-request_not_valid (restore_row 2)');