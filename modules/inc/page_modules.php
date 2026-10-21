<?php
// data management mode page_with_modules: menu pages and module permissions of the logged-in user
// (tables pages, page_modules, module_permissions, created by the first setup in this mode)
//
// rights of a module for a user:
//   - visible if one of the user's roles has can_view = 1 for the module (module_permissions)
//   - can_edit / can_add: one of the user's roles has the right AND the generator line allows it
//   - can_delete: one of the user's roles has the right => the can_delete value of the generator line (1 or 2)
// these rights are about modules only: row scope (request_where) and columns (display) stay defined by the generator line

// group permission lines (one per page x module x role of the user) into pages with their modules
// $lines: page_id, page_title, page_order, generator_id, module_title, table_name, position,
//         perm_view, perm_edit, perm_add, perm_delete, g_edit, g_add, g_delete
function labo_group_page_modules ($lines) {
	$pages = array();
	foreach ($lines as $l) {
		if ((int) $l['perm_view'] !== 1) {
			continue;
		}
		$pid = (int) $l['page_id'];
		$gid = (int) $l['generator_id'];
		if (!isset($pages[$pid])) {
			$pages[$pid] = array(
				'id' => $pid,
				'title' => $l['page_title'],
				'order' => (int) $l['page_order'],
				'modules' => array()
			);
		}
		if (!isset($pages[$pid]['modules'][$gid])) {
			$pages[$pid]['modules'][$gid] = array(
				'generator_id' => $gid,
				'title' => $l['module_title'],
				'table_name' => $l['table_name'],
				'position' => (int) $l['position'],
				'perm_edit' => 0, 'perm_add' => 0, 'perm_delete' => 0,
				'g_edit' => (int) $l['g_edit'], 'g_add' => (int) $l['g_add'], 'g_delete' => (int) $l['g_delete']
			);
		}
		// several roles: a right given by one of them is enough
		$m = &$pages[$pid]['modules'][$gid];
		$m['perm_edit'] = max($m['perm_edit'], (int) $l['perm_edit']);
		$m['perm_add'] = max($m['perm_add'], (int) $l['perm_add']);
		$m['perm_delete'] = max($m['perm_delete'], (int) $l['perm_delete']);
		unset($m);
	}

	// effective rights, order of pages and modules
	foreach ($pages as $pid => $p) {
		foreach ($p['modules'] as $gid => $m) {
			$pages[$pid]['modules'][$gid] = array(
				'generator_id' => $m['generator_id'],
				'title' => $m['title'],
				'table_name' => $m['table_name'],
				'position' => $m['position'],
				'can_edit' => ($m['perm_edit'] === 1 && $m['g_edit'] === 1) ? 1 : 0,
				'can_add' => ($m['perm_add'] === 1 && $m['g_add'] === 1) ? 1 : 0,
				'can_delete' => $m['perm_delete'] === 1 ? $m['g_delete'] : 0
			);
		}
		uasort($pages[$pid]['modules'], function ($a, $b) {
			return array($a['position'], $a['generator_id']) <=> array($b['position'], $b['generator_id']);
		});
	}
	uasort($pages, function ($a, $b) {
		return array($a['order'], $a['id']) <=> array($b['order'], $b['id']);
	});
	return $pages;
}

// session authorizations from the grouped pages:
//   authorized[generator_id] (same format as single_generator_per_page) and authorized_pages[page_id] = module ids in order
function labo_page_authorizations ($pages) {
	$authorized = array();
	$authorized_pages = array();
	foreach ($pages as $pid => $p) {
		$authorized_pages[$pid] = array();
		foreach ($p['modules'] as $gid => $m) {
			$authorized_pages[$pid][] = array('generator_id' => $gid, 'title' => $m['title']);
			$authorized[$gid] = array(
				'table_name' => $m['table_name'],
				'can_edit' => $m['can_edit'],
				'can_add' => $m['can_add'],
				'can_delete' => $m['can_delete']
			);
		}
	}
	return array($authorized, $authorized_pages);
}

// access check of a request index (used by check_hability.php for every read and write)
// returns array('type' => 'homepage' | 'page' | 'module', ...) or null if not authorized
//   page: only to read the page (to_do get_content); module: the authorization of the module
function labo_check_page_index ($index, $to_do, $authorized, $authorized_pages, $display_roles) {
	$index = (string) $index;
	if ($index === '') {
		return null;
	}
	if (strpos($index, 'homepage_') === 0) {
		return in_array(substr($index, 9), is_array($display_roles) ? $display_roles : array(), true) ? array('type' => 'homepage') : null;
	}
	if (strpos($index, 'page_') === 0) {
		$pid = substr($index, 5);
		if ($to_do !== 'get_content' || !ctype_digit($pid) || !isset($authorized_pages[(int) $pid])) {
			return null;
		}
		return array('type' => 'page', 'page_id' => (int) $pid, 'modules' => $authorized_pages[(int) $pid]);
	}
	if (ctype_digit($index) && isset($authorized[(int) $index])) {
		return array('type' => 'module', 'authorisation' => $authorized[(int) $index]);
	}
	return null;
}

// real roles of the logged-in user (never the "see as" user)
function labo_session_user_roles () {
	$roles = array();
	if (isset($_SESSION['roles']) && is_array($_SESSION['roles'])) {
		foreach ($_SESSION['roles'] as $k => $r) {
			if ($k !== 'max_level' && is_string($r) && $r !== '') {
				$roles[] = $r;
			}
		}
	}
	return $roles;
}

// pages of the menu for the logged-in user, and the session authorizations
function labo_define_pages_menu () {
	global $db;

	$roles = labo_session_user_roles();
	$_SESSION['authorized'] = array();
	$_SESSION['authorized_pages'] = array();
	if (count($roles) === 0) {
		return array();
	}

	$in = array();
	$values = array();
	foreach ($roles as $i => $r) {
		$in[] = ':r' . $i;
		$values['r' . $i] = $r;
	}
	$d_lines = $db->prepare('
		SELECT p.id AS page_id, p.title AS page_title, p.tab_order AS page_order,
			g.id AS generator_id, COALESCE(NULLIF(pm.title, ""), g.title) AS module_title, g.table_name, pm.position,
			mp.can_view AS perm_view, mp.can_edit AS perm_edit, mp.can_add AS perm_add, mp.can_delete AS perm_delete,
			g.can_edit AS g_edit, g.can_add AS g_add, g.can_delete AS g_delete
		FROM pages AS p
		INNER JOIN page_modules AS pm ON pm.page_id = p.id AND pm.logical_delete = 0
		INNER JOIN generator AS g ON g.id = pm.generator_id AND g.logical_delete = 0
		INNER JOIN module_permissions AS mp ON mp.generator_id = g.id AND mp.logical_delete = 0
		INNER JOIN roles AS r ON r.id = mp.role_id AND r.logical_delete = 0
		WHERE p.enabled = 1 AND p.logical_delete = 0 AND r.role_short_name IN (' . implode(', ', $in) . ')
	');
	$d_lines->execute($values);
	$pages = labo_group_page_modules($d_lines->fetchAll(PDO::FETCH_ASSOC));

	list($_SESSION['authorized'], $_SESSION['authorized_pages']) = labo_page_authorizations($pages);
	return $pages;
}
