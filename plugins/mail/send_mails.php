<?php
$allowed_sending_types = ['to', 'cc', 'bcc'];

if (!isset($_POST['recipients']) || !isset($_POST['object']) || !isset($_POST['mail_content'])) {
	exit('ko-uncomplete_request (send_mails 1)');
}

// check sending type
$sending_type = false;
foreach($_POST as $k => $v) {
	if (strpos($k, 'sending_type') !== false && in_array($v, $allowed_sending_types)) {
		$_POST['sending_type'] = $v;
		$sending_type = true;
		break;
	}
}
if (!$sending_type) {
	exit('ko-uncomplete_request (send_mails 2)');
}

$headers = '';
if (isset($_POST['reply_to']) && $_POST['reply_to'] != '' && $_POST['reply_to'] != null) {
	$headers .= "Reply-To: " . strip_tags($_POST['reply_to']) . "\r\n";
}
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

// create array with all recipients
$all_recipients = explode(',', str_replace(' ', '', $_POST['recipients']));

// check and concatene all recipients
// parse all POST to search otherMails_ entries
foreach ($_POST as $k => $v) {
	if (strpos($k, 'other_mails_') === 0 && $v != '' && $v != null) {
		$new_recipients = explode(',', str_replace(' ', '',$v));
		$all_recipients = array_merge($all_recipients, $new_recipients);
	}
}

// deduplication of recipients
if (count($all_recipients) > 1) {
	$all_recipients = array_unique($all_recipients);
}

$object = $_POST['object'];
$mail_content = $_POST['mail_content'];

$result = '';
if ($_POST['sending_type'] == 'to') {
	foreach($all_recipients as $r) {
		$result .= strlen($result) > 0 ? ' / ' : '';
		$result .= $r . ' => ';
		$result .= mail($r, $object, $mail_content, $headers) ? 'OK<br />' : 'error<br />';
	}
} else {
	$header_base = $_POST['sending_type'] == 'cc' ? "Cc: " : "Bcc: ";

	// sending 50 mails to 50 mails
	for ($inc_mail = 0; $inc_mail < count($all_recipients); $inc_mail += 50) {
		
		$result .= 'Sending mail => ' . ($inc_mail + 1) . ' -> ' . ($inc_mail + 49 );
		$recipents_part = implode(', ', array_slice($all_recipients, $inc_mail, $inc_mail + 49));
		$headers .= $header_base . $recipents_part . "\r\n";
		$result .= mail('', $object, $mail_content, $headers) ? 'OK<br />' : 'error<br />';
	}
}

$return_string = 'mailSendedEndSplash|' . $result;

// prepare new inputs:
$inputs[] = ['column_name' => 'success'];
$_POST['success'] = $result;

$inputs[] = ['column_name' => 'sender'];
$_POST['sender'] = $_SESSION['user']['id'];