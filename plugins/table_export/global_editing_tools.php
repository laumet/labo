<?php
if (isset($selector['plugin_table_export']) && $selector['plugin_table_export'] != 0) {
	$content .= '<img title="exporter la sélection au format ';
	$content .= $selector['plugin_table_export'] == 2 ? 'xlsx' : 'csv';
	$content .=  '" src="../plugins/table_export/icons/table_export.svg" class="table_export';
	$content .= $selector['plugin_table_export'] == 2 ? '_xlsx' : '_csv';
	$content .=' other_buttons" function="tableExport">';
}