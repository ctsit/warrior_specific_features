<?php
/**
 * @file
 * Sets subject_id as DAG-[FIRST_NAME_INITIAL][LAST_NAME_INITIAL]RECORD_ID.
 */

/**
 * Handles @DEFAULT-SUBJECT-ID action tag.
 */
function warrior_specific_features_default_subject_id($project_id) {
    global $Proj;

    // Checking if we are in a data entry or survey page.
    if (!in_array(PAGE, array('DataEntry/index.php', 'surveys/index.php', 'Surveys/theme_view.php'))) {
        return;
    }

    if (empty($_GET['id'])) {
        return;
    }

    // Checking additional conditions for survey pages.
    if (PAGE == 'surveys/index.php' && !(isset($_GET['s']) && defined('NOAUTH'))) {
        return;
    }

    if (warrior_specific_features_form_has_data()) {
        return;
    }

    $action_tag = '@DEFAULT-SUBJECT-ID';

    if (!warrior_specific_features_is_action_tag_present($Proj, $action_tag)) {
        return;
    }

    $metadata = $Proj->metadata;
    $req_fields = array();
    $req_fields[] = 'first_name';
    $req_fields[] = 'last_name';

    if (!warrior_specific_features_fields_exists($req_fields, $metadata)) {
        return;
    }

    $data = REDCap::getData($Proj->project['project_id'], 'array', $_GET['id']);

    if (empty($data)) {
        return;
    }

    $event_id = $_GET['event_id'];
    $record_id = $_GET['id'];
    $data = $data[$record_id][$event_id];
    $firstname = $data['first_name'];
    $lastname = $data['last_name'];

    $result = warrior_specific_features_format_subject_id($record_id, $firstname, $lastname);
    if (!$result) {
        return;
    }

    // $Proj->metadata['subject_id']['misc'] .= ' @DEFAULT="' . $result . '"';
    warrior_specific_features_save_record_field($project_id, $event_id, $record_id, 'subject_id', $result);
    
}

/*
* This function inserts or updated into redcap_data table.
* If successful it returns true.
*/
function warrior_specific_features_save_record_field($project_id, $event_id, $record_id, $field_name, $value, $instance = null) {
    $readsql = "SELECT 1 from redcap_data where project_id = $project_id and event_id = $event_id and record = '".db_escape($record_id)."' and field_name = '".db_escape($field_name)."' " . ($instance == null ? "AND instance is null" : "AND instance = '".db_escape($instance)."'");
    $q = db_query($readsql);
    if (!$q) return false;
    $record_count = db_result($q, 0);
    if ($record_count == 0) {
        if (isSet($instance)) {
            $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance) " . "VALUES ($project_id, $event_id, '".db_escape($record_id)."', '".db_escape($field_name)."', '".db_escape($value)."' , $instance)";
        } else {
            $sql = "INSERT INTO redcap_data (project_id, event_id, record, field_name, value) " . "VALUES ($project_id, $event_id, '".db_escape($record_id)."', '".db_escape($field_name)."', '".db_escape($value)."')";
        }
        $q = db_query($sql);
        if (!$q) return false;
        return true;
    } else {
        $sql = "UPDATE redcap_data set value = '".db_escape($value)."' where project_id = $project_id and event_id = $event_id and record = '".db_escape($record_id)."' and field_name = '".db_escape($field_name)."' " . ($instance == null ? "AND instance is null" : "AND instance = '".db_escape($instance)."'");
        $q = db_query($sql);
        if (!$q) return false;
        return true;
    }
}

/*
* This function format the subject id to the following format.
* dag_id + "-" + first_name_initial + last_name_initial + record_id
*/
function warrior_specific_features_format_subject_id($record_id, $firstname, $lastname) {//, $padding_digits) {
    $rec_arr = explode("-", $record_id);
    $dag_id = "";
    $s_record_id = "";
    if (count($rec_arr) == 2) {
        $dag_id = $rec_arr[0];
        $s_record_id = $rec_arr[1];
    } else {
        $s_record_id = $rec_arr[0];
    }
    if (!($firstname != null && strlen($firstname) > 0 && $lastname != null && strlen($lastname) > 0)) {
        return false;
    }
    
    //do padding.
    // $padded_s_record_id = str_pad($s_record_id, $padding_digits, "0", STR_PAD_LEFT);
    $res = substr($firstname, 0, 1) . substr($lastname, 0, 1) . $s_record_id;
    if (count($rec_arr) == 2) {
        $res = $dag_id . '-' . $res;
    }
    return $res;
}

function warrior_specific_features_is_action_tag_present($Proj, $action_tag) {
    foreach (warrior_specific_features_get_fields_names() as $field_name) {
        $misc = $Proj->metadata[$field_name]['misc'];
        if (strpos($misc, $action_tag)) {
            return true;
        }

    }
    return false;
}

/**
 * Gets fields names for the current event.
 *
 * @return array
 *   An array of fields names.
 */
 function warrior_specific_features_get_fields_names() {
    global $Proj;
    $fields = empty($_GET['page']) ? $Proj->metadata : $Proj->forms[$_GET['page']]['fields'];
    return array_keys($fields);
}

/**
 * Checks if fields present in the array exists.
 *
 * @return bool
 *   TRUE if all the fields are present in the project, FALSE otherwise.
 */
function warrior_specific_features_fields_exists($fields, $metadata) {
    foreach ($fields as $val) {
        $flag = false;
        foreach ($metadata as $field) {
            if (strcmp($field, $val) == 0) {
                $flag = true;
                break;
            }
        }
        if (!($flag)) {
            return false;
        }
    }
    return true;
}

/**
 * Checks if the current form has data.
 *
 * @return bool
 *   TRUE if the current form contains data, FALSE otherwise.
 */
 function warrior_specific_features_form_has_data() {
    global $double_data_entry, $user_rights, $quesion_by_section, $pageFields;

    $record = $_GET['id'];
    if ($double_data_entry && $user_rights['double_data'] != 0) {
        $record = $record . '--' . $user_rights['double_data'];
    }

    if (PAGE != 'DataEntry/index.php' && $question_by_section && Records::fieldsHaveData($record, $pageFields[$_GET['__page__']], $_GET['event_id'])) {
        // The survey has data.
        return true;
    }

    if (Records::formHasData($record, $_GET['page'], $_GET['event_id'], $_GET['instance'])) {
        // The data entry has data.
        return true;
    }

    return false;
}

function pp($a) {
    echo "<pre>";
    echo print_r($a,1);
    echo "</pre>";
}


?>