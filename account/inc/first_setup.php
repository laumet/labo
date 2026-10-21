<?php
// first opening of LABO: while the users table doesn't exist, the installation account
// (admin / see readme.txt) opens the create account flow to create the first administrator
// the basis tables are created when this first administrator is saved, for the data management mode
// of default/labo_settings.json (page_with_modules adds pages, page_modules and module_permissions)

// hash of the installation password (never store the password itself)
const LABO_FIRST_SETUP_USERNAME = 'admin';
const LABO_FIRST_SETUP_PASSWORD_HASH = '$2y$12$ZKIUUnpgE1Lg4X4Aj0F89e8/tCzkLvmygwZw0rFDgQDxTsPHzKPbS';

// columns of every table (same as the base table, model for new tables: CREATE TABLE x LIKE base)
const LABO_BASE_COLUMNS = '
	id INT(11) NOT NULL AUTO_INCREMENT,
	creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
	modification_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	logical_delete INT(4) DEFAULT 0,
	can_not_be_deleted INT(4) DEFAULT 0,
';
const LABO_TABLE_OPTIONS = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

// inputs of the GENERATOR line for table_name (tb) and form: selects with all the tables and the display modes
// keep_unknown_value keeps the values out of the lists (plugins, views, form with |complement)
function labo_generator_editor_inputs () {
	return array(
		'table_name' => array(
			'type' => 'db_selector', 'table' => 'information_schema.tables', 'no_logical_delete' => true,
			'select' => 'table_name AS name', 'where' => 'table_schema = DATABASE()', 'order' => 'name ASC',
			'value_on' => 'name', 'option_text_on' => 'name', 'placeholder' => 'Choisir une table', 'keep_unknown_value' => true
		),
		'form' => array(
			'type' => 'select', 'keep_unknown_value' => true,
			'options' => array(
				'0' => array('value' => 'row', 'text' => 'row'),
				'1' => array('value' => 'column', 'text' => 'column'),
				'2' => array('value' => 'table', 'text' => 'table')
			)
		)
	);
}

// display JSON of the GENERATOR line with the inputs of labo_generator_editor_inputs (labels and order kept)
// and the js_include field (after css_include) if missing; the entries are numbered again from 1
// $only_text: replace only the inputs still of type text (not customised), for existing installations
// returns false if the display is not valid JSON
function labo_generator_editor_display ($display, $only_text = false) {
	$entries = json_decode($display, true);
	if (!is_array($entries)) {
		return false;
	}
	$entries = array_values($entries);
	$columns = array_map(function ($entry) { return is_array($entry) && isset($entry['column_name']) ? $entry['column_name'] : null; }, $entries);
	if (!in_array('js_include', $columns, true)) {
		$js_entry = array('column_name' => 'js_include', 'label' => 'include for JS', 'input' => array('type' => 'text'));
		$position = array_search('css_include', $columns, true);
		array_splice($entries, $position === false ? count($entries) : $position + 1, 0, array($js_entry));
	}
	// keys "1", "2"... (as the display editor saves them)
	$entries = array_combine(range(1, count($entries)), $entries);
	$inputs = labo_generator_editor_inputs();
	foreach ($entries as $k => $entry) {
		if (!is_array($entry) || !isset($entry['column_name']) || !isset($inputs[$entry['column_name']])) {
			continue;
		}
		if ($only_text && isset($entry['input']['type']) && $entry['input']['type'] !== 'text') {
			continue;
		}
		$entries[$k]['input'] = $inputs[$entry['column_name']];
	}
	return json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// first lines of the generator table, inserted in this order (ids 1, 2, 3, then 4, 5, 6 in page_with_modules)
// columns not given keep their default value
function labo_first_setup_generator_lines ($mode = LABO_DATA_MODE_DEFAULT) {
	$lines = array(
		// GENERATOR: edit the generator table itself
		array(
			'text_id' => '', 'logical_delete' => 0, 'can_not_be_deleted' => 1,
			'title' => 'GENERATOR', 'table_name' => 'generator', 'user_type' => 'sa', 'generator_see_as' => '1',
			'page' => 'labo', 'form' => 'row', 'section' => 1, 'position_in_section' => 1,
			'request_select' => '*', 'request_from' => '', 'request_where' => '', 'request_order' => '', 'request_values' => '',
			'display' => labo_generator_editor_display(<<<'JSON'
{"1":{"column_name":"title","label":"title","input":{"type":"text"}},"2":{"column_name":"text_id","label":"text_id","input":{"type":"text"}},"3":{"column_name":"table_name","label":"table_name ⚠️ AS => tb","input":{"type":"text"}},"4":{"column_name":"user_type","label":"user_type","input":{"type":"text"}},"5":{"column_name":"generator_see_as","label":"see_as","input":{"type":"text"}},"6":{"column_name":"page","label":"page","input":{"type":"text"}},"7":{"column_name":"form","label":"form","input":{"type":"text"}},"8":{"column_name":"section","label":"section","input":{"type":"text"}},"9":{"column_name":"position_in_section","label":"position_in_section","input":{"type":"text"}},"10":{"column_name":"request_select","label":"request_select ⚠️ selecting 'id' is required","input":{"type":"text"}},"11":{"column_name":"request_from","label":"request_from ⚠️ added to table_name !","input":{"type":"text"}},"12":{"column_name":"request_where","label":"request_where","input":{"type":"text"}},"13":{"column_name":"request_order","label":"request_order","input":{"type":"text"}},"14":{"column_name":"request_values","label":"request_values","input":{"type":"text"}},"15":{"column_name":"display","label":"display","input":{"type":"json"}},"16":{"column_name":"css_include","label":"include for CSS","input":{"type":"text"}},"17":{"column_name":"save_include","label":"include when saving row","input":{"type":"text"}},"18":{"column_name":"container_start","label":"container_start","input":{"type":"text"}},"19":{"column_name":"after_start_include","label":"include just after creating content","input":{"type":"text"}},"20":{"column_name":"before_end_include","label":"include just before ending content","input":{"type":"text"}},"21":{"column_name":"container_end","label":"container_end","input":{"type":"text"}},"22":{"column_name":"counter_rows","label":"counter_rows","input":{"type":"radio","content":{"0":"off","1":"on"}}},"23":{"column_name":"can_edit","label":"can_edit","input":{"type":"radio","content":{"0":"off","1":"on"}}},"24":{"column_name":"can_add","label":"can_add","input":{"type":"radio","content":{"0":"off","1":"on"}}},"25":{"column_name":"can_delete","label":"can_delete","input":{"type":"radio","content":{"0":"off","1":"on","2":"logical_delete"}}},"26":{"column_name":"can_filter","label":"can_filter","input":{"type":"radio","content":{"0":"off","1":"on"}}},"27":{"column_name":"can_refresh","label":"can_refresh","input":{"type":"radio","content":{"0":"off","1":"on"}}},"28":{"column_name":"edit_by_row_mode","label":"edit_by_row_mode","input":{"type":"radio","content":{"0":"off","1":"on"}}},"29":{"column_name":"plugin_pdf_button","label":"plugin_pdf_button","input":{"type":"radio","content":{"0":"off","1":"on"}}},"30":{"column_name":"plugin_pdf_params","label":"plugin_pdf_params","input":{"type":"json"}},"31":{"column_name":"conditional_display","label":"conditional_display","input":{"type":"json"}},"32":{"column_name":"plugin_table_export","label":"plugin_table_export","input":{"type":"select","options":{"0":{"value":"0","text":"no_export","attributes":{"selected":""}},"1":{"value":"1","text":"csv"},"2":{"value":"2","text":"xlsx"}}}}}
JSON),
			'css_include' => 'file-GENERATOR.css', 'js_include' => '', 'save_include' => '',
			'container_start' => '', 'after_start_include' => '', 'before_end_include' => '', 'container_end' => '',
			'counter_rows' => 0, 'can_edit' => 1, 'can_add' => 1, 'can_delete' => 0, 'can_filter' => 1, 'can_refresh' => 0, 'edit_by_row_mode' => 0,
			'conditional_display' => 'null', 'in_case_of_null' => null, 'reset_session' => null
		),
		// Statuts: edit the roles
		array(
			'text_id' => '', 'logical_delete' => 0, 'can_not_be_deleted' => 1,
			'title' => 'Statuts', 'table_name' => 'roles', 'user_type' => 'sa', 'generator_see_as' => null,
			'page' => 'labo', 'form' => 'row', 'section' => 2, 'position_in_section' => 2,
			'request_select' => '*', 'request_from' => '', 'request_where' => '', 'request_order' => 'tab_order ASC', 'request_values' => '',
			'display' => <<<'JSON'
{"1":{"column_name":"role_name","label":"Statut","input":{"type":"text"}},"2":{"column_name":"role_short_name","label":"Nom court","input":{"type":"text","attributes":{"disabled":""}}},"3":{"column_name":"role_short_name_display","label":"Nom court (affichage)","input":{"type":"text"}},"4":{"column_name":"two_factors_authentication","label":"Authentificaton à deux facteurs","input":{"type":"radio","content":{"0":"Authentification simple","1":"Authentification double"}}},"5":{"column_name":"tab_order","label":"Ordre d'apparition","input":{"type":"number"}},"6":{"column_name":"level","label":"Niveau","input":{"type":"text"}}}
JSON,
			'css_include' => '', 'save_include' => '',
			'container_start' => '', 'after_start_include' => '', 'before_end_include' => '', 'container_end' => '',
			'counter_rows' => 0, 'can_edit' => 1, 'can_add' => 1, 'can_delete' => 0, 'can_filter' => 1, 'can_refresh' => 0, 'edit_by_row_mode' => 0,
			'conditional_display' => 'null', 'in_case_of_null' => null, 'reset_session' => null
		),
		// Utilisateurs: list of the users, roles saved by generator/inc/users_save.php
		array(
			'text_id' => '', 'logical_delete' => 0, 'can_not_be_deleted' => 0,
			'title' => 'Utilisateurs', 'table_name' => 'users', 'user_type' => 'sa', 'generator_see_as' => '',
			'page' => 'labo', 'form' => 'table', 'section' => 2, 'position_in_section' => 1,
			'request_select' => '*', 'request_from' => '', 'request_where' => '', 'request_order' => '', 'request_values' => '',
			'display' => <<<'JSON'
{"1":{"column_name":"name","label":"Prénom","input":{"type":"display"}},"2":{"column_name":"lastname","label":"Nom","input":{"type":"display"}},"3":{"column_name":"username","label":"E-mail","input":{"type":"mailto"}},"4":{"column_name":"roles","label":"Statut·s","input":{"type":"db_checkboxes","table":"roles","where":"level <= :level","order":"level ASC","values_on":"role_short_name","text_on":"role_name","request_values":{"level":"SESSION=>roles|max_level"},"attributes":{"disabled":""},"save_method":{"type":"implode","attribute":","}}}}
JSON,
			'css_include' => '', 'save_include' => 'generator/inc/users_save.php',
			'container_start' => '<div class="users">', 'after_start_include' => '', 'before_end_include' => '', 'container_end' => '</div>',
			'counter_rows' => 0, 'can_edit' => 1, 'can_add' => 1, 'can_delete' => 1, 'can_filter' => 1, 'can_refresh' => 0, 'edit_by_row_mode' => 0,
			'conditional_display' => 'null', 'in_case_of_null' => null, 'reset_session' => null,
			'plugin_pdf_button' => 0, 'plugin_pdf_params' => 'null', 'plugin_table_export' => 2
		)
	);

	if ($mode === 'page_with_modules') {
		// administration of the logical pages, their modules and the permissions of the roles
		$page_line = array(
			'text_id' => '', 'logical_delete' => 0, 'can_not_be_deleted' => 1,
			'user_type' => 'sa', 'generator_see_as' => '', 'page' => 'labo', 'form' => 'table', 'section' => 3,
			'request_select' => '*', 'request_from' => '', 'request_where' => '', 'request_values' => '',
			'css_include' => '', 'save_include' => '',
			'container_start' => '', 'after_start_include' => '', 'before_end_include' => '', 'container_end' => '',
			'counter_rows' => 0, 'can_edit' => 1, 'can_add' => 1, 'can_delete' => 2, 'can_filter' => 1, 'can_refresh' => 0, 'edit_by_row_mode' => 0,
			'conditional_display' => 'null', 'in_case_of_null' => null, 'reset_session' => null
		);
		$lines[] = array_merge($page_line, array(
			'title' => 'Pages', 'table_name' => 'pages', 'position_in_section' => 1, 'request_order' => 'tab_order ASC',
			'display' => <<<'JSON'
{"1":{"column_name":"title","label":"Titre","input":{"type":"text"}},"2":{"column_name":"slug","label":"Identifiant (slug)","input":{"type":"text"}},"3":{"column_name":"tab_order","label":"Ordre dans le menu","input":{"type":"number"}},"4":{"column_name":"enabled","label":"Active","input":{"type":"radio","content":{"0":"off","1":"on"}}}}
JSON
		));
		$lines[] = array_merge($page_line, array(
			'title' => 'Modules des pages', 'table_name' => 'page_modules', 'position_in_section' => 2, 'request_order' => 'page_id ASC, position ASC',
			'display' => <<<'JSON'
{"1":{"column_name":"page_id","label":"Page","input":{"type":"db_selector","table":"pages","value_on":"id","option_text_on":"title","order":"tab_order ASC"}},"2":{"column_name":"generator_id","label":"Module (ligne du generator)","input":{"type":"db_selector","table":"generator","value_on":"id","option_text_on":"title","order":"title ASC"}},"3":{"column_name":"position","label":"Position dans la page","input":{"type":"number"}},"4":{"column_name":"title","label":"Titre affiché (optionnel)","input":{"type":"text"}}}
JSON
		));
		$lines[] = array_merge($page_line, array(
			'title' => 'Permissions des modules', 'table_name' => 'module_permissions', 'position_in_section' => 3, 'request_order' => 'generator_id ASC, role_id ASC',
			'display' => <<<'JSON'
{"1":{"column_name":"role_id","label":"Statut","input":{"type":"db_selector","table":"roles","value_on":"id","option_text_on":"role_name","order":"tab_order ASC"}},"2":{"column_name":"generator_id","label":"Module (ligne du generator)","input":{"type":"db_selector","table":"generator","value_on":"id","option_text_on":"title","order":"title ASC"}},"3":{"column_name":"can_view","label":"Voir","input":{"type":"radio","content":{"0":"off","1":"on"}}},"4":{"column_name":"can_edit","label":"Modifier","input":{"type":"radio","content":{"0":"off","1":"on"}}},"5":{"column_name":"can_add","label":"Ajouter","input":{"type":"radio","content":{"0":"off","1":"on"}}},"6":{"column_name":"can_delete","label":"Supprimer","input":{"type":"radio","content":{"0":"off","1":"on"}}}}
JSON
		));
	}

	return $lines;
}

function labo_users_table_exists () {
	global $db;

	$d_table = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t');
	$d_table->execute(array('t' => 'users'));
	return $d_table->fetchColumn() > 0;
}

// CREATE TABLE statements of the first setup, in creation order, for a data management mode
// users comes last: its existence ends the first opening
function labo_basis_tables_sql ($mode) {
	$tables = array();

	// model for new tables
	$tables['base'] = 'CREATE TABLE IF NOT EXISTS base (' . LABO_BASE_COLUMNS . '
		PRIMARY KEY (id)
	)' . LABO_TABLE_OPTIONS;

	// settings fixed at installation (data_management_mode)
	$tables['labo_settings'] = 'CREATE TABLE IF NOT EXISTS labo_settings (' . LABO_BASE_COLUMNS . '
		setting_key VARCHAR(100) NOT NULL,
		setting_value VARCHAR(255) NULL DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY setting_key (setting_key)
	)' . LABO_TABLE_OPTIONS;

	// definition of the LABO blocs / modules (the generator table names its date column change_date)
	$tables['generator'] = 'CREATE TABLE IF NOT EXISTS generator (
		id INT(11) NOT NULL AUTO_INCREMENT,
		text_id VARCHAR(100) NULL DEFAULT NULL,
		creation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
		change_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		logical_delete INT(4) DEFAULT 0,
		can_not_be_deleted INT(4) DEFAULT 0,
		title VARCHAR(255) NULL DEFAULT NULL,
		table_name VARCHAR(255) NULL DEFAULT NULL,
		user_type VARCHAR(50) NULL DEFAULT NULL,
		generator_see_as VARCHAR(50) NULL DEFAULT NULL,
		page VARCHAR(50) NULL DEFAULT NULL,
		form VARCHAR(50) NULL DEFAULT NULL,
		section INT(11) NULL DEFAULT NULL,
		position_in_section INT(11) NULL DEFAULT NULL,
		request_select TEXT NULL,
		request_from TEXT NULL,
		request_where TEXT NULL,
		request_order TEXT NULL,
		request_values TEXT NULL,
		display LONGTEXT NULL,
		css_include TEXT NULL,
		js_include TEXT NULL,
		save_include TEXT NULL,
		container_start TEXT NULL,
		after_start_include TEXT NULL,
		before_end_include TEXT NULL,
		container_end TEXT NULL,
		counter_rows INT(4) DEFAULT 0,
		can_edit INT(4) DEFAULT 0,
		can_add INT(4) DEFAULT 0,
		can_delete INT(4) DEFAULT 0,
		can_filter INT(4) DEFAULT 0,
		can_refresh INT(4) DEFAULT 0,
		edit_by_row_mode INT(4) DEFAULT 0,
		conditional_display TEXT NULL,
		in_case_of_null TEXT NULL,
		reset_session VARCHAR(100) NULL DEFAULT NULL,
		plugin_pdf_button INT(4) DEFAULT 0,
		plugin_pdf_params TEXT NULL,
		plugin_table_export INT(4) DEFAULT 0,
		PRIMARY KEY (id)
	)' . LABO_TABLE_OPTIONS;

	// roles of the users
	$tables['roles'] = 'CREATE TABLE IF NOT EXISTS roles (' . LABO_BASE_COLUMNS . '
		level INT(11) DEFAULT 0,
		role_name VARCHAR(100) NULL DEFAULT NULL,
		role_short_name VARCHAR(20) NOT NULL,
		role_short_name_display VARCHAR(20) NULL DEFAULT NULL,
		role_short_name_html TEXT NULL,
		two_factors_authentication INT(4) DEFAULT 0,
		tab_order INT(11) DEFAULT 0,
		PRIMARY KEY (id),
		UNIQUE KEY role_short_name (role_short_name)
	)' . LABO_TABLE_OPTIONS;

	if ($mode === 'page_with_modules') {
		// logical pages of the menu
		$tables['pages'] = 'CREATE TABLE IF NOT EXISTS pages (' . LABO_BASE_COLUMNS . '
			slug VARCHAR(100) NOT NULL,
			title VARCHAR(255) NOT NULL,
			tab_order INT(11) DEFAULT 0,
			enabled INT(4) DEFAULT 1,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		)' . LABO_TABLE_OPTIONS;

		// modules (generator lines) of a page, in display order
		$tables['page_modules'] = 'CREATE TABLE IF NOT EXISTS page_modules (' . LABO_BASE_COLUMNS . '
			page_id INT(11) NOT NULL,
			generator_id INT(11) NOT NULL,
			position INT(11) DEFAULT 0,
			title VARCHAR(255) NULL DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY page_generator (page_id, generator_id),
			KEY generator_id (generator_id),
			CONSTRAINT page_modules_page FOREIGN KEY (page_id) REFERENCES pages (id),
			CONSTRAINT page_modules_generator FOREIGN KEY (generator_id) REFERENCES generator (id)
		)' . LABO_TABLE_OPTIONS;

		// what a role can do with a module (combined with the can_* columns of the generator line)
		$tables['module_permissions'] = 'CREATE TABLE IF NOT EXISTS module_permissions (' . LABO_BASE_COLUMNS . '
			role_id INT(11) NOT NULL,
			generator_id INT(11) NOT NULL,
			can_view INT(4) DEFAULT 0,
			can_edit INT(4) DEFAULT 0,
			can_add INT(4) DEFAULT 0,
			can_delete INT(4) DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY role_generator (role_id, generator_id),
			KEY generator_id (generator_id),
			CONSTRAINT module_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id),
			CONSTRAINT module_permissions_generator FOREIGN KEY (generator_id) REFERENCES generator (id)
		)' . LABO_TABLE_OPTIONS;
	}

	$tables['users'] = 'CREATE TABLE IF NOT EXISTS users (' . LABO_BASE_COLUMNS . '
		title VARCHAR(50) NULL DEFAULT NULL,
		name VARCHAR(100) NULL DEFAULT NULL,
		lastname VARCHAR(100) NULL DEFAULT NULL,
		address VARCHAR(255) NULL DEFAULT NULL,
		post_number VARCHAR(10) NULL DEFAULT NULL,
		city VARCHAR(100) NULL DEFAULT NULL,
		username VARCHAR(255) NOT NULL,
		password VARCHAR(255) NOT NULL,
		must_be_validated INT(4) DEFAULT 0,
		roles VARCHAR(255) NOT NULL DEFAULT "me",
		max_level INT(11) DEFAULT 10,
		two_factors_authentication INT(4) DEFAULT 0,
		lang CHAR(2) NULL DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY username (username)
	)' . LABO_TABLE_OPTIONS;

	return $tables;
}

// create the basis tables and their first lines (safe to run again after an interrupted first opening:
// IF NOT EXISTS, INSERT IGNORE, existing lines kept)
function labo_create_basis_tables ($mode) {
	global $db;

	if (!in_array($mode, LABO_DATA_MODES, true)) {
		throw new RuntimeException('data_mode_invalid');
	}

	foreach (labo_basis_tables_sql($mode) as $table => $sql) {
		$db->exec($sql);
		labo_fill_basis_table($table, $mode);
	}
}

// first lines of a basis table
function labo_fill_basis_table ($table, $mode) {
	global $db;

	switch ($table) {
		case 'labo_settings' :
			// the mode is fixed here for the whole life of the installation
			$i_mode = $db->prepare('INSERT IGNORE INTO labo_settings (can_not_be_deleted, setting_key, setting_value) VALUES (1, :k, :v)');
			$i_mode->execute(array('k' => 'data_management_mode', 'v' => $mode));
			if (labo_installed_data_mode($db) !== $mode) {
				// an interrupted first opening was started with another mode
				throw new RuntimeException('data_mode_mismatch');
			}
			break;
		case 'generator' :
			// a line already there is not inserted again
			$d_line = $db->prepare('SELECT COUNT(*) FROM generator WHERE table_name = :table_name AND user_type = :user_type AND page = :page');
			foreach (labo_first_setup_generator_lines($mode) as $line) {
				$d_line->execute(array('table_name' => $line['table_name'], 'user_type' => $line['user_type'], 'page' => $line['page']));
				if ($d_line->fetchColumn() > 0) {
					continue;
				}
				$columns = array_keys($line);
				$i_line = $db->prepare('INSERT INTO generator (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')');
				$i_line->execute($line);
			}
			break;
		case 'roles' :
			$db->exec('
				INSERT IGNORE INTO roles (logical_delete, can_not_be_deleted, level, role_name, role_short_name, role_short_name_display, role_short_name_html, two_factors_authentication, tab_order)
				VALUES (0, 0, 100, "super-admin", "sa", "SA", NULL, 1, 0)
			');
			break;
		case 'module_permissions' :
			// page_with_modules: one page "Administration" with the administration modules, all rights for sa
			$db->exec('INSERT IGNORE INTO pages (can_not_be_deleted, slug, title, tab_order, enabled) VALUES (1, "administration", "Administration", 0, 1)');
			$page_id = $db->query('SELECT id FROM pages WHERE slug = "administration"')->fetchColumn();
			$role_id = $db->query('SELECT id FROM roles WHERE role_short_name = "sa"')->fetchColumn();
			$d_module = $db->prepare('SELECT id FROM generator WHERE table_name = :table_name AND user_type = "sa" AND page = "labo" ORDER BY id LIMIT 1');
			$i_page_module = $db->prepare('INSERT IGNORE INTO page_modules (page_id, generator_id, position) VALUES (:page_id, :generator_id, :position)');
			$i_permission = $db->prepare('INSERT IGNORE INTO module_permissions (role_id, generator_id, can_view, can_edit, can_add, can_delete) VALUES (:role_id, :generator_id, 1, 1, 1, 1)');
			$position = 1;
			foreach (array('generator', 'roles', 'users', 'pages', 'page_modules', 'module_permissions') as $module_table) {
				$d_module->execute(array('table_name' => $module_table));
				$generator_id = $d_module->fetchColumn();
				if ($generator_id === false) {
					continue;
				}
				$i_page_module->execute(array('page_id' => $page_id, 'generator_id' => $generator_id, 'position' => $position++));
				$i_permission->execute(array('role_id' => $role_id, 'generator_id' => $generator_id));
			}
			break;
	}
}

// fields of the first administrator form (the generator table doesn't exist yet)
function labo_first_setup_form () {
	$fields = array(
		'name' => 'text',
		'lastname' => 'text',
		'username' => 'email'
	);

	$form = array();
	foreach ($fields as $column => $type) {
		$form[] = array(
			'column_name' => $column,
			'label' => labo_t('setup.' . $column),
			'input' => array(
				'type' => $type,
				'placeholder' => labo_t('setup.' . $column),
				'attribute' => 'required'
			)
		);
	}
	return $form;
}
