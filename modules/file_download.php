<?php
// file download, included by file_swap.php for logged-in users, never called directly
chdir(__DIR__);

require_once __DIR__ . '/../inc/basis_functions.php';
require_once __DIR__ . '/inc/connect_db.php';

// load plugins index functions;
$plugins = getPluginsRepositories('get_file_before.php');
if (is_countable($plugins)) {
    foreach($plugins as $p) {
		include __DIR__ . '/../plugins/' . $p . '/get_file_before.php';
    }
}
if (!isset($_GET['f']) || !is_numeric($_GET['f'])) { exit('rejected'); }

$d_file = $db->prepare('SELECT * FROM files WHERE id = :id');
$d_file->execute(array('id' => $_GET['f']));

$file = $d_file->fetch(PDO::FETCH_ASSOC);

// load plugins index functions;
$plugins = getPluginsRepositories('get_file.php');
if (is_countable($plugins)) {
    foreach($plugins as $p) {
		include __DIR__ . '/../plugins/' . $p . '/get_file.php';
    }
}

$filename = $file['file_name'];
$ext = pathinfo($filename, PATHINFO_EXTENSION);

if ($file['download_name'] != '' && $file['download_name'] != null) {
	// check if extensions allready exists
	if ($ext != pathinfo($file['download_name'], PATHINFO_EXTENSION)) {
		$filename = $file['download_name'] . '.' . $ext;
	} else {
		$filename = $file['download_name'];
	}
}

// send file
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
if ($ext == 'pdf') {
	header('Content-Disposition: inline; filename=' . $filename);
} else {
	header('Content-Disposition: attachment; filename=' . $filename);
}
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
echo base64_decode($file['file']);