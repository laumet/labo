<?php
function txtconv ($string) {
	return iconv('UTF-8', 'windows-1252', $string);
}

if (!array_key_exists($_POST['generator'], $_SESSION['authorized'])) {
	exit('ko-not_authorized');
}

require_once(__DIR__ . '/fpdf/fpdf.php');

if (is_file(__DIR__ . '/extends.php')) {
	require_once(__DIR__ . '/extends.php');
}

$see_as['id'] = $_SESSION['user']['id'];
if (isset($_POST['see_as']) && $_POST['see_as'] != null && $_POST['see_as'] != '' && $_POST['see_as'] != $_SESSION['user']['id'] && (in_array('sa', $_SESSION['roles']) || in_array('ad', $_SESSION['roles'])))  {
	$see_as['id'] = $_POST['see_as'];
}

// get generator line
$d_selector = $db->prepare('SELECT * FROM generator WHERE id = :id');
$d_selector->execute(array('id' => $_POST['generator']));
$selector = $d_selector->fetch();
$d_selector->closeCursor();

$pdf = new PDF();
if (is_file(__DIR__ . '/define_content.php')) {
	require_once(__DIR__ . '/define_content.php');
}

if (is_file(__DIR__ . '/fonts.php')) {
	require_once(__DIR__ . '/fonts.php');
} else {
	$pdf->SetFont('Arial','',12);
}

if (is_file(__DIR__ . '/margins.php')) {
	require_once(__DIR__ . '/margins.php');
} else {
	$pdf->SetMargins(20, 20, 20);
	$pdf->SetAutoPageBreak('auto', 20);
}

$spec_content = false;
$spec_display = false;

if (isset($selector['plugin_pdf_params']) && $selector['plugin_pdf_params'] != null && $selector['plugin_pdf_params'] != '') {
	$plugin_pdf_params = json_decode($selector['plugin_pdf_params'], true);
	if (isset($plugin_pdf_params['content_from']) && $plugin_pdf_params['content_from'] == 'file' && isset($plugin_pdf_params['content_file']) && is_file(__DIR__ . '/' . $plugin_pdf_params['content_file'])) {
		$spec_content = true;
		include(__DIR__ . '/' .$plugin_pdf_params['content_file']);
	}

	if (isset($plugin_pdf_params['display_values']) && $plugin_pdf_params['display_values'] == 'file' && isset($plugin_pdf_params['display_file']) && is_file(__DIR__ . '/' .$plugin_pdf_params['display_file'])) {
		$spec_display = true;
		include(__DIR__ . '/' .$plugin_pdf_params['display_file']);
	}
}

if (!$spec_content) {
	$pdf->AddPage();
	$pdf->SetXY(17,50);
	$pdf->MultiCell(0, 8, txtConv("Il n'est pas encore possible de générer des PDFs sans définition de contenu."));
}

if (!$spec_display) {
	if ($spec_content) {
		$pdf->AddPage();
	}
	$y = $pdf->GetY();
	$pdf->SetXY(17,$y + 20);
	$pdf->MultiCell(0, 8, txtConv("Il n'est pas encore possible de générer des PDFs sans définition d'affichage."));
}

//$pdf->SetXY(0, 50);
//$pdf->Cell(100,30,'Hello World RMR 11 !', 1,0,'C');





// save the pdf on server
$pdf_name = str_replace(['.', ' '], '_', microtime());
if (isset($file_name) && $file_name != null && $file_name != '') {
	$pdf_name_on_load = $file_name;
} else {
	$pdf_name_on_load = $selector['title'];
}
$pdf_name_on_load =  preg_replace( '/[^a-z0-9]+/', '-', strtolower($pdf_name_on_load) );

$pdf->Output('F', __DIR__ . '/tmp/' . $pdf_name . '.pdf');


// open in a new tab
exit ('ok-' . $pdf_name . '.pdf->' . $pdf_name_on_load);
// to be done, before exiting: remove all tmp created 5 min and more before.

