<?php
/*
one plugin_rows
 ["5 - É2"]=>
  array(34) {
    [501]=>
    array(9) {
      ["id"]=>
      int(381)
      ["category_name"]=>
      string(7) "5 - É2"
      ["class_code"]=>
      int(501)
      ["finalist_class"]=>
      string(7) "checked"
      ["problem_result_77"]=>
      string(13) "4b"
      ["problem_result_78"]=>
      NULL
      ["problem_result_79"]=>
      NULL
      ["problem_result_80"]=>
      NULL
      ["total_points_1"]=>
      int(5)
    }, ...
categories (example):
array(4) {
  ["5 - É2"]=>
  string(4) "5ᵉ"
  ["6 - É2"]=>
  string(4) "6ᵉ"
  ["5 - É1"]=>
  string(4) "5ᵉ"
  ["6 - É1"]=>
  string(4) "6ᵉ"
}

two rows cat_to_test_to_problems
 ["5 - É2"]=>
  array(4) {
    [1]=>
    int(77)
    [2]=>
    int(78)
    [3]=>
    int(79)
    [4]=>
    int(80)
  }
  ["6 - É2"]=>
  array(4) {
    [2]=>
    int(78)
    [3]=>
    int(79)
    [4]=>
    int(80)
    [5]=>
    int(81)
  } ...

to_be_highlighted:
array(2) {
  [0]=>
  int(390)
  [1]=>
  int(393)
}
*/

function tableHeader () {
    global $pdf, $cat_to_test_to_problems;
  // echo 'ok-<p>TableHeader</p><pre>';
  // var_dump($plugin_rows[$cat_to_display[$i_content_loop]]);
  // 
 // var_dump($cat_to_test_to_problems);
  //var_dump($cat_to_test_to_problems);
  /*
  exit();
  */

    if ($pdf->newPage) {
        $pdf->addPage();
    }
    // bold
    $pdf->SetFont('', 'B');
    // column 1: classes
    $pdf->Cell(20, 7, 'Classes', 'LTB', 0, 'C');
    // problems
    foreach($cat_to_test_to_problems[$pdf->cat] as $n => $p) {
        $pdf->Cell(16, 7, 'P' . $n, 'LTB', 0, 'C');
        $pdf->Cell(7, 7, 'b', 'LTB', 0, 'C');
    }
    // total
    $pdf->Cell(22, 7, 'Total', 1, 1, 'C');

    // "reset" bold...
    $pdf->SetFont('', '');

    // end header
    $pdf->newPage = false;
}

foreach($categories as $k => $cat) {
  $pdf->category = $k . 'e';
  $pdf->AddPage();

  // set category
  $pdf->cat = $k;

  // first table header:
  $pdf->newPage = false;
  tableHeader();
  
  $line = 1;
  foreach($plugin_rows[$k] as $r => $v) {
    if ($pdf->newPage) {
      tableHeader();
      $line = 1;
    }
      $fill = false;
      if (in_array($v['id'], $to_be_highlighted)) {
      $pdf->setFillColor(0, 255, 29);
      $fill = 'true';
      $line = $line == 2 ? 0 : 1;
      } else if ($line == 2) {
        $pdf->setFillColor(220, 220, 220);
        $fill = 'true';
        $line = 0;
      }

      $pdf->Cell(20, 7, $v['class_code'], 'LB', 0, 'C', $fill);
    // results
    foreach($cat_to_test_to_problems[$k] as $p) {
      $bonus = '';
      $points = $v['problem_result_' . $p];
      if ($points != '' && $points != null && strpos($points,'<sup>') !== false) {
        $bonus = 'x';
        $points = substr($points, 0, 1);
      }
      $pdf->Cell(16, 7, $points, 'LB', 0, 'C', $fill);
      $pdf->Cell(7, 7, $bonus, 'LB', 0, 'C', $fill);
    }
    // total (test index matches results.php: 0 = É1, 1 = É2, 2 = finale)
    $pdf->Cell(22, 7, $v['total_points_' . $rmr_test_ref], 'LBR', 1, 'C', $fill);
    $line++;
  }
}