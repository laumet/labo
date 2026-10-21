<?php
$d_rmr_values = $db->prepare('SELECT * FROM rmr WHERE id = :id');
$d_rmr_values->execute(array('id' => $_SESSION['rmr_status']['id']));
$rmr_values = $d_rmr_values->fetch();
$d_rmr_values->closeCursor();

// define rmr_test_ref based on rmr_status_id
$rmr_status_available = [null, 0, 0, 0, 0, 0, 0, 1, 1, 1, 2, null, null];
$rmr_test_ref = $rmr_status_available[$_SESSION['rmr_status']['status_id']];

$date_rmr = substr($rmr_values['start_date'], strrpos($rmr_values['start_date'], '.') + 1) . '-' . substr($rmr_values['end_date'], strrpos($rmr_values['end_date'], '.') + 1);
$tests = ['1re épreuve', '2e épreuve', 'finale'];
$tests_file_name = ['epreuve-1', 'epreuve-2', 'finale'];
$test_name = $tests[$rmr_test_ref];

$pdf->category = '';
$file_name = 'RMR-' . $rmr_values['number'] . '-resultats-' .  $tests_file_name[$rmr_test_ref];