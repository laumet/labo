<?php
// login page, included by index.php when no user is logged in, never called directly
chdir(__DIR__);
session_regenerate_id();

// connect to the db:
require_once __DIR__ . '/../modules/inc/connect_db.php';

// add functions
// load basis functions
require_once __DIR__ . '/../inc/basis_functions.php';

// define site_default
include __DIR__ . '/../inc/site_default.php';

// load plugins index functions;
requireOncePlugins('index_functions.php');

// reset any old values
$_SESSION['state'] = 'unconnected';

// adding head and opening body
include 'inc/account_head.php';

// include functions
include 'inc/account_functions.php';

?>
<header class= horizontal>
	<a href="/">
<?php
	echo $site_default['html_content_for_site_name'];
?>
	</a>
<?php include __DIR__ . '/../inc/lang_switcher.php'; ?>
</header>
<div class="account"><div>
<?php
// check incoming
if (!empty($_SESSION['first_setup'])) {
	// first opening: the installation account creates the first administrator
	include 'pages/register.php';
} else if (
	!isset($_POST['to_do']) ||
	($_POST['to_do'] == 'create_account' && !labo_feature('create_account'))
) {
	include 'pages/connect.php';
} else {
	switch ($_POST['to_do']) {
		case 'create_account':
			include 'pages/register.php';
			break;
		default :
			exit('ko-unknow_action');
			break;
	}
}

?>
</div></div>
<?php
// adding scripts et closing page from site
include 'inc/account_scripts.php';

include 'inc/account_footer.php';