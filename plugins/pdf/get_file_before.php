<?php
if (!isset($_GET['p']) || $_GET['p'] != 'pdf' || !isset($_GET['f']) || !isset($_GET['n'])) { return; };

// Check if the file exists (basename: only a file of the tmp folder, never a path)
$filePath = '../plugins/pdf/tmp/' . basename($_GET['f']);
if (!file_exists($filePath)) {
    http_response_code(404); // Not Found
    exit('File not found.');
}



// Get the file's MIME type (important for browsers)
$mimeType = mime_content_type($filePath);
if ($mimeType === false) {
    $mimeType = 'application/octet-stream'; // Default if MIME type can't be determined
}

// Set headers for file download
header('Content-Type: ' . $mimeType);
// download name without quotes, slashes or line breaks
$downloadName = preg_replace('#["/\\\\\r\n]#', '', $_GET['n']);
header('Content-Disposition: attachment; filename="' . $downloadName . '.pdf"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private'); // Optional: for better caching control

// Send the file content
readfile($filePath);

// delete file in tmp:
unlink($filePath);

exit; // Important: stop further script execution