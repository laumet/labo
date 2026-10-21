<?php
if ($_POST['splash'] == true && $_POST['to_do'] == 'get_file_galery_splash') {
	// as file galery is a very special system all is written here and nothing comes from GENERATOR.
	
	// define content to display
	$db_query_array = array(
		'from' => 'files',
		'inner_join' => [],
		'where' => ['logical_delete = 0'],
		'order' => [],
	);
	$db_query_values = array();

	// run (if existing) plugin "get_file_galery_splash_db_query"
    $plugins = getPluginsRepositories('get_file_galery_splash_db_query.php');
    if (is_countable($plugins)) {
        foreach($plugins as $p) {
            include '../plugins/' . $p . '/get_file_galery_splash_db_query.php';
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

	// echo 'ok-sp-' . $string_request;

    // execute request
    $d_request = $db->prepare($string_request);
    if (!$d_request->execute($db_query_values)) {
    	$sp .= '<p>Problème rencontré avec la recherche dans la base de données: ' . json_encode($d_request->errorInfo()) . '(' . $string_request . '</p>';
    };
    $request = $d_request->fetchAll(PDO::FETCH_ASSOC);

    $sp = '';
    $used_files = [];
    if (isset($_POST['with']) && $_POST['with'] != null &&  $_POST['with'] != '') {
    	$used_files = explode(',', $_POST['with']);
    }
	if (is_countable($request) && count($request) > 0) {
		$sp .= '<splashfilegalery>';
		foreach($request as $v) {
			$selected = false;
			if (in_array($v['id'], $used_files)) {
				$selected = true;
				unset($used_files[array_keys($used_files, $v['id'])[0]]);
			}
			$sp .= '<fileelement data-id="' . $v['id'] . '" ';
			$sp .= $selected ? 'selected ' : '';
			$sp .= '>';
			$thumb = $v['thumbnail'] != 'doc' ? 'data:image/jpeg;base64,' . $v['thumbnail'] : 'icons/doc.svg';
			$sp .= '<img src=' . $thumb . '>';
			$sp .= '<a href="' . labo_url('file_swap.php') . '?f=' . $v['id'] . '" target="download" >' . $v['file_name'] . '</a>';
			$sp .= '</fileelement>';
		}
		$sp .= '</splashfilegalery>';
	} else {
		$sp .= '<h1>AUCUN FICHIER À DISPOSITION DANS CETTE SITUATION</h1>';
	}
	exit ('ok-sp-' . $sp);
}