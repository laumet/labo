<?php
// upgrade of an existing installation:
// - column js_include of the generator table (after css_include)
// - table_name (tb) and form of the GENERATOR line become selects, js_include field added after css_include
//   (see labo_generator_editor_display in account/inc/first_setup.php)
//   only the inputs still of type text are replaced, labels and order are kept
//
// run from the project root with the DB_* variables of core/connect_db.php:
//   DB_HOST=... DB_NAME=... DB_USER=... DB_PASS=... php maintenance/upgrade_generator_display.php [--dry-run]

if (PHP_SAPI !== 'cli') {
	exit;
}

require __DIR__ . '/../inc/basis_functions.php';
require __DIR__ . '/../account/inc/first_setup.php';
require __DIR__ . '/../core/connect_db.php';

$dry_run = in_array('--dry-run', $argv, true);

$d_column = $db->query('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = "generator" AND column_name = "js_include"');
if ((int) $d_column->fetchColumn() === 0) {
	if (!$dry_run) {
		$db->exec('ALTER TABLE generator ADD COLUMN js_include TEXT NULL AFTER css_include');
	}
	echo 'generator.js_include: ' . ($dry_run ? 'would be added' : 'added') . "\n";
} else {
	echo 'generator.js_include: already there' . "\n";
}

// the GENERATOR lines: the ones editing the generator table itself
$d_lines = $db->query('SELECT id, title, display FROM generator WHERE table_name = "generator"');
$u_line = $db->prepare('UPDATE generator SET display = :display WHERE id = :id');

foreach ($d_lines->fetchAll(PDO::FETCH_ASSOC) as $line) {
	$display = labo_generator_editor_display($line['display'], true);
	if ($display === false) {
		echo 'line ' . $line['id'] . ' (' . $line['title'] . '): display is not valid JSON, not changed' . "\n";
		continue;
	}
	if (json_decode($display, true) == json_decode($line['display'], true)) {
		echo 'line ' . $line['id'] . ' (' . $line['title'] . '): nothing to change' . "\n";
		continue;
	}
	if (!$dry_run) {
		$u_line->execute(array('display' => $display, 'id' => $line['id']));
	}
	echo 'line ' . $line['id'] . ' (' . $line['title'] . '): ' . ($dry_run ? 'would be upgraded' : 'upgraded') . "\n";
}
