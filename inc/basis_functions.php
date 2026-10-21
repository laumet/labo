<?php
function runPlugins ($file_name) {
    if (is_null($file_name)) { return false; }

    global $db;
    $path = __DIR__ . '/../';
    $plugins = scandir($path . 'plugins');
    if (is_countable($plugins) && count($plugins) > 2) {
        for ($i_p = 2; $i_p < count($plugins); $i_p++) {
            if (is_file($path . 'plugins/' . $plugins[$i_p] . '/' . $file_name)) {
                include $path . 'plugins/' . $plugins[$i_p] . '/' . $file_name;
            }
        }
    }
    return true;
}

function requireOncePlugins ($file_name) {
    if (is_null($file_name)) { return false; }

    global $db;
    $path = __DIR__ . '/../';
    $plugins = scandir($path . 'plugins');
    if (is_countable($plugins) && count($plugins) > 2) {
        for ($i_p = 2; $i_p < count($plugins); $i_p++) {
            if (is_file($path . 'plugins/' . $plugins[$i_p] . '/' . $file_name)) {
                require_once $path . 'plugins/' . $plugins[$i_p] . '/' . $file_name;
            }
        }
    }
    return true;
}

function getPluginsRepositories ($file_name) {
    if (is_null($file_name)) { return false; }

    $path = __DIR__ . '/../';
    $repo_list = [];

    $plugins = scandir($path . 'plugins');
    if (is_countable($plugins) && count($plugins) > 2) {
        for ($i_p = 2; $i_p < count($plugins); $i_p++) {
            if (is_file($path . 'plugins/' . $plugins[$i_p] . '/' . $file_name)) {
                $repo_list[] = $plugins[$i_p];
            }
        }
    }
    return $repo_list;
}

// FILE GALERY FONCTIONS /////////////////////////////////////////
function fileList ($fileList, $display = 'both', $path = '', $plugins = false) {
    global $db;

    $display = $display == null ? 'both' : $display;

    // define content to display
    $db_query_array = array(
        'from' => 'files',
        'inner_join' => [],
        'where' => ['logical_delete = 0'],
        'order' => [],
    );
    $db_query_values = array();

    // prepare content
    $fl = '';
    
    // get all files
    if ($fileList != null && $fileList != '' &&strlen($fileList) > 0) {

        $files_ids = explode(',', $fileList);
        $in = '';
        $i = 0;
        foreach($files_ids as $v) {
            if (strlen($in) > 0) {$in .= ', '; }
            $in .= ':f' . $i;
            $db_query_values['f' . $i] = $v;
            $i++;
        }
        $db_query_array['where'][] = 'id in (' . $in . ')';        
        

        // run (if existing) plugin "get_file_galery_edit_list_db_query"
        $plugins = getPluginsRepositories('get_file_galery_edit_list_db_query.php');
        if (is_countable($plugins)) {
            foreach($plugins as $p) {
                include __DIR__ . '/../plugins/' . $p . '/get_file_galery_edit_list_db_query.php';
            }
        }

        // create request
        $string_request = 'SELECT * FROM ' . $db_query_array['from'];

        if (count($db_query_array['inner_join']) > 0) {
            // if adding inner_join from plugin => write "INNER JOIN ..."
            foreach($db_query_array['inner_join'] as $v) {
                $string_request .= ' ' . $v;
            }
        }
        if (count($db_query_array['where']) > 0) {
            $where = ' WHERE';
            foreach($db_query_array['where'] as $v) {
                $where .= strlen($where) > 6 ? ' AND' : '';
                $where .= ' ' . $v;
            }
            $string_request .= $where;
        }
           if (count($db_query_array['order']) > 0) {
            $order_by = ' ORDER BY';
            foreach($db_query_array['order'] as $v) {
                $order_by .= strlen($where) > 9 ? ' ,' : '';
                $order_by .= ' ' . $v;
            }
            $string_request .= $order_by;
        }

        // execute request
        $d_request = $db->prepare($string_request);
        if (!$d_request->execute($db_query_values)) {
            $fl = 'Problème rencontré...';
        }
       
        $request = $d_request->fetchAll(PDO::FETCH_ASSOC);
        if (is_countable($request) && count($request) > 0) {
            foreach($request as $v) {
                $selected = false;
                $fl .= '<fileelement data-id="' . $v['id'] . '" ';
                $fl .= $selected ? 'selected ' : '';
                $fl .= '>';
                $thumb = $v['thumbnail'] != 'doc' ? 'data:image/jpeg;base64,' . $v['thumbnail'] : 'icons/doc.svg';
                $filename = $v['download_name'] != null && $v['download_name'] != '' ? $v['download_name'] : $v['file_name'];
                $fl .= '<img src=' . $thumb . '>';
                $fl .= '<a href="' . labo_url('file_swap.php') . '?f=' . $v['id'] . '" target="_blank" >' . $filename . '</a>';
                $fl .= '</fileelement>';
            }
        }
    }
    return $fl;
}

// languages available on the site (a lang/xx.json file must exist for each)
const LABO_LANGS = array('en', 'fr', 'de');

// define the language of the page: session, then browser language, then english
function labo_lang () {

    if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], LABO_LANGS)) {
        return $_SESSION['lang'];
    }

    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        foreach (explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']) as $l) {
            $l = strtolower(substr(trim($l), 0, 2));
            if (in_array($l, LABO_LANGS)) {
                return $l;
            }
        }
    }

    return 'en';
}

// texts of a language from lang/xx.json (loaded once per request)
function labo_texts ($lang) {
    static $texts = array();

    if (!isset($texts[$lang])) {
        $texts[$lang] = array();
        $file = __DIR__ . '/../lang/' . $lang . '.json';
        if (in_array($lang, LABO_LANGS) && is_file($file)) {
            $content = json_decode(file_get_contents($file), true);
            if (is_array($content)) {
                $texts[$lang] = $content;
            }
        }
    }

    return $texts[$lang];
}

// text of a key in the page language, english as fallback, the key itself if missing (escaped for html)
function labo_t ($key) {
    $texts = labo_texts(labo_lang());
    if (!isset($texts[$key])) {
        $texts = labo_texts('en');
    }
    $text = isset($texts[$key]) ? $texts[$key] : $key;

    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// features that can be switched on (true) or off (false) in default/features.json
// values used when the file or a key is missing
const LABO_FEATURES_DEFAULT = array(
    'create_account' => false,
    'forgotten_password' => true,
    'lang_switcher' => true
);

function labo_feature ($name) {
    static $features = null;

    if ($features === null) {
        $features = LABO_FEATURES_DEFAULT;
        $file = __DIR__ . '/../default/features.json';
        if (is_file($file)) {
            $content = json_decode(file_get_contents($file), true);
            if (is_array($content)) {
                // only known features, only true/false values
                foreach (LABO_FEATURES_DEFAULT as $k => $v) {
                    if (isset($content[$k]) && is_bool($content[$k])) {
                        $features[$k] = $content[$k];
                    }
                }
            }
        }
    }

    return isset($features[$name]) && $features[$name] === true;
}

// url of the project root (where the entry points index.php, labo_swap.php and file_swap.php are), with an optional path
function labo_url ($path = '') {
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/' . $path;
}

// true when a user is logged in (same check for pages, ajax actions and files)
function labo_is_connected () {
    return isset($_SESSION['user']) &&
        isset($_SESSION['user']['username']) &&
        isset($_SESSION['roles']) &&
        $_SESSION['roles'];
}

// data management mode, chosen in default/labo_settings.json BEFORE the first setup (see readme.txt)
//   single_generator_per_page: one generator line = one menu page (legacy, default)
//   page_with_modules: a page (table pages) groups several generator lines (modules)
// the first setup saves the mode in the table labo_settings; afterwards both values must stay identical
const LABO_DATA_MODES = array('single_generator_per_page', 'page_with_modules');
const LABO_DATA_MODE_DEFAULT = 'single_generator_per_page';

// mode written in the settings file: default if the file or the value is missing, null if the value is unknown
function labo_configured_data_mode ($file = null) {
    $file = $file === null ? __DIR__ . '/../default/labo_settings.json' : $file;
    if (!is_file($file)) {
        return LABO_DATA_MODE_DEFAULT;
    }
    $settings = json_decode(file_get_contents($file), true);
    if (!is_array($settings)) {
        return null;
    }
    if (!isset($settings['data_management_mode'])) {
        return LABO_DATA_MODE_DEFAULT;
    }
    return in_array($settings['data_management_mode'], LABO_DATA_MODES, true) ? $settings['data_management_mode'] : null;
}

// mode to use, from the settings file and the mode saved at installation (null: installed before this setting existed)
// returns array(mode, error key); the mode is never guessed from the content of the database
function labo_resolve_data_mode ($configured, $installed) {
    if ($configured === null) {
        return array(null, 'data_mode_invalid');
    }
    if ($installed === null) {
        // installation older than the setting: it is a single_generator_per_page installation
        return $configured === LABO_DATA_MODE_DEFAULT ? array($configured, null) : array(null, 'data_mode_not_installed');
    }
    if (!in_array($installed, LABO_DATA_MODES, true)) {
        return array(null, 'data_mode_invalid');
    }
    if ($installed !== $configured) {
        return array(null, 'data_mode_mismatch');
    }
    return array($installed, null);
}

// mode saved in the database at installation (null if not saved)
function labo_installed_data_mode ($db) {
    try {
        $d_mode = $db->prepare('SELECT setting_value FROM labo_settings WHERE setting_key = :k AND logical_delete = 0');
        $d_mode->execute(array('k' => 'data_management_mode'));
        $mode = $d_mode->fetchColumn();
        return $mode === false ? null : $mode;
    } catch (PDOException $e) {
        // 42S02: no labo_settings table => installation older than the setting
        if ($e->getCode() === '42S02') {
            return null;
        }
        throw $e;
    }
}

// mode of the running LABO (stops the request if the settings file and the installation disagree)
function labo_data_mode () {
    static $mode = null;
    global $db;

    if ($mode === null) {
        list($resolved, $error) = labo_resolve_data_mode(labo_configured_data_mode(), labo_installed_data_mode($db));
        if ($error !== null) {
            if (function_exists('labo_log')) {
                labo_log('core', 'Data management mode problem.', array('error' => $error));
            }
            exit('ko-' . $error);
        }
        $mode = $resolved;
    }
    return $mode;
}

function labo_page_mode () {
    return labo_data_mode() === 'page_with_modules';
}
