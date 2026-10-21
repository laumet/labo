<h2 data-i18n="register.title"><?php echo labo_t('register.title'); ?></h2>
<?php if (!empty($_SESSION['first_setup'])) { ?>
<p class="first_setup" data-i18n="setup.explanation"><?php echo labo_t('setup.explanation'); ?></p>
<?php } ?>
<form id="register">
	<h3 data-i18n="register.step1_title"><?php echo labo_t('register.step1_title'); ?></h3>
	<div>
		<label for="username" data-i18n="register.username_label"><?php echo labo_t('register.username_label'); ?></label>
		<input type="text" id="username" name="username" size="50" placeholder="<?php echo labo_t('connect.username_placeholder'); ?>" data-i18n-placeholder="connect.username_placeholder"<?php if (isset($_POST['username']) && is_string($_POST['username'])) { echo ' value="' . htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') . '"';} ?> required />
	</div>
	<div id="temp_password" class="hidden" style="flex-direction: column;">
		<p data-i18n="register.temp_password_sent"><?php echo labo_t('register.temp_password_sent'); ?></p>
		<div class="horizontal">
			<label for="temp_password_input" data-i18n="register.temp_password_label"><?php echo labo_t('register.temp_password_label'); ?></label>
			<input id="temp_password_input" type="password" name="temp_password" />
		</div>
	</div>
	<input id="to_do" type="hidden" name="to_do" value="create_account" />
	<div style="justify-content: flex-end;"><input id="first_stage_register" type="submit" value="<?php echo labo_t('register.validate'); ?>" data-i18n-value="register.validate" /></div>
</form>
<form id="user_values" class="hidden">
	<h3 data-i18n="register.step2_title"><?php echo labo_t('register.step2_title'); ?></h3>
	<div id="user_values_content" class="vertical">
	</div>
	<input type="hidden" name="to_do" value="create_account" />
<?php
$status = 'required ';
include 'inc/new_password.php';
?>
	<div style="justify-content: flex-end;">
		<input id="register_button" type="submit" value="<?php echo labo_t('register.validate'); ?>" data-i18n-value="register.validate">
	</div>
</form>
<script>
	// first opening: no e-mail check, the first administrator form is displayed directly
	let firstSetup = <?php echo !empty($_SESSION['first_setup']) ? 'true' : 'false'; ?>;
	let form = document.getElementById('register');
	let formValues = document.getElementById('user_values');
	let tempPW = document.getElementById('temp_password');
	let tempPWInput = document.getElementById('temp_password_input');
	let toDo = document.getElementById('to_do');
	let userNameInput = document.getElementById('username');
	let registerValidButton = document.getElementById('register_button');
	let userValuesForm = document.getElementById('user_values');
	let userValuesFormContent = document.getElementById('user_values_content')

	function deleteFormFirstStage () {
		register.parentElement.removeChild(register);
		return;
	}

	function addUserCreation () {
		// first get form from server
		let f = new FormData();
		f.append('to_do', 'get_user_form');
		ajaxPost(labSwapUrl, f, function (r) {
			console.log(r);
			if (r.length > 0) {
				userValuesFormContent.innerHTML = r;
			}

			// get now username in new form
			let newUserNameInput = document.getElementById('user_form_username');
			if (newUserNameInput != null && !firstSetup) {
				newUserNameInput.value = userNameInput.value;
				// freeze username input
				newUserNameInput.disabled = true;
			} else if (!firstSetup) {
				console.log("username not found");
			}


			userValuesForm.classList.toggle('hidden', false);
		})

	}

	function userAlreadyExists () {
		// disable username and temp password
		userNameInput.disabled = true;
		tempPWInput.disabled = true;

		// remove temp password text
		tempPW.removeChild(tempPW.firstElementChild);

		// get those values and put them in form 'user_values'
		userValuesFormContent.appendChild(userNameInput.parentElement);
		userValuesFormContent.appendChild(tempPW);

		// remove first stage form
		form.parentElement.removeChild(form);

		// change title...
		let title = userValuesForm.querySelector('h3');
		if (title == null) {
			console.log('title not found - exit');
			return;
		}
		labSetText(title, 'register.step2_user_exists');

		// add explanations (translatable: text and link keep their keys)
		let exp = document.createElement('p');
		let expText = document.createElement('span');
		labSetText(expText, 'register.user_exists_explanation');
		let expLink = document.createElement('a');
		expLink.href = labRootUrl;
		expLink.style.color = 'var(--main)';
		labSetText(expLink, 'register.login_link');
		exp.appendChild(expText);
		exp.appendChild(document.createElement('br'));
		exp.appendChild(expLink);
		userValuesForm.insertBefore(exp, title.nextElementSibling);

		// display second stage
		userValuesForm.classList.toggle('hidden', false);

	}

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		let f = new FormData(form);
		ajaxPost(labSwapUrl, f, function (r) {
			console.log(r);
			if (r.substr(0, 2) == 'ok') {
				switch (r.substr(3)) {
					case 'secret_code_mail_sended' :
					case 'new_pw_mail_sended' :
						tempPW.classList.toggle('hidden', false);
						break;
					case 'user_can_be_created' :
						addUserCreation();
						deleteFormFirstStage();
						break;
					case 'user_already_exits' :
						userAlreadyExists();
						break;
				}
			} else {
				throwMessage(r.substr(3));
			}
		})

	})

	formValues.addEventListener('submit', function (e) {
		e.preventDefault();
		let f = new FormData();
		// add content of formValue...
		let fi = formValues.querySelectorAll('input:not([type="submit"])');
		if (fi.length > 0) {
			fi.forEach(function (i) {
				f.append(i.name, i.value);
			});
			f.append('to_do', 'create_account');
			ajaxPost(labSwapUrl, f, function (r) {
				console.log(r);
				if (r.substr(0, 2) == 'ok') {
				switch (r.substr(3)) {
						case 'labo_open' :
							window.location.href = labRootUrl;
							break;
					}
				} else {
					throwMessage(r.substr(3));
				}
			})
		}
	})

	// after loading: ajaxPost comes from js/labo_basis.js, loaded at the end of the page
	if (firstSetup) {
		document.addEventListener('DOMContentLoaded', function () {
			addUserCreation();
			deleteFormFirstStage();
		});
	}
</script>
