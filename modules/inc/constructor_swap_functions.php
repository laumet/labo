<?php
function stringToSession ($tab) {
    global $db, $see_as;

    foreach($tab as $k => $v) {
        if (strpos($v, 'SESSION=>') !== false) {
            $session = explode('|', substr($v, 9));
            if (is_countable($session)) {
                $tab[$k] = $_SESSION[$session[0]];
                if ($see_as && $session[0] == 'user') {
                    $tab[$k] = $see_as;
                }
                
                if (count($_SESSION[$session[0]]) > 1) {
                    for ($i_s = 1 ; $i_s < count($session); $i_s++) {
                        $tab[$k] = $tab[$k][$session[$i_s]];
                    }
                }
            }
        }
    }
    return $tab;
}

function addFilter($display, $type) {
    $display = json_decode($display, true);
    $filter = '';
    switch ($type) {
        case 'standard':
            $filter .= '<filter>';
            $filter .= '<select id="filter_select">';
            $filter .= '<option selected value="none" >Filtre sur toutes les colonnes</option>';
            $options_filter = '';
            $options_order = '';
            foreach($display as $v) {
                if (isset($v['column_name'])) {
                    $option = '<option value="' . $v['column_name'] . '">';
                    $option .= isset($v['label']) ? $v['label'] : $v['column_name'];
                    $options_filter .= $option . '</option>';
                    if ($v['input']['type'] != 'checkboxes' && $v['input']['type'] != 'db_checkboxes') {
                       $options_order .= $option . '</option>';
                    }
                }
            }
            $filter .= $options_filter;
            $filter .= '</select>';
            $filter .= '<input id="filter_value" type="text" placeholder="filtrer" />';

            // add sorter
            $filter .= '<select id="sorter_select">';
            $filter .= '<option selected value="none" >Tri par colonne</option>';
            $filter .= $options_order;
            $filter .= '</select>';
            $filter .= '<sorterindicator class="hidden" ></sorterindicator>';
            $filter .= '</filter>';
            break;
    }
    return $filter;
}

function addFormat ($format, $row_format) {
    $add_format = '';
    if (!isset($format['type'])) { return; }
    switch ($format['type']) {
        case 'newline' :
            if ($row_format == 'column') {
                $add_format .= '</div><div class="column">';
            } else if ($row_format == 'row') {
                $add_format .= '</div><div class="row">';
            }
            break;
        default :
            if (strpos($format['type'], 0, 1) == '<') {
                $add_format .= $format['type'];
            }
    }
    return $add_format;
}

$inputs_memo = [];
// content of the css_include / js_include columns of the generator (paths relative to modules/)
// "file:name" or "file-name" => content of the file generator/<dir>/name (never outside of this folder)
// $any_path (css, as before): "file-path" can also be any file path
// otherwise the column contains the code itself
function generatorIncludeContent($include, $dir, $any_path = false) {
    if ($include === null || $include === '') {
        return '';
    }
    $name = substr($include, 5);
    if (strpos($include, 'file:') === 0 || strpos($include, 'file-') === 0) {
        $folder = realpath('generator/' . $dir);
        $file = realpath('generator/' . $dir . '/' . $name);
        if ($folder && $file && strpos($file, $folder . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
            return file_get_contents($file);
        }
        if ($any_path && is_file($name)) {
            return file_get_contents($name);
        }
        // missing file: nothing (the name is not code)
        return '';
    }
    if ($any_path && $name !== '' && is_file($name)) {
        return file_get_contents($name);
    }
    return stripcslashes($include);
}

// options for the values of a select that are not in its list (input option keep_unknown_value)
// $known: values of the options already created; $value: string or array (multiple)
function unknownValuesOptions($value, $known) {
    $options = '';
    $values = is_array($value) ? $value : array($value);
    foreach($values as $v) {
        if ($v === null || $v === '' || in_array((string) $v, array_map('strval', $known), true)) {
            continue;
        }
        $v = htmlspecialchars($v, ENT_QUOTES);
        $options .= '<option value="' . $v . '" selected data-unknown_value>⚠️ ' . $v . '</option>';
    }
    return $options;
}

function createInput($format, $value, $table, $row, $edit, $can_be_edited = true, $extra_condition = false) {
    global $db, $inputs_memo, $display_mode;

    $elt = '';
    $cond = false;
    if ($format['input']['type'] != 'hidden') {
        $elt .= '<div';
        if (isset($format['container'])) {
            if (isset($format['container']['class'])) {
                $elt .= ' class="' . $format['container']['class'] . '"';
            } else if (
                isset($format['container']['class_on_condition']) &&
                isset($format['container']['class_on_condition']['class']) &&
                is_array($format['container']['class_on_condition']) &&
                isset($format['container']['class_on_condition']['conditions']) &&
                isset($format['container']['class_on_condition']['conditions_type']) &&
                is_array($format['container']['class_on_condition']['conditions'])
            ) {
                switch ($format['container']['class_on_condition']['conditions_type']) {
                    case 'not_or' :
                        // check all conditions like or and reverse result (not)
                        foreach($format['container']['class_on_condition']['conditions'] as $c) {
                            if ($c == 'null' && ($extra_condition == 0 || $extra_condition == null)) {
                                $cond = true;
                            } else if ($c == $extra_condition) {
                                $cond = true;
                            }
                        }
                        $cond = !$cond;       
                        break;
                }
                if ($cond && $display_mode != 'table') {
                    $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
                }
            }
        }
        $elt .= ' >';
    }
    $id_elt = $table . '_' . $format['column_name'] . '_' . $row;
    $value_encoded = false;

    // create label if label !
    if (isset($format['label']) && $format['label'] != null & $format['label'] != '' && $display_mode != 'table') {
        $label = $format['label'] != '-' ? $format['label'] : '';        
        if ($format['input']['type'] != 'radio' && $format['input']['type'] != 'display_on_db') {
            $elt .= '<label for="' . $id_elt . '" >' . $label . '</label>';
        } else {
            $elt .= '<div class="label">' . $label . '</div>';
        }

        if ($value != null) {
            $value = htmlentities($value);
            $value_encoded = true;
        }
    }

    $attributes = false;
    if (isset($format['input']['attributes']) &&
        is_array($format['input']['attributes']) &&
        is_countable($format['input']['attributes']) &&
        count($format['input']['attributes']) > 0
    ) {
        $attributes = '';
        foreach($format['input']['attributes'] as $k => $a) {
            if (strlen($attributes) > 0) {
                $attributes .= ' ';
            }
            $attributes .= $k . '="' . $a . '"';
        }
    }

    // create input
    switch ($format['input']['type']) {
        case 'hidden' :
            $elt .= '<input type="hidden" name="' .$format['column_name'] . '" value="' . $value . '" />';
            break;
        case 'display' :
        case 'mailto' :
            $mailto = false;
            if ($format['input']['type'] == 'mailto') {
                $mailto = true;
                $elt .= '<a href="mailto:' . $value . '" >';
            }

            $elt .= '<div id="' . $id_elt . '" class="input_type_display';
            if ($cond && $display_mode == 'table') {
                $elt .= ' ' . $format['container']['class_on_condition']['class'];
            }
            $elt .= '" column="' . $format['column_name'] . '" name="' .$format['column_name'] . '"';
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' >';
            if ($value != '' && $value != '' ) {
                $elt .= htmlspecialchars_decode($value);
            }
            $elt .= '</div>';
            if ($mailto) {
                $elt .= '<input type="hidden" value="'. $value . '" name="username" />';
                $elt .= '</a>';
            }
            break;
        case 'display_on_db':
            $elt .= '<div id="' . $id_elt . '" column="' . $format['column_name'] . '" class="input_type_display on_db';
            if ($cond && $display_mode == 'table') {
                $elt .= ' ' . $format['container']['class_on_condition']['class'];
            }
            $elt .= '" >';
            if ($value != null && $value != '' && isset($format['input']['content_table']) && isset($format['input']['content_on_field']) && isset($format['input']['content_display'])) {
                // get content on $value
                $d_display = $db->prepare('SELECT * FROM ' . $format['input']['content_table'] . ' WHERE ' . $format['input']['content_on_field'] . ' = :' . $format['input']['content_on_field']);
                $d_display->execute(array($format['input']['content_on_field'] => $value));
                $display = $d_display->fetch();
                $d_display->closeCursor();

                if ($display && is_countable($format['input']['content_display']) && count($format['input']['content_display']) > 0) {
                    foreach($format['input']['content_display'] as $d_d) {
                        if (substr($d_d, 0, 1) == '_') {
                            switch (substr($d_d, 1)) {
                                case 'space':
                                    $elt .= ' ';
                                    break;
                                case 'newline' :
                                    $elt .= '<br />';
                                    break;
                                default :
                                    $elt .= substr($d_d, 1);    
                            }
                        } else {
                            $elt .= $display[$d_d];   
                        }
                    }
                }
            }
            $elt .= '</div>';
            break;
        case 'text' :
            $elt .= '<input id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" type="text" value="' . $value . '"';
            if (isset($format['input']['function'])) {
                $elt .= ' data_func="' . $format['input']['function'] . '"';
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            $elt .= ' />';
            break;
         case 'tel':
            $elt .= '<input id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" type="tel" value="' . $value . '"';
            if (isset($format['input']['function'])) {
                $elt .= ' data_func="' . $format['input']['function'] . '"';
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            $elt .= ' />';
            break;
        case 'number' :
            $elt .= '<input id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" type="number" value="' . $value . '"';
            if (isset($format['input']['function'])) {
                $elt .= ' data_func="' . $format['input']['function'] . '"';
            }
            if (isset($format['input']['min'])) {
                $elt .= ' min="' . $format['input']['min'] . '"';
            }
            if (isset($format['input']['max'])) {
                $elt .= ' max="' . $format['input']['max'] . '"';
            }
            if (isset($format['input']['step'])) {
                $elt .= ' step="' . $format['input']['step'] . '"';
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' />';
            break;
        case 'text_empty' :
            $elt .= '<input id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" type="text"';
            if ($function) {
                $elt .= ' data_func="' . $function . '"';
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' />';
            break;
        case 'select' :
            $elt .= '<select id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '"';
             if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' >';
            // compare the options with the value saved in the db (not encoded by the label)
            if ($value_encoded) {
                $value = html_entity_decode($value);
            }
            // prepare case of select with attribute mutliple
            $multiple = false;
            if (isset($format['input']['attributes']) && (in_array('multiple', $format['input']['attributes']) || array_key_exists('multiple', $format['input']['attributes']))) {
                $multiple = true;
                $value = explode(',', $value);
            }

            $known_values = [];
            if (isset($format['input']['options']) && is_countable($format['input']['options'])) {
                foreach($format['input']['options'] as $s_o) {
                    $elt .= '<option';
                    if ($multiple) {
                    $selected = in_array($s_o['value'], $value) ? ' selected' : '';
                    } else {
                        $selected = $s_o['value'] == $value ? ' selected' : '';
                    }
                    if (isset($s_o['value'])) {
                        $elt .= ' value="' . $s_o['value'] . '"' . $selected;
                        $known_values[] = $s_o['value'];
                    }
                    if (isset($s_o['attributes']) && is_countable($s_o['attributes'])) {
                        // list of attributes ["disabled"] or object {"data-x": "y"}
                        foreach($s_o['attributes'] as $s_k => $s_a) {
                            $elt .= is_int($s_k) ? ' ' . $s_a : ' ' . $s_k . '="' . $s_a . '"';
                        }
                    }
                    $elt .= '>';
                    if (isset($s_o['text'])) { $elt .= $s_o['text']; };
                    $elt .= "</option>";
                }
            }
            if (!empty($format['input']['keep_unknown_value'])) {
                $elt .= unknownValuesOptions($value, $known_values);
            }
            $elt .= "</select>";
            break;
         case 'checkbox' :
            $elt .= '<checkboxes';
            $cb_checked = 'checked';
            $cb_unchecked = 'unchecked';
            if (isset($format['input']['format']) && $format['input']['format'] != null && $format['input']['format'] != '' && strpos($format['input']['format'], '#') !== false) {
                $checkbox_format = explode('#', $format['input']['format']);
                $cb_checked = $checkbox_format[0];
                $cb_unchecked = $checkbox_format[1];
            }

            if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            $elt .= '><input id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" type="checkbox" ';
            if ($value == $cb_checked) {
                $elt .= ' checked';
            }
            if (isset($format['input']['function'])) {
                $elt .= ' data_func="' . $format['input']['function'] . '"';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' /></checkboxes>';
            break;
        case 'radio' :
            $buttons = $format['input']['content'];

            $elt .= '<radiobox class="radio_buttons';
            if ($cond && $display_mode == 'table') {
                $elt .= ' ' . $format['container']['class_on_condition']['class'];
            }
            $elt .= '" name="' . $format['column_name'] . '">';
            foreach($buttons as $radio_key => $radio_value) {
                $checked = false;
                if ($radio_key == $value) {
                        $checked = true;
                } else if (($value == '' || $value == null || !$value) &&
                    isset($format['input']['select_if_null']) &&
                    $format['input']['select_if_null'] == $radio_key) {
                    $checked = true;
                }
                $elt .= '<div>';
                $elt .= '<label for="' . $id_elt . '_' . $radio_key . '" >' . $radio_value . '</label>';
                $elt .= '<input id="' .$id_elt . '_' . $radio_key . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '_' . $row . '" type="radio" value="' . $radio_key . '"';
                if (!$edit || !$can_be_edited) {
                    $elt .= ' disabled';
                }
                if ($checked) {
                    $elt .= ' checked';
                }
                if ($attributes) {
                    $elt .= ' ' . $attributes;
                }
                $elt .= ' />'; // close input
                $elt .= '</div>'; // close button div
            }
            $elt .= '</radiobox>'; // close buttons div
            break;
        case 'textarea' :
            $elt .= '<textarea id="' .$id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '"';
            $elt .= isset($format['input']['rows']) ? ' rows="' . $format['input']['rows'] . '"' : '';
            if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' >';
            $elt .= $value;
            $elt .= '</textarea>';
            break;
        case 'text_editor':
            $elt .= '<div class="text_editor';
            if ($cond && $display_mode == 'table') {
                $elt .= ' ' . $format['container']['class_on_condition']['class'];
            }
            $elt .= '" id="' . $id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '"';
            if (isset($format['input']['placeholder'])) {
                $elt .= 'placeholder="' . $format['input']['placeholder'] . '" ';
            }

            if ($value != null && $value != '') {
                $elt .= 'content="' . $value . '" ';
            }
            if (!$edit || !$can_be_edited) {
                $elt .= ' disabled';
            }
            $elt .= '>';
            $elt .= '<div id="te_' . $id_elt . '" ></div>';
            $elt .= '</div>';
            break;
        case 'db_selector':
            // first check if job already done...
            $selector = $format['input'];
            $selector_content = false;
            if (isset($inputs_memo[$table . '_' . $format['column_name']])) {
                $selector_content = $inputs_memo[$table . '_' . $format['column_name']];
            } else {
                // get content in db
                // check if all is ok
                if (!isset($selector['table']) || !isset($selector['option_text_on'])) {
                    $elt .= '<div>ERROR db_selector: missing datas</div>';
                    break;
                }
                // create query
                $request = 'SELECT ';
                $request .= isset($selector['select']) ? ' ' . $selector['select'] : ' *';
                $request .= ' FROM ' . $selector['table'];
                // no_logical_delete: source without logical_delete column (ex. information_schema.tables)
                $conditions = [];
                if (isset($selector['where'])) {
                    $conditions[] = $selector['where'];
                }
                if (empty($selector['no_logical_delete'])) {
                    $conditions[] = 'logical_delete = 0';
                }
                $request .= count($conditions) > 0 ? ' WHERE ' . implode(' AND ', $conditions) : '';
                $request .= isset($selector['order']) ? ' ORDER BY ' . $selector['order'] : '';
                $request .= isset($selector['limit']) ? ' LIMIT ' . $selector['limit'] : '';
                $d_selector = $db->prepare($request);
                // execute query
                if (isset($selector['request_values'])) {
                    $d_selector->execute(stringToSession($selector['request_values']));
                } else {
                    $d_selector->execute();
                }

                // get content
                $selector_content = $d_selector->fetchAll();
                if (!isset($selector['where'])) {
                    $inputs_memo[$table . '_' . $format['column_name']] = $selector_content;
                }
            }
            if ($selector_content === false) {
                $elt .= '<div>ERROR db_selector: request failed ' . json_encode($d_selector->errorInfo()) . ' - ' . $request . '</div>';
                break;
            }
            $in_case_of_null = true;
            if (count($selector_content) == 0 && isset($selector['in_case_of_null']) && $selector['in_case_of_null'] == 'disabled') {
                $in_case_of_null = false;
            } 

            // define select
            $elt .= '<select id="' . $id_elt . '" table="' . $table . '" row="' . $row . '" column="' . $format['column_name'] . '" name="' . $format['column_name'] . '"';
            if ($cond && $display_mode == 'table') {
                $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
            }
            if (!$edit || !$can_be_edited || !$in_case_of_null) {
                    $elt .= ' disabled';
                }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            $elt .= ' >';
            // define first option
            $disabled = ' disabled';
            if (isset($selector['null_allowed'])) {
                $disabled = '';
                if (!empty($selector['null_allowed'])) {
                    $selector['placeholder'] = $selector['null_allowed'];
                }
            }
            $elt .= '<option ' . $disabled . ' value=""';
            $elt .= $value && $value != '' && $value != null && $value != 0 ? '' : ' selected';
            $elt .=  ' >';
            $elt .= isset($selector['placeholder']) ? $selector['placeholder'] : 'Veuillez choisir';
            $elt .= '</option>';
            // compare the options with the value saved in the db (not encoded by the label)
            if ($value_encoded) {
                $value = html_entity_decode($value);
            }
            // prepare case of select with attribute mutliple
            $multiple = false;
            if (isset($selector['attributes']) && (in_array('multiple', $selector['attributes']) || array_key_exists('multiple', $selector['attributes'])) && $value != '' && $value != null) {
                $multiple = true;
                $value = explode(',', $value);
            }
            // define all options
            $known_values = [];
            foreach ($selector_content as $k => $v) {
                if ($multiple) {
                    $selected = in_array($v[$selector['value_on']], $value) ? ' selected' : '';
                } else {
                    $selected = $v[$selector['value_on']] == $value ? ' selected' : '';
                }
                $option_value = isset($selector['value_on']) ? $v[$selector['value_on']] : $v['id'];
                $known_values[] = $option_value;
                $option_content = '';
                if (is_array($selector['option_text_on'])) {
                    $separator = !empty($selector['option_text_on_separator']) ? $selector['option_text_on_separator'] : ' ';
                    $insert_before_next_if_not_null = '';
                    $insert_before_next = '';
                    $next = true;
                    foreach($selector['option_text_on'] as $option_text) {
                        $next = $next ? false : true;

                        $option_content .= strlen($option_content) > 0 ? $separator : '';
                        
                        $insert_before_next = '';
                        
                        if (substr($option_text, 0, 1 ) == '_') {
                            $insert_before_next_if_not_null = substr($option_text, 1);
                            $next = true;
                        }

                        if (substr($option_text, 0, 1 ) == '|') {
                            $option_content .= substr($option_text, 1);
                        } else if (!$next) {
                            $option_content .= $option_text != '' && $option_text != null ? $insert_before_next_if_not_null . $v[$option_text] : '';
                            $insert_before_next_if_not_null = '';
                        }
                    }
                } else {            
                    $option_content = $v[$selector['option_text_on']];
                }
                $elt .= '<option value="' . $option_value . '"' . $selected;
                if (isset($selector['set_data_infos']))  {
                    $elt .= ' data-' . $selector['set_data_infos'] . '=\'' . json_encode($v, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '\'';
                }
                 $elt .= ' >' . $option_content . '</option>';
            }
            if (isset($selector['last_options']) && is_array($selector['last_options'])) {
                foreach($selector['last_options'] as $o) {
                    if (isset($o['content'])) {
                        $elt .= '<option ';
                        if (isset($o['value'])) {
                            $elt .= 'value="' . $o['value'] . '" ';
                            if ($o['value'] == $value) {
                                $elt .= ' selected';
                            }
                        }
                        $elt .= '>' . $o['content'] . '</option>';
                        if (isset($o['value'])) {
                            $known_values[] = $o['value'];
                        }
                    }
                }
            }
            if (!empty($selector['keep_unknown_value'])) {
                $elt .= unknownValuesOptions($value, $known_values);
            }
            $elt .= '</select>';
            break;
        case 'db_checkboxes':
             // first check if job already done...
            $checkboxes = $format['input'];
            if (isset($inputs_memo[$table . '_' . $format['column_name']])) {
                $checkboxes_content = $inputs_memo[$table . '_' . $format['column_name']];
            } else {
                // get content in db
                // check if all is ok
                if (!isset($checkboxes['table']) || !isset($checkboxes['values_on'])  || !isset($checkboxes['save_method']) || !isset($checkboxes['save_method']['type'])) {
                    $elt .= '<div>ERROR db_checkboxes: missing datas</div>';
                    break;
                }
                // create query
                $request = 'SELECT ';
                $request .= isset($checkboxes['select']) ? $checkboxes['select'] : '*';
                $request .= ' FROM ' . $checkboxes['table'];
                $request .= isset($checkboxes['where']) ? ' WHERE ' . $checkboxes['where'] : '';
                $request .= isset($checkboxes['order']) ? ' ORDER BY ' . $checkboxes['order'] : '';
                $request .= isset($checkboxes['limit']) ? ' LIMIT ' . $checkboxes['limit'] : '';
                $d_checkboxes = $db->prepare($request);
                // execute query
                if (isset($checkboxes['request_values'])) {
                    // check if any value is to be treated:
                    $request_values = stringToSession($checkboxes['request_values']);
                    $d_checkboxes->execute($request_values);
                } else {
                    $d_checkboxes->execute();
                }
                // get content
                $checkboxes_content = $d_checkboxes->fetchAll();
                $inputs_memo[$table . '_' . $format['column_name']] = $checkboxes_content;
            }
            if (!$checkboxes_content || !is_countable($checkboxes_content) || count($checkboxes_content) < 1) {
                $elt .= '<div>ERROR db_checkboxes: request failed</div>';
                break;
            }
            
            // define values
            $values_defined_array = [];
            switch ($checkboxes['save_method']['type']) {
                case 'implode' :
                case 'explode' :
                    if (!isset($checkboxes['save_method']['attribute'])) {
                        $elt .= '<div>ERROR db_checkboxes : save_method not well defined</div>';
                    }
                    $values_defined_array = $value != '' && $value != null ? explode($checkboxes['save_method']['attribute'], $value) : [];
                    break;
                default : 
                    $elt .= '<div>ERROR db_checkboxes : save_method not yet build</div>';
                    break;
            }
            if ($display_mode != 'table') {
                // define group of checkboxes
                $elt .= '<checkboxes id="' . $id_elt . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" ';
                if ($cond && $display_mode == 'table') {
                    $elt .= ' class="' . $format['container']['class_on_condition']['class'] . '"';
                }
                if (isset($checkboxes['save_method'])) {
                    $elt .= 'save_method=\'' . json_encode($checkboxes['save_method']) . '\' ';
                }
                $elt .= '>';
            }
            $start = 0;
            foreach ($checkboxes_content as $k => $cb) {
                $elt .= $start > 0 ? '</div>' : '';
                if ($display_mode == 'table') {
                    if ($start > 0 ) {
                        $elt .= '<div>';
                    } else {
                        $elt .= '<checkboxes_in_table id="' . $id_elt . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" class="hidden" ';
                        if (isset($checkboxes['save_method'])) {
                            $elt .= 'save_method=\'' . json_encode($checkboxes['save_method']) . '\' ';
                        }
                        $elt .= '></checkboxes_in_table>';
                            
                    }
                } 
                $elt .=  $display_mode != 'table' ? '<div class="horizontal" >' : '';
                $elt .= '<input name="' . $format['column_name'] . '_|_' . $cb[$checkboxes['values_on']] . '" id="cb_' . $table . '_' . $row . '_' . $k . '" type="checkbox" value="' . $cb[$checkboxes['values_on']] . '" ';
                if (isset($checkboxes['title_on']) && isset($cb[$checkboxes['title_on']]) && $cb[$checkboxes['title_on']] != '') {
                    $elt .= 'title="' . htmlspecialchars($cb[$checkboxes['title_on']], ENT_QUOTES) . '" ';
                }
                if (!$edit || !$can_be_edited) {
                    $elt .= ' disabled';
                }
                if (in_array($cb[$checkboxes['values_on']], $values_defined_array)) {
                    $elt .= 'checked ';
                }
                if ($display_mode == 'table') {
                    $elt .= ' checkbox_in_table="' . $id_elt .'"';
                }

                $elt .= '>';                    
                if ($display_mode != 'table') {
                    $cb_label_content = isset($checkboxes['text_on']) ? $cb[$checkboxes['text_on']] : $cb[$checkboxes['values_on']];
                    $elt .= '<label for="cb_' . $table . '_' . $row . '_' . $k . '" >' . $cb_label_content . '</label>';
                }
                $start++;
            }
            $elt .= $display_mode != 'table' ? '</checkboxes>' : '';
            break;
        case 'json' :
            $elt .= '<textarea class="json_editor';
            if ($cond && $display_mode == 'table') {
                $elt .= ' ' . $format['container']['class_on_condition']['class'];
            }
            $elt .= '" id="' . $id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '"';
            if (!$edit || !$can_be_edited) {
                    $elt .= ' disabled';
            }
            if ($attributes) {
                $elt .= ' ' . $attributes;
            }
            if (isset($format['input']['placeholder'])) {
                $elt .= 'placeholder="' . $format['input']['placeholder'] . '" ';
            }
            $elt .= '>';
            if ($value != null && $value != '') {
                $elt .= $value;
            }
            $elt .= "</textarea>";
            break;
        case 'file' :
            $elt .= '<input type="file" id="' . $id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '"';
            if ($attributes) {
                if ($value != '' && $value != null && $value) {
                    // remove required attribute
                    $attributes = str_replace('required', '', $attributes);
                    // remove double spaces
                    $attributes = str_replace('  ', ' ', $attributes);
                }
                $elt .= $attributes;
            }
            $elt .= ' >';
            if ($value != '' && $value != null && $value) {
                $elt .= '<a href="' . labo_url('file_swap.php') . '?f=' . $row . '" >' . $value . '</a>';
            }
            break;
        case 'thumb' :
            $elt .= '<img id="' . $id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '"';
            if ($value == 'doc') {
                $elt .= ' src="icons/doc.svg" ';
            } else if ($value != null && strlen($value) > 1){
                $elt .= ' src="data:image/jpeg;base64,' . $value . '" ';
            }
            $elt .= '/>';
            break;
        case 'file_list' : 
            $elt .= '<div class="horizontal" ><edit function="editFileList" data-file_list_id="' . $id_elt . '" row="' . $row . '" ></edit>';
            $elt .= '<input type="hidden" id="' . $id_elt . '" column="' . $format['column_name'] . '" table="' . $table . '" row="' . $row . '" name="' .$format['column_name'] . '" value="' . $value . '" />';
            $elt .= '<listfilegalery  row="' . $row . '" >';
            $elt .= fileList($value, isset($format['display']) ? $format['display'] : null);
            $elt .= '</listfilegalery>';
            $elt .= '</div>';
            break;
    }
    if ($format['input']['type'] != 'hidden') {
        $elt .= '</div>';
    }
    return $elt;
}

// page_with_modules: the rights of the user for a module replace the can_* of its generator line
// (null if the user can't view the module); see modules/inc/page_modules.php
function labo_apply_module_rights ($selector) {
    if (!$selector || !isset($selector['id']) || !isset($_SESSION['authorized'][(int) $selector['id']])) {
        return null;
    }
    $rights = $_SESSION['authorized'][(int) $selector['id']];
    $selector['can_edit'] = $rights['can_edit'];
    $selector['can_add'] = $rights['can_add'];
    $selector['can_delete'] = $rights['can_delete'];
    return $selector;
}

function defineHomePageUser ($user_type) {
    global $content, $db;

    // first get content
    // we need or a page linked to rmr_status, or basis page !
    $d_content_tab = array(
        'id' => isset($_SESSION['rmr_status']['id']) ? $_SESSION['rmr_status']['id'] : null,
        'status_id' => isset($_SESSION['rmr_status']['status_id']) ? $_SESSION['rmr_status']['status_id'] : null
    );
    $d_content_tab['role_short_name'] = $user_type;
    // the homepage tables can be missing (new installation): the default homepage is used
    try {
        $d_content_user_homepage = $db->prepare('
            SELECT pa.*, po.content, po.files FROM labo_homepages AS pa
            INNER JOIN labo_homepages_posts AS po
            ON po.page_id = pa.id
            LEFT JOIN roles AS r
            ON pa.user_type_id = r.id
            WHERE ( r.role_short_name = :role_short_name)
                AND
                ( pa.rmr_id IS NULL  OR pa.rmr_id = "" OR  pa.rmr_id = :id)
                AND
                ( pa.status_id IS NULL OR pa.status_id = "" OR pa.status_id = :status_id)
            ORDER BY
                po.id DESC,
                pa.status_id DESC
            LIMIT 1
        ');
        $d_content_user_homepage->execute($d_content_tab);
        $content_user_homepage = $d_content_user_homepage->fetch();
        $d_content_user_homepage->closeCursor();
    } catch (PDOException $e) {
        $content_user_homepage = false;
    }

    $content .= '<div class="ql-editor labo_homepage">';
    if (!$content_user_homepage) {
        // default homepage: no homepage written for this role
        $content_user_homepage = array(
            'content' => '<h2 data-i18n="homepage.default_title">' . labo_t('homepage.default_title') . '</h2>'
                . '<p data-i18n="homepage.default_text">' . labo_t('homepage.default_text') . '</p>',
            'files' => null
        );
    }

    $plugins = getPluginsRepositories('define_homepage_files_content.php');
    if (is_countable($plugins)) {
        foreach($plugins as $p) {
            include '../plugins/' . $p . '/define_homepage_files_content.php';
        }
    }

    $content .= $content_user_homepage['content'];
    if (!empty($content_user_homepage['files'])) {
        $content .= '<listfilegalery>';
        $content .= fileList($content_user_homepage['files'], 'both', '');
        $content .= '</listfilegalery>';
    }
    $content .= '</div>';
    return;
}

function defineRow ($generator_index, $id_row) {
    global $content, $display_mode, $db;

    // first get content selector
    $d_selector = $db->prepare('SELECT * FROM generator WHERE id = :id');
    $d_selector->execute(array('id' => $generator_index));
    $selector = $d_selector->fetch(PDO::FETCH_ASSOC);
    $d_selector->closeCursor();

    // check hability
    if (labo_page_mode()) {
        // page_with_modules: rights of the user for this module (module_permissions)
        $selector = labo_apply_module_rights($selector);
        if ($selector === null) {
            exit('ko-not_authorised (csf_module)');
        }
    } else if (!in_array($selector['user_type'], $_SESSION['display_roles'])) {
        // user_type can be integer...
        if ($_SESSION['roles']['max_level'] <= intval($selector['user_type'])) {
                exit('ko-not_authorised (csf_defineContent)');
       }
    }
 
    // define display mode
    $display_mode = $selector['form'];

    // define edition possibilities
    $edit = false;
    if ($selector['can_edit'] === 1) {
        $edit = true;
    }
    
    $edit_by_row = false;
    if ($selector['edit_by_row_mode'] === 1) {
        $edit_by_row = true;
    }
   
    $add = false;
    if ($selector['can_add'] === 1) {
        $add = true;
    }
    
    $delete = $selector['can_delete'];
    
    $refresh = false;
    if ($selector['can_refresh'] === 1) {
        $refresh = true;
    }

    // run (if existing) plugin "privileges_definition"
    $plugins = getPluginsRepositories('privileges_definition.php');
    if (is_countable($plugins)) {
        foreach($plugins as $p) {
            include '../plugins/' . $p . '/privileges_definition.php';
        }
    }

    $rows = false;
    if (strpos($selector['table_name'], '/plugins/') === false) {
        $request = 'SELECT ';
        if ($selector['request_select'] != null && $selector['request_select'] != '') {
            $request .= $selector['request_select'];
        } else {
            $request .= '*';
        }
        
        $request .= ' FROM ' . $selector['table_name'] . ' AS tb ';

        if ($selector['request_from'] != null && $selector['request_from'] != '') {
            $request .= ' ' . $selector['request_from'] . ' ';
        }

        $request .= 'WHERE id = :id_row ';
        if ($selector['request_where'] != null && $selector['request_where'] != '') {
            $request .= 'AND ' . $selector['request_where'] . ' ';
        }

        if ($delete !== 1) {
            $request .= "AND tb.logical_delete <> 1 "; 
        }

        if ($selector['request_order'] != null && $selector['request_order'] != '') {
            $request .= 'ORDER BY ' . $selector['request_order'] . ' ';
        }
        /*
        echo 'ok-' . $request;
        */
        // then get content based on selector
        $d_row = $db->prepare($request);
        if ($selector['request_values'] == null) {
            $d_row->execute(array('id_row' => $id_row));
        } else {
            $where_values = json_decode($selector['request_values'], true);
            $where_values = stringToSession($where_values);
            $where_values['id_row'] = $id_row;
            $d_row->execute($where_values);
        }

        $row = $d_row->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $content = defineRowContent($row, $generator_index, $selector, $edit, $edit_by_row, $add, $delete, $refresh);
        } else {
            $content = 'ko-fail_getting_row';
        }

        return;
        /*
        echo 'ok-';
        var_dump($rows);
        var_dump($d_rows->errorInfo());
        */
    }
}


function defineContent($generator_index) {
    global $content, $see_as, $display_mode, $db, $to_be_highlighted, $highlight_check;

    $global_editing_tools = true; // add end editing tools 
    $content_container = true; // create a container to receive rows and new_rows

    $to_be_highlighted = false;
    $highlight_check = '';

    // first get content selector
    $d_selector = $db->prepare('SELECT * FROM generator WHERE id = :id');
    $d_selector->execute(array('id' => $generator_index));
    $selector = $d_selector->fetch(PDO::FETCH_ASSOC);
    $d_selector->closeCursor();

    // check hability
    if (labo_page_mode()) {
        // page_with_modules: rights of the user for this module (module_permissions)
        $selector = labo_apply_module_rights($selector);
        if ($selector === null) {
            exit('ko-not_authorised (csf_module)');
        }
    } else if (!in_array($selector['user_type'], $_SESSION['display_roles'])) {
        // user_type can be integer...
        if ($_SESSION['roles']['max_level'] <= intval($selector['user_type'])) {
                exit('ko-not_authorised (csf_defineContent)');
       }
    }
    
    // define edition possibilities
    $edit = false;
    if ($selector['can_edit'] === 1) {
        $edit = true;
    }
    
    $edit_by_row = false;
    if ($selector['edit_by_row_mode'] === 1) {
        $edit_by_row = true;
    }
    
    $add = false;
    if ($selector['can_add'] === 1) {
        $add = true;
    }
    
    $delete = $selector['can_delete'];
    
    $refresh = false;
    if ($selector['can_refresh'] === 1) {
        $refresh = true;
    }
    
    /* 
    if ($_SESSION['user']['id'] == 1)
    {
        echo 'ok-<p>Constructor Swap Functions => selector</p><pre>';
        echo 'edit: ' . $edit;
        echo 'edit_by_row: ' . $edit_by_row;
        echo 'add: ' . $add;
        echo 'delete: ' . $delete;
        echo 'refresh: ' . $refresh;
        // exit();
        }
    */

    // run (if existing) plugin "privileges_definition"
    $plugins = getPluginsRepositories('privileges_definition.php');
    if (is_countable($plugins)) {
        foreach($plugins as $p) {
            include '../plugins/' . $p . '/privileges_definition.php';
        }
    }

    // run (if existing) plugin "before_getting_content"
    $plugins = getPluginsRepositories('before_getting_content.php');
    if (is_countable($plugins)) {
        foreach($plugins as $p) {
            include '../plugins/' . $p . '/before_getting_content.php';
        }
    }

    // "see as" for ad and sa management
    if (in_array('ad', $_SESSION['display_roles']) || in_array('sa', $_SESSION['display_roles'])) {
        // create "see as" selector
        // get all user that can see the page
        $req = 'SELECT id, name, lastname FROM users WHERE roles LIKE CONCAT("%", :r , "%")';
        $req_vals = array(':r' => $selector['user_type']);
        /*
        if ($_SESSION['roles'][0] == 'sa') {
            $req .= ' OR roles LIKE CONCAT("%", :t, "%")';
            $req_vals['t'] = 'ad';
        }
        */
        if (isset($selector['generator_see_as']) && $selector['generator_see_as'] != '' && $selector['generator_see_as'] != null) {
            $req .= ' OR roles LIKE CONCAT("%", :u, "%")';
            $req_vals['u'] = $selector['generator_see_as'];
        }
        $req .= ' ORDER BY lastname ASC';
        $d_all_other_users = $db->prepare($req);
        $d_all_other_users->execute($req_vals);
        $all_other_users = $d_all_other_users->fetchAll(PDO::FETCH_ASSOC);

        $content .= '<select name="see_as" basis_user="' . $_SESSION['user']['id'] . '" >';
        $content .= '<option ';
        if (!isset($_POST['see_as']) || $_POST['see_as'] == $_SESSION['user']['id'])
        {
            $content .= 'selected ';
        }
        $content .= 'value="' . $_SESSION['user']['id'] . '" >' .  $_SESSION['user']['name'] . ' ' .  $_SESSION['user']['lastname'] . '</option>';
        foreach($all_other_users AS $u) {
            if ($u['id'] != $_SESSION['user']['id']) {
                $content .= '<option ';
                if (isset($_POST['see_as']) && $_POST['see_as'] == $u['id'])
                {
                    $content .= 'selected ';
                }
                $content .= ' value="' . $u['id'] . '" >' . $u['lastname'] . ' ' . $u['name'] . '</option>';
            }
        }
        $content .= '</select>'; 
    }

    $rows = false;
    if (strpos($selector['table_name'], '/plugins/') === false) {
        $request = 'SELECT ';
        if ($selector['request_select'] != null && $selector['request_select'] != '') {
            $request .= $selector['request_select'];
        } else {
            $request .= '*';
        }
        
        $request .= ' FROM ' . $selector['table_name'] . ' AS tb ';

        if ($selector['request_from'] != null && $selector['request_from'] != '') {
            $request .= ' ' . $selector['request_from'] . ' ';
        }

        if ($selector['request_where'] != null && $selector['request_where'] != '') {
            $request .= 'WHERE ' . $selector['request_where'] . ' ';
        }

        if ($delete !== 1) {
            $request .= empty($selector['request_where']) ? "WHERE tb.logical_delete != 1 " : "AND tb.logical_delete <> 1 "; 
        }

        if ($selector['request_order'] != null && $selector['request_order'] != '') {
            $request .= 'ORDER BY ' . $selector['request_order'] . ' ';
        }
        /*
        echo 'ok-' . $request;
        */
        // then get content based on selector
        $d_rows = $db->prepare($request);
        if ($selector['request_values'] == null) {
            $d_rows->execute();
        } else {
            $where_values = json_decode($selector['request_values'], true);
            $where_values = stringToSession($where_values);
            $d_rows->execute($where_values);
        }
        $rows = $d_rows->fetchAll(PDO::FETCH_ASSOC);
        
        /*
        echo 'ok-';
        echo $request;
        var_dump($rows);
        var_dump($d_rows->errorInfo());
        */
    }

    // define global div to gather all elements
    $content .= '<wrapper role="' . $selector['user_type'] . '" content="' . $selector['id'] . '">';

    $content .= '<style>';
    // add css
    if ($selector['css_include'] != null &&
        $selector['css_include'] != '') {
        $content .= generatorIncludeContent($selector['css_include'], 'css', true);

        // run (if existing) plugin "include_css"
        $plugins = getPluginsRepositories('include_css.php');
        if (is_countable($plugins)) {
            foreach($plugins as $p) {
                $content .= "\n";
                include '../plugins/' . $p . '/include_css.php';
            }
        }
    }
    $content .= '</style>';

    // create content
    $content .= $selector['container_start'];

    // add title
    $content .= '<h1>' . $selector['title'] . '</h1>';

    // add first line filter
    if ($selector['can_filter'] == 1 && is_countable($rows) && count($rows) > 3 ) {
        $filter_type = 'standard';
        $content .= addFilter($selector['display'], $filter_type);
    }
    
    // install plugin form or define format
    if (strpos($selector['table_name'], 'plugin') !== false) {
        if (is_file('../plugins/' . $selector['form'])) {
            include '../plugins/' . $selector['form'];
        }
    }

    // add if required counter rows
    if (isset($selector['counter_rows']) && $selector['counter_rows'] === 1) {
        $content .= '<div id="counter_rows" class="horizontal';
        if ($selector['form'] == 'table') {
            $content .= ' counter_table';
        }
        $content .= '" >Nombre d\'entrées&nbsp;:<totaliser totalrows>' . count($rows) . '</totaliser></div>';
    }

    if (strpos($selector['form'], '|') !== false) {
        // explode form to get basis form
        $exploded_form = explode('|', $selector['form']);
        $selector['form'] = $exploded_form[0];
        $selector['form_complement'] = $exploded_form['1'];
        // exploded (if required) form_complement
        if (strpos($exploded_form[1], '§') !== false) {
            $selector['form_complement'] = explode('§', $exploded_form['1']);
        }
    }

    // variable content loop is set to allow tab system to create a loop over the content
    $content_loop = 1;
    $tabs = false;
    $title_tab = false;
    if (isset($selector['form_complement']) && is_array($selector['form_complement']) && $selector['form_complement'][0] == 'headertabs' && isset($selector['form_complement'][1])) {

        // manage rows content to loop over form_complement[1];
        $tabs = true;
        $reordered_rows = [];
        $header_tabs = [];

        $multi_column_tab_column = false;
        $tab_column = [$selector['form_complement'][1]];
        if (strpos($selector['form_complement'][1], '{"') !== false) {
            $tab_column = json_decode($selector['form_complement'][1], true);
            if (isset($tab_column['multi_column_tab'])) {
                $multi_column_tab_column = true;
            }
        }
        $counter = 0;
        if (isset($tab_column['title_tab_on'])) { 
            $title_tab = [];
        }
        foreach($rows as $r) {
            $key;
            if ($multi_column_tab_column) {
                $key = '';
                foreach($tab_column['multi_column_tab'] as $mt) {
                    if (strlen($key) > 0 ) { $key .= '_'; }
                    $key .= $r[$mt];
                }
            } else {
                $key = $r[$tab_column[0]];
            }

            if (!isset($reordered_rows[$key])) {
                $reordered_rows[$key] = [];
                $header_tabs[$counter] = [];
                $header_tabs[$counter]['key'] = $key;
                $header_tabs[$counter]['txts'] = [];
                if (isset($tab_column['header_content'])) {
                    $header_tabs[$counter]['header_row'] = [];
                    foreach($tab_column['header_content'] as $idx => $t) {
                        if (strpos($t, '-->') !== false) {
                            $header_val = substr($t, 3);
                        } else {
                            $header_val = $r[$t];
                        }
                        $header_tabs[$counter]['header_row'][$idx] = $header_val;
                        $header_tabs[$counter]['txts'][] = $header_val;
                    }
                } else {
                    $header_tabs[$counter]['txts'] = [$r[$selector['form_complement'][1]]];
                }
                $counter++;
            }
            $reordered_rows[$key][] = $r;
            if ($title_tab !== false) {
                $title_tab[$key] = $r[$tab_column['title_tab_on']];
            }
        }

        // then: loop to create tabs
        $content .= '<headertabs>';
        $first_active = true;
        foreach ($header_tabs as $v) {
            $texts = '';
            $content .= '<headertab ';
            if ($first_active) {
                $content .= 'active ';
                $first_active = false;
            }
            $content .= 'key="' . $v['key'] . '" >';
            foreach($v['txts'] as $tab_txt) {
                $texts .= $tab_txt;
            }
            /*
            */
            $content .= $texts . '</headertab>';
        }
        $content .= '</headertabs>';

        /*
        echo 'ok-<pre>';
        // 
        var_dump($header_tabs);
        exit();
        */
        // start tab container loop
        $content_loop = count($header_tabs);
    }

    if ($content_loop > 0) {
        for ($i_content_loop = 0; $i_content_loop < $content_loop; $i_content_loop++) {
            $content_container_class = '';
            $content_container_attribute = '';
            
            if ($tabs) {
                $rows = $reordered_rows[$header_tabs[$i_content_loop]['key']];
                $content_container_class = $i_content_loop > 0 ? ' hidden' : '';
                $content_container_attribute = 'key="' . $header_tabs[$i_content_loop]['key'] . '" ';
                if (isset($header_tabs[$i_content_loop]['header_row'])) {
                    $content_container_attribute .= 'data-tab-header-row="' . htmlspecialchars(json_encode($header_tabs[$i_content_loop]['header_row'], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '" ';
                }
            }
            
            if ($title_tab !== false) {
                $content .= '<h2 id="table_title_' . $header_tabs[$i_content_loop]['key'] . '" class="' . $content_container_class . '" >' . $title_tab[$header_tabs[$i_content_loop]['key']] . '</h2>';
            }

            $display_mode = $selector['form'];
            if ($content_container) {
                // create container indicating type of display
                $content .= '<contentcontainer class="' . $selector['form'] . $content_container_class . '" ' . $content_container_attribute . '>';
                if ($selector['form'] == 'table') {
                    $content .= '<div>';
                }
            }

            $display_content = true;

            $plugins = getPluginsRepositories('define_table_content.php');
            if (is_countable($plugins)) {
                foreach($plugins as $p) {
                    include '../plugins/' . $p . '/define_table_content.php';
                }
            }

            if ($display_content && $selector['form'] == 'table' && $selector['display'] != null && $selector['display'] != '') {
                $content .= '<headerrow>';


                // loop to create header
                $header_cells = json_decode($selector['display'], true);
                foreach ($header_cells as $hc) {
                    if ($hc['input']['type'] != 'hidden') {                   
                        if (isset($hc['input']) && isset($hc['label'])) {
                            if ($hc['input']['type'] == 'db_checkboxes') {
                                // in this case, we have to make one column for each checkbox... I write that here, it's maybe not the good choice...
                                // get all values defined by input
                                /*
                                "table":"roles","where":"level <= :level","order":"level ASC","values_on":"role_short_name","text_on":"role_name","request_values":{"level":"SESSION=>roles|max_level"}
                                */
                                $dbc_req = 'SELECT * FROM ' . $hc['input']['table'];
                                $dbc_req .= isset($hc['input']['where']) ?  ' WHERE ' . $hc['input']['where'] : '';
                                $dbc_req .= isset($hc['input']['order']) ? ' ORDER BY ' . $hc['input']['order'] : '';
                                $d_header_cb = $db->prepare($dbc_req);
                                if (isset($hc['input']['request_values'])) {
                                    $d_header_cb->execute(stringToSession($hc['input']['request_values']));
                                } else {
                                    $d_header_cb->execute();
                                }

                                $header_cb = $d_header_cb->fetchAll(PDO::FETCH_ASSOC);
                                if (is_countable($header_cb) && count($header_cb) > 0 && isset($hc['input']['text_on'])) {
                                    foreach ($header_cb as $c) {
                                        $content .= '<div class="checkbox_header" name="' . $hc['column_name'] . '" ';
                                        if (isset($hc['input']['title_on']) && isset($c[$hc['input']['title_on']]) && $c[$hc['input']['title_on']] != '') {
                                            $content .= 'title="' . htmlspecialchars($c[$hc['input']['title_on']], ENT_QUOTES) . '" ';
                                        }
                                        $content .= '>' . $c[$hc['input']['text_on']] . '</div>';
                                    }
                                }
                            } else {
                                $content .= '<div>';
                                $content .= $hc['label'];
                            }
                            if (isset($hc['header'])) {
                                $content .= '<div class="vertical" >';
                                // first complete_all element
                                if (isset($hc['header']['complete_all'])) {
                                    $header_input = $hc;
                                    if (isset($hc['header']['ca_attributes'])) {
                                        $header_input['input']['attributes'] = $hc['header']['ca_attributes'];
                                    }
                                    $content .= createInput($header_input, null, $selector['table_name'], 'complete_all_' . $hc['header']['complete_all'], 1, 1);
                                }
                                // then "total" elements
                                if (isset($hc['header']['total_from_column_name'])) {
                                    $content .= '<div class="horizontal">' . (isset($hc['header']['tfcn_title']) ? $hc['header']['tfcn_title'] : '') . '<totaliser totalfromcolumn column="' . $hc['header']['total_from_column_name'] . '" where="' . $hc['header']['tfcn_where']  . '" ></totaliser></div>';
                                }
                                if ($hc['input']['type'] != 'db_checkboxes') {
                                    $content .= '</div>';
                                }
                            }
                             if ($hc['input']['type'] != 'db_checkboxes') {
                                 $content .= '</div>' ;
                            }
                        }
                    }
                }
                if ($selector['can_edit'] === 1) {
                    $content .= '<headerrowhidder style="color:transparent;">Pour voir</headerrowhidder>';
                }
                $content .= '</headerrow>'; 
            }

            if ($display_content) {
                // stop parsing content if no content ;)
                if (!is_countable($rows) || count($rows) == 0 ) {
                    if ($selector['in_case_of_null'] != '' && $selector['in_case_of_null'] != null) {
                        $content .= $selector['in_case_of_null'];
                    } else {
                        $content .= "<h3>Aucune donnée n'a été trouvée pour cet onglet.</h3>";
                    }
                } else {
                    // if format exists and if rows exists => create formulars
                    if ($rows && $selector['display'] != null && $selector['display'] != '') {
                        foreach($rows as $row) {
                            if (!isset($row['id'])) {
                                $content .= 'csf-1265-'.json_encode($row);
                            } else {
                                $content .= defineRowContent($row, $generator_index, $selector, $edit, $edit_by_row, $add, $delete, $refresh);
                            }
                        }
                    }
                }
            }

            // close container based on "form" content
            if ($content_container) {
                if ($selector['form'] == 'table') {
                    $content .= '</div>';
                }
                $content .= '</contentcontainer>';
            }
        }
    } else {
        if ($selector['in_case_of_null'] != '' && $selector['in_case_of_null'] != null) {
            $content .= $selector['in_case_of_null'];
        } else {
            $content .= "<h3>Aucune donnée n'a été trouvée pour cet onglet.</h3>";
        }
    }    
        
    /* 
    if ($_SESSION['user']['id'] == 1)
    {
        echo 'ok-<p>Constructor Swap Functions => editing tools</p><pre>';
        echo 'edit: ' . $edit;
        echo 'edit_by_row: ' . $edit_by_row;
        echo 'add: ' . $add;
        echo 'delete: ' . $delete;
        echo 'refresh: ' . $refresh;
        // exit();
    }
    */

    if ($global_editing_tools) {
        // open global editing tools
        $content .= '<editingtools class="global">';

        // run (if existing) plugin "editing_tools"
        $plugins = getPluginsRepositories('editing_tools.php');
        if (is_countable($plugins)) {
            foreach($plugins as $p) {
                include '../plugins/' . $p . '/editing_tools.php';
            }
        }

        if ($edit && is_countable($rows) && count($rows) > 2) {
            $content .= '<save all not id="save_all" index="' . $generator_index . '" function="saveAll" ></save>';
        }
        /*
            echo '<pre>';
            var_dump($rows[0]);
            echo '</pre>';
        */
        // add "add" button
        if ($add) {
            $content .= '<add row function="addRow" id="add_row_' . $selector['table_name'] . '" index="' . $generator_index . '" ></add>';
        }

        // close global editing tools
        $content .= '</editingtools>';
    }

    // close container
    $content .= $selector['container_end'];

    // add js at the end of the content: not run by innerHTML (type), js/script.js runGeneratorScripts() runs it once displayed
    $js_include = isset($selector['js_include']) ? generatorIncludeContent($selector['js_include'], 'js') : '';
    if (trim($js_include) !== '') {
        $content .= '<script type="text/labo-generator" data-generator="' . $selector['id'] . '">';
        $content .= str_ireplace('</script', '<\/script', $js_include);
        $content .= '</script>';
    }

    // close general container
    $content .= '</wrapper>';

    return;
} 

function defineRowContent ($row, $generator_index, $selector, $edit, $edit_by_row, $add, $delete, $refresh) {
    global $to_be_highlighted, $highlight_check;
    /*
    echo 'ok-';
    var_dump($row);
    exit();
    */
    $id_row  = $row['id'];

    $format = json_decode($selector['display'], true);

    // manage "can_be_edited" system
    $tab_can_be_edited = false;
    if (isset($row['what_can_be_edited']) && $row['what_can_be_edited'] != null && $row['what_can_be_edited'] != '') {
        // echo $row['tab_can_be_edited'];
        $tab_can_be_edited = json_decode($row['what_can_be_edited'], true);
    }

    $row_content = '<row id="row_' . $id_row . '" ';
    $row_content .= $delete === 1 && $row['logical_delete'] === 1 ? 'logical_deleted ' : '';
    $row_content .= 'class="';
    /*
    echo 'ok-';
    var_dump ($to_be_highlighted);
    var_dump($row);
    var_dump($highlight_check);
    exit();
    */
    if ($to_be_highlighted && in_array($row[$highlight_check], $to_be_highlighted)) {
        $row_content .= 'highlighted ';
    }
    if ($selector['form'] == 'column') {
        $row_content .= 'column" ><div class="column">';
    } else if ($selector['form'] == 'row') {
        $row_content .= 'row" ><div class="row">';
    } else {
        $row_content .= '" >';
    }
    
    foreach($format as $format_key => $format_value) {
        if (isset($format_value['formatting'])) {
            $row_content .= addFormat($format_value['formatting'], $selector['form']);
        } else if (isset($format_value['group'])) {
            switch ($format_value['group']) {
                case 'start' :
                    $row_content .= '<div class="cells_groups';
                    if (isset($format_value['group_type'])) {
                        $row_content .= ' ' . $format_value['group_type'] . '" >';
                    }
                    break;
                case 'end' :
                    $row_content .= '</div>';
                    break;
            }
        } else {
            $input_value = $row[$format_value['column_name']];
            /*
            echo 'ok-' . $format_value['column_name'];
            var_dump($row);
            exit();
            */
            // maybe input use other elements from row
            if (isset($format_value['input']['request_values'])) {
                $new_f_v_t = [];
                $check_null_exists = false;
                foreach ($format_value['input']['request_values'] as $fv_k => $fv_v) {
                    if (strpos($fv_v, 'SESSION=>') !== false) {
                        $new_f_v_t[$fv_k] = $fv_v;
                    } else {
                        $new_f_v_t[$fv_k] = $row[$fv_v];
                    }
                }
                
                $format_value['input']['request_values'] = $new_f_v_t;
                if (
                    isset($format_value['input']['check_null_exists']) && 
                    (
                        $row[$format_value['input']['check_null_exists']] == ''  ||
                        $row[$format_value['input']['check_null_exists']] == null
                    ) &&
                    isset($format_value['input']['input_in_case_of_null'])) {
                    $format_value['input'] = $format_value['input']['input_in_case_of_null'];
                }
            }

            $can_be_edited = true;
            if ($tab_can_be_edited && !in_array($format_value['column_name'], $tab_can_be_edited)) {
                $can_be_edited = false;
            }

            $extra_condition = false;
            if (isset($format_value['container']) && isset($format_value['container']['class_on_condition']) && isset($format_value['container']['class_on_condition']['conditions_on'])) {
                $extra_condition = $row[$format_value['container']['class_on_condition']['conditions_on']];
            }

            $row_content .= createInput($format_value, $input_value, $selector['table_name'], $id_row, $edit, $can_be_edited, $extra_condition);
        }
    }

    if ($selector['form'] == 'column' || $selector['form'] == 'row') {
        $row_content .= '</div>';
    }

    // open editing tools
    $row_content .= '<editingtools>';
    if ($selector['form'] == 'table') {
        $row_content .= '<div class="one_line">';
    }

    if ($edit_by_row) {
        $row_content .= '<editrow id="edit_row_' . $id_row . '" row="' . $id_row . '" index="' . $generator_index . '" function="editRow" ></editrow>';
    }

    if ($refresh) {
        $row_content .= '<refresh id="refresh_' . $id_row . '" row="' . $id_row . '" index="' . $generator_index . '" function="refreshRow" ></refresh>';
    }

    if ($edit) {
        $row_content .= '<save not id="save_row_' . $id_row . '" row="' . $id_row . '" index="' . $generator_index . '" function="saveRow" ></save>';
    }
    if ($delete !== 0 && $row['can_not_be_deleted'] !== 1) {
        $row_content .= '<remove id="delete_row_' . $id_row . '" row="' . $id_row . '" index="' . $generator_index . '" function="deleteRow" ></remove>';
    }
    if ($delete === 1 && isset($row['logical_delete']) && $row['logical_delete'] === 1) {
        $row_content .= '<restore id="delete_row_' . $id_row . '" row="' . $id_row . '" index="' . $generator_index . '" function="restoreRow" ></restore>';
    }

    // close editing tools
    if ($selector['form'] == 'table') {
        $row_content .= '</div>';
    }
    $row_content .= '</editingtools>';

    $row_content .= '</row>';
    return $row_content;
}

function defineEmptyRow ($generator_index, $generator_content = false)
{
    global $content, $db, $display_mode;

    if (!$generator_content) {
        // first get content selector
        $d_selector = $db->prepare('SELECT * FROM generator WHERE id = :id OR text_id = :id');
        $d_selector->execute(array('id' => $generator_index));
        $selector = $d_selector->fetch(PDO::FETCH_ASSOC);
        $d_selector->closeCursor();

        // check hability
        if (labo_page_mode()) {
            // page_with_modules: rights of the user for this module (module_permissions)
            $selector = labo_apply_module_rights($selector);
        }
        if ($selector === null ||
            (!labo_page_mode() && !in_array($selector['user_type'], $_SESSION['display_roles'])) ||
            $selector['can_edit'] !== 1 ||
            $selector['can_add'] !== 1
        ) {
            exit('ko-not_authorised (defineEmptyRow - csfunc)');
        }

    } else {
        $selector = $generator_content;
        $generator_index = $selector['id'];
    }

    $format = false;
    if ($selector['display'] != null && $selector['display'] != '') {
        $format = json_decode($selector['display'], true);
        
        $display_mode = $selector['form'];

        $id_row  = 'new_row_' . intval(microtime(true) * 1000);
        $content .= '<row id="row_' . $id_row . '"';
        if ($selector['form'] == 'column') {
            $content .= ' class="column" ><div class="column">';
        } else if ($selector['form'] == 'row') {
            $content .= ' class="row" ><div class="row">';
        } else {
            $content .= '>';
        }
        
        foreach($format as $format_key => $format_value) {
             if (isset($format_value['formatting'])) {
                    $content .= addFormat($format_value['formatting'], $selector['form']);
                } else if (isset($format_value['group'])) {
                    switch ($format_value['group']) {
                        case 'start' :
                            $content .= '<div class="cells_groups';
                            if (isset($format_value['group_type'])) {
                                $content .= ' ' . $format_value['group_type'] . '" >';
                            }
                            break;
                        case 'end' :
                            $content .= '</div>';
                            break;
                    }
                } else {
                    $input_value = '';

                    // maybe input use other elements from row
                    if (isset($format_value['input']['request_values'])) {
                        $new_f_v_t = [];
                        $check_null_exists = false;
                        foreach ($format_value['input']['request_values'] as $fv_k => $fv_v) {
                            if (strpos($fv_v, 'SESSION=>') !== false) {
                                $new_f_v_t[$fv_k] = $fv_v;
                            } else {
                                $new_f_v_t[$fv_k] = null;
                            }
                        }
                        
                        $format_value['input']['request_values'] = $new_f_v_t;
                        if (
                            isset($format_value['input']['check_null_exists']) &&
                            isset($format_value['input']['input_in_case_of_null'])) {
                            $format_value['input'] = $format_value['input']['input_in_case_of_null'];
                        }
                    }

                    $extra_condition = false;
                    if (isset($format_value['container']) && isset($format_value['container']['class_on_condition']) && isset($format_value['container']['class_on_condition']['conditions_on'])) {
                        // no field created
                        if (isset($format_value['container']['class_on_condition']['class_for_new_row'])) {
                            $format_value['container']['class'] = $format_value['container']['class_on_condition']['class_for_new_row'];
                            unset($format_value['container']['class_on_condition']);
                        } else {
                            $extra_condition = null;
                        }
                    }

                    $content .= createInput($format_value, $input_value, $selector['table_name'], $id_row, true, true, $extra_condition);
                }
        }

        if ($selector['form'] == 'column' || $selector['form'] == 'row') {
            $content .= '</div>';
        }

        // open editing tools
        $content .= '<editingtools>';

        $content .= '<save not id="save_row_' . $id_row . '" row="' . $id_row . '" index="' . $generator_index . '" function="saveRow" ></save>';
        
        // close editing tools
        $content .= '</editingtools>';
             
        $content .= '</row>';
    }
}

function deleteRow ($table, $row) {
    global $db, $authorisation;

    // security checks are done before
    if (!$authorisation) {
        exit('ko-not_authorised (csf_deleteRow)');
    }    

    // check plugins
     // run (if existing) plugin "delete_row.php"
    $plugins = getPluginsRepositories('delete_row.php');
    if (is_countable($plugins)) {
        foreach($plugins as $p) {
            include '../plugins/' . $p . '/delete_row.php';
        }
    }

    switch ($authorisation['can_delete']) {
        case 1:
            // physical delete
            $delete = $db->prepare('DELETE FROM ' . $table . ' WHERE id = :id');
            if ($delete->execute(array('id' => $row))) {
                if (!isset($return_string)) {$return_string ='ok-row_deleted'; }
            } else {
                exit('ko-fail_deleting_in_db');
            }
            break;
        case 2:
            // logical delete
            $logical_delete = $db->prepare('UPDATE ' . $table . ' SET logical_delete = 1 WHERE id = :id');
            if ($logical_delete->execute(array('id' => $row))) {
                  if (!isset($return_string)) {$return_string ='ok-row_logical_deleted'; }
            } else {
                exit('ko-fail_logical_deleting_in_db');
            }
            break;
        default :
            exit ('ko-not_authorised (csf_deleteRow-default)');
    }
    return $return_string;
}

function restoreRow ($table, $row) {
    global $db, $authorisation;

    // security checks are done before
    if (!$authorisation) {
        exit('ko-not_authorised (csf_restoreRow)');
    }    

    // restore row is only on logical delete
    $logical_delete = $db->prepare('UPDATE ' . $table . ' SET logical_delete = 0 WHERE id = :id');
    if ($logical_delete->execute(array('id' => $row))) {
        exit('ok-row_logical_restored');
    } else {
        exit('ko-fail_logical_restoring_in_db');
    }
}