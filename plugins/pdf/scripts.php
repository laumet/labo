<script>
	function getPdf(e) {
		console.log('getting PDF from', e);

		// send request for pdf
		let f = new FormData();
		f.append('to_do', 'plugin_job');
		f.append('plugin', 'pdf');
		f.append('plugin_job', 'get_pdf');
		// module of the button (a page can contain several modules in page_with_modules)
		f.append('generator', e != null && e.getAttribute('index') != null ? e.getAttribute('index') : generator);
		// see_as management
		let seeAs = editor.querySelector('select[name="see_as"]');
		let seeAsVal = false;
		if (seeAs != null) {
			f.append('see_as', seeAs.value);
		}
		ajaxPost(labSwapUrl, f, function (r) {
			console.log(r);
			if (r.substr(0, 2) == 'ok') {
				let file = r.substr(3).split('->');
				window.open(labFileUrl + '?p=pdf&f=' + file[0] + '&n=' + file[1], '_blank');
			} else {
				alert('Erreur rencontrée');
			}
		})
	}
</script>