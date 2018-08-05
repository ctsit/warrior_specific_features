<?php
/**
 * @file
 * Provides ExternalModule class for Warrior Specific Features.
 */

namespace WarriorSpecificFeatures\ExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Records;
use REDCap;

/**
 * ExternalModule class for Warrior Specific Features.
 */
class ExternalModule extends AbstractExternalModule {

    static protected $maxDateFields = [];

    /**
     * @inheritdoc
     */
    function redcap_every_page_before_render($project_id) {
        if (!$project_id) {
            return;
        }

        global $Proj;

        $form = $_GET['page'];
        foreach ($Proj->metadata as &$info) {
            // Checking if field has the correct date format and contains
            // @MAX-DATE action tag.
            if ($info['element_validation_type'] == 'date_ymd' && ($prefix = Form::getValueInActionTag($info['misc'], '@MAX-DATE'))) {
                self::$maxDateFields[$info['field_name']] = $prefix;

                // Hiding the field from the end-users.
                $info['misc'] .= ' @HIDDEN';
            }
        }
    }

    /**
     * @inheritdoc
     */
    function redcap_save_record($project_id, $record = null, $instrument, $event_id, $group_id = null, $survey_hash = null, $response_id = null, $repeat_instance = 1) {
        if (empty(self::$maxDateFields)) {
            return;
        }

        global $Proj;

        // Looping over all fields containing @MAX-DATE.
        foreach (self::$maxDateFields as $field_name => $prefix) {
            // Looking for date fields that match the given prefix.
            if (!$date_fields = preg_grep('/^' . $prefix . '*/', array_keys($Proj->metadata))) {
                continue;
            }

            $data = REDCap::getData($project_id, 'array', $record, $date_fields);

            $max = 0;
            foreach ($data[$record] as $values) {
                foreach ($values as $value) {
                    $timestamp = strtotime($value);

                    if ($timestamp !== false && $timestamp > $max_timestamp) {
                        // Storing max date.
                        $max_timestamp = $timestamp;
                        $max_date = $value;
                    }
                }
            }

            if ($max_timestamp) {
                foreach ($Proj->eventsForms as $event_id => $forms) {
                    if (in_array($Proj->metadata[$field_name]['form_name'], $forms)) {
                        // Saving the max date into the action tagged field
                        // only for the first event it appears.
                        REDCap::saveData($project_id, 'array', [$record => [$event_id => [$field_name => $max_date]]]);
                        break;
                    }
                }
            }
        }
    }

    /**
     * @inheritdoc
     */
    function redcap_data_entry_form_top($project_id, string $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        global $Proj;

        // Read our settings from the project configuration.
        $settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);
        $first_name_field = empty($settings['first_name']['value']) ? 'first_name' : $settings['first_name']['value'][0];
        $last_name_field = empty($settings['last_name']['value']) ? 'last_name' : $settings['last_name']['value'][0];

        // determine if any field on this form references the action tag that triggers subject-id-setting behavior.
        $action_tag = '@SUBJECT-ID';
        foreach (array_keys($Proj->forms[$instrument]['fields']) as $field_name) {
            // check if action is present or not
            if (strpos($Proj->metadata[$field_name]['misc'] . ' ', $action_tag . ' ') !== false) {
                $target_field = $field_name;
                break;
            }
        }

        if (!isset($target_field)) {
            return;
        }

        // don't allow any such field to be writeable
        $Proj->metadata[$target_field]['misc'] .= ' @READONLY';

        // don't modify the field if this form already has saved data
        if (Records::formHasData($record, $instrument, $event_id, $repeat_instance)) {
            return;
        }

        // check if the required input fields are present in the project or not.
        $req_fields = [$first_name_field, $last_name_field];
        foreach ($req_fields as $req_field) {
            if (!isset($Proj->metadata[$req_field])) {
                return;
            }
        }

        // get data from redcap.  if data is empty then return.
        $data = REDCap::getData($Proj->project['project_id'], 'array', $record, $req_fields);
        if (empty($data)) {
            return;
        }

        $data = $data[$record][$event_id];
        $first_name = $data[$first_name_field];
        $last_name  = $data[$last_name_field];

        if (empty($first_name) || empty($last_name)) {
            return;
        }

        // format the subject_id with the given format.
        $res = '';

        $rec_arr = explode('-', $record);
        if (count($rec_arr) == 2) {
            $res .= $rec_arr[0] . '-';
            $s_record_id = $rec_arr[1];
        }
        else {
            $s_record_id = $rec_arr[0];
        }

        $res .= strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) . $s_record_id;

        // Use the @DEFAULT action tag to set the value we generated.
        $Proj->metadata[$target_field]['misc'] .= ' @DEFAULT="' . $res . '"';
    }
}
