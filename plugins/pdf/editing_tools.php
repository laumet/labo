<?php
if ($selector['plugin_pdf_button'] == 1 && is_countable($rows) && $rows > 0) {
	$content .= '
	<style>
		pdfcreator {
			width: 3em !important;
			height: 3em !important;
			top: 3em;
			position: fixed;
			right: 6em;
		}
		pdfcreator:hover {
			transform: scale(3);
		}
	</style>
	';
    $content .= '<pdfcreator all class="other_buttons" id="get_pdf" index="' . $generator_index . '" function="getPdf" ></pdfcreator>';
}