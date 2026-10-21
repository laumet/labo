<?php
$site_default = false;

if (is_file(__DIR__ . '/../default/site_default.json')) {
	$site_default = json_decode(file_get_contents(__DIR__ . '/../default/site_default.json'), true);
} else if (is_file(__DIR__ . '/../default/default.json')) {
	$site_default = json_decode(file_get_contents(__DIR__ . '/../default/default.json'), true);
} else {
	exit('default file missing');
}

requireOncePlugins('site_default.php');