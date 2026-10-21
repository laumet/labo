<?php
// remove menu in cache
if (is_file('../cache/menu/menu.json')) {
	unlink('../cache/menu/menu.json');
}

// adapt content to save
if (
	(
		!isset($_POST['page_slug']) ||
		$_POST['page_slug'] == null ||
		$_POST['page_slug'] == ''
	) &&
	isset($_POST['title']) &&
	$_POST['title'] != null &&
	$_POST['title'] != ''
) {
	$slug = $_POST['title'];
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9 -]+/', '', $slug);
    $slug = str_replace(' ', '_', $slug);
    $slug = trim($slug, '-');
    $_POST['page_slug'] = $slug;
} else if (
	!isset($_POST['page_slug']) ||
	$_POST['page_slug'] == null ||
	$_POST['page_slug'] == ''
	){
	$_POST['page_slug'] = 'undefined';
}