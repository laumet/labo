Quill.register("modules/resize", window.QuillResizeModule);

let tabList = [...document.getElementsByTagName('tab')];
let sectionList = [...document.getElementsByTagName('section')];
let blocList = [...document.getElementsByTagName('bloc')];
let page = document.getElementsByTagName('page')[0];
let editor = document.getElementsByTagName('editor')[0];
let wrapper;
let generator;

let tabs = document.getElementsByTagName('tabs')[0];
let spaces = document.getElementsByTagName('spaces')[0];
let plugins = {
    'setEditor' : [],
    'inputsChange' : [],
    'saveRow' : [],
    'editFileList' : []
};
let splash = document.querySelector('splashanchor');
let splashContainer = document.querySelector('splashcontainer');
let splashCloser = document.querySelector('splashcloser');

function setup() {
    tabList.forEach(t => {
        t.addEventListener('click', () => {
            if (t.getAttribute('activeRole') == null) {
                activeRole(t.getAttribute("role"))
            }
        })
    });
    blocList.forEach(b => {
        b.addEventListener('click', () => {
            if (b.getAttribute('activeTable') == null) {
                activeBloc(b.getAttribute('index'));
                setTable(b.getAttribute('index'));
            }
        })
    })

    let sessid = document.getElementById('sessid');
    console.log('Sessid => ', sessid);
    let existingSession = false;

    if (sessid != null) {
        sessid = sessid.getAttribute('sessid');
        if (sessionStorage.getItem('role') != null) {
            // check if we are already in same server session
            // search sessid
            console.log('server sessid:', sessid);
            if (sessionStorage.getItem('sessid') == sessid) {
               existingSession = true
            }
        }
    }

    if (!existingSession) {
        activeRole(tabList[0].getAttribute('role'));
        sessionStorage.setItem('sessid', sessid);
        sessionStorage.setItem('role', tabList[0].getAttribute('role'));
    } else {
        activeRole(sessionStorage.getItem('role'), false);
    }

    splashCloser.addEventListener('click', () => {
        splashContainer.innerHTML = '';
        sessionStorage.setItem('sessid', sessid);
        splash.classList.toggle('hidden', true);
    })
}

function getAllInputs(r) {
    let row = editor.querySelector('row[id="row_' + r + '"]');
    if (row == null) {
        // check in splash
        row = document.querySelector('splashcontainer row[id="row_' + r + '"]')
        if (row == null) { console.log('no inputs found', r); return false;}
    }
    let inputs = row.querySelectorAll('input:not([type="checkbox"]), select, textarea, checkboxes, checkboxes_in_table, div.text_editor');
    console.log('Get inputs => ', inputs.length);
    if (inputs.length > 0) { return inputs; }
    else {return false;}
}

function getStringFromCheckboxes(c) {
    //let allCBs = c.querySelectorAll('input[type="checkbox"]:checked');
    let allCBs;
    if (c.tagName == 'CHECKBOXES') {
        allCBs = c.querySelectorAll('input[type="checkbox"]:checked');
    } else {
        allCBs = editor.querySelectorAll('#row_' + c.getAttribute('row') + ' [checkbox_in_table="' + c.id + '"]:checked');
    }
    if (allCBs.length == 0) {
        return '';
    }
    let sm = JSON.parse(c.getAttribute('save_method'));
    switch (sm['type']) {
        case 'implode' :
        case 'explode' :
            let s = '';
            allCBs.forEach((i) => {
                s += s.length > 0 ? sm['attribute'] : '';
                s += i.value; 
            })
            console.log('getStringFromCheckboxes : ', allCBs, s);
            return s;
            break;
        default :
            return 'save_method_not_build_yet';
            break;
    }
}

function updatePage () {
    window.location.href = window.location.href;
}
 
function getTabFormCheckboxes(c) {
    //let allCBs = c.querySelectorAll('input[type="checkbox"]');
    let allCBs;
    if (c.tagName == 'CHECKBOXES') {
        allCBs = c.querySelectorAll('input[type="checkbox"]');
    } else {
        allCBs = editor.querySelectorAll('#row_' + c.getAttribute('row') + ' [checkbox_in_table="' + c.id + '"]');
    }
    if (allCBs.length == 0) {
        return [];
    }
    let tab = [];
    allCBs.forEach((b) => {
        tab[b.name] = b.checked ? 'checked' : 'unchecked';
    })
    console.log('tab from checkboxes', tab);
    return tab;
}

function addRow (toAddButton) {
    let t = toAddButton.getAttribute('index');
    let sp = toAddButton.getAttribute('splash');
    console.log('start add row', t, sp);
    if (t == null) { console.log('index not defined'); return; }
    let f = new FormData();
    f.append('to_do', 'get_empty_row');
    f.append('index', t);
    if ( sp != null) {
        f.append('splash', true)
    }
    ajaxPost(labSwapUrl, f, function (r) {
        if (r == 'ko-not_logged') {
            window.location.href = ''
        } else if (r.substr(0, 2) == 'ok' && sp == null) {
            let d = document.createElement('div');
            let lastRow = editor.querySelector('row:last-of-type');
            if (lastRow != null) {
                lastRow.parentElement.insertBefore(d, lastRow.nextSibling)
            } else {
                toAddButton.parentElement.parentElement.insertBefore(d, toAddButton.parentElement);
            }
            d.outerHTML = r.substr(3);
            let newRow = editor.querySelector('row:last-of-type');
            if (newRow != null) {
                setEditor();
                // scroll to new row
                let height = editor.scrollHeight;
                if (editor.querySelector('contentcontainer') != null) {
                    height = editor.querySelector('contentcontainer').scrollHeight;
                    editor.querySelector('contentcontainer').scroll({'left':0,'top':height,'behavior':'smooth'})
                } else {
                    editor.scroll({'left':0,'top':height,'behavior':'smooth'})
                }
               
            }

            toAddButton.setAttribute('not', '');


            return;
        } else if (r.substr(0, 2) == 'ok') {
            if (toAddButton.getAttribute('splashfunction') && typeof(window[toAddButton.getAttribute('splashfunction')]) == 'function') {
                console.log('splash, returning:', r.substr(3));
                window[toAddButton.getAttribute('splashfunction')](r.substr(3))
            }
            return (r.substr(3));
        } else {
            throwMessage(r.substr(3));
        }
    })
}

function refreshRow (toRefreshButton) {
    let t = toRefreshButton.getAttribute('index');
    let ri = toRefreshButton.getAttribute('row');
    console.log('start refresh row', t, ri);
    if (t == null || ri == null) { console.log('table or row not defined'); return; }
    let f = new FormData();
    f.append('to_do', 'get_row');
    f.append('index', t);
    f.append('row', ri);
    ajaxPost(labSwapUrl, f, function (r) {
        if (r == 'ko-not_logged') {
            window.location.href = ''
        } else if (r.substr(0, 2) == 'ok') {
            let row = document.getElementById('row_' + ri);
            if (row == null) { console.log( 'row not found'); return;}
            row.outerHTML = r.substr(3);
            row = document.getElementById('row_' + ri);
            setEditor(row);
        } else {
            throwMessage(r.substr(3));
        }
    })
}

let sortingBasis = [];
let sortingContainer = [];
let sorting = false;
function sort(what, how = false) {
    if (sortingBasis.length == 0) {
        // check if "in table" (=> one more div)
        let containers = editor.querySelector('contentcontainer.table') != null ? editor.querySelectorAll('contentcontainer > div') : editor.querySelectorAll('contentcontainer');
        if (containers.length > 0) {
            containers.forEach(sb => {
                sortingBasis.push(sb.querySelectorAll('row'));
                sortingContainer.push(sb);
            })
        }
    }
    if (what == 'clear') {
        for (let i = 0; i < sortingBasis.length; i++) {
            if (sortingBasis[i].length > 0 ) {
                sortingBasis[i].forEach(r => {
                    r.parentElement.appendChild(r)
                })
            }
        }
        sorting = false;
    } else if (what == 'reverse') {
        for (let i = 0; i < sorting.length; i++) {
            if (!how || how == 'desc') {
                for (let j = sorting[i].length - 1; j >= 0; j--) {
                   sortingContainer[i].appendChild(sortingBasis[i][sorting[i][j].pos]);
                }
            } else {
                for (let j = 0; j < sorting[i].length; j++) {
                   sortingContainer[i].appendChild(sortingBasis[i][sorting[i][j].pos]);
                }
            }
        }
    } else {
        sorting = [];
        for (let i = 0; i < sortingBasis.length; i++) {
            let pos = 0;
            if (sortingBasis[i].length > 0 ) {
                sorting.push([]);
                sortingBasis[i].forEach(row => {
                    // get cell and its content identified by column
                    let c = row.querySelector('[column="' + what + '"]');
                    // console.log(row, c, c.value);
                    if (c != null) {
                        let cContent = c.classList.contains('input_type_display') ? c.textContent : c.value;
                        sorting[i].push({'pos': pos, 'content' : cContent});
                    }
                    pos++;
                });
            }
        }
        let j = how == 'desc' ? -1 : 1;
        if (sorting.length == 0) { return; }
        for (let i = 0; i < sorting.length; i++) {
            if (sorting[i].length > 0 ) {
                sorting[i] = sorting[i].sort(function (a, b) {
                    if (isNaN(a.content) || isNaN(b.content)) {
                        if (a.content.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() < b.content.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()) {
                            return -j;
                        }
                        if (a.content.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() > b.content.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()) {
                            return j;
                        }
                    } else {
                        if (parseFloat(a.content) < parseFloat(b.content)) {
                            return -j;
                        } else if (parseFloat(a.content) > parseFloat(b.content)) {
                            return j;
                        }
                    }
                    return 0;
                })
            }
        }
        for (let i = 0; i < sorting.length ; i++) {
            for (let j = 0; j < sorting[i].length; j++) {
                sortingContainer[i].appendChild(sortingBasis[i][sorting[i][j].pos]);
            }
        }

    }
}

let filterRows = false; 
function filterRow (clear = false) {
    console.log('filter start', filterRows, filter);
    // filter is when setting editor ! (at this time : contain : input (filter.input) and select (filter.select))
    // 23.07.24 - adding checkboxes
    if (filter && typeof filter.input != 'undefined') {
        if (!filterRows) {
            console.log('setting filterRows')
            let basis = document.querySelectorAll('row');
            console.log('basis :', basis);
            if (basis.length > 1) {
                filterRows = [];
                basis.forEach((r) => {
                    // /!\ .textarea is to get all divs 'textarea editable'
                    // console.log('r object : ', r)
                    let content = r.querySelectorAll(':not([class*="ql"]) > input:not([type="radio"], [type="checkbox"]), textarea, .text_editor, select:not([class*="ql"]), .input_type_display, .input_type_display_on_bd, checkboxes > input[type="checkbox"]');
                    if (content.length > 0) {
                        filterRows[r.id] = [];
                        content.forEach((c) => {
                            filterRows[r.id][c.getAttribute('column')] = c;
                        })
                    }
                });
            } else {
                return;
            }
        };
        console.log('rows to filter', Object.keys(filterRows).length, filterRows);

        if (clear === true && Object.keys(filterRows).length > 1) {
            for (var key in filterRows) {
                let row = document.getElementById(key);
                if (row != null) { row.classList.toggle('hidden', false);
                }
            }
            return;
        }
        
        let boxFilter = false;
        let oneCheckbox = false;
        if (Object.keys(filterRows).length > 1) {
            // start filtering
            let value = filter.input.value;
            if (filter.input.classList.contains('hidden')) {
                // filter input has been hidden to show an other type of selector (checkboxes i.e.)
                let newFilter = filter.input.nextElementSibling;
                let ai; // all inputs (in checkboxes, radiobox)
                console.log('newFilter => ', newFilter);
                if (newFilter == null) { return }

                // get values of new filter
                switch (newFilter.tagName) {
                    case 'FILTERCHECKBOXES':
                        boxFilter = new Array;
                        ai = newFilter.querySelectorAll('input');
                        console.log('checkboxes found: ', ai);
                        if (ai.length > 0) {
                            ai.forEach(i => {
                                if (i.checked) {
                                    boxFilter.push(i.value);
                                }
                            })
                        }
                        break;
                    case 'RADIOBOX':
                        boxFilter = new Array;
                        ai = newFilter.querySelectorAll('input');
                        if (ai.length > 0) {
                            ai.forEach(i => {
                                if (i.checked) {
                                    boxFilter.push(i.value);
                                }
                            })
                        }
                        break;
                    case 'ONE_CHECKBOX':
                        boxFilter = new Array;
                        boxFilter.push(filter.select.value);
                        oneCheckbox = newFilter.firstElementChild.checked;
                        
                        break; 
                }
            }

            let field = typeof(filter.select) != 'undefined' && filter.select.value != 'none' ? filter.select.value : false;
            console.log('ok let\'s start', filter, value, field, boxFilter);
           
            for (var key in filterRows) {
                toBeHidden = true;
                if (field) {
                    // console.log('filter check ->',filterRows[key][field], key, field, boxFilter);
                    if (boxFilter) {
                        // parse all values of boxfilter and search for checked cb
                        if (boxFilter.length > 0) {
                            boxFilter.forEach(cb => {
                                // console.log("cb checking: ", key, cb);

                                // get element with field name
                                let fieldInRow = editor.querySelector('#' + key + ' [name="' + field + '"]');
                                if (fieldInRow == null) { return; }
                                // switch on fieldType...
                                switch (fieldInRow.tagName) {
                                    case 'RADIOBOX' :
                                    case 'CHECKBOXES' :
                                        if (fieldInRow.querySelector('input[value="' + cb + '"]:checked')) {
                                            toBeHidden = false;
                                        }
                                        break;
                                    case 'SELECT' :
                                         if (fieldInRow.querySelector('option[value="' + cb + '"]:checked')) {
                                            toBeHidden = false;
                                        }
                                        break;
                                    case 'CHECKBOXES_IN_TABLE' :
                                        if (editor.querySelector('#' + key + ' input[value="' + cb + '"]:checked')) {
                                            toBeHidden = false;
                                        }
                                        break;
                                    case 'INPUT' :
                                        let input = editor.querySelector('#' + key + ' input[name="' + cb + '"]');
                                        console.log('one_checkbox => ', cb, key, input, oneCheckbox);
                                        if (input != null) {
                                            if ((!input.checked && !oneCheckbox) || (input.checked && oneCheckbox)) {
                                                toBeHidden = false;
                                            }
                                        }
                                        break;
                                }
                            })
                        }
                    } else if (filterRows[key][field].tagName == 'DIV') { if (filterRows[key][field].textContent.toLowerCase().indexOf(value.toLowerCase()) != -1) {
                            toBeHidden = false;
                        }
                    } else if (filterRows[key][field].value.toLowerCase().indexOf(value.toLowerCase()) != -1) {
                        toBeHidden = false;
                    }
                } else {
                    // console.log("check all inputs", filterRows[key]);
                    for (var fieldKey in filterRows[key]) {
                        if (filterRows[key][fieldKey].classList.contains('text_editor')) {
                            if (filterRows[key][fieldKey].textContent.toLowerCase().indexOf(value.toLowerCase()) != -1) {
                                toBeHidden = false;
                            }
                        } else if (typeof(filterRows[key][fieldKey].value) != 'undefined' && filterRows[key][fieldKey].value.toLowerCase().indexOf(value.toLowerCase()) != -1) {
                            toBeHidden = false;
                        } else if ((filterRows[key][fieldKey].classList == 'input_type_display' || filterRows[key][fieldKey].classList == 'input_type_display on_db') && filterRows[key][fieldKey].textContent.toLowerCase().indexOf(value.toLowerCase()) != -1) {
                            toBeHidden = false;
                        }
                    }
                }
                let row = document.getElementById(key);
                if (row != null) { row.classList.toggle('hidden', toBeHidden)};
            }
        }
    }
    // change totalisers values
    setTotalisers();
}   

function filterAdapt () {
    console.log('filter adapt', filter.select.value, filter.input);
    if (filter == null || filter.select == null || filter.input == null) { return }
    if (filter.select.value == 'none') {
        // 2024.21.06 : check last element in filter row // no more available... last child is now sorter !!!
        // let le = filter.input.parentElement.lastElementChild;
        // check next's filter.input.sibling
        let le = filter.input.nextElementSibling;
        if (le != null && le.id != 'filter_value'  && le.id != 'sorter_select') {
            // remove elt
            le.parentElement.removeChild(le);
        }
        // be sure input is displayed
        filter.input.classList.toggle('hidden', false);

        // filter row and return...
        filterRow();
        return;
    }

    let filterAfterChange = true;
    let rowInput = editor.querySelector('row [name="' + filter.select.value + '"]');
    let newFilter, content;
    if (rowInput == null) { console.log('no input found'); return }
    console.log('input type:', rowInput.tagName);
    let switcher = rowInput.tagName;
    if (rowInput.type == 'checkbox') { switcher = 'CHECKBOX'; }

    switch (switcher) {
        case 'CHECKBOX':
            // I think this case is only when one checkbox is setted for one column... => trigger (should be named trigger ;)
            // hide filter input text
            filter.input.classList.toggle('hidden', true);
            // actually only case detected is in a table...
            // create a checkbox
            let cbCont = document.createElement('one_checkbox');
            cbCont.classList.add('oneCheckbox');
            let cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.classList.add('alone');
            cb.addEventListener('change', filterRow);
            cbCont.appendChild(cb);
            filter.input.after(cbCont);
            break;
        case 'CHECKBOXES' :
        case 'CHECKBOXES_IN_TABLE':
            // hide filter input text
            filter.input.classList.toggle('hidden', true);

            // get all checkboxes and labels
            let allI, allL;
            if (rowInput.tagName == 'CHECKBOXES') {
                allI = rowInput.querySelectorAll('input');
                allL = rowInput.querySelectorAll('label');
            } else {
                allI = editor.querySelectorAll('#row_' + rowInput.getAttribute('row') + ' [checkbox_in_table="' + rowInput.id + '"]');
                allL = editor.querySelectorAll('headerrow [name="' + rowInput.getAttribute('name') + '"]');
            }

            // create a checkboxes filter container ;)
            let cbfc = document.createElement('filterCheckBoxes');
            
            // both allI and allL must have the same count of entries !
            console.log('checkboxes:', allI, 'labels:', allL);
            // create the filter
            if (allI.length == 0 || allL.length != allI.length) {return; }
            for (let i = 0; i < allI.length; i++) {
                // create entity containert
                let item = document.createElement('div');
                let newCB = allI[i].cloneNode(true);
                newCB.checked = false;
                newCB.id = 'IN_' + i;
                item.appendChild(newCB);
                newCB.addEventListener('change', filterRow);
                let newLabel
                if (allL[i].tagName == 'LABEL') {
                    newLabel = allL[i].cloneNode(true);
                } else {
                    newLabel = document.createElement('label');
                    newLabel.innerHTML = allL[i].innerHTML;
                }
                newLabel.htmlFor = 'IN_' + i;
                item.appendChild(newLabel);
                cbfc.appendChild(item);
            }
            filter.input.after(cbfc);

            /*
            content = newFilter.querySelectorAll('input, label');
            if (content.length > 0) {
                let counter = 0;
                content.forEach(c => {
                    if (c.tagName == 'INPUT') {
                        c.id = 'IN_' + counter;
                        c.addEventListener('change', filterRow);
                    } else if (c.tagName == 'LABEL') {
                        c.htmlFor = 'IN_' + counter;
                        counter++;
                    }

                })                    
            }
            */
            break;
        case 'RADIOBOX' :
             // hide filter input text
            filter.input.classList.toggle('hidden', true);

            // get all radiobox content and copy them after select
            newFilter = rowInput.cloneNode(true);
            filter.input.parentElement.appendChild(newFilter);

            // remove all ids and get change event !
            newFilter.id = "filterRadiobox";
            content = newFilter.querySelectorAll('input, label');
            if (content.length > 0) {
                let counter = 0;
                content.forEach(c => {
                    if (c.tagName == 'INPUT') {
                        c.checked = false;
                        c.id = 'IN_' + counter;
                        c.type = 'checkbox';
                        c.addEventListener('change', filterRow);
                    } else if (c.tagName == 'LABEL') {
                        c.htmlFor = 'IN_' + counter;
                        counter++;
                    }

                })                    
            }
            break;
        case 'SELECT':
            // here we have to get all options of select and transform them into checkboxes !
            console.log('Select choosen => creating checkboxes');
            // get all options content and copy them after select
            selectOptions = rowInput.querySelectorAll('option');
            if (selectOptions.length == 0) {
                console.log('Select to filter: no options found');
                return;
            }

            newFilter = document.createElement('checkboxes');
            newFilter.id = 'filterSelect';
            selectOptions.forEach(o => {
                if (!o.disabled) {
                    let nod = document.createElement('div');
                    nod.classList = 'horizontal';
                    let nodI = document.createElement('input');
                    nodI.type = 'checkbox';
                    nodI.value = o.value;
                    nodI.id = o.id;
                    nodI.addEventListener('change', filterRow);
                    let nodL = document.createElement('label');
                    nodL.htmlFor = o.id;
                    nodL.textContent = o.textContent;
                    nod.appendChild(nodI);
                    nod.appendChild(nodL);
                    newFilter.appendChild(nod);
                }
            })

            // hide filter input text
            filter.input.classList.toggle('hidden', true);
            
            // add new filter
            filter.input.parentElement.appendChild(newFilter);
            break;
        default :
            // show filter input text
            if (filter.input.classList.contains('hidden')) {
                filter.input.classList.toggle('hidden', false);
                // get last filter element
                if (filter.input.nextElementSibling != null) {
                    filter.input.parentElement.removeChild(filter.input.nextElementSibling);
                }
                filterAfterChange = false;
            }

            break;
    }

    // filter row
    if (filterAfterChange) {
        filterRow(true);
    } else {
        filterRow();
    }

}


function restoreRow (toRestoreButton) {
    let ta = toRestoreButton.getAttribute('index');
    let ro = toRestoreButton.getAttribute('row');
    console.log('start restroe row', ta, ro, toRestoreButton);
    if (ta == null) { console.log('index not defined'); return; }
    let f = new FormData();
    f.append('to_do', 'restore_row');
    f.append('index', ta);
    f.append('row', ro);
    ajaxPost(labSwapUrl, f, function (r) {
        if (r == 'ko-not_logged') {
            window.location.href = ''
        } else if (r.substr(0, 2) == 'ok') {
            // restore row
            let toRest = document.getElementById('row_' + ro);
            if (toRest != null) {
                toRest.removeAttribute('logical_deleted');
                // remove restore button
                let restBut = toRest.querySelector('restore');
                if (restBut != null) {
                    restBut.parentElement.removeChild(restBut);
                }
            } else {
                console.log('Element not found : row_' + ro)
            }

            return;
        } else {
            throwMessage(r.substr(3));
            return;
        }
    })
}

function deleteRow (toDeleteButton) {
    let ta = toDeleteButton.getAttribute('index');
    let ro = toDeleteButton.getAttribute('row');
    console.log('start delete row', ta, ro, toDeleteButton);
    if (ta == null) { console.log('index not defined'); return; }
    let f = new FormData();
    f.append('to_do', 'delete_row');
    f.append('index', ta);
    f.append('row', ro);
    ajaxPost(labSwapUrl, f, function (r) {
        if (r == 'ko-not_logged') {
            window.location.href = ''
        } else if (r.substr(0, 2) == 'ok') {
            if (r.substr(0,5) == 'ok-rs') {
                // throw function from string
                let f = r.substr(6);
                let v = false;
                if (f.indexOf('|') != -1) {
                    f = f.split('|');
                    v = f[1];
                    f = f[0];
                }
                console.log('throw function : ', f, v);
                if (typeof(window[f]) == 'function') {
                    if (v) {
                        window[f](v);
                    } else {
                        window[f]();
                    }
                }
            }
            let toDel = document.getElementById('row_' + ro);
            if (toDel != null) {
                toDel.parentElement.removeChild(toDel);
            } else {
                console.log('Element not found : row_' + ro)
            }
            return;
        } else {
            throwMessage(r.substr(3));
            return;
        }
    })
}

function checkGlobalSaving () {
    // search global saving button
    console.log('check global save button');
    let sa = document.getElementById('save_all');
    if (sa == null || sa.getAttribute('not') != null) { return; }
    let sb = document.querySelectorAll('save:not([id="save_all"]):not([not])');
    if (sb.length == 0) { sa.setAttribute('not', ''); }
}

function saveRow (toSaveButton) {
    return new Promise((resolve) => {
        if (toSaveButton.getAttribute('not') != null) { return;}
        // alert if click is on save_all button
        let row = toSaveButton.getAttribute('row');
        if (row == null) {console.log('row not defined'); return; }
        let index = toSaveButton.getAttribute('index')
        if (index == null) {console.log('index not defined'); return; }
        console.log('start save row', row);

        // check required
        let required = editor.querySelectorAll('#row_' + row + ' [required]');
        if (required.length > 0) {
            let ok = true;
            console.log('required: ', required);
            required.forEach((r) => { 
                if (!r.reportValidity()) { ok = false };
            });
            if (!ok) { console.log('empty required found => return'); return; } else { console.log('no empty required going forward');}

        }

        if (row.indexOf('new_row') != -1 && editor.querySelectorAll('.save_row.clickable:not(.not)').length > 1) {
            if (!confirm('Attention ! En validant une ligne nouvellement créée, vous perdrez les modifications faites sur les autres lignes ! Voulez-vous continuer ?')) {
                return;
            }
        }
        let s = getAllInputs(row);
        if (s) {
            let f = new FormData();
            f.append('to_do', 'save_row');
            f.append('row_id', row);
            f.append('index', index);
            s.forEach(i => {
                if (i.tagName == 'CHECKBOXES' || i.tagName == 'CHECKBOXES_IN_TABLE') {
                    if (i.getAttribute('save_method') !== null) {
                        f.append(i.getAttribute('name'), getStringFromCheckboxes(i))
                    } else {
                        t = getTabFormCheckboxes(i);
                        console.log('receveveid checkboxes tab: ', t, Object.keys(t), t.length);
                        if (Object.keys(t).length > 0){
                            Object.keys(t).forEach((k) => {
                                console.log(k, t[k]);
                                f.append(k, t[k]);
                            })
                        }
                    }
                } else if (i.tagName == 'SELECT' && i.getAttribute('multiple') !== null) {
                    let selectedOptions = i.querySelectorAll('option:checked');
                    if (selectedOptions.length == 0) {
                        f.append(i.name, '')
                    } else {
                        let values = '';
                        selectedOptions.forEach((o) => {
                            values += values.length > 0 ? ',' : '';
                            values += o.value;
                        })
                        f.append(i.name, values);
                    }
                } else if (i.classList.contains('text_editor')) {
                    // f.append(i.getAttribute('name'), window['quill_editor_' + i.id].getSemanticHTML());
                    f.append(i.getAttribute('name'), window['quill_editor_' + i.id].root.innerHTML);
                } else if (i.classList.contains('json_editor')) {
                    if (i.value != null && i.value != '') {
                        f.append(i.name, JSON.stringify(JSON.parse(i.value)));
                    } else {
                        f.append(i.name, null);   
                    }
                } else if (i.type == 'file') {
                    console.log('uploading files:', i.files);
                    for (let j = 0; j < i.files.length; j++) {
                        f.append(i.name + '[]', i.files[j]);
                    }
                } else if (i.type != 'radio') {
                    f.append(i.name, i.value)
                } else if (i.checked) {
                    f.append(i.name.substr(0, i.name.lastIndexOf('_')), i.value);
                }
            })

            // add plugins action (saveRow)
            let formPlugin = [];
            if (Object.keys(plugins.saveRow).length > 0) {
                Object.values(plugins.saveRow).forEach(p => {
                    if (typeof(window[p]) == 'function') {
                        let toAppend = window[p](row);
                        console.log('received from plugin : ', toAppend)
                        if (toAppend) { formPlugin.push(toAppend); }
                    }
                })
            }
            if (formPlugin.length > 0) {
                formPlugin.forEach(fp => {
                    if (Array.isArray(fp)) {
                        Object.keys(fp).forEach(a => {
                            f.append(a, fp[a]);
                        })
                    }
                })
            }
            
            if (Array.from(f.keys()).length == 3) {
                // nothing to save
                console.log('nothing to save');
                toSaveButton.setAttribute('not', '');
                checkGlobalSaving();
                return;
            }

            // see_as management
            let seeAs = editor.querySelector('select[name="see_as"]');
            if (seeAs != null && seeAs.basis_user != seeAs.value) {
                f.append('see_as', seeAs.value);
            }

            ajaxPost(labSwapUrl, f, function (r) {
                console.log(r);
                if (r == 'ko-not_logged') {
                    window.location.href = ''
                } else if (r.indexOf('updatePage') != -1) {
                    updatePage();
                } else if (r.substr(0, 2) == 'ok') {
                    if (r.substr(0, 5) == 'ok-rv') {
                        let returnValues = JSON.parse(r.substr(6));
                        console.log('modifying elements', returnValues);
                        if (Object.keys(returnValues).length > 0) {
                            for (var i = 0; i < Object.keys(returnValues).length; i++) {
                                let elt = document.querySelector('row[id="row_' + row + '"] [column="' + Object.keys(returnValues)[i] +'"]');
                                console.log('looking for [column="' + Object.keys(returnValues)[i] +'"]', elt);
                                if (elt != null) {
                                    switch (elt.tagName) {
                                        case 'DIV' :
                                            elt.innerHTML = returnValues[Object.keys(returnValues)[i]];
                                            break;
                                        case 'INPUT' :
                                            elt.value = returnValues[Object.keys(returnValues)[i]];
                                            break;
                                        case 'IMG' :
                                            elt.src = returnValues[Object.keys(returnValues)[i]];
                                    }
                                }
                            }
                        }
                        toSaveButton.setAttribute('not', '');
                        checkGlobalSaving();
                    } else if (r.substr(0,5) == 'ok-rs') {
                        // throw function from string
                        toSaveButton.setAttribute('not', '');
                        checkGlobalSaving();
                        let f = r.substr(6);
                        let v = false;
                        if (f.indexOf('|') != -1) {
                            f = f.split('|');
                            v = f[1];
                            f = f[0];
                        }
                        console.log('throw function : ', f, v);
                        if (typeof(window[f]) == 'function') {
                            if (v) {
                                window[f](v);
                            } else {
                                window[f]();
                            }
                            return;
                        }
                    } else if (row.indexOf('new_row') != -1) {
                        // reload the open view: the module, or the whole page in page_with_modules
                        setTable(generator);
                     } else {
                        toSaveButton.setAttribute('not', '');
                        checkGlobalSaving();
                    }
                    return;
                } else {
                    throwMessage(r.substr(3));
                    return;
                }
            })
        }
    })
}

async function saveAll() {
    let allSaveButtons = document.querySelectorAll('save:not([not]):not(#save_all');
    if (allSaveButtons.length > 0) {
        for await (const b of allSaveButtons) {
            saveRow(b);
        }
    }
}

function defineToSave (r) {
    if (r == null) { return;}
    console.log('define to save function r -> ', r);
    let s;
    if (r.indexOf('new_row') == -1) {
        s = document.querySelector('save[id$="_' + r + '"]:not([id*="new_row_"]');
    } else {
        s = document.querySelector('save[id$="_' + r + '"]');
    }
    let change = true;
    if (s != null && s.getAttribute('not') !== null) {
        if (Object.keys(plugins.inputsChange).length > 0) {
            Object.keys(plugins.inputsChange).forEach(p => {
                if (typeof(window[plugins.inputsChange[p]]) == 'function') {
                    change = window[plugins.inputsChange[p]]()
                }
            })
        }
        if (change) {
            s.removeAttribute('not');
            let as = document.querySelector('save[all]');
            if (as != null && as.getAttribute('not') !== null){
                as.removeAttribute('not');
            }
        }
    }
    setTotalisers();
}

let filter = false;
function setEditor(row = false) {
    console.log('setting editor : row = ', row);
    filterRows = false;
    filter = false;
    sortingBasis = [];
    sortingContainer = [];
    sorting = false;
    let inputsToSet = false;
    let checkboxesToSet = false; 
    if (row) {
        inputsToSet = row.querySelectorAll('input, select, textarea, div.text_editor, listfilegalery');
        checkboxesToSet = row.querySelectorAll('checkboxes, checkboxes_in_table');
    } else {
        inputsToSet = editor.querySelectorAll('row input, row select, row textarea, row div.text_editor, row listfilegalery');
        checkboxesToSet = editor.querySelectorAll('row checkboxes, row checkboxes_in_table');
    }
    if (inputsToSet.length > 0) {
        inputsToSet.forEach(i => {
            if (i.id == 'filter_value' || i.id == 'filter_select') { return; }
            if (i.classList.contains('text_editor')) { 
                let t = false;
                if (i.getAttribute('content') != null) {
                    t = i.getAttribute('content');
                }
                ph = '';
                if (i.getAttribute('placeholder') != null) {
                    ph = i.getAttribute('placeholder');
                }
                let opt = {
                  // debug: 'info',
                  modules: {
                    toolbar: [
                      ['bold', 'italic', 'underline', 'strike'],        // toggled buttons
                      ['blockquote', 'code-block'],
                      ['link', 'image', 'video', 'formula'],

                      [{ 'header': 1 }, { 'header': 2 }],               // custom button values
                      [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'list': 'check' }],
                      [{ 'script': 'sub'}, { 'script': 'super' }],      // superscript/subscript
                      [{ 'indent': '-1'}, { 'indent': '+1' }],          // outdent/indent
                      [{ 'direction': 'rtl' }],                         // text direction

                      //[{ 'size': ['small', false, 'large', 'huge'] }],  // custom dropdown
                      [{ 'header': [1, 2, 3, 4, 5, 6, false] }],

                      [{ 'color': [] }, { 'background': [] }],          // dropdown with defaults from theme
                      [{ 'align': [] }],

                      ['clean']                                         // remove formatting button
                    ],
                    //imageDrop: true,
                    resize: { locale : {}}
                    },
                  placeholder: ph,
                  theme: 'snow'
                };
               
                // get element
                let elt = document.getElementById('te_' + i.id);
                window['quill_editor_' + i.id] = new Quill(elt, opt);
                if (t) {
                    window['quill_editor_' + i.id].container.firstElementChild.innerHTML = t;
                }
                window['quill_editor_' + i.id].on('text-change', (d,o) => {
                    console.log("quill event !", elt, d,o, o.ops[0].insert);
                    if ( o.ops[0].insert != "\n") {
                        defineToSave(elt.parentElement.getAttribute('row'));
                    }
                });
            }

            if (i.classList.contains('json_editor') && i.value.length > 0) { 
                i.value = JSON.stringify(JSON.parse(i.value), undefined, 4);
            }
            i.addEventListener('input', () => {
                defineToSave(i.getAttribute('row'));
            });
            i.addEventListener('keyup', () => {
                defineToSave(i.getAttribute('row'));
            })
            i.addEventListener('change', () => {
                if (i.type == 'number') {
                    if (i.getAttribute('max') != undefined && i.value > i.getAttribute('max')) {
                        i. value = i.getAttribute('max')
                    }
                    if (i.getAttribute('min') != undefined && i.value < i.getAttribute('min')) {
                        i. value = i.getAttribute('min');
                    }
                    if (i.getAttribute('step') != undefined && i.value / i.getAttribute('step') != parseInt(i.value / i.getAttribute('step'))) {
                        i. value = parseInt(i.value / i.getAttribute('step')) * i.getAttribute('step');
                    } 
                } 
                defineToSave(i.getAttribute('row'));
            })
        })
    }
    // checkboxes
    if (checkboxesToSet && checkboxesToSet.length > 0) {
        checkboxesToSet.forEach((cbxs) => {
            let cbxsRow = cbxs.getAttribute('row');
            let allCBs;
            if (cbxs.tagName == 'CHECKBOXES') {
                allCBs = cbxs.querySelectorAll('input[type="checkbox"]');
            } else {
                allCBs = editor.querySelectorAll('#row_' + cbxs.getAttribute('row') + ' [checkbox_in_table="' + cbxs.id + '"]');
            }

            if (allCBs.length > 0) {
                allCBs.forEach((cb) => {
                    cb.addEventListener('change', () => {
                        defineToSave(cbxsRow);
                    })
                })
            }

        })
    }
    let clickables = false;
    if (row) {
        clickables = row.querySelectorAll('add, save, edit, editrow, refresh, remove, restore, splashcloser, exit, home, locked, unlocked, sorterindicator, .other_buttons');
    } else {
        clickables = editor.querySelectorAll('add, save, edit, editrow, refresh, remove, restore, splashcloser, exit, home, locked, unlocked, sorterindicator, .other_buttons');
    }



    console.log('clickables => ', clickables.length);
    if (clickables.length > 0) {
        clickables.forEach(c => {
            // console.log('add click to', c.id);
            c.addEventListener('click', (e) => {
                if (c.parentElement.tagName != 'A') {
                    e.stopPropagation();
                    e.preventDefault();
                }

                if (c.getAttribute('not')) {
                    return;
                } else {
                    let f = c.getAttribute('function');
                    console.log('click on', c, '=>', f);
                    if (typeof(window[f]) == 'function') {
                        window[f](c);
                    } else {
                        console.log('function ' + f + ' not defined');
                    }
                }
            })
        })
    }

    let expandables = false;
    if (row) {
        expandables = row.querySelectorAll('.expandable');
    } else {
        expandables = document.querySelectorAll('.expandable');
    }

    if (expandables.length > 0) {
        console.log('adding explandables')
        expandables.forEach(e => {
            e.addEventListener('click', () => {
                e.classList.toggle('reduced');
            })
        })
    }

    if (!row) {
        filterElements = editor.querySelectorAll('#filter_select, filter input');
        if (filterElements.length > 0) {
            filter = [];
            filterElements.forEach((f) => {
                if (f.tagName == 'SELECT') {
                    filter.select = f;
                    filter.select.addEventListener('change', filterAdapt);
                } else {
                    filter.input = f;
                    f.addEventListener('keyup', filterRow)
                }
            })
        }

        // add sorting
        sortingSelect = editor.querySelector('#sorter_select');
        sortingIndicator = editor.querySelector('sorterindicator');
        if (sortingSelect != null && sortingIndicator != null) {
            sortingSelect.addEventListener('change', () => {
                console.log('requesting sort :', sortingSelect.value)
                if (sortingSelect.value == 'none') {
                    // hidde indicator
                    sortingIndicator.classList.toggle('hidden', true);
                    sortingIndicator.classList.toggle('desc', false);
                    sort('clear');
                } else {
                    sortingIndicator.classList.toggle('hidden', false);
                    sortingIndicator.classList.toggle('desc', false);
                    sort(sortingSelect.value, 'asc');
                }
            });
            sortingIndicator.addEventListener('click', () => {
                if (sortingIndicator.classList.contains('desc')) {
                    sortingIndicator.classList.toggle('desc', false);
                    sort('reverse', 'asc');
                } else {
                    sortingIndicator.classList.toggle('desc', true);
                    sort('reverse', 'desc');
                }
            })
        }
    }

    if (Object.keys(plugins.setEditor).length > 0) {
        Object.values(plugins.setEditor).forEach(p => {
            if (typeof(window[p]) == 'function') {
                window[p](row);
            }
        })
    }

    // add header input (complete_all input)
    let completeAll = editor.querySelectorAll('*[id*="_complete_all_"]');
    if (completeAll.length > 0) {
        completeAll.forEach(ca => {
            // column name
            let n = ca.getAttribute('column');
            // type of change (filtred / unfiltred)
            let t = ca.id.substr(ca.id.lastIndexOf('_') + 1);
            // search
            let s; 
            if (t == 'filtred') { s = ':not(.hidden)' ; } else if (t == 'unfiltred') { s = ''} else { return; } 
            
            
            switch (ca.type) {
                case 'checkbox' :
                     ca.addEventListener('change', () => {
                        let txt = ca.getAttribute('confirm_name') != null ? ' ' + ca.getAttribute('confirm_name') : '';
                        if (confirm('En modifiant cette valeur, les colonnes' + txt + ' de toutes les lignes affichées vont être mise à la même valeur. Sans sauvegarder, un rafraîchissement de la page (F5) remettra la page dans son état initial. Voulez-vous poursuivre ?')) {
                            let toComplete = editor.querySelectorAll('row' + s + ' [name="' + n + '"]');
                            console.log('input to complete', toComplete, s, n)
                            if (toComplete.length = 0) { return; }
                            // value to insert
                            let v = ca.checked;
                            toComplete.forEach(tc => {
                                tc.checked = v;
                                tc.dispatchEvent(new Event('change'));
                            })
                        }
                        ca.checked = false;
                    });
                    break;
                default :
                    ca.addEventListener('keyup', e => {
                        if (e.key == 'Enter') {
                            let txt = ca.getAttribute('confirm_name') != null ? ' ' + ca.getAttribute('confirm_name') : '';
                            if (confirm('En modifiant cette valeur, les colonnes' + txt + ' de toutes les lignes affichées vont être mise à la même valeur. Sans sauvegarder, un rafraîchissement de la page (F5) remettra la page dans son état initial. Voulez-vous poursuivre ?')) {
                                let toComplete = editor.querySelectorAll('row' + s + ' [name="' + n + '"]');
                                console.log('input to complete', toComplete, s, n)
                                if (toComplete.length = 0) { return; }
                                // value to insert
                                let v = ca.value;
                                toComplete.forEach(tc => {
                                    tc.value = v;
                                    tc.dispatchEvent(new Event('change'));
                                })
                            }
                        }
                    })
                    ca.value = null;
                    break;
            }
        })
    }

    // set see_as
    let seeAs = editor.querySelector('select[name="see_as"]');
    if (seeAs != null) {
        seeAs.addEventListener('change', () => {
            let w = editor.querySelector('wrapper');
            if (w == null) { console.log('no wrapper'); return; }
            let f = new FormData();
            f.append('to_do', 'get_content');
            f.append('index', w.getAttribute('content'));
            f.append('see_as', seeAs.value);
            ajaxPost(labSwapUrl, f, function (r) {
                if (r == 'ko-not_logged') {
                    window.location.href = '';
                } else if (r.substr(0, 5) == 'ok-sp') {
                    // set splash 
                } else if (r.substr(0, 2) == 'ok') {
                    editor.innerHTML = r.substr(3);
                    setEditor();
                } else {
                    console.log(r);
                    throwMessage(r.substr(3));
                }
            })
        })
    }

    // set headertabs (tabs of the rows of a module, and tabs of the modules of a page)
    setModuleTabs();

    // add totalisers
    setTotalisers();
}

// headertabs of a module (form_complement headertabs): show the rows of the active tab
// scope: the editor (single_generator_per_page) or one <module> of a page (page_with_modules)
function setHeaderTabs (scope) {
    let headerTabs = scope.querySelectorAll('headertabs:not(.module_tabs) headertab');
    if (headerTabs.length > 0) {
        let titleHeight = 0;
        let headerTabHeight = headerTabs[0].parentElement.offsetHeight;
        console.log('setting headertabs', headerTabHeight);
        let tables = scope.querySelectorAll('contentcontainer');
        if (tables.length > 0) {
            // check if title is present and add height
            let title = scope.querySelector('#table_title_' + headerTabs[0].getAttribute('key'));
            if (title != null) {
                titleHeight = title.offsetHeight;
            }
            tables[0].style.top = (tables[0].offsetTop + headerTabHeight + titleHeight) + 'px';
            let maxHeight = window.getComputedStyle(tables[0]).getPropertyValue('max-height');
            tables[0].style.maxHeight = 'calc(' + maxHeight + ' - ' + (headerTabHeight + titleHeight) + 'px)';
        }
        headerTabs.forEach(h => {
            h.addEventListener('click', () => {
                if (h.getAttribute('active') != null) { return; }
                headerTabs.forEach(h2 => { h2.removeAttribute('active'); })
                h.setAttribute('active' , '');
                if (tables.length > 0) {
                    tables.forEach(t => {
                        if (t.getAttribute('key') == h.getAttribute('key')) {
                            t.classList.toggle('hidden', false);
                            title = scope.querySelector('#table_title_' + t.getAttribute('key'));
                            let titleHeight = 0;   
                            if (title != null) {
                                title.classList.toggle('hidden', false);
                                titleHeight = title.offsetHeight
                            }
                            t.style.top = t.offsetTop + headerTabHeight + titleHeight + 'px';
                            let maxHeight = window.getComputedStyle(t).getPropertyValue('max-height');
                            t.style.maxHeight = 'calc(' + maxHeight + ' - ' + (headerTabHeight + titleHeight) + 'px)';
                        } else {
                            t.style.top = '12vh';
                            t.style.maxHeight = '85vh';
                            t.classList.toggle('hidden', true);
                            console.log('key', t.getAttribute('key'));
                            if (scope.querySelector('#table_title_' + t.getAttribute('key')) != null) {
                                console.log('title founded...');
                                scope.querySelector('#table_title_' + t.getAttribute('key')).classList.toggle('hidden', true);
                            }
                        }
                    })
                }
            })
        })
    }
}

// page_with_modules: one headertab per module of the page (modules/inc/get_content.php)
// the active module tab is remembered for the page, so it stays open after a reload (save of a new row)
function setModuleTabs () {
    let modules = [...editor.querySelectorAll('module')];
    if (modules.length == 0) {
        // single_generator_per_page: unchanged
        setHeaderTabs(editor);
        return;
    }
    let bar = editor.querySelector('headertabs.module_tabs');
    if (bar == null) {
        // page with one module: no module tab
        modules.forEach(m => {
            if (!m.hasAttribute('tabs_set')) {
                m.setAttribute('tabs_set', '');
                setHeaderTabs(m);
            }
        });
        return;
    }
    // setEditor() is called again after some actions: tabs set only once per page load
    if (bar.hasAttribute('tabs_set')) { return; }
    bar.setAttribute('tabs_set', '');
    let tabs = [...bar.querySelectorAll('headertab')];

    let showModule = function (key) {
        tabs.forEach(t => {
            if (t.getAttribute('key') == key) { t.setAttribute('active', ''); } else { t.removeAttribute('active'); }
        });
        modules.forEach(m => { m.classList.toggle('hidden', m.getAttribute('key') != key); });
        let m = modules.find(m => m.getAttribute('key') == key);
        // first display of a module: room for the module tabs above its fixed content, then its own headertabs
        if (m != null && !m.hasAttribute('tabs_set')) {
            m.setAttribute('tabs_set', '');
            m.querySelectorAll('contentcontainer').forEach(c => {
                c.style.top = (c.offsetTop + bar.offsetHeight) + 'px';
                c.style.maxHeight = 'calc(' + window.getComputedStyle(c).getPropertyValue('max-height') + ' - ' + bar.offsetHeight + 'px)';
            });
            setHeaderTabs(m);
        }
        try { sessionStorage.setItem('module_tab_' + generator, key); } catch (e) {}
    };

    tabs.forEach(t => {
        t.addEventListener('click', () => {
            if (t.getAttribute('active') == null) { showModule(t.getAttribute('key')); }
        });
    });

    let remembered = null;
    try { remembered = sessionStorage.getItem('module_tab_' + generator); } catch (e) {}
    showModule(tabs.some(t => t.getAttribute('key') == remembered) ? remembered : tabs[0].getAttribute('key'));
}

function setTotalisers () {
    let totalisers = editor.querySelectorAll('totaliser[totalfromcolumn]');
    if (totalisers.length > 0) {
        totalisers.forEach(t => {
            let c = t.getAttribute('column');
            if (c != null) {
                let w = t.getAttribute('where');
                let values = editor.querySelectorAll('row:not(.hidden) [name="' + c + '"]' );
                let total = 0;
                if (w != null && w != '') { w = w.split('|') };
                if (values.length > 0) {
                    values.forEach(v => {
                        if (w == null || w == '') {
                            total += v.value != '' && v.value != null ? parseInt(v.value) : 0;
                        } else {
                            // check where value
                            let cond = editor.querySelector('#row_' + v.getAttribute('row') + ' [name="' + w[0] + '"]');
                            if (cond != null) {
                                switch (w[1]) {
                                    case 'checked' :
                                        if (cond.checked) {
                                            let val = v.value != '' && v.value != null ? v.value: 0;
                                            total += parseInt(parseFloat(val) * 10) / 10;
                                        }
                                        break;
                                }

                            }

                        }
                    })
                }
                t.textContent = total;
            }
        })
    }
    let counters = editor.querySelectorAll('totaliser[totalrows]');
    if (counters.length > 0) {
        counters.forEach(c => {
            c.textContent = editor.querySelectorAll('row:not(.hidden)').length;
        })
    }
}

function setTable(b) {
    console.log('get_table', b)
    let f = new FormData();
    f.append('to_do', 'get_content');
    f.append('index', b);
    ajaxPost(labSwapUrl, f, function (r) {
        if (r == 'ko-not_logged') {
            window.location.href = '';
        } else if (r.substr(0, 5) == 'ok-sp') {
            // set splash 
        } else if (r.substr(0, 2) == 'ok') {
            generator = b;
            editor.innerHTML = r.substr(3);
            wrapper = editor.querySelector('wrapper');
            setEditor();
        } else {
            console.log(r);
            throwMessage(r.substr(3));
        }
    })
}

function setSplash(splashContent) {
    splash.classList.toggle('hidden', false);
    splashContainer.innerHTML = splashContent;
}

function activeBloc(bloc) {
    for (let b of blocList) {
        b.removeAttribute("activeTable")
        if (b.getAttribute("index") === bloc) { b.setAttribute("activeTable", ""); }
        sessionStorage.setItem('bloc', bloc);
    }
}

function activeRole(r, click = true) {
    for (let t of tabList) {
        t.removeAttribute("activeRole")
        if (t.getAttribute("role") === r) {t.setAttribute("activeRole", "")}
    }
    for (let s of sectionList) {
        s.removeAttribute("activeRole")
        if (s.getAttribute("role") === r) {s.setAttribute("activeRole", "");
        }
    }
    sessionStorage.setItem('role', r);
    
    // get first element of tabList and select it
    let bloc;
    if (click || sessionStorage.getItem('bloc') == null) {
        bloc = document.querySelector('section[activerole] bloc');
    } else {
        bloc = document.querySelector('section[activerole] [index="' + sessionStorage.getItem('bloc') + '"]')
    }
    
    console.log('bloc: ', bloc);
    if (bloc != null) {
        activeBloc(bloc.getAttribute('index'));
        setTable(bloc.getAttribute('index'));
    } else {
        setTable('homepage_' + r);
    }
}

// FILE GALERY ////////////////////////////////////////////////
function setFileContent (i) {
    // get all selected files in splash and insert them in hidden input (i) of file list element
    console.log ('setting file content', i);
    let fileList = i.parentElement.querySelector('listfilegalery');
    let sel = splashContainer.querySelectorAll('fileelement[selected]');
    // clean fileList
    if (fileList != null) {
        fileList.innerHTML = '';
        // throw change event
        fileList.dispatchEvent(new Event('change'));
    }
    if (sel.length > 0) {
        let ids = [];
        sel.forEach((s) => {
            ids.push(s.dataset.id);
            if (fileList != null) { fileList.appendChild(s.cloneNode(true)); }
        });

        i.value = ids.join(',');
        // 
    } else {
        i.value = '';
    }

}

function editFileList (b) {
    console.log('start editing file list', b);
    // get actual content of file list
    if (b.dataset.file_list_id == null) { return; }
    let fl = editor.querySelector('#' + b.dataset.file_list_id);
    if (fl == null) { return; }

    // prepare splash
    let f = new FormData();
    f.append('index', 'file_galery');
    f.append('splash', true);
    f.append('to_do', 'get_file_galery_splash');
    f.append('with', fl.value);

    // throw plugins functions to addContent to formData
    let formPlugin = [];
    if (Object.keys(plugins.editFileList).length > 0) {
        let row = b.getAttribute('row');
        Object.values(plugins.editFileList).forEach(p => {
            if (typeof(window[p]) == 'function') {
                let toAppend = window[p](row)
                if (toAppend) { formPlugin.push(toAppend); }
            }
        })
    }
    console.log('adds from plugins: ', formPlugin);
    if (formPlugin.length > 0) {
        formPlugin.forEach(fp => {
            console.log(fp);
            if (typeof(fp) != '') {
                Object.keys(fp).forEach(a => {
                    console.log(a, fp[a])
                    f.append(a, fp[a]);
                })
            }
        })
    }

    // call splash
    ajaxPost(labSwapUrl, f, function (r) {
        if (r == 'ko-not_logged') {
            window.location.href = '';
        } else if (r.substr(0, 5) == 'ok-sp') {
            setSplash(r.substr(6));
            // get all file elements
            setFileContent(fl);
            let fe = splashContainer.querySelectorAll('fileelement');
            console.log('setting file elements', fe)
            if (fe.length > 0) {
                fe.forEach((i) => {
                    i.addEventListener('click', () => {
                        if (i.getAttribute('selected') == null) {
                            i.setAttribute('selected', '');
                        } else {
                            i.removeAttribute('selected');

                        }
                        setFileContent(fl);
                    })
                })
            }
        }
    })
}

// END OF FILE GALERY /////////////////////////////////////////

setup();