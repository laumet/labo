<?php
// LABO tests against a real MariaDB TEST database: installer in both data management modes,
// foreign keys, rerun safety, mode protection, page/module menu and rights
//
// run from the project root (the database name must contain TEST; its LABO tables are emptied):
//   LABO_TEST_DB_HOST=127.0.0.1 LABO_TEST_DB_NAME=LABO_CLAUDE_TEST LABO_TEST_DB_USER=... LABO_TEST_DB_PASS=... php tests/run_db_tests.php
// optional: LABO_TEST_DB_READONLY=LABO  => also checks the mode of an existing installation (read only)

if (PHP_SAPI !== 'cli') {
	exit;
}

require __DIR__ . '/../inc/basis_functions.php';
require __DIR__ . '/../account/inc/first_setup.php';
require __DIR__ . '/../modules/inc/page_modules.php';

$host = getenv('LABO_TEST_DB_HOST') ?: '127.0.0.1';
$name = getenv('LABO_TEST_DB_NAME');
$user = getenv('LABO_TEST_DB_USER');
$pass = getenv('LABO_TEST_DB_PASS');
if (!$name || !$user || $pass === false) {
	exit("Set LABO_TEST_DB_NAME, LABO_TEST_DB_USER and LABO_TEST_DB_PASS.\n");
}
if (stripos($name, 'test') === false) {
	exit("Refused: the database name must contain TEST (its LABO tables are emptied).\n");
}

$options = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false);
$server = new PDO('mysql:host=' . $host . ';charset=utf8mb4', $user, $pass, $options);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db = new PDO('mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4', $user, $pass, $options);

$failed = 0;
$count = 0;
function check ($label, $condition) {
	global $failed, $count;
	$count++;
	if (!$condition) { $failed++; }
	echo ($condition ? '  ok    ' : '  FAIL  ') . $label . "\n";
}
function section ($label) { echo "\n" . $label . "\n"; }
function value ($sql) { global $db; return $db->query($sql)->fetchColumn(); }
function tables () {
	global $db;
	// sorted in PHP: the collation of information_schema doesn't sort "_" like strcmp
	$tables = $db->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
	sort($tables, SORT_STRING);
	return $tables;
}
// empty the test database: only the LABO tables (children first)
function reset_test_db () {
	global $db;
	$db->exec('SET FOREIGN_KEY_CHECKS = 0');
	foreach (array('module_permissions', 'page_modules', 'pages', 'users', 'roles', 'generator', 'labo_settings', 'base', 'test_items') as $t) {
		$db->exec('DROP TABLE IF EXISTS ' . $t);
	}
	$db->exec('SET FOREIGN_KEY_CHECKS = 1');
}
function counts () {
	return array(
		'generator' => (int) value('SELECT COUNT(*) FROM generator'),
		'roles' => (int) value('SELECT COUNT(*) FROM roles'),
		'labo_settings' => (int) value('SELECT COUNT(*) FROM labo_settings')
	);
}

echo 'MariaDB ' . value('SELECT VERSION()') . "\n";

// ---------------------------------------------------------------------------
section('1. Installer, single_generator_per_page');
reset_test_db();
check('no labo_settings table => installed mode null (installation older than the setting)', labo_installed_data_mode($db) === null);
labo_create_basis_tables('single_generator_per_page');
check('tables', tables() === array('base', 'generator', 'labo_settings', 'roles', 'users'));
check('mode saved', labo_installed_data_mode($db) === 'single_generator_per_page');
check('3 generator lines in order (GENERATOR, Statuts, Utilisateurs)',
	$db->query('SELECT title FROM generator ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) === array('GENERATOR', 'Statuts', 'Utilisateurs'));
check('role sa', value('SELECT level FROM roles WHERE role_short_name = "sa"') == 100);
$before = counts();
labo_create_basis_tables('single_generator_per_page');
check('rerun: nothing duplicated', counts() === $before);
check('collation utf8mb4_unicode_ci on every table',
	(int) value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_collation <> "utf8mb4_unicode_ci"') === 0);
$stopped = false;
try { labo_create_basis_tables('page_with_modules'); } catch (RuntimeException $e) { $stopped = $e->getMessage() === 'data_mode_mismatch'; }
check('rerun with the other mode => stopped (data_mode_mismatch)', $stopped);
check('... and no page table created', !in_array('pages', tables(), true));
check('resolution: file single_generator_per_page => used', labo_resolve_data_mode('single_generator_per_page', labo_installed_data_mode($db)) === array('single_generator_per_page', null));
check('resolution: file changed to page_with_modules => refused', labo_resolve_data_mode('page_with_modules', labo_installed_data_mode($db))[1] === 'data_mode_mismatch');

// ---------------------------------------------------------------------------
section('2. Installer, page_with_modules');
reset_test_db();
labo_create_basis_tables('page_with_modules');
check('tables', tables() === array('base', 'generator', 'labo_settings', 'module_permissions', 'page_modules', 'pages', 'roles', 'users'));
check('mode saved', labo_installed_data_mode($db) === 'page_with_modules');
check('6 generator lines', (int) value('SELECT COUNT(*) FROM generator') === 6);
check('page Administration with 6 modules in order',
	$db->query('SELECT g.table_name FROM page_modules pm JOIN pages p ON p.id = pm.page_id JOIN generator g ON g.id = pm.generator_id WHERE p.slug = "administration" ORDER BY pm.position')->fetchAll(PDO::FETCH_COLUMN)
	=== array('generator', 'roles', 'users', 'pages', 'page_modules', 'module_permissions'));
check('6 permissions for sa, all rights', (int) value('SELECT COUNT(*) FROM module_permissions mp JOIN roles r ON r.id = mp.role_id WHERE r.role_short_name = "sa" AND can_view = 1 AND can_edit = 1 AND can_add = 1 AND can_delete = 1') === 6);
check('4 foreign keys', (int) value('SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = DATABASE()') === 4);
$before = array(counts(), (int) value('SELECT COUNT(*) FROM pages'), (int) value('SELECT COUNT(*) FROM page_modules'), (int) value('SELECT COUNT(*) FROM module_permissions'));
labo_create_basis_tables('page_with_modules');
check('rerun: nothing duplicated', $before === array(counts(), (int) value('SELECT COUNT(*) FROM pages'), (int) value('SELECT COUNT(*) FROM page_modules'), (int) value('SELECT COUNT(*) FROM module_permissions')));
$refused = false;
try { $db->exec('INSERT INTO page_modules (page_id, generator_id) VALUES (1, 999999)'); } catch (PDOException $e) { $refused = true; }
check('foreign key: module of an unknown generator line refused', $refused);
$refused = false;
try { $db->exec('INSERT INTO module_permissions (role_id, generator_id) SELECT r.id, 1 FROM roles r WHERE r.role_short_name = "sa"'); } catch (PDOException $e) { $refused = true; }
check('unique: second permission line for the same role and module refused', $refused);
$refused = false;
try { $db->exec('DELETE FROM generator WHERE id = 1'); } catch (PDOException $e) { $refused = true; }
check('foreign key: generator line used by a page can not be deleted', $refused);

// ---------------------------------------------------------------------------
section('3. Menu and rights, page_with_modules');
// test data: roles me and pa, page "Students" with two modules on a test table
$db->exec('CREATE TABLE test_items (' . LABO_BASE_COLUMNS . ' label VARCHAR(50) NULL, PRIMARY KEY (id))' . LABO_TABLE_OPTIONS);
$db->exec('INSERT INTO roles (level, role_name, role_short_name, tab_order) VALUES (10, "member", "me", 1), (20, "participant", "pa", 2)');
$db->exec('INSERT INTO generator (title, table_name, user_type, page, form, can_edit, can_add, can_delete, display) VALUES
	("Students list", "test_items", "me", "labo", "table", 1, 1, 2, "{}"),
	("Students admin", "test_items", "pa", "labo", "table", 1, 0, 1, "{}"),
	("Hidden module", "test_items", "pa", "labo", "table", 1, 1, 1, "{}")');
$list = (int) value('SELECT id FROM generator WHERE title = "Students list"');
$admin = (int) value('SELECT id FROM generator WHERE title = "Students admin"');
$hidden = (int) value('SELECT id FROM generator WHERE title = "Hidden module"');
$db->exec('INSERT INTO pages (slug, title, tab_order) VALUES ("students", "Students", 1), ("disabled", "Disabled", 2)');
$students = (int) value('SELECT id FROM pages WHERE slug = "students"');
$disabled = (int) value('SELECT id FROM pages WHERE slug = "disabled"');
$db->exec('UPDATE pages SET enabled = 0 WHERE id = ' . $disabled);
$db->exec("INSERT INTO page_modules (page_id, generator_id, position, title) VALUES ($students, $list, 1, NULL), ($students, $admin, 2, 'Administration of the students'), ($students, $hidden, 3, NULL), ($disabled, $list, 1, NULL)");
$db->exec("INSERT INTO module_permissions (role_id, generator_id, can_view, can_edit, can_add, can_delete)
	SELECT id, $list, 1, 0, 0, 0 FROM roles WHERE role_short_name = 'me' UNION ALL
	SELECT id, $list, 1, 1, 1, 0 FROM roles WHERE role_short_name = 'pa' UNION ALL
	SELECT id, $admin, 1, 1, 1, 1 FROM roles WHERE role_short_name = 'pa' UNION ALL
	SELECT id, $hidden, 0, 1, 1, 1 FROM roles WHERE role_short_name = 'pa'");

$_SESSION = array('roles' => array('me', 'max_level' => 10));
$pages = labo_define_pages_menu();
check('member: one page Students (disabled page not shown)', array_column($pages, 'title') === array('Students'));
check('member: only the list module', array_keys($pages[$students]['modules']) === array($list));
check('member: read only', $_SESSION['authorized'][$list]['can_edit'] === 0 && $_SESSION['authorized'][$list]['can_add'] === 0);
check('member: the admin module is refused on the server', labo_check_page_index((string) $admin, 'get_content', $_SESSION['authorized'], $_SESSION['authorized_pages'], array('me')) === null);

$_SESSION = array('roles' => array('me', 'pa', 'max_level' => 20));
$pages = labo_define_pages_menu();
check('member + participant: still one menu entry Students', array_column($pages, 'title') === array('Students'));
check('member + participant: 2 modules in order, module without can_view left out', array_keys($pages[$students]['modules']) === array($list, $admin));
check('rights of both roles combined on the list module', $_SESSION['authorized'][$list]['can_edit'] === 1 && $_SESSION['authorized'][$list]['can_add'] === 1);
check('generator line forbids add => no add, even with the role right', $_SESSION['authorized'][$admin]['can_add'] === 0);
check('can_delete: generator value kept when the role allows it', $_SESSION['authorized'][$admin]['can_delete'] === 1 && $_SESSION['authorized'][$list]['can_delete'] === 0);
check('module title of the page used', $pages[$students]['modules'][$admin]['title'] === 'Administration of the students');
check('page read allowed, page write refused',
	labo_check_page_index('page_' . $students, 'get_content', $_SESSION['authorized'], $_SESSION['authorized_pages'], array()) !== null &&
	labo_check_page_index('page_' . $students, 'save_row', $_SESSION['authorized'], $_SESSION['authorized_pages'], array()) === null);

$db->exec('UPDATE module_permissions SET logical_delete = 1 WHERE generator_id = ' . $admin);
$pages = labo_define_pages_menu();
check('permission logically deleted => module gone', array_keys($pages[$students]['modules']) === array($list));
$db->exec('UPDATE generator SET logical_delete = 1 WHERE id = ' . $list);
$pages = labo_define_pages_menu();
check('page without any visible module => not in the menu', count($pages) === 0);

$_SESSION = array('roles' => array('max_level' => 10));
check('user without role => no page', labo_define_pages_menu() === array());

// ---------------------------------------------------------------------------
section('4. Manual migration single_generator_per_page => page_with_modules (steps of readme.txt)');
reset_test_db();
labo_create_basis_tables('single_generator_per_page');
// an installation older than the setting: no labo_settings table
$db->exec('DROP TABLE labo_settings');
$db->exec('INSERT INTO roles (level, role_name, role_short_name, tab_order) VALUES (10, "member", "me", 1)');
$db->exec('INSERT INTO generator (title, table_name, user_type, page, form, can_edit, can_add, can_delete, display) VALUES ("Students", "users", "me", "labo", "table", 1, 0, 2, "{}")');
$generator_before = $db->query('SELECT * FROM generator ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check('before: page_with_modules refused (data_mode_not_installed)', labo_resolve_data_mode('page_with_modules', labo_installed_data_mode($db))[1] === 'data_mode_not_installed');
// step 2: tables (CREATE TABLE IF NOT EXISTS of the installer)
$sql = labo_basis_tables_sql('page_with_modules');
foreach (array('labo_settings', 'pages', 'page_modules', 'module_permissions') as $t) {
	$db->exec($sql[$t]);
}
// step 3: pages and their modules, chosen by the administrator
$db->exec("INSERT INTO pages (slug, title, tab_order) VALUES ('students', 'Students', 1)");
$db->exec("INSERT INTO page_modules (page_id, generator_id, position) SELECT p.id, g.id, 1 FROM pages p, generator g WHERE p.slug = 'students' AND g.title = 'Students'");
// step 4: rights of single_generator_per_page (query of readme.txt)
$db->exec("INSERT IGNORE INTO module_permissions (role_id, generator_id, can_view, can_edit, can_add, can_delete)
	SELECT r.id, g.id, 1, g.can_edit, g.can_add, IF(g.can_delete > 0, 1, 0)
	FROM generator AS g INNER JOIN roles AS r ON r.role_short_name = g.user_type
	WHERE g.page = 'labo' AND g.logical_delete = 0");
check('step 4: one permission per generator line of a role (3 sa + 1 me)', (int) value('SELECT COUNT(*) FROM module_permissions') === 4);
// step 5: mode saved
$db->exec("INSERT INTO labo_settings (can_not_be_deleted, setting_key, setting_value) VALUES (1, 'data_management_mode', 'page_with_modules')");
check('after: page_with_modules used', labo_resolve_data_mode('page_with_modules', labo_installed_data_mode($db)) === array('page_with_modules', null));
check('generator lines unchanged by the migration', $db->query('SELECT * FROM generator ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) === $generator_before);
$_SESSION = array('roles' => array('me', 'max_level' => 10));
$pages = labo_define_pages_menu();
$gid = (int) value('SELECT id FROM generator WHERE title = "Students"');
check('member sees the page Students with the same rights as before (edit, no add, delete 2)',
	array_column($pages, 'title') === array('Students') &&
	$_SESSION['authorized'][$gid] === array('table_name' => 'users', 'can_edit' => 1, 'can_add' => 0, 'can_delete' => 2));

// ---------------------------------------------------------------------------
$readonly = getenv('LABO_TEST_DB_READONLY');
if ($readonly) {
	section('5. Existing installation (read only): ' . $readonly);
	$db_test = $db;
	$db = new PDO('mysql:host=' . $host . ';dbname=' . $readonly . ';charset=utf8mb4', $user, $pass, $options);
	$installed = labo_installed_data_mode($db);
	check('installed mode: ' . var_export($installed, true), $installed === null || in_array($installed, LABO_DATA_MODES, true));
	check('with the settings file of the project => usable', labo_resolve_data_mode(labo_configured_data_mode(), $installed)[1] === null);
	$db = $db_test;
}

reset_test_db();
echo "\n$count tests, $failed failed (test database emptied)\n";
exit($failed === 0 ? 0 : 1);
