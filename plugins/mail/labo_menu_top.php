<?php
// get user level and check hability
if (is_file(__DIR__ . '/mail_default.json')) {
	$mail_level = json_decode(file_get_contents(__DIR__ . '/mail_default.json'), true);
} else {
	return;
}
if ($mail_level['mail_level_min'] <= $_SESSION['roles']['max_level']) {
?>
<style>
	.plugin_mail_button_fixator {
		height: 0;
		width: 100%;
		position: relative;
    	z-index: 10;
	}

	.plugin_mail_button_container {
    	background-color: white;
	    border-radius: 50%;
	    padding: 0.25rem;
	    margin: 0.5rem;
	    height: 3rem;
	    width: 3rem;
	    position: absolute;
	    top: 0;
	    right: 0;
	}

	.plugin_mail_button {
		background-image: url("icons/mail.svg");
	}

	splashcontainer {
		overflow: auto;
	}

	splashcontainer row {
		background-color: var(--bg) !important;
		width : auto;
		padding : 0;
		border : 0 !important;
	}

	splashcontainer .ql-editor {
	    background-color: white;
	    resize: vertical;
	    height: 30vh;
	    max-height: 44vh;
	    overflow-y: auto;
	}

	splashcontainer .row > div {
    	width: -webkit-fill-available;
	}

	splashcontainer input[name="object"], splashcontainer textarea[name="recipients"] {
	    width: 100%;
	}

	splashcontainer textarea[name="recipients"] {
		resize: vertical;
	}

	splashcontainer .row > div:nth-child(2) >div {
	    width: auto;
	}

    splashcontainer label, splashcontainer .label {
    	font-weight: bolder;
    	margin-top: 0.5rem;
    }
    
	splashcontainer .radio_buttons {
	    flex-direction: row;
	    display: flex;
	}

    splashcontainer .radio_buttons input {
 	   margin: 0 1rem;
	}

	splashcontainer .radio_buttons label {
 	   font-weight: normal;
	}

    splashcontainer editingtools {
    	bottom: -4rem;
    	height: auto;
    }

    splashcontainer save, .plugin_mail_close_splash {
        background-image: none;
	    width: 15rem;
	    font-size: 2rem;
	    background-color: blue;
	    text-align: center;
	    border-radius: 0.5rem;
	    height: 3rem;
	    padding: 0.5rem;
	    color: white;
    }

    splashcontainer save:after {
	    content: 'ENVOYER';
	}

	.plugin_mail_close_splash {
		position: absolute;
    	right: 22%;
	}

	.plugin_mail_close_splash:after {
		content: 'FERMER';
	}

	 splashcontainer save[not] {
	 	 background-color: gray; 
	 }

	.plugin_mail_end_sended_mail {
	    width: 60%;
	    margin: 10vh auto;
	}

	.plugin_mail_end_sended_mail * {
	    font-size: 2rem;
	}

	.expandable {
	    max-height: 40vh;
	    overflow: auto;
	}

</style>
<div class="plugin_mail_button_fixator">
	<div id="plugin_mail_button" class="plugin_mail_button_container hidden" index="plugin_mail_1" splash splashfunction="setMailSplash" >
		<div class="plugin_mail_button other_buttons"></div>
	</div>
</div>
<?php
}