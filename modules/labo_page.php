<?php
// LABO application page, included by index.php when a user is logged in, never called directly
chdir(__DIR__);

error_reporting(E_ALL);
ini_set('display_errors', '0');
// exit("fini fertig");

if (!isset($_SESSION['roles'])) {
    $_SESSION['state'] = 'unconnected';
}

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['username']) || !isset($_SESSION['roles']) || !$_SESSION['roles'])
{
    header('Location: ' . labo_url());
    exit();
} else {

    $_SESSION['authorized'] = false;

    require_once __DIR__ . '/inc/connect_db.php';

    require_once __DIR__ . '/../inc/basis_functions.php';

    require_once __DIR__ . "/inc/constructor.php";
    
    include __DIR__ . '/../inc/site_default.php';

    include __DIR__ . '/inc/labo_head.php';

    runPlugins('labo_head.php');
?>
<div id="sessid" sessid="<?php echo session_id(); ?>"></div>
<div style="position:fixed; top: 1vh; right: 1vh; display: flex;flex-direction: row; z-index: 10;">
    <a href="../" title="<?php echo $site_default['site_link_in_labo']; ?>"><home></home></a>
    <a href="../?page=deconnexion" title="Déconnexion" ><exit></exit></a>
</div>
<?php
?>
    <!-- <install></install> -->
    <!-- <page mode="menu">
    </page> -->
    
<page>
    <splashanchor class="hidden">
        <splashcloser></splashcloser>
        <splashbg></splashbg>
        <splashcontainer></splashcontainer>
    </splashanchor>
    <menu>
<?php
    runPlugins('labo_menu_top.php');
?>
        <h1> <!-- HTML -->
            <img src="../img/logo_color.png" />
        </h1>
<?php
    // page_with_modules: one menu entry per page (modules/inc/page_menu.php)
    if (labo_page_mode()) {
        include __DIR__ . '/inc/page_menu.php';
    } else {
?>
        <tabs<?php 
            // tab visible only when more of 1
            if (count($roles) == 1) {
                echo ' class="hidden"';
            }
            ?>> <!-- HTML -->
            <!-- php -->
            <?php
                foreach ($roles as $role) {
                    $role_title = count($roles) > 2 ? $role['role_short_name_display'] : $role['role_name'];
                    echo '<tab ';
                    if (count($roles) == 1) { echo ' class="hidden" '; }
                    echo 'level="' . $role['id'] . '" role="' . $role['role_short_name'] . '" >' . $role_title . '</tab>';
                }
            ?>
        </tabs>
        <spaces>
            <!--php? -->
<?php
            /*
            echo '<pre>';
            var_dump($blocs);
            echo '</pre>';
            */
            if (is_array($blocs) && count($blocs) > 0) {
            $previous_section = $blocs[0]['section'];
            $previous_user_type = $blocs[0]['user_type'];
            $active_table = true;
            $active_role = $blocs[0]['user_type'];

            // each role start with access to homepage of role
            echo '<section role="' . $blocs[0]['user_type'] . '" >';
            echo '<bloc index="homepage_' . $blocs[0]['user_type'] . '" role="' . $blocs[0]['user_type'] . '" >ACCUEIL - ' . $blocs[0]['role_name'] . '</bloc>';
            echo '</section>';

            echo '<section role="' . $blocs[0]['user_type'] . '">';
            foreach ($blocs as $b) {
                $display_bloc = true;
                if ($_SESSION['roles']['max_level'] < 90 && $b['conditional_display'] != null && $b['conditional_display'] != '' && $b['conditional_display'] != 'null') {
                    $display_bloc = checkDisplay($b['conditional_display']);
                }

                $section = $b['section'];
                $user_type = $b['user_type'];
                if ($display_bloc == true && ($section != $previous_section || $user_type != $previous_user_type)) {
                    echo '</section>';
                    
                    // each role start with access to homepage of role => check if role changes
                    if ($user_type != $previous_user_type) {
                        echo '<section role="' . $b['user_type'] . '"';
                        if ($b['user_type'] == $active_role) {
                            echo ' activerole';
                        }
                        echo ' >';
                        echo '<bloc index="homepage_' . $b['user_type'] . '" role="' . $b['user_type'] . '">ACCUEIL - ' . $b['role_name'] . '</bloc>';
                        echo '</section>';
                    }

                    echo '<section role="' . $b['user_type'] . '"';
                    if ($b['user_type'] == $active_role) {
                        echo ' activerole';
                    }
                    echo ' >';

                    $previous_section = $section;
                    $previous_user_type = $user_type;
                }

                if ($display_bloc == true) {
                    echo '<bloc index="' . $b['id'] . '" role="' . $b['user_type'] . '" ';
                    
                    if ($active_table) {
                        echo 'activetable ';
                        $active_table = false;
                    }
                    
                    echo '>' . $b['title'] . '</bloc>';
                }
            }
            echo "</section>";
            } else {
            // no bloc to display: still give each role/menu its ACCUEIL homepage link
            $first_role = true;
            foreach ($roles as $role) {
                echo '<section role="' . $role['role_short_name'] . '"';
                // mark first role as active so its section is visible even before JS runs
                if ($first_role) {
                    echo ' activerole';
                    $first_role = false;
                }
                echo ' >';
                echo '<bloc index="homepage_' . $role['role_short_name'] . '" role="' . $role['role_short_name'] . '" >ACCUEIL - ' . $role['role_name'] . '</bloc>';
                echo '</section>';
            }
            }
?>
        </spaces>
<?php
    }
    runPlugins('labo_menu_end.php');
?>            
    </menu>
    <editor>
    </editor>
</page>
<?php
    include __DIR__ . '/inc/labo_scripts.php';

    runPlugins('scripts.php');

    include __DIR__ . '/inc/labo_footer.php';
}