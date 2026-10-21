<form id="connection">
	<div>
		<input type="hidden" name="domain" value="account">
		<label for="username" data-i18n="connect.username_label"><?php echo labo_t('connect.username_label'); ?></label>
		<input type="text" id="username" name="username" size="50" placeholder="<?php echo labo_t('connect.username_placeholder'); ?>" data-i18n-placeholder="connect.username_placeholder" required />
	</div>
	<div id="secret_code_explanation" class="hidden">
		<p id="secret_code_txt" data-i18n="connect.new_password_sent"><?php echo labo_t('connect.new_password_sent'); ?></p>
	</div>
	<div class="horizontal" style="justify-content: space-between; align-items: baseline;">
		<div>
			<label id="label_password" for="password" data-i18n="connect.password_label"><?php echo labo_t('connect.password_label'); ?></label>
			<input type="password" id="password" name="password" />
		</div>
	</div>
	<div id="secret_code_div" class="hidden">
		<label for="secret_code" data-i18n="connect.secret_code_label"><?php echo labo_t('connect.secret_code_label'); ?></label>
		<input type="text" id="secret_code" name="secret_code" />
	</div>
	<div id="new_password" class="hidden">
<?php
$label_key = 'password.new_password';
include 'inc/new_password.php';
?>
	</div>	
	<div style="justify-content: flex-end;">
		<input id="connect" type="submit" value="<?php echo labo_t('connect.connect_button'); ?>" data-i18n-value="connect.connect_button">
	</div>
	<div class="account_links horizontal" style="justify-content: flex-end; align-items: baseline;">
<?php if (labo_feature('forgotten_password')) { ?>
		<input id="forgotten_password" class="link_button" type="submit" value="<?php echo labo_t('connect.forgotten_password'); ?>" data-i18n-value="connect.forgotten_password">
<?php } ?>
<?php if (labo_feature('create_account')) { ?>
		<input id="create_account" class="link_button" type="submit" form="register" value="<?php echo labo_t('connect.create_account'); ?>" data-i18n-value="connect.create_account" />
<?php } ?>
	</div>
</form>
<?php if (labo_feature('create_account')) { ?>
<form id="register" action="<?php echo labo_url(); ?>" method="post" >
	<input type="hidden" name="username" />
	<input type="hidden" name="to_do" value="create_account" />
</form>
<?php } ?>

<script>
let form = document.getElementById('connection');

let register = document.getElementById('register');

let userName = document.getElementById('username');

let password = document.getElementById('password');
let labelPassword = document.getElementById('label_password');

let secretCodeExplanation = document.getElementById('secret_code_explanation');

let secretCodeDiv = document.getElementById('secret_code_div');
let secretCodeTxt = document.getElementById('secret_code_txt');

let newPWDiv = document.getElementById('new_password');
let newPW1 = document.getElementById('new_password_1');
let newPW2 = document.getElementById('new_password_2');

let connect = document.getElementById('connect');

let forgottenPassword = document.getElementById('forgotten_password');

let newAccountButton = document.getElementById('create_account');

function resetForm () {
	labSetText(secretCodeTxt, 'connect.new_password_sent');
	secretCodeDiv.classList.toggle('hidden', true);
	secretCodeExplanation.classList.toggle('hidden', true);
	if (newAccountButton != null) {
		newAccountButton.classList.toggle('hidden', false);
	}
	newPWDiv.classList.toggle('hidden', true);
	labSetText(labelPassword, 'connect.password_label');
	connect.id = 'connect';
	password.name = 'password';
	return;
}

function removeButtons (buttons = false) {
	if (buttons && buttons.length > 0) {
		buttons.forEach(function (b) {
			// button switched off in default/features.json or already removed
			if (b != null && b.parentElement != null) {
				b.parentElement.removeChild(b);
			}
		});
	}
	return;
}

function disableInputs (inputs = false) {
	if (inputs && inputs.length > 0) {
		inputs.forEach(function (i) {
			i.disabled = true;
		});
	}
	return;
}

function displaySecretCode () {
	resetForm();
	secretCodeExplanation.classList.toggle('hidden', false);
	labSetText(secretCodeTxt, 'connect.auth_code_sent');
	secretCodeDiv.classList.toggle('hidden', false);

	// remove forgotten && new_account button
	removeButtons([forgottenPassword, newAccountButton]);

	// disable username and password
	disableInputs([userName, password]);
	return;
}

function displayNewPassword () {
	resetForm();
	newPWDiv.classList.toggle('hidden', false);
	labSetText(labelPassword, 'connect.recovery_password_label');
	secretCodeExplanation.classList.toggle('hidden', false);

	// change id's / names : connexion button, password
	connect.id = 'save_new_password';
	labSetText(connect, 'connect.save_new_password_button', 'data-i18n-value');
	password.name = 'temp_password';
	password.value = '';

	// remove forgotten && new_account button
	removeButtons([forgottenPassword, newAccountButton]);

	// disable username
	disableInputs([userName]);
	return;
}

if (register != null) register.addEventListener('submit', function (e) {
	if (userName.value.length > 0) {
		let registerUserName = document.getElementById('register_username');
		if (registerUserName != null && userName.value != '' && userName.value != null ) {
			registerUserName = userName.value;
		}
	}
})

form.addEventListener('submit', function (e) {
	e.preventDefault();
	console.log(e.submitter, e.activeElement);

	// if submitter id != activeElement ID => Enter action => got to connect 
	let submissionBy = e.submitter.id;
	if (submissionBy != document.activeElement.id) {
		submissionBy = 'connect';
	}

	switch (submissionBy) {
		case 'save_new_password' :
			newPW1.required = true;
			newPW2.required = true;
		case 'connect' :
			password.required = true;
		case 'forgotten_password' :
			userName.required = true;

			let f = new FormData();
			let ai = form.querySelectorAll('*:not(.hidden) input:not([type="submit"])');
			if (ai.length > 0) {
				ai.forEach(function (i) {
					f.append(i.name, i.value);
				})
			}

			f.append('to_do', submissionBy);
			
			console.log('start ajax', f);
			ajaxPost(labSwapUrl, f, function (r) {
				console.log(r);
				if (r.substr(0, 2) == 'ko') {
					alert(labT(r.substr(3)));
					return;
				} else {
					if (r.indexOf('|') != -1) {
						r = r.split('|');
						content = r[1];
						r = r[0];
					}

					switch (r.substr(3)) {
						case 'new_account_can_be_created' :
							registerUserName.value = userName.value;
							register.submit();
							break; 
						case 'secret_code_mail_sended' :
							displaySecretCode();
							break;
						case 'new_pw_mail_sended' :
							displayNewPassword();
							break;
						case 'first_setup' :
							// installation account: the page now shows the first administrator form
							window.location.href = labRootUrl;
							break;
						case 'labo_open' :
							window.location.href = labRootUrl;
							break;
						case 'site_in_maintenance' :
							window.location.href = labRootUrl;
							break;

					}
				}
			})
			break;
	}
})
</script>