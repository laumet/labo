<?php
// LABO tests: data management modes, installer schema selection, page/module menu grouping, permission checks
// run from the project root:  php tests/run_tests.php
// no database connection: the installer runs against a fake database that records the SQL

if (PHP_SAPI !== 'cli') {
	exit;
}

require __DIR__ . '/../inc/basis_functions.php';
require __DIR__ . '/../account/inc/first_setup.php';
require __DIR__ . '/../modules/inc/page_modules.php';

$labo_tests_failed = 0;
$labo_tests_count = 0;

function check ($name, $condition) {
	global $labo_tests_failed, $labo_tests_count;
	$labo_tests_count++;
	if ($condition) {
		echo "  ok    $name\n";
	} else {
		$labo_tests_failed++;
		echo "  FAIL  $name\n";
	}
}

function section ($name) {
	echo "\n$name\n";
}

// fake database: records exec/prepare/query, answers the few SELECT of the installer
class FakeStatement {
	public $sql;
	private $db;
	public function __construct ($db, $sql) { $this->db = $db; $this->sql = $sql; }
	public function execute ($values = array()) { $this->db->executed[] = array('sql' => $this->sql, 'values' => $values); return true; }
	public function fetchColumn () { return $this->db->answer($this->sql); }
	public function fetchAll ($mode = null) { return array(); }
	public function closeCursor () { return true; }
}

class FakeDb {
	public $exec = array();
	public $executed = array();
	public $installed_mode = null;
	private $next_id = 0;
	public function exec ($sql) { $this->exec[] = $sql; return 0; }
	public function prepare ($sql) { return new FakeStatement($this, $sql); }
	public function query ($sql) { $this->exec[] = $sql; return new FakeStatement($this, $sql); }
	public function answer ($sql) {
		if (strpos($sql, 'FROM labo_settings') !== false) {
			return $this->installed_mode === null ? false : $this->installed_mode;
		}
		if (strpos($sql, 'COUNT(*)') !== false) {
			return 0;
		}
		return ++$this->next_id;
	}
	public function created_tables () {
		$tables = array();
		foreach ($this->exec as $sql) {
			if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $sql, $m)) {
				$tables[] = $m[1];
			}
		}
		return $tables;
	}
	public function executed_with ($needle) {
		return array_values(array_filter($this->executed, function ($e) use ($needle) { return strpos($e['sql'], $needle) !== false; }));
	}
}

// ---------------------------------------------------------------------------
section('1. Settings file (default/labo_settings.json)');

$tmp = tempnam(sys_get_temp_dir(), 'labo_settings_');
$write = function ($content) use ($tmp) { file_put_contents($tmp, $content); };

check('missing file => default single_generator_per_page', labo_configured_data_mode($tmp . '_missing') === 'single_generator_per_page');
$write('{"data_management_mode": "single_generator_per_page"}');
check('single_generator_per_page read', labo_configured_data_mode($tmp) === 'single_generator_per_page');
$write('{"data_management_mode": "page_with_modules"}');
check('page_with_modules read', labo_configured_data_mode($tmp) === 'page_with_modules');
$write('{"other": 1}');
check('missing value => default', labo_configured_data_mode($tmp) === 'single_generator_per_page');
$write('{"data_management_mode": "pages"}');
check('unknown value => invalid (null), not guessed', labo_configured_data_mode($tmp) === null);
$write('{broken json');
check('broken file => invalid (null)', labo_configured_data_mode($tmp) === null);
unlink($tmp);
check('project settings file is valid', labo_configured_data_mode() !== null);

// ---------------------------------------------------------------------------
section('2. Mode resolution (settings file x mode saved at installation)');

check('old installation (nothing saved) + default => single_generator_per_page',
	labo_resolve_data_mode('single_generator_per_page', null) === array('single_generator_per_page', null));
check('old installation + page_with_modules => refused (migration needed)',
	labo_resolve_data_mode('page_with_modules', null) === array(null, 'data_mode_not_installed'));
check('same mode in file and database => used',
	labo_resolve_data_mode('page_with_modules', 'page_with_modules') === array('page_with_modules', null));
check('file changed after installation => refused',
	labo_resolve_data_mode('single_generator_per_page', 'page_with_modules') === array(null, 'data_mode_mismatch'));
check('invalid file => refused',
	labo_resolve_data_mode(null, 'page_with_modules') === array(null, 'data_mode_invalid'));
check('invalid value in database => refused',
	labo_resolve_data_mode('page_with_modules', 'other') === array(null, 'data_mode_invalid'));

// ---------------------------------------------------------------------------
section('3. Installer schema selection');

$legacy_sql = labo_basis_tables_sql('single_generator_per_page');
$page_sql = labo_basis_tables_sql('page_with_modules');
check('single_generator_per_page tables', array_keys($legacy_sql) === array('base', 'labo_settings', 'generator', 'roles', 'users'));
check('page_with_modules tables', array_keys($page_sql) === array('base', 'labo_settings', 'generator', 'roles', 'pages', 'page_modules', 'module_permissions', 'users'));
check('users created last in both modes (its existence ends the first opening)',
	array_key_last($legacy_sql) === 'users' && array_key_last($page_sql) === 'users');
$all_sql = implode("\n", $page_sql);
check('every table utf8mb4 / utf8mb4_unicode_ci', substr_count($all_sql, 'CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci') === count($page_sql));
check('every table CREATE TABLE IF NOT EXISTS (safe to rerun)', substr_count($all_sql, 'CREATE TABLE IF NOT EXISTS') === count($page_sql));
check('no DROP, TRUNCATE or DELETE', preg_match('/\b(DROP|TRUNCATE|DELETE)\b/i', $all_sql) === 0);
check('page_modules: one line per page x module (unique, foreign keys)',
	strpos($page_sql['page_modules'], 'UNIQUE KEY page_generator (page_id, generator_id)') !== false &&
	strpos($page_sql['page_modules'], 'REFERENCES pages (id)') !== false &&
	strpos($page_sql['page_modules'], 'REFERENCES generator (id)') !== false);
check('module_permissions: one line per role x module (unique, foreign keys)',
	strpos($page_sql['module_permissions'], 'UNIQUE KEY role_generator (role_id, generator_id)') !== false &&
	strpos($page_sql['module_permissions'], 'REFERENCES roles (id)') !== false);
check('generator lines: 3 in single_generator_per_page, 6 in page_with_modules',
	count(labo_first_setup_generator_lines('single_generator_per_page')) === 3 &&
	count(labo_first_setup_generator_lines('page_with_modules')) === 6);
$displays_ok = true;
foreach (labo_first_setup_generator_lines('page_with_modules') as $line) {
	$displays_ok = $displays_ok && is_array(json_decode($line['display'], true));
}
check('every generator line has a valid display JSON', $displays_ok);

// ---------------------------------------------------------------------------
section('4. Installer run (fake database)');

$db = new FakeDb();
$db->installed_mode = 'single_generator_per_page';
labo_create_basis_tables('single_generator_per_page');
check('single_generator_per_page: no page tables created', $db->created_tables() === array('base', 'labo_settings', 'generator', 'roles', 'users'));
$settings = $db->executed_with('INSERT IGNORE INTO labo_settings');
check('mode saved at installation', count($settings) === 1 && $settings[0]['values']['v'] === 'single_generator_per_page');
check('single_generator_per_page: no page or permission line', count($db->executed_with('page_modules')) === 0);

$db = new FakeDb();
$db->installed_mode = 'page_with_modules';
labo_create_basis_tables('page_with_modules');
check('page_with_modules: page tables created before users', $db->created_tables() === array('base', 'labo_settings', 'generator', 'roles', 'pages', 'page_modules', 'module_permissions', 'users'));
check('page_with_modules: 6 modules on the Administration page', count($db->executed_with('INSERT IGNORE INTO page_modules')) === 6);
check('page_with_modules: 6 permissions for sa', count($db->executed_with('INSERT IGNORE INTO module_permissions')) === 6);

$db = new FakeDb();
$db->installed_mode = 'single_generator_per_page';
$stopped = false;
try {
	labo_create_basis_tables('page_with_modules');
} catch (RuntimeException $e) {
	$stopped = $e->getMessage() === 'data_mode_mismatch';
}
check('interrupted first opening with another mode => stopped', $stopped);
check('... before generator, roles or users are created', $db->created_tables() === array('base', 'labo_settings'));

$stopped = false;
try {
	labo_create_basis_tables('pages');
} catch (RuntimeException $e) {
	$stopped = $e->getMessage() === 'data_mode_invalid';
}
check('invalid mode => nothing created', $stopped);

// ---------------------------------------------------------------------------
section('5. Menu grouping (page_with_modules)');

function line ($page, $gid, $view, $edit, $add, $delete, $g = array(1, 1, 1), $order = 0, $position = 0) {
	return array(
		'page_id' => $page, 'page_title' => 'Page ' . $page, 'page_order' => $order,
		'generator_id' => $gid, 'module_title' => 'Module ' . $gid, 'table_name' => 't' . $gid, 'position' => $position,
		'perm_view' => $view, 'perm_edit' => $edit, 'perm_add' => $add, 'perm_delete' => $delete,
		'g_edit' => $g[0], 'g_add' => $g[1], 'g_delete' => $g[2]
	);
}

// "Students" page: module 10 for role A (read only) and role B (edit); module 11 only role B; module 12 not viewable
$pages = labo_group_page_modules(array(
	line(1, 10, 1, 0, 0, 0, array(1, 1, 1), 0, 1),
	line(1, 10, 1, 1, 0, 0, array(1, 1, 1), 0, 1),
	line(1, 11, 1, 1, 1, 1, array(1, 0, 2), 0, 2),
	line(1, 12, 0, 1, 1, 1),
	line(2, 20, 1, 0, 0, 0, array(1, 1, 1), 5),
	line(3, 30, 0, 0, 0, 0)
));
check('one menu entry per page, even with several roles and modules', array_keys($pages) === array(1, 2));
check('page without any viewable module is not in the menu', !isset($pages[3]));
check('modules of a page in order, not viewable ones left out', array_keys($pages[1]['modules']) === array(10, 11));
check('rights of several roles combined (edit given by one role)', $pages[1]['modules'][10]['can_edit'] === 1);
check('role right AND generator line: no add if the generator line forbids it', $pages[1]['modules'][11]['can_add'] === 0);
check('can_delete keeps the generator value (2 = logical delete)', $pages[1]['modules'][11]['can_delete'] === 2);
check('no right given => none', $pages[1]['modules'][10]['can_add'] === 0 && $pages[1]['modules'][10]['can_delete'] === 0);
$ordered = labo_group_page_modules(array(line(7, 1, 1, 0, 0, 0, array(1, 1, 1), 9), line(8, 2, 1, 0, 0, 0, array(1, 1, 1), 1)));
check('pages ordered by tab_order', array_keys($ordered) === array(8, 7));

list($authorized, $authorized_pages) = labo_page_authorizations($pages);
check('session authorizations only for viewable modules', array_keys($authorized) === array(10, 11, 20));
check('page lists its modules in order', array_column($authorized_pages[1], 'generator_id') === array(10, 11));

// ---------------------------------------------------------------------------
section('6. Permission checks of requests (page_with_modules)');

$display_roles = array('me');
check('page read with get_content', labo_check_page_index('page_1', 'get_content', $authorized, $authorized_pages, $display_roles)['type'] === 'page');
check('page index refused for writes (save_row)', labo_check_page_index('page_1', 'save_row', $authorized, $authorized_pages, $display_roles) === null);
check('page not authorized', labo_check_page_index('page_3', 'get_content', $authorized, $authorized_pages, $display_roles) === null);
check('authorized module => its rights', labo_check_page_index('11', 'save_row', $authorized, $authorized_pages, $display_roles)['authorisation']['can_add'] === 0);
check('module not viewable (12) refused, even for reading', labo_check_page_index('12', 'get_content', $authorized, $authorized_pages, $display_roles) === null);
check('injected index refused', labo_check_page_index('10 OR 1=1', 'get_content', $authorized, $authorized_pages, $display_roles) === null);
check('malformed page index refused', labo_check_page_index('page_1x', 'get_content', $authorized, $authorized_pages, $display_roles) === null);
check('empty index refused', labo_check_page_index('', 'get_content', $authorized, $authorized_pages, $display_roles) === null);
check('homepage of an own role', labo_check_page_index('homepage_me', 'get_content', $authorized, $authorized_pages, $display_roles)['type'] === 'homepage');
check('homepage of another role refused', labo_check_page_index('homepage_sa', 'get_content', $authorized, $authorized_pages, $display_roles) === null);

$_SESSION = array('roles' => array('me', 'pa', 'max_level' => 10));
check('real roles of the session (max_level left out)', labo_session_user_roles() === array('me', 'pa'));

// ---------------------------------------------------------------------------
section('7. Every read and write action goes through check_hability.php');

$root = __DIR__ . '/..';
foreach (array('modules/inc/get_content.php', 'modules/inc/save_row.php', 'modules/inc/delete_row.php', 'modules/inc/restore_row.php') as $file) {
	check($file . ' includes check_hability.php', preg_match('/(require|include)(_once)?\s*\(?\s*\'inc\/check_hability\.php\'/', file_get_contents($root . '/' . $file)) === 1);
}
$check = file_get_contents($root . '/modules/inc/check_hability.php');
check('check_hability.php stops a request without index (exit, not return)', preg_match('/\$_POST\[\'index\'\][^\n]*\)\s*\{\s*exit\(/', $check) === 1);
check('save_row.php checks the module rights in page_with_modules', strpos(file_get_contents($root . '/modules/inc/save_row.php'), "\$authorisation['can_add'] !== 1") !== false);

// ---------------------------------------------------------------------------
section('8. GENERATOR editor: table_name and form selects (createInput)');

require_once $root . '/modules/inc/constructor_swap_functions.php';

$generator_display = json_decode(labo_first_setup_generator_lines('page_with_modules')[0]['display'], true);
$generator_inputs = array();
foreach ($generator_display as $entry) {
	$generator_inputs[$entry['column_name']] = $entry;
}
check('GENERATOR line: table_name is a db_selector on information_schema.tables',
	$generator_inputs['table_name']['input']['type'] === 'db_selector' &&
	$generator_inputs['table_name']['input']['table'] === 'information_schema.tables' &&
	$generator_inputs['table_name']['label'] === 'table_name ⚠️ AS => tb');
check('GENERATOR line: form is a select (row, column, table)', $generator_inputs['form']['input']['type'] === 'select' &&
	array_column($generator_inputs['form']['input']['options'], 'value') === array('row', 'column', 'table'));
check('GENERATOR line: order of the display kept', array_keys($generator_display)[2] === 3 && $generator_display['7']['column_name'] === 'form');

$customised = '{"1":{"column_name":"table_name","input":{"type":"textarea"}},"2":{"column_name":"form","input":{"type":"text"}}}';
$upgraded = json_decode(labo_generator_editor_display($customised, true), true);
check('upgrade: customised input kept, text input replaced', $upgraded['1']['input']['type'] === 'textarea' && $upgraded['2']['input']['type'] === 'select');
check('upgrade: invalid display refused', labo_generator_editor_display('{not json', true) === false);

// fake database for db_selector: records the request, answers the given rows
class FakeSelectorStatement {
	public function __construct ($rows) { $this->rows = $rows; }
	public $rows;
	public function execute ($values = array()) { return true; }
	public function fetchAll ($mode = null) { return $this->rows; }
}
class FakeSelectorDb {
	public $sql = array();
	public $rows = array();
	public function prepare ($sql) { $this->sql[] = $sql; return new FakeSelectorStatement($this->rows); }
}
$installer_db = $db;
$db = new FakeSelectorDb();
$db->rows = array(array('name' => 'generator'), array('name' => 'users'));
$display_mode = 'row';

$tb = $generator_inputs['table_name'];
$html = createInput($tb, 'users', 'generator', 1, true);
check('db_selector no_logical_delete: no logical_delete condition',
	end($db->sql) === 'SELECT  table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY name ASC');
check('db_selector: one option per table, value selected', strpos($html, '<option value="users" selected') !== false && strpos($html, '<option value="generator" >') !== false);
check('db_selector: known value not duplicated', strpos($html, 'data-unknown_value') === false);
$html = createInput($tb, '/plugins/pdf/define_content.php', 'generator', 1, true);
check('db_selector keep_unknown_value: plugin path kept and selected', strpos($html, '<option value="/plugins/pdf/define_content.php" selected data-unknown_value>') !== false);
$db->rows = array();
createInput(array('column_name' => 'role_id', 'input' => array('type' => 'db_selector', 'table' => 'roles', 'option_text_on' => 'role_name', 'value_on' => 'id')), 1, 'users', 1, true);
check('db_selector without no_logical_delete: logical_delete condition kept', strpos(end($db->sql), 'WHERE logical_delete = 0') !== false);

$form = $generator_inputs['form'];
$html = createInput($form, 'table', 'generator', 1, true);
check('select: value selected, no unknown option', strpos($html, '<option value="table" selected>') !== false && strpos($html, 'data-unknown_value') === false);
$html = createInput($form, 'table|headertabs§type', 'generator', 1, true);
check('select keep_unknown_value: form with |complement kept (label encoding undone)',
	strpos($html, '<option value="table|headertabs§type" selected data-unknown_value>') !== false && strpos($html, '<option value="table" selected>') === false);
check('select: disabled when not editable', strpos(createInput($form, 'row', 'generator', 1, false), ' disabled') !== false);
$html = createInput(array('column_name' => 'x', 'input' => array('type' => 'select', 'options' => array(array('value' => 'a', 'text' => 'A', 'attributes' => array('data-x' => 'y', 'hidden'))))), 'a', 't', 1, true);
check('select: option attributes as names and values', strpos($html, '<option value="a" selected data-x="y" hidden>') !== false);
check('select without keep_unknown_value: unknown value not added', strpos(createInput(array('column_name' => 'form', 'input' => array_diff_key($form['input'], array('keep_unknown_value' => 1))), 'other', 'generator', 1, true), 'data-unknown_value') === false);

$db = $installer_db;

// ---------------------------------------------------------------------------
section('9. js_include / css_include of the generator lines');

$generator_columns = array_column($generator_display, 'column_name');
check('GENERATOR line: js_include field just after css_include',
	array_search('js_include', $generator_columns, true) === array_search('css_include', $generator_columns, true) + 1);
check('GENERATOR line: display numbered from 1 without gap', array_keys($generator_display) === range(1, count($generator_display)));
check('generator table: js_include column after css_include',
	strpos(labo_basis_tables_sql('page_with_modules')['generator'], "css_include TEXT NULL,\n\t\tjs_include TEXT NULL,") !== false);
$upgraded_twice = array_column(json_decode(labo_generator_editor_display(labo_generator_editor_display($customised, true), true), true), 'column_name');
check('upgrade: js_include added once (no css_include => at the end)',
	count(array_keys($upgraded_twice, 'js_include')) === 1 && end($upgraded_twice) === 'js_include');

$cwd = getcwd();
chdir($root . '/modules');
$generator_css = file_get_contents('generator/css/GENERATOR.css');
check('css file-name => content of generator/css/name', generatorIncludeContent('file-GENERATOR.css', 'css', true) === $generator_css);
check('css file:name => content of generator/css/name', generatorIncludeContent('file:GENERATOR.css', 'css', true) === $generator_css);
check('css code => the code itself', generatorIncludeContent('h1 { color: red; }', 'css', true) === 'h1 { color: red; }');
check('js code => the code itself', generatorIncludeContent('console.log(1);', 'js') === 'console.log(1);');
check('js missing file => nothing (not the name as code)', generatorIncludeContent('file:missing.js', 'js') === '');
check('js file out of generator/js refused', generatorIncludeContent('file:../inc/users_save.php', 'js') === '' &&
	generatorIncludeContent('file:../../../core/connect_db.php', 'js') === '');
check('empty include => nothing', generatorIncludeContent(null, 'js') === '' && generatorIncludeContent('', 'css', true) === '');
chdir($cwd);

// ---------------------------------------------------------------------------
echo "\n$labo_tests_count tests, $labo_tests_failed failed\n";
exit($labo_tests_failed === 0 ? 0 : 1);
