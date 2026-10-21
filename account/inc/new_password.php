<?php
// $label_key: text key of the first password label
if (!isset($label_key)) {
	$label_key = 'password.password';
}

if (!isset($status)) {
	$status = '';
}
?>
<style>
	#password_setter .not_ok
	{
		color: red;
	}

	#password_setter .ok
	{
		color: green;
	}

	#password_setter .hidden
	{
		display: none;
	}	

</style>
<div id="password_setter" class="vertical">
	<div  class="horizontal">
		<label for="new_password_1" data-i18n="<?php echo $label_key; ?>"><?php echo labo_t($label_key); ?></label>
		<input type="password" id="new_password_1" name="new_password_1" pattern="^(?=.*\d)(?=.*[a-zA-Z])(?=.*[A-Z])(?=.*[\-\#\$\%\&\*])(?=.*[a-zA-Z]).{8,16}$" minlength="8" maxlength="16"  <?php echo $status; ?>/>
	</div>
	<div id="pw_validation" class="not_ok">
		<div id="pw_lower_char" data-i18n="password.rule_lower"><?php echo labo_t('password.rule_lower'); ?></div>
		<div id="pw_upper_char" data-i18n="password.rule_upper"><?php echo labo_t('password.rule_upper'); ?></div>
		<div id="pw_nbr_char" data-i18n="password.rule_number"><?php echo labo_t('password.rule_number'); ?></div>
		<div id="pw_spec_char" data-i18n="password.rule_special"><?php echo labo_t('password.rule_special'); ?></div>
		<div id="pw_length" data-i18n="password.rule_length"><?php echo labo_t('password.rule_length'); ?></div>
	</div>
	<div>
		<label for="new_password_2" data-i18n="password.confirm_label"><?php echo labo_t('password.confirm_label'); ?></label>
		<input type="password" id="new_password_2" name="new_password_2" <?php echo $status; ?>/>
	</div>
	<div id="second_pw_ok" class="ok hidden" data-i18n="password.same"><?php echo labo_t('password.same'); ?></div>
	<div id="second_pw_not_ok" class="not_ok" data-i18n="password.not_same"><?php echo labo_t('password.not_same'); ?></div>
</div>
<script>
	function checkPW() {
		if (pwInputPW1.value.length >= 8 && pwInputPW1.value.length <= 16) {
			pwLength.classList.toggle('ok', true);
		} else {
			pwLength.classList.toggle('ok', false);
		}
		if (rgLower.test(pwInputPW1.value)) {
			pwLower.classList.toggle('ok', true);
		} else {
			pwLower.classList.toggle('ok', false);
		}

		if (rgUpper.test(pwInputPW1.value)) {
			pwUpper.classList.toggle('ok', true);
		} else {
			pwUpper.classList.toggle('ok', false);
		}

		if (rgNumber.test(pwInputPW1.value)) {
			pwNumber.classList.toggle('ok', true);
		} else {
			pwNumber.classList.toggle('ok', false);
		}

		if (rgSpec.test(pwInputPW1.value)) {
			pwSpec.classList.toggle('ok', true);
		} else {
			pwSpec.classList.toggle('ok', false);
		}

		if (pwInputPW1.value == pwInputPW2.value) {
			secondPWOK.classList.toggle('hidden', false);
			secondPWNotOK.classList.toggle('hidden', true);
		} else {
			secondPWNotOK.classList.toggle('hidden', false);
			secondPWOK.classList.toggle('hidden', true);
		}
	}

	let pwInputPW1 = document.getElementById('new_password_1');
	let pwInputPW2 = document.getElementById('new_password_2');
	let pwLower = document.getElementById('pw_lower_char');
	let pwUpper = document.getElementById('pw_upper_char');
	let pwNumber = document.getElementById('pw_nbr_char');
	let pwSpec = document.getElementById('pw_spec_char');
	let pwLength = document.getElementById('pw_length');
	let secondPWOK = document.getElementById('second_pw_ok');
	let secondPWNotOK = document.getElementById('second_pw_not_ok');

	let rgLower = new RegExp("([a-z].*[a-z])");
	let rgUpper = new RegExp("([A-Z].*[A-Z])");
	let rgNumber = new RegExp("([0-9].*[0-9])");
	let rgSpec = new RegExp("([-#$%&*].*[-#$%&*])");

	pwInputPW1.addEventListener('keyup', (e) => {
		checkPW();
	});
		pwInputPW1.addEventListener('change', (e) => {
		checkPW();
	});
	pwInputPW2.addEventListener('keyup', (e) => {
		checkPW();
	});

	checkPW();
</script>	