<?php
if ($_POST['plugin_job'] == 'modell' && isset($_POST['mail_id'])) {
	// in this case, we have to get object and content for this message
	require_once 'inc/connect_db.php';

	$d_modell = $db->prepare('SELECT object, mail_content FROM mail_plugin WHERE id = :id AND is_modell = "checked"');
	$d_modell->execute(array('id' => $_POST['mail_id']));
	$modell = $d_modell->fetch(PDO::FETCH_ASSOC);
	$d_modell->closeCursor();

	if (!$modell || !is_countable($modell) || count($modell) == 0) {
		exit('ko-no_modell_found');
	} else {
		echo 'ok-' . json_encode($modell);
	}
	exit();
} else {
	exit ('ko-incomplete_request');
}