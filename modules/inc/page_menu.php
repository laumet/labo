<?php
// menu of the data management mode page_with_modules (included by modules/labo_page.php)
// one hidden tab and one section, so that js/script.js opens the first entry as in single_generator_per_page
// $blocs: pages of the logged-in user (labo_define_pages_menu)
$home_roles = labo_session_user_roles();
$home_role = count($home_roles) > 0 ? $home_roles[0] : '';
?>
        <tabs class="hidden">
            <tab class="hidden" role="pages"><?php echo labo_t('menu.pages'); ?></tab>
        </tabs>
        <spaces>
            <section role="pages" activerole>
<?php
if ($home_role !== '') {
    echo '<bloc index="homepage_' . htmlspecialchars($home_role, ENT_QUOTES, 'UTF-8') . '" role="pages" data-i18n="menu.homepage">' . labo_t('menu.homepage') . '</bloc>';
}
foreach ($blocs as $page) {
    echo '<bloc index="page_' . (int) $page['id'] . '" role="pages">' . htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') . '</bloc>';
}
?>
            </section>
        </spaces>
