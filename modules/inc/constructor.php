<?php
$roles = array();
$blocs = array();
$content = '';

require_once __DIR__ . '/constructor_swap_functions.php';
require_once __DIR__ . '/page_modules.php';

function checkDisplay ($check) {

    if ($check == '' || $check == null) { return true;}

    /*
    CHECK EXAMPLE =>
    { "1":"db",
      "2":{"request":"",
        "values" : {"username":"SESSION=>user|username", "xx":"value"},
        "condition_on":"count",
        "condition_comparator":">",
        "condition_value":"0"
        }
    }*/
    $check = json_decode($check, true);
    if (!isset($check['type'])) { return ;}

    switch ($check['type']) {
        case 'db':
            global $db;
            $check = $check['check_content'];
            if (!isset($check['request']) || !isset($check['values']) || !isset($check['condition_on']) || !isset($check['condition_comparator']) || !isset($check['condition_value'])) { return true;}

            // ready to check !
            // get content
            $values = stringToSession($check['values']);
            $d_checking = $db->prepare($check['request']);
            $d_checking->execute($values);
            $checking = $d_checking->fetchAll();

            $value_to_check;
            if ($check['condition_on'] == 'count') {
                if (is_countable($checking)) {
                    $value_to_check = count($checking);
                } else { return true; }
            } else {
                $value_to_check = $checking[$check['condition_on']];
            }

            switch ($check['condition_comparator']) {
                case '<' :
                    return $value_to_check < $check['condition_value'];
                    break;
                case '>' :
                    return $value_to_check > $check['condition_value'];
                    break;
                case '=' :
                    return $value_to_check == $check['condition_value'];
                    break;
                default:
                    return true;
                    break;
            }
        return true;

        break;
    }
}

function defineRoles()
{
    global $roles, $db;

    $roles = $_SESSION['roles'];
    unset($_SESSION['display_roles']);
    //ask for role list in order of importance and push them
    $search_roles = '';
    foreach($roles as $key => $role) {
        // skip the max_level entry (stored under the 'max_level' key) and any empty role
        // empty roles would otherwise build `LIKE "%%"` and match every role (incl. sa)
        if ($key === 'max_level' || $role === '' || $role === null) {
            continue;
        }
        if (strlen($search_roles) > 0) {
            $search_roles .= ' OR ';
        }
        $search_roles .= 'role_short_name = ' . $db->quote($role);
    }

    if (in_array('ad', $roles)) {
        $search_roles = 'role_short_name <> "ev" AND role_short_name <> "sa"';
    } else if (in_array('sa', $roles)) {
        $search_roles = 'role_short_name <> "ev"';
    }

    // no valid role => no display role (avoids an empty WHERE clause matching everything)
    if ($search_roles === '') {
        return;
    }

    $d_roles_names = $db->prepare('SELECT id, role_short_name, role_short_name_display, role_name FROM roles WHERE ' . $search_roles . ' ORDER BY tab_order ASC');
    $success = $d_roles_names->execute();
    if (!$success) {
        exit ('ko-db_problem');
    }

    $roles = $d_roles_names->fetchAll(PDO::FETCH_ASSOC);
    // define session display_roles
    foreach ($roles as $role) {
        $_SESSION['display_roles'][] = $role['role_short_name'];
    }
}

function defineBlocs()
{
    global $blocs, $db;

    // reject if necessary...
    if (!isset($_SESSION['roles']) || isset($_SESSION['roles']['none'])) {
        header('Location: index.php');
    }

    // page_with_modules: one menu entry per page, rights from module_permissions (modules/inc/page_modules.php)
    if (labo_page_mode()) {
        $blocs = labo_define_pages_menu();
        return;
    }

    // ask for bloc list, limited by roles in order of section and defined order
    $search_roles = implode('", "', $_SESSION['display_roles']);
    $search_roles = '"' . $search_roles . '"';
    $d_blocs = $db->prepare('
        SELECT g.id, g.title, g.table_name, g.user_type, g.section, g.position_in_section, g.can_edit, g.can_add, g.can_delete, r.role_name, g.conditional_display
        FROM generator  AS g
        INNER JOIN roles AS r
        ON r.role_short_name = g.user_type
        WHERE g.user_type IN (' . $search_roles . ') AND g.page = "labo"
        ORDER BY r.tab_order, g.user_type, g.section, g.position_in_section ASC
    ');
    $d_blocs->execute();
    $blocs = $d_blocs->fetchAll(PDO::FETCH_ASSOC);

    // load plugins index functions;
    requireOncePlugins('editor_blocs_definition.php');

    // use blocs to set authorized requests
    $_SESSION['authorized'] = [];
    foreach($blocs as $b) {
        $_SESSION['authorized'][$b['id']] = [
            'table_name' => $b['table_name'],
            'can_edit' => $b['can_edit'],
            'can_add' => $b['can_add'],
            'can_delete' => $b['can_delete']
        ];
    }
}

function setup() {
    defineRoles();

    defineBlocs();

    // defineContent();
}

setup();