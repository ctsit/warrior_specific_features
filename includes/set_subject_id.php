<?php
/**
 * @file
 * Sets subject ID as [DAG]-[FIRST_NAME_INITIAL][LAST_NAME_INITIAL][RECORD_ID].
 */

/**
 * Handles @SUBJECT-ID action tag.
 */
function warrior_specific_features_set_subject_id($record_id, $instrument, $event_id, $instance, $first_name_field, $last_name_field) {
    if (!$field_name = warrior_specific_features_is_action_tag_present($Proj, '@SUBJECT-ID', $instrument)) {
        return;
    }

    global $Proj;
    $Proj->metadata[$field_name]['misc'] .= ' @READONLY';

    if (Records::formHasData($record_id, $instrument, $event_id, $instance)) {
        return;
    }

    // collect the required fields.
    $req_fields = array($first_name_field, $last_name_field);

    // check if the required fields are present in the project or not.
    $metadata = $Proj->metadata;
    if (!warrior_specific_features_fields_exists($req_fields, $metadata)) {
        return;
    }

    // get data from redcap if data is empty then return .
    $data = REDCap::getData($Proj->project['project_id'], 'array', $record_id);
    if (empty($data)) {
        return;
    }

    $data = $data[$record_id][$event_id];
    $first_name = $data[$first_name_field];
    $last_name  = $data[$last_name_field];

    // format the subject_id with the given format.
    if (!$result = warrior_specific_features_format_subject_id($record_id, $first_name, $last_name)) {
        return;
    }

    $Proj->metadata[$field_name]['misc'] .= ' @DEFAULT="' . $result . '"';
}

/**
 * This function format the subject id to the following format.
 * dag_id + "-" + first_name_initial + last_name_initial + record_id
 */
function warrior_specific_features_format_subject_id($record_id, $first_name, $last_name) {
    $res = '';
    if (!$first_name || !$last_name) {
        return $res;
    }

    $rec_arr = explode('-', $record_id);
    if (count($rec_arr) == 2) {
        $res .= $rec_arr[0] . '-';
        $s_record_id = $rec_arr[1];
    }
    else {
        $s_record_id = $rec_arr[0];
    }

    $res .= strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) . $s_record_id;
    return $res;
}

/*
 * This method extract record_id, given_name, surname and field_name and returns an array
 * containing those fields as keys and the redcap fields that are referring them as values.
 * If @SET-SUBJECT-ID action tag is not present for any of the field then it returns false.
 */
function warrior_specific_features_is_action_tag_present($Proj, $action_tag, $instrument) {
    global $Proj;

    foreach (array_keys($Proj->forms[$instrument]['fields']) as $field_name) {
        // check if action is present or not
        if (strpos($Proj->metadata[$field_name]['misc'] . ' ', $action_tag . ' ') !== false) {
            return $field_name;
        }
    }

    return false;
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
        if (!$flag) {
            return false;
        }
    }
    return true;
}
