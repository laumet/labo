<?php
if (isset($_POST['must_be_validated'])) {
	// I add here field roles and max_level to updates those fields at the same time
	switch ($_POST['must_be_validated']) {
		case '0':
			$_POST['roles'] = 'me';
			$_POST['max_level'] = '10';
			$inputs[] = ['column_name' =>'roles'];
			$inputs[] = ['column_name' =>'max_level'];
			break;
		case '1':
			$_POST['roles'] = 'ev';
			$_POST['max_level'] = '0';
			$inputs[] = ['column_name' =>'roles'];
			$inputs[] = ['column_name' =>'max_level'];
			break;
		default :
			$_POST['roles'] = 'none';
			$_POST['max_level'] = '-1';
			$inputs[] = ['column_name' =>'roles'];
			$inputs[] = ['column_name' =>'max_level'];
			break;
	}
}