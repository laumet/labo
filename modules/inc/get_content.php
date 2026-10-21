<?php
require_once 'inc/connect_db.php';

require_once 'inc/check_hability.php';

require_once 'inc/constructor_swap_functions.php';

$content = '';
$display_mode = '';
$see_as = false;
if (isset($_POST['see_as']) && $_SESSION['roles']['max_level'] >= 90) {
    // get all values for user (id_name, lastname and username)
	$d_sa = $db->prepare('SELECT id, name, lastname, username FROM users WHERE id = :id');
    $d_sa->execute(array('id' => $_POST['see_as']));
    $see_as = $d_sa->fetch(PDO::FETCH_ASSOC);
    $d_sa->closeCursor();
    $d_sa->closeCursor();
}

switch ($_POST['to_do']) {
	case 'get_content' :
		if (strpos($_POST['index'], 'homepage_') !== false) {
			defineHomePageUser(substr($_POST['index'], 9));
		} else if (isset($page_access) && $page_access['type'] === 'page') {
			// page_with_modules: the modules of the page the user can view (checked in check_hability.php), in order
			// several modules: one headertab per module (same tabs as form_complement headertabs), js/script.js setModuleTabs()
			$module_tabs = count($page_access['modules']) > 1;
			if ($module_tabs) {
				$content .= '<headertabs class="module_tabs">';
				foreach ($page_access['modules'] as $i_module => $module) {
					$content .= '<headertab key="module_' . (int) $module['generator_id'] . '"' . ($i_module === 0 ? ' active' : '') . '>';
					$content .= htmlspecialchars((string) $module['title'], ENT_QUOTES, 'UTF-8') . '</headertab>';
				}
				$content .= '</headertabs>';
			}
			foreach ($page_access['modules'] as $i_module => $module) {
				$content .= '<module index="' . (int) $module['generator_id'] . '" key="module_' . (int) $module['generator_id'] . '"';
				$content .= ($module_tabs && $i_module > 0) ? ' class="hidden">' : '>';
				if (!$module_tabs) {
					$content .= '<h2 class="module_title">' . htmlspecialchars((string) $module['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
				}
				defineContent($module['generator_id']);
				$content .= '</module>';
			}
		} else {
			defineContent($_POST['index']);
		}
	break;
	case 'get_empty_row':
		if ($authorisation['can_add'] === 1) {
			if (isset($generator_row)) {
				defineEmptyRow(false, $generator_row);
			} else {
				defineEmptyRow($_POST['index']);
			}
		} else {
			exit('ko-not_authorised (get_content 1)');
		}
	break;
	case 'get_row' :
		if (isset($_POST['row'])) {
			defineRow($_POST['index'], $_POST['row']);
		} else {
			exit('ko-request_not_valid');
		}
		break;
}

exit('ok-' . $content);