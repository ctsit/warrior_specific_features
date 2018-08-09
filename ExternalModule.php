<?php
/**
 * @file
 * Provides ExternalModule class for Warrior Specific Features.
 */

namespace WarriorSpecificFeatures\ExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use Form;
use Project;
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
        foreach ($Proj->metadata as $field_name => &$info) {
            // Checking if field has the correct date format and contains
            // @DATE-MAX action tag.
            if ($info['element_validation_type'] == 'date_ymd' && ($prefix = Form::getValueInActionTag($info['misc'], '@DATE-MAX'))) {
                self::$maxDateFields[$field_name] = $prefix;

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

        // Looping over all fields containing @DATE-MAX.
        foreach (self::$maxDateFields as $field_name => $prefix) {
            // Avoiding the tagged field to be included on calculation.
            $date_fields = $Proj->metadata;
            unset($date_fields[$field_name]);

            // Looking for date fields that match the given prefix.
            if (!$date_fields = preg_grep('/^' . $prefix . '*/', array_keys($date_fields))) {
                continue;
            }

            $data = REDCap::getData($project_id, 'array', $record, $date_fields);

            $max_timestamp = 0;
            foreach ($data[$record] as $values) {
                foreach (array_filter($values) as $value) {
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
                        $this->saveFieldData($project_id, $event_id, $record, $field_name, $max_date);
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

    /**
     * Creates or updates a data entry field value.
     *
     * @param int $project_id
     *   Data entry project ID.
     * @param int $event_id
     *   Data entry event ID.
     * @param int $record
     *   Data entry record ID.
     * @param string $field_name
     *   Machine name of the field to be updated.
     * @param mixed $value
     *   The value to be saved.
     * @param int $instance
     *   (optional) Data entry instance ID (for repeat instrument cases).
     *
     * @return bool
     *   TRUE if success, FALSE otherwise.
     */
    protected function saveFieldData($project_id, $event_id, $record, $field_name, $value, $instance = null) {
        try {
            $proj = new Project($project_id);
        }
        catch (Exception $e) {
            return false;
        }

        // Initial checks to make sure we are creating/editing a real thing.
        if (
            !isset($proj->eventInfo[$event_id]) || !isset($proj->metadata[$field_name]) ||
            !in_array($proj->metadata[$field_name]['form_name'], $proj->eventsForms[$event_id]) ||
            !Records::recordExists($project_id, $record, $proj->eventInfo[$event_id]['arm_num'])
        ) {
            return false;
        }

        $project_id = intval($project_id);
        $event_id = intval($event_id);
        $record = db_escape($record);
        $field_name = db_escape($field_name);
        $value = db_escape($value);
        $instance = intval($instance);

        $sql = 'SELECT 1 FROM redcap_data
                WHERE project_id = "' . $project_id . '" AND
                      event_id = "' . $event_id . '" AND
                      record = "' . $record . '" AND
                      field_name = "' . $field_name . '" AND
                      instance ' . ($instance ? '= "' . $instance . '"' : 'IS NULL');

        if (!$q = $this->query($sql)) {
            return false;
        }

        if (db_num_rows($q)) {
            $sql = 'UPDATE redcap_data SET value = "' . $value . '"
                    WHERE project_id = "' . $project_id . '" AND
                          event_id = "' . $event_id . '" AND
                          record = "' . $record . '" AND
                          field_name = "' . $field_name . '" AND
                          instance ' . ($instance ? '= "' . $instance . '"' : 'IS NULL');
        }
        else {
            $instance = $instance ? '"' . $instance . '"' : 'NULL';
            $sql = 'INSERT INTO redcap_data (project_id, event_id, record, field_name, value, instance)
                    VALUES ("' . $project_id . '", "' . $event_id . '", "' . $record . '", "' . $field_name . '", "' . $value . '", ' . $instance . ')';
        }

        if (!$q = $this->query($sql)) {
            return false;
        }

        return true;
    }
}
