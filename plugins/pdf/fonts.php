<?php
$pdf->AddFont('AndikaNewBasic', '', 'AndikaNewBasic-R.php', __DIR__ . '/custom_fonts');
$pdf->AddFont('AndikaNewBasic', 'B', 'AndikaNewBasic-B.php', __DIR__ . '/custom_fonts');
$pdf->AddFont('AndikaNewBasic', 'I', 'AndikaNewBasic-I.php',__DIR__ . '/custom_fonts');
$pdf->AddFont('AndikaNewBasic', 'BI', 'AndikaNewBasic-BI.php', __DIR__ . '/custom_fonts');
$pdf->SetFont('AndikaNewBasic', 'BI', 12);