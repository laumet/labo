<script>
    let mailButton = document.getElementById('plugin_mail_button');
    if (mailButton != null) {
        mailButton.addEventListener('click', () => {
            addRow(mailButton);
        })
    }

    function mailSendedEndSplash(v) {
        console.log(v);
        let htmlContainer = '<div class="plugin_mail_end_sended_mail">';
        let htmlContainerEnd = '</div><div class="plugin_mail_close_splash"></div>';
        let htmlContent = '<p>Le message a bien été envoyé.</>';
        let htmlContentDetails = '<div class="plugin_mail_end_sended_mail_details expandable reduced"><div>Détails de l\'envoi</div>';
        let htmlContentDetailsEnd = '</div>';
        if (v.indexOf('error') != -1) {
            let htmlContent = '<p>Le message a envoyé, mais une ou plusieurs erreurs ont été rencontrées.</>';
        }
        let htmlContentDetailsElts = '<p class="plugin_mail_details">' + v + '</p>';
        if (v.indexOf(' / ') != -1) {
            let details = v.split(' / ');
            htmlContentDetailsElts = '';
            details.forEach(d => {
                htmlContentDetailsElts += '<p class="plugin_mail_details">' + d + '</p>';
            })
        }
        splashContainer.innerHTML = htmlContainer + htmlContent + htmlContentDetails + htmlContentDetailsElts + htmlContentDetailsEnd + htmlContainerEnd;

        let pluginSplashCloser = splashContainer.querySelector('.plugin_mail_close_splash');
        if (pluginSplashCloser != null) {
            pluginSplashCloser.addEventListener('click', () => { splashCloser.click(); })
        }

        let pluginsExpandables = splashContainer.querySelectorAll('.expandable');
        if (pluginsExpandables.length > 0) {
            pluginsExpandables.forEach(ex => {
                ex.addEventListener('click', () => {
                    ex.classList.toggle('reduced');
                })
            })
        }
     }

    function setMailSplash (w) {
        // console.log("call splash mail ! => first get all mail adresses not hidden");
        let emailAddresses = editor.querySelectorAll('row:not(.hidden) [name="username"]');
        // console.log("call splash mail ! => first get all mail adresses not hidden");
        console.log("rows founded: ", emailAddresses)
        let mailingList = '';
        if (emailAddresses.length > 0) {
            // create mailing list
            emailAddresses.forEach(ea => {
                if (ea.value != '' && ea.value != null) {
                    mailingList += mailingList.length > 0 ? ', ' : '';
                    mailingList += ea.value;
                }
            })
        }

        // console.log("then => get all specific inputs taged 'plugin_mail'")
        let pluginMailAddresses = editor.querySelectorAll('row:not(.hidden) [plugin_mail]');
        let otherMails = false;
        if (pluginMailAddresses.length > 0) {
            // create different listes to separate the columns
            otherMails = [];
            pluginMailAddresses.forEach(pma => {
                if (pma.value != '' && pma.value != null) {
                    if (otherMails[pma.getAttribute('plugin_mail')] == undefined) { otherMails[pma.getAttribute('plugin_mail')] = ''; }
                    otherMails[pma.getAttribute('plugin_mail')] += otherMails[pma.getAttribute('plugin_mail')].length > 0 ? ', ' : '';
                    otherMails[pma.getAttribute('plugin_mail')] += pma.value;
                }  
            })
        }

        console.log('mailingList: ', mailingList, 'otherMails', otherMails, Object.keys(otherMails).length);
        setSplash(w);
        setEditor(document.querySelector('splashanchor'));
        let recipients = document.querySelector('splashcontainer [name="recipients"]')
        if (recipients != null) { recipients.value = mailingList;}

        // add, if required, other fields !
        if (Object.keys(otherMails).length > 0) {
            for (const [key, value] of Object.entries(otherMails)) {
                console.log('add', key, value);
                let newColumn = document.createElement('div');
                let newLabel = document.createElement('label');
                newLabel.htmlFor = key.replaceAll(' ', '');
                newLabel.textContent = key;
                let newRecipientList = document.createElement('textarea');
                newRecipientList.id = key.replaceAll(' ', '');
                newRecipientList.name = 'other_mails_' + key.replaceAll(' ', '');
                newRecipientList.value = value;
                newRecipientList.style.width = '100%';
                newColumn.appendChild(newLabel);
                newColumn.appendChild(newRecipientList);
                if (recipients != null) {
                    recipients.parentElement.appendChild(newColumn);
                }
            }
        }

        // get change to load modell
        let modellSelect = document.querySelector('select[id^="mail_plugin_none_"]');
        if (modellSelect != null) {
            modellSelect.addEventListener('change', () => {
                getModell(modellSelect.value)
            });
        }
    }

    function mailInputChange () {
        // check if object and mail aren't empty
        let text = document.querySelector('splashcontainer .ql-editor.ql-blank');
        if (text != null) { return false; }
        let object = document.querySelector('splashcontainer [name="object"]');
        if (object != null && object.value == '') { return false; }
        let recipients = document.querySelector('splashcontainer [name="recipients"]')
        if (recipients != null && recipients.value == '') { return false; }
        return true;
    }

    function mailSetEditor () {
        if (mailButton) {
            let emailAddresses = editor.querySelector('wrapper:not([role="pa"]) [name="username"]');
            if (emailAddresses != null) {
                mailButton.classList.toggle('hidden', false);
            } else {
                mailButton.classList.toggle('hidden', true);
            }
        }
    }

    function getModell(id) {
        console.log("getting modell => ", id);
        if (id == null || id == '') { return; }
        let f = new FormData();
        f.append('to_do', 'plugin_job');
        f.append('plugin', 'mail');
        f.append('plugin_job', 'modell');
        f.append('mail_id', id);
        ajaxPost(labSwapUrl, f, function (r) {
            if (r.substr(0, 2) == 'ok') {
                console.log(r.substr(3));
                let content = JSON.parse(r.substr(3));
                let text = document.querySelector('splashcontainer .text_editor');
                if (text != null && content['mail_content'] != '' && content['mail_content'] != null) { 
                    window['quill_editor_' + text.id].root.innerHTML = content['mail_content'];
                }
                let object = document.querySelector('splashcontainer [name="object"]');
                if (object != null && content['object'] != '' && content['object'] != null) {
                    object.value = content['object'];
                }

                // allow sending
                let mailButton = document.querySelector('splashcontainer save');
                if (mailButton != null) {
                    mailButton.removeAttribute('not')
                }
            } else {
                console.log(r);
                throwMessage(r.substr(3));
            }
        })

    }
    
    plugins.setEditor.mail = 'mailSetEditor';
    plugins.inputsChange.mail = 'mailInputChange';
</script>