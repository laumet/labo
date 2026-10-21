<?php
// check if file is returned
if (isset($_FILES) && is_countable($_FILES) && count($_FILES) > 0) {
	$thumb = 'doc';
	$filePath = realpath($_FILES['file_name']['tmp_name'][0]);
	// Attempt to create an Imagick object for the uploaded file
	try {
	    if (pathinfo($_FILES['file_name']['name'][0], PATHINFO_EXTENSION) == 'pdf') {
	    	// save tmp
	    	if (move_uploaded_file($filePath, 'tmp/tmp.pdf')) {
	    		$imagick = new Imagick('tmp/tmp.pdf[0]');
	    		$filePath = 'tmp/tmp.pdf';
	    	}
	    } else {
	    	$imagick = new Imagick($filePath);
	    }

	    // Create a thumbnail
	    $imagick->setbackgroundcolor('rgb(64, 64, 64)');
		$imagick->thumbnailImage(100, 100, true, true);
		$thumb = base64_encode($imagick->getImageBlob());
		
	} catch (ImagickException $e) { $return_string = $e->getMessage(); }

	if (strpos($_POST['row_id'], 'new_row_') !== false) {
		$request = $db->prepare('
		INSERT INTO files (file_name, file, thumbnail_name, thumbnail) VALUES (:n, :f, :tn, :tf)
		');
		$request->execute(array(
			'n' => preg_replace( '/[^a-z0-9]+/', '_', strtolower(pathinfo($_FILES['file_name']['name'][0], PATHINFO_FILENAME))) . '.' . pathinfo($_FILES['file_name']['name'][0], PATHINFO_EXTENSION),
			'f' => base64_encode(file_get_contents($filePath)),
			'tn' => 'tmp.jpg',
			'tf' => $thumb
		));

		$row_id = $db->lastInsertId();
		$_POST['row_id'] = $row_id;
		$update = true; // from save_row.php lines 44-55

		$update_row = $db->prepare('
			UPDATE files SET thumbnail_name = :tn WHERE id = :id
		');
		$update_row->execute(array(
			'id' => $row_id,
			'tn' => $row_id . '.jpg'
		));
		$return_string = 'updatePage';
	} else {
		$request = $db->prepare('
			UPDATE files SET file_name = :n, file = :f, thumbnail = :t WHERE id = :id
		');
		$request->execute(array(
			'n' => $_FILES['file_name']['name'][0],
			'f' => base64_encode(file_get_contents($filePath)),
			't' => $thumb,
			'id' => $_POST['row_id']
		));
		$return_values = array('file_name' => '');
		if ($thumb != 'doc') {
			$return_values['thumbnail'] = 'data:image/jpeg;base64,' . $thumb;
		} else {
			$return_values['thumbnail'] = 'icons/doc.svg';
		}

	}
	unset($_POST['file_name']);
}
$_POST['download_name'] =  preg_replace( '/[^a-z0-9]+/', '_', strtolower(pathinfo($_POST['download_name'], PATHINFO_FILENAME)));
if (pathinfo($_POST['download_name'], PATHINFO_EXTENSION) != '' && pathinfo($_POST['download_name'], PATHINFO_EXTENSION) != null) {
	$_POST['download_name'] .= '.' . pathinfo($_POST['download_name'], PATHINFO_EXTENSION);

} 