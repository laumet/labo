<?php
// save the language chosen with the language switcher
// in the session, and in the user settings if connected
// included by labo_swap.php (to_do = set_lang)

if (!isset($_POST['lang']) || !in_array($_POST['lang'], LABO_LANGS)) {
	exit('ko-unknown_lang');
}

$_SESSION['lang'] = $_POST['lang'];

if (isset($_SESSION['user']) && isset($_SESSION['user']['id'])) {
	require_once __DIR__ . '/../core/connect_db.php';

	try {
		$u_lang = $db->prepare('UPDATE users SET lang = :lang WHERE id = :id');
		$u_lang->execute(array('lang' => $_SESSION['lang'], 'id' => $_SESSION['user']['id']));
	} catch (PDOException $e) {
		// the choice stays in the session even if it can't be saved for the user
		labo_log('database', 'User language could not be saved.', array(
			'error' => $e->getMessage()
		));
		exit('ko-lang_not_saved');
	}
}

exit('ok-lang_saved');
