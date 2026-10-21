<?php
// roles are in checkboxes
if (isset($_POST['roles']) && $_POST['roles'] != null && $_POST['roles'] != '') {
	// we need to explode roles to define max_level
	$in_values = '';
	$incomming_roles = explode(',', $_POST['roles']);
	
	// get all roles to make security control :
	// abort if non existent role is submitted,
	// abort if higher role is submitted
	$d_roles = $db->prepare('SELECT role_short_name, level, two_factors_authentication FROM roles');
	$d_roles->execute();
	$roles = [];
	$levels = [];
	while ($role = $d_roles->fetch()) {
		$roles[] = $role['role_short_name'];
		$levels[$role['role_short_name']] = $role['level'];
	}

	// set max_level of roles and check if two-factor-authentication is required
	$two_fa = 0;
	foreach($incomming_roles as $k => $r) {
		if (!in_array($r, $roles)) {
			exit('ko-non_existent_role');
		} else if ($levels[$r] > $_SESSION['roles']['max_level']) {
			exit('ko-role_level_submitted_to_high_for_user');
		}
		
		if (strlen($in_values > 0)) {
			$in_values .= ', ';
		}
		$in_values .= ':iv' . $k;
		$in_values_tab['iv' . $k] = $r;
	}

	$d_max_level = $db->prepare('SELECT MAX(level) AS max_level, MAX(two_factors_authentication) as two_factors_authentication FROM roles WHERE role_short_name IN (' . $in_values . ')');
	$d_max_level->execute($in_values_tab);
	$max_level = $d_max_level->fetch(PDO::FETCH_ASSOC);

	if ($max_level) {
		$_POST['max_level'] = $max_level['max_level'];
		$_POST['two_factors_authentication'] = $max_level['two_factors_authentication'];
	} else {
		$_POST['max_level'] = 0;
		$_POST['two_factors_authentication'] = 0;
	}
} else {
	$_POST['roles'] = 'none';
	$_POST['max_level'] = '-1';
}

$inputs[] = ['column_name' => 'max_level'];
$inputs[] = ['column_name' => 'two_factors_authentication'];