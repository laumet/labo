<?php
// language switcher: one box per available language, handled by js/labo_basis.js
// needs inc/basis_functions.php
if (!labo_feature('lang_switcher')) {
	return;
}
?>
<div class="lang_switcher" data-i18n-title="lang.switcher_title" title="<?php echo labo_t('lang.switcher_title'); ?>">
<?php
foreach (LABO_LANGS as $l) {
	$active = $l == labo_lang() ? ' active' : '';
	echo '<button type="button" class="lang_box' . $active . '" data-lang="' . $l . '" lang="' . $l . '">' . strtoupper($l) . '</button>';
}
?>
</div>
