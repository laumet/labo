<?php
if (!isset($_POST['row'])) {
	exit('ko-row_not_defined (delete_row 1)');
}

require 'inc/check_hability.php';

require_once 'inc/connect_db.php';

require_once 'inc/constructor_swap_functions.php';

switch ($_POST['to_do']) {
	case 'delete_row' :
		if ($authorisation && $authorisation['can_delete'] !== 0) {
			$return_string = deleteRow($authorisation['table_name'], $_POST['row']);
		} else {
			exit('ko-not_authorised (delete_row 2)');
		}
	break;
	default :
		exit('ko-request_not_valid (delete_row)');
		break;
}

if (isset($return_string) && strpos($return_string, 'updatePage') !== false) {
		exit('ok-rs-' . $return_string);
}

if (isset($return_values)) {
	exit('ok-rv-' . json_encode($return_values));
}
if (isset($return_string) && $return_string != '' && $return_string != null) {
	exit('ok-rs-' . $return_string);
}

exit('ok-done'); 
