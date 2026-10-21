// Execute an AJAX POST call
// Takes as parameters the target URL, the data to send and the callback function called in case of success
// The isJson parameter allows you to indicate whether the sending concerns JSON data
// The optional onError parameter replaces the default error message (receives the error key and the request)
function ajaxPost(url, data, callback, isJson, onError) {
    var req = new XMLHttpRequest();
    req.open("POST", url);
    req.timeout = 30000;

    function fail(key, details) {
        console.error(details + " " + url);
        if (typeof onError === 'function') {
            onError(key, req);
        } else {
            throwMessage(key);
        }
    }

    req.addEventListener("load", function () {
        if (req.status >= 200 && req.status < 400) {
            // Calls the callback function passing it the query response
            callback(req.responseText);
        } else {
            fail('ko-server_error', req.status + " " + req.statusText);
        }
    });
    req.addEventListener("error", function () {
        fail('ko-network_error', "Erreur réseau avec l'URL");
    });
    req.addEventListener("timeout", function () {
        fail('ko-timeout', "Délai dépassé avec l'URL");
    });
    if (isJson) {
        // Defines the request content as JSON
        req.setRequestHeader("Content-Type", "application/json");
        // Transforms data from JSON format to text format before sending
        data = JSON.stringify(data);
    }
    req.send(data);
}

// texts of the page (error messages and interface), keys from lang/xx.json
// built-in english texts: used when the language file can't be loaded (e.g. server down)
let labBuiltInTexts = {
    'ko-server_error' : "The server encountered an error and could not process the request. Please try again later or contact the administrator.",
    'ko-network_error' : "Unable to reach the server. Check your internet connection and try again.",
    'ko-timeout' : "The server is not responding. Please try again later."
};
// koTexts is filled by labLoadLang(), always the same object (pages read it directly)
let koTexts = Object.assign({}, labBuiltInTexts);

// available languages (same list as LABO_LANGS in inc/basis_functions.php)
const labLangs = ['en', 'fr', 'de'];

// language of the page, defined by PHP in <html lang="">
let labLang = labLangs.indexOf(document.documentElement.lang) != -1 ? document.documentElement.lang : 'en';

// lang/ folder is next to js/, whatever the page calling this file
const labBasisUrl = document.currentScript ? document.currentScript.src : window.location.href;

// the three entry points of the project root: pages, ajax requests, files
const labRootUrl = new URL('../', labBasisUrl).href;
const labSwapUrl = new URL('../labo_swap.php', labBasisUrl).href;
const labFileUrl = new URL('../file_swap.php', labBasisUrl).href;

function labLangUrl (file) {
    return new URL('../lang/' + file, labBasisUrl).href;
}

// load the texts of a language, english as fallback for missing keys
function labLoadLang (l) {
    let load = function (l) {
        return fetch(labLangUrl(l + '.json')).then(function (r) {
            if (!r.ok) { throw new Error(r.status + ' ' + r.url); }
            return r.json();
        }).catch(function (e) {
            console.error('Language file could not be loaded: ' + e.message);
            return {};
        });
    };
    let langs = l == 'en' ? ['en'] : ['en', l];
    return Promise.all(langs.map(load)).then(function (all) {
        Object.keys(koTexts).forEach(function (k) { delete koTexts[k]; });
        Object.assign(koTexts, labBuiltInTexts);
        // page language applied after english, so its texts win
        all.forEach(function (texts) {
            Object.assign(koTexts, texts);
        });
    });
}

// text of a key in the current language (the key itself if missing)
function labT (key) {
    return koTexts[key] != undefined ? koTexts[key] : key;
}

// translatable elements:
//   data-i18n="key"             => text of the element
//   data-i18n-placeholder="key" => placeholder attribute
//   data-i18n-value="key"       => value attribute (input buttons)
//   data-i18n-title="key"       => title attribute
const labI18nAttributes = {
    'data-i18n' : 'textContent',
    'data-i18n-placeholder' : 'placeholder',
    'data-i18n-value' : 'value',
    'data-i18n-title' : 'title'
};

function labTranslate (root = document) {
    Object.keys(labI18nAttributes).forEach(function (a) {
        root.querySelectorAll('[' + a + ']').forEach(function (el) {
            // unknown key: keep the text written by PHP
            if (koTexts[el.getAttribute(a)] != undefined) {
                el[labI18nAttributes[a]] = labT(el.getAttribute(a));
            }
        });
    });
}

// change the text key of an element (keeps it right on the next language change)
function labSetText (el, key, attribute = 'data-i18n') {
    el.setAttribute(attribute, key);
    el[labI18nAttributes[attribute]] = labT(key);
}

// language switcher (inc/lang_switcher.php): save the choice, reload the texts and update the page
function labSwitchLang (l) {
    if (labLangs.indexOf(l) == -1 || l == labLang) { return; }

    let f = new FormData();
    f.append('to_do', 'set_lang');
    f.append('lang', l);
    ajaxPost(labSwapUrl, f, function (r) {
        if (r.substr(0, 2) == 'ko') {
            console.error('Language choice not saved: ' + r);
        }
    }, false, function (key) {
        // the page is translated anyway, only the saving failed
        console.error('Language choice not saved: ' + key);
    });

    labLoadLang(l).then(function () {
        labLang = l;
        document.documentElement.lang = l;
        labTranslate();
        document.querySelectorAll('.lang_box').forEach(function (b) {
            b.classList.toggle('active', b.dataset.lang == l);
        });
    });
}

document.addEventListener('click', function (e) {
    let box = e.target.closest('.lang_box');
    if (box != null) {
        labSwitchLang(box.dataset.lang);
    }
});

labLoadLang(labLang).then(function () {
    labTranslate();
});

function throwMessage (m) {
    if (koTexts[m] != undefined) {
        alert(koTexts[m]);
        if (m == 'ko-site_in_maintenance') {
            window.location.href = 'rallye-mathematique-romand.ch';
        }
    } else {
        alert(m);
    }
}

// menu opener
let menuOpener = document.querySelector('.menu_hidder img');
let menu = document.querySelector('menu');
if (menuOpener != null && menu != null) {
    menuOpener.addEventListener('click', () => {
        menu.classList.toggle('open');
        if (menu.classList.contains('open')) {
            menu.style.bottom = 'calc( -' + menu.clientHeight + 'px - 0.6em)';
        } else {
            menu.style.bottom = '0';
        }
    })
}