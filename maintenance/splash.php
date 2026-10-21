<style>
	splash, locked {display:block;}
	splash {position: fixed; top:0; left:0; width:100vw; height: 100vh; padding:25vh 25vw;}
	splashcontent {
		background-color: #dc3e53b0;
	    color: white;
	    font-size: 200%;
	    text-align: center;
	    padding: 7vh;
	    font-weight: bolder;
	    width: 100%;
	    height: 100%;
	    display: flex;
	    justify-content: center;
	    align-items: center;
	}
	locked, unlocked  { width: 2rem; height: 2rem; background-repeat: no-repeat; background-size: cover; border-radius: 0.25rem; margin: 0.25rem; position:fixed; bottom: 0.2em; right: 0.2em;}
	locked { background-image: url("labo/icons/locked.svg");}
	unlocked { background-image: url("labo/icons/unlocked.svg");}
	splashcontent a:hover {transform:unset !important;}
</style>
<splash>
	<splashcontent>
		<h1>Site en maintenance</h1>
<?php
	$blocked = true;
	if (isset($_SESSION) && isset($site_default) && isset($_SESSION['display_roles']) && isset($site_default['maintenance']) && isset($site_default['maintenance']['login']) && $site_default['maintenance']['login'] == 'on' && isset($site_default['maintenance']['login_for']) && $site_default['maintenance']['login_for'] != '' && $site_default['maintenance']['login_for'] != null) {
		$accepted_maintenance_roles = explode(',', $site_default['maintenance']['login_for']);
		foreach($_SESSION['display_roles'] as $r) {
			if (in_array($r, $accepted_maintenance_roles)) {
				$blocked = false;
			}
		}
	}
	if ($blocked) {
?>
		<a href="account"><locked></locked></a>
<?php
	} else {
?>
		<a href="labo"><unlocked></unlocked></a>
<?php
	}
?>
	</splashcontent>
</splash>
<?php
// as html registred page site_maintenance.html is not closed... do it	
?>
</body>
</html>