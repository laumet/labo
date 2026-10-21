const generatorDisplayInputTypes = [
    'text', 'tel', 'number', 'text_empty', 'select', 'checkbox', 'radio',
    'textarea', 'text_editor', 'db_selector', 'db_checkboxes', 'json',
    'display', 'mailto', 'display_on_db', 'hidden', 'file', 'thumb', 'file_list'
];

function setupGeneratorDisplayEditors(container) {
    if (!container) { return; }

    container.querySelectorAll('textarea.json_editor[table="generator"][column="display"]').forEach(textarea => {
        if (textarea.dataset.generatorDisplayInitialized === 'true') { return; }
        textarea.dataset.generatorDisplayInitialized = 'true';

        const editorRoot = document.createElement('div');
        editorRoot.className = 'generator-display-editor';
        editorRoot.dataset.invalid = 'false';

        const toolbar = document.createElement('div');
        toolbar.className = 'generator-display-toolbar';

        const title = document.createElement('strong');
        title.textContent = 'Champs affichés';

        const rawToggle = document.createElement('button');
        rawToggle.type = 'button';
        rawToggle.textContent = 'Modifier le JSON brut';
        rawToggle.setAttribute('aria-expanded', 'false');

        toolbar.append(title, rawToggle);

        const status = document.createElement('p');
        status.className = 'generator-display-status';
        status.setAttribute('role', 'alert');
        status.setAttribute('aria-live', 'polite');

        const help = document.createElement('p');
        help.className = 'generator-display-help';
        help.textContent = 'Configurez les colonnes, libellés et types de champs. Les paramètres avancés restent disponibles dans le JSON brut.';

        const fields = document.createElement('div');
        fields.className = 'generator-display-fields';

        const addFieldButton = document.createElement('button');
        addFieldButton.type = 'button';
        addFieldButton.textContent = 'Ajouter un champ';

        editorRoot.append(toolbar, status, help, fields, addFieldButton);
        textarea.parentElement.insertBefore(editorRoot, textarea);

        const originalDisplay = textarea.style.display;
        textarea.classList.add('generator-display-raw');
        textarea.style.display = 'none';

        let entries = [];

        function decodeDisplay(value) {
            const parsed = JSON.parse(value);
            if (parsed === null || typeof parsed !== 'object') {
                throw new Error('La configuration doit être un objet ou une liste JSON.');
            }

            const values = Array.isArray(parsed) ? parsed : Object.values(parsed);
            if (!values.every(entry => entry && typeof entry === 'object' && !Array.isArray(entry))) {
                throw new Error('Chaque élément de la configuration doit être un objet JSON.');
            }
            values.forEach((entry, index) => {
                if (Object.prototype.hasOwnProperty.call(entry, 'column_name') && !String(entry.column_name || '').trim()) {
                    throw new Error(`Élément ${index + 1} : indiquez une colonne de la base de données.`);
                }
                if (Object.prototype.hasOwnProperty.call(entry, 'column_name') &&
                    (!entry.input || typeof entry.input !== 'object' || typeof entry.input.type !== 'string' || !entry.input.type)) {
                    throw new Error(`Élément ${index + 1} : sélectionnez un type de champ.`);
                }
            });
            return values;
        }

        function setStatus(message, invalid = false) {
            status.textContent = message;
            status.dataset.invalid = invalid ? 'true' : 'false';
            editorRoot.dataset.invalid = invalid ? 'true' : 'false';
        }

        function syncTextarea() {
            const display = {};
            entries.forEach((entry, index) => {
                display[String(index + 1)] = entry;
            });
            textarea.value = JSON.stringify(display);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            try {
                decodeDisplay(textarea.value);
                setStatus('Configuration valide.', false);
            } catch (error) {
                setStatus(error.message, true);
            }
        }

        function makeButton(label, action, disabled = false) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', action);
            return button;
        }

        function makeLabeledControl(labelText, control) {
            const label = document.createElement('label');
            const text = document.createElement('span');
            text.textContent = labelText;
            label.append(text, control);
            return label;
        }

        function makeTextInput(value, onChange) {
            const input = document.createElement('input');
            input.type = 'text';
            input.value = value == null ? '' : String(value);
            input.addEventListener('input', () => onChange(input.value));
            return input;
        }

        function renderOptions(entry, type, target) {
            const inputConfig = entry.input;
            const isSelect = type === 'select';
            const collectionName = isSelect ? 'options' : 'content';
            const source = inputConfig[collectionName];
            const options = source && typeof source === 'object' ? source : {};
            const optionEntries = Array.isArray(options)
                ? options.map((option, index) => [String(index), option])
                : Object.entries(options);
            const optionRows = optionEntries.map(([, option]) => option);

            const heading = document.createElement('strong');
            heading.textContent = isSelect ? 'Choix proposés' : 'Valeurs et libellés';
            target.append(heading);

            const list = document.createElement('div');
            list.className = 'generator-display-options';

            function saveOptions() {
                const updated = {};
                list.querySelectorAll('.generator-display-option').forEach((row, index) => {
                    const keyControl = row.querySelector('[data-option-key]');
                    const valueControl = row.querySelector('[data-option-value]');
                    const previous = row.optionData || {};
                    if (isSelect) {
                        updated[String(index)] = {
                            ...previous,
                            value: keyControl.value,
                            text: valueControl.value
                        };
                    } else {
                        updated[keyControl.value] = valueControl.value;
                    }
                });
                inputConfig[collectionName] = updated;
                syncTextarea();
            }

            function appendOption(optionKey = '', optionValue = '', prior = {}) {
                const row = document.createElement('div');
                row.className = 'generator-display-option';
                row.optionData = prior;

                const keyInput = makeTextInput(optionKey, saveOptions);
                keyInput.dataset.optionKey = '';
                keyInput.setAttribute('aria-label', isSelect ? 'Valeur enregistrée' : 'Clé enregistrée');
                const valueInput = makeTextInput(optionValue, saveOptions);
                valueInput.dataset.optionValue = '';
                valueInput.setAttribute('aria-label', 'Libellé affiché');

                row.append(keyInput, valueInput, makeButton('Supprimer', () => {
                    row.remove();
                    saveOptions();
                }));
                list.append(row);
            }

            optionEntries.forEach(([key, option]) => {
                if (isSelect) {
                    appendOption(option.value ?? '', option.text ?? '', option);
                } else {
                    appendOption(key, option);
                }
            });

            target.append(list, makeButton('Ajouter un choix', () => {
                appendOption();
                saveOptions();
            }));
        }

        function renderAdvancedEntry(entry, index, card) {
            const details = document.createElement('details');
            details.className = 'generator-display-advanced';
            const summary = document.createElement('summary');
            summary.textContent = 'Modifier cet élément JSON';
            const jsonEditor = document.createElement('textarea');
            jsonEditor.value = JSON.stringify(entry, null, 2);
            jsonEditor.addEventListener('change', () => {
                try {
                    const updated = JSON.parse(jsonEditor.value);
                    if (!updated || typeof updated !== 'object' || Array.isArray(updated)) {
                        throw new Error('Cet élément doit rester un objet JSON.');
                    }
                    entries[index] = updated;
                    syncTextarea();
                    renderFields();
                } catch (error) {
                    setStatus(error.message, true);
                }
            });
            details.append(summary, jsonEditor);
            card.appendChild(details);
        }

        function renderInputSettings(entry, type, card) {
            const settings = document.createElement('div');
            settings.className = 'generator-display-grid';

            function addSetting(key, label, options = {}) {
                const currentValue = entry.input[key];
                let control;
                if (options.multiline) {
                    control = document.createElement('textarea');
                    control.value = currentValue == null ? '' :
                        typeof currentValue === 'object' ? JSON.stringify(currentValue, null, 2) : String(currentValue);
                    control.addEventListener('input', () => {
                        if (control.value === '') {
                            delete entry.input[key];
                        } else if (options.json) {
                            try {
                                entry.input[key] = JSON.parse(control.value);
                                setStatus('Configuration valide.', false);
                            } catch (error) {
                                setStatus(`Paramètre « ${label} » : JSON invalide.`, true);
                                return;
                            }
                        } else {
                            entry.input[key] = control.value;
                        }
                        syncTextarea();
                    });
                } else {
                    control = makeTextInput(currentValue ?? '', value => {
                        if (value === '') {
                            delete entry.input[key];
                        } else if (options.list) {
                            entry.input[key] = value.split(',').map(item => item.trim()).filter(Boolean);
                        } else {
                            entry.input[key] = value;
                        }
                        syncTextarea();
                    });
                }
                settings.appendChild(makeLabeledControl(label, control));
            }

            if (type === 'number') {
                addSetting('min', 'Valeur minimale');
                addSetting('max', 'Valeur maximale');
                addSetting('step', 'Incrément');
                addSetting('placeholder', 'Texte indicatif');
                addSetting('function', 'Fonction personnalisée');
            } else if (type === 'checkbox') {
                const formatParts = String(entry.input.format ?? 'checked#unchecked').split('#');
                let checked = formatParts[0];
                let unchecked = formatParts[1] ?? '';
                const checkedValue = makeTextInput(formatParts[0], value => {
                    checked = value;
                    entry.input.format = `${checked}#${unchecked}`;
                    syncTextarea();
                });
                const uncheckedValue = makeTextInput(formatParts[1] ?? '', value => {
                    unchecked = value;
                    entry.input.format = `${checked}#${unchecked}`;
                    syncTextarea();
                });
                settings.append(
                    makeLabeledControl('Valeur cochée', checkedValue),
                    makeLabeledControl('Valeur décochée', uncheckedValue)
                );
            } else if (type === 'db_selector') {
                addSetting('table', 'Table source');
                addSetting('select', 'Colonnes à charger');
                addSetting('value_on', 'Colonne de valeur');
                addSetting('option_text_on', 'Colonne(s) du libellé', { list: true });
                addSetting('where', 'Condition SQL');
                addSetting('order', 'Tri SQL');
                addSetting('limit', 'Limite');
                addSetting('placeholder', 'Texte du choix vide');
                addSetting('null_allowed', 'Libellé du choix vide');
                addSetting('request_values', 'Valeurs des paramètres SQL', { multiline: true, json: true });
            } else if (type === 'db_checkboxes') {
                addSetting('table', 'Table source');
                addSetting('select', 'Colonnes à charger');
                addSetting('values_on', 'Colonne de valeur');
                addSetting('text_on', 'Colonne du libellé');
                addSetting('title_on', 'Colonne de l’infobulle');
                addSetting('where', 'Condition SQL');
                addSetting('order', 'Tri SQL');
                addSetting('limit', 'Limite');
                addSetting('request_values', 'Valeurs des paramètres SQL', { multiline: true, json: true });

                if (!entry.input.save_method || typeof entry.input.save_method !== 'object') {
                    entry.input.save_method = { type: 'implode', attribute: ',' };
                }
                const method = document.createElement('select');
                ['implode', 'explode'].forEach(value => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    option.selected = entry.input.save_method.type === value;
                    method.appendChild(option);
                });
                method.addEventListener('change', () => {
                    entry.input.save_method.type = method.value;
                    syncTextarea();
                });
                settings.appendChild(makeLabeledControl('Enregistrement des valeurs', method));
                const separator = makeTextInput(entry.input.save_method.attribute ?? ',', value => {
                    entry.input.save_method.attribute = value;
                    syncTextarea();
                });
                settings.appendChild(makeLabeledControl('Séparateur des valeurs', separator));
            } else if (type === 'display_on_db') {
                addSetting('content_table', 'Table source');
                addSetting('content_on_field', 'Colonne de correspondance');
                addSetting('content_display', 'Colonnes affichées (séparées par des virgules)', { list: true });
            } else if (type === 'text' || type === 'tel' || type === 'text_empty' || type === 'textarea' || type === 'text_editor') {
                addSetting('placeholder', 'Texte indicatif');
                addSetting('function', 'Fonction personnalisée');
            } else if (type === 'file_list') {
                addSetting('display', 'Types de fichiers affichés', { list: true });
            }

            if (settings.childElementCount > 0) {
                card.appendChild(settings);
            }
        }

        function renderFields() {
            fields.replaceChildren();

            entries.forEach((entry, index) => {
                const card = document.createElement('div');
                card.className = 'generator-display-field';

                const heading = document.createElement('div');
                heading.className = 'generator-display-field-heading';
                const name = document.createElement('strong');
                name.textContent = entry.label || entry.column_name || `Élément ${index + 1}`;
                const actions = document.createElement('div');
                actions.className = 'generator-display-actions';
                const dragHandle = makeButton('↕', () => {});
                dragHandle.className = 'generator-display-drag-handle';
                dragHandle.draggable = true;
                dragHandle.setAttribute('aria-label', `Faire glisser pour déplacer ${name.textContent}`);
                dragHandle.title = 'Faire glisser pour changer l’ordre';
                const moveUp = makeButton('↑', () => moveEntry(index, -1), index === 0);
                moveUp.setAttribute('aria-label', 'Déplacer vers le haut');
                const moveDown = makeButton('↓', () => moveEntry(index, 1), index === entries.length - 1);
                moveDown.setAttribute('aria-label', 'Déplacer vers le bas');
                actions.append(
                    dragHandle,
                    moveUp,
                    moveDown,
                    makeButton('Supprimer', () => {
                        entries.splice(index, 1);
                        renderFields();
                        syncTextarea();
                    })
                );
                heading.append(name, actions);
                card.appendChild(heading);

                card.addEventListener('dragstart', event => {
                    if (!event.target.closest('.generator-display-drag-handle')) {
                        event.preventDefault();
                        return;
                    }
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(index));
                    card.classList.add('is-dragging');
                });
                card.addEventListener('dragover', event => {
                    if (!event.dataTransfer.types.includes('text/plain')) { return; }
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    const bounds = card.getBoundingClientRect();
                    const insertAfter = event.clientY > bounds.top + bounds.height / 2;
                    card.classList.toggle('drop-before', !insertAfter);
                    card.classList.toggle('drop-after', insertAfter);
                });
                card.addEventListener('dragleave', event => {
                    if (!card.contains(event.relatedTarget)) {
                        card.classList.remove('drop-before', 'drop-after');
                    }
                });
                card.addEventListener('drop', event => {
                    event.preventDefault();
                    const sourceIndex = Number(event.dataTransfer.getData('text/plain'));
                    const bounds = card.getBoundingClientRect();
                    const insertAfter = event.clientY > bounds.top + bounds.height / 2;
                    card.classList.remove('drop-before', 'drop-after');
                    if (Number.isInteger(sourceIndex) && sourceIndex >= 0 && sourceIndex < entries.length) {
                        moveEntryTo(sourceIndex, index, insertAfter);
                    }
                });
                card.addEventListener('dragend', () => {
                    fields.querySelectorAll('.is-dragging, .drop-before, .drop-after').forEach(item => {
                        item.classList.remove('is-dragging', 'drop-before', 'drop-after');
                    });
                });

                if (!Object.prototype.hasOwnProperty.call(entry, 'column_name')) {
                    const hint = document.createElement('p');
                    hint.className = 'generator-display-help';
                    hint.textContent = 'Élément de mise en page ou configuration avancée. Modifiez-le dans son JSON ou dans le mode JSON brut.';
                    card.appendChild(hint);
                    renderAdvancedEntry(entry, index, card);
                    fields.appendChild(card);
                    return;
                }

                if (!entry.input || typeof entry.input !== 'object') {
                    entry.input = { type: 'text' };
                }

                const grid = document.createElement('div');
                grid.className = 'generator-display-grid';

                const columnControl = makeTextInput(entry.column_name, value => {
                    entry.column_name = value;
                    name.textContent = entry.label || value || `Élément ${index + 1}`;
                    syncTextarea();
                });
                grid.appendChild(makeLabeledControl('Colonne de la base de données', columnControl));

                const labelControl = makeTextInput(entry.label ?? '', value => {
                    entry.label = value;
                    name.textContent = value || entry.column_name || `Élément ${index + 1}`;
                    syncTextarea();
                });
                grid.appendChild(makeLabeledControl('Libellé affiché', labelControl));

                const typeControl = document.createElement('select');
                const currentType = entry.input.type || 'text';
                const types = [...generatorDisplayInputTypes];
                if (!types.includes(currentType)) { types.push(currentType); }
                types.forEach(type => {
                    const option = document.createElement('option');
                    option.value = type;
                    option.textContent = type;
                    option.selected = type === currentType;
                    typeControl.appendChild(option);
                });
                typeControl.addEventListener('change', () => {
                    entry.input.type = typeControl.value;
                    renderFields();
                    syncTextarea();
                });
                grid.appendChild(makeLabeledControl('Type de champ', typeControl));
                card.appendChild(grid);

                const type = currentType;
                if (type === 'select' || type === 'radio') {
                    const optionsPanel = document.createElement('div');
                    optionsPanel.className = 'generator-display-advanced';
                    optionsPanel.style.marginTop = '0.75rem';
                    renderOptions(entry, type, optionsPanel);
                    card.appendChild(optionsPanel);
                } else if (type === 'textarea') {
                    const rowsInput = document.createElement('input');
                    rowsInput.type = 'number';
                    rowsInput.min = '1';
                    rowsInput.value = entry.input.rows ?? '';
                    rowsInput.addEventListener('input', () => {
                        if (rowsInput.value === '') {
                            delete entry.input.rows;
                        } else {
                            entry.input.rows = Number(rowsInput.value);
                        }
                        syncTextarea();
                    });
                    const textareaSettings = document.createElement('div');
                    textareaSettings.className = 'generator-display-grid';
                    textareaSettings.appendChild(makeLabeledControl('Nombre de lignes', rowsInput));
                    card.appendChild(textareaSettings);
                } else {
                    const hasVisualSettings = [
                        'checkbox', 'db_selector', 'db_checkboxes', 'display_on_db',
                        'number', 'text', 'tel', 'text_empty', 'text_editor'
                    ].includes(type);
                    if (!hasVisualSettings) {
                        const hint = document.createElement('p');
                        hint.className = 'generator-display-help';
                        hint.textContent = 'Les paramètres propres à ce type de champ peuvent être ajustés dans le JSON brut.';
                        card.appendChild(hint);
                    }
                }

                renderInputSettings(entry, type, card);

                const advanced = document.createElement('details');
                advanced.className = 'generator-display-advanced';
                const advancedSummary = document.createElement('summary');
                advancedSummary.textContent = 'Paramètres avancés de ce champ (JSON)';
                const advancedInput = document.createElement('textarea');
                advancedInput.value = JSON.stringify(entry.input, null, 2);
                advancedInput.addEventListener('change', () => {
                    try {
                        const updated = JSON.parse(advancedInput.value);
                        if (!updated || typeof updated !== 'object' || Array.isArray(updated) ||
                            typeof updated.type !== 'string' || !updated.type) {
                            throw new Error('Les paramètres doivent rester un objet JSON avec un type de champ.');
                        }
                        entry.input = updated;
                        syncTextarea();
                        renderFields();
                    } catch (error) {
                        setStatus(error.message, true);
                    }
                });
                advanced.append(advancedSummary, advancedInput);
                card.appendChild(advanced);
                fields.appendChild(card);
            });
        }

        function moveEntry(index, offset) {
            const destination = index + offset;
            if (destination < 0 || destination >= entries.length) { return; }
            moveEntryTo(index, destination, offset > 0);
        }

        function moveEntryTo(sourceIndex, targetIndex, insertAfter) {
            if (sourceIndex === targetIndex) { return; }
            const insertionIndex = targetIndex + (insertAfter ? 1 : 0);
            const [entry] = entries.splice(sourceIndex, 1);
            const destination = sourceIndex < insertionIndex ? insertionIndex - 1 : insertionIndex;
            entries.splice(destination, 0, entry);
            renderFields();
            syncTextarea();
        }

        function updateFromRaw() {
            try {
                entries = decodeDisplay(textarea.value);
                renderFields();
                addFieldButton.disabled = false;
                setStatus('Configuration JSON valide.', false);
            } catch (error) {
                addFieldButton.disabled = true;
                setStatus(error.message, true);
            }
        }

        rawToggle.addEventListener('click', () => {
            const rawMode = rawToggle.getAttribute('aria-expanded') !== 'true';
            rawToggle.setAttribute('aria-expanded', String(rawMode));
            rawToggle.textContent = rawMode ? 'Revenir à l’éditeur visuel' : 'Modifier le JSON brut';
            fields.hidden = rawMode;
            help.hidden = rawMode;
            addFieldButton.hidden = rawMode;
            textarea.style.display = rawMode ? 'block' : originalDisplay;
            if (rawMode) {
                try {
                    textarea.value = JSON.stringify(JSON.parse(textarea.value), null, 2);
                    textarea.focus();
                    setStatus('Mode JSON brut : vérifiez la syntaxe avant l’enregistrement.', false);
                } catch (error) {
                    setStatus(error.message, true);
                }
            } else {
                updateFromRaw();
            }
        });

        textarea.addEventListener('input', () => {
            if (rawToggle.getAttribute('aria-expanded') !== 'true') { return; }
            try {
                decodeDisplay(textarea.value);
                setStatus('Configuration JSON valide.', false);
            } catch (error) {
                setStatus(error.message, true);
            }
        });
        textarea.addEventListener('change', updateFromRaw);
        textarea.addEventListener('blur', updateFromRaw);

        addFieldButton.addEventListener('click', () => {
            entries.push({
                column_name: '',
                label: '',
                input: { type: 'text' }
            });
            renderFields();
            syncTextarea();
        });

        try {
            entries = decodeDisplay(textarea.value || '{}');
            renderFields();
            setStatus('', false);
        } catch (error) {
            entries = [];
            renderFields();
            addFieldButton.disabled = true;
            setStatus(error.message, true);
        }
    });
}
