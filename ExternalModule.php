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
use Randomization;
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
                $info['misc'] .= SUPER_USER || ACCOUNT_MANAGER ? ' @READONLY' : ' @HIDDEN';
            }
        }

        /**
         * Assuming Randomization and Subject ID fields belong to the same form,
         * the purpose of the following code is to prevent the following case:
         * 1. User accesses an empty randomization form, which contains a
         *    pre-set/default value for Subject ID field.
         * 2. User clicks on Randomization button, which saves data into that
         *    form.
         * 3. User leaves the form (instead of saving).
         * 4. User goes back to the form - which now has data, so default values
         *    do not apply anymore. Thus, Subject ID gets blank.
         */
        if (PAGE != 'Randomization/randomize_record.php' || empty($_POST['action']) || $_POST['action'] != 'randomize') {
            return;
        }

        $fields = Randomization::getRandomizationFields();

        if (empty($fields['target_field'])) {
            return;
        }

        // Checking if randomization field has a subject ID neighbor.
        if (!$subject_id_field = $this->instrumentHasSubjectIdTag($Proj->metadata[$fields['target_field']]['form_name'])) {
            return;
        }

        $record = $_POST['record'];
        $event_id = $_POST['event_id'];

        $data = REDCap::getData($project_id, 'array', $record, $subject_id_field);
        if (!empty($data) && !empty($data[$record][$event_id][$subject_id_field])) {
            // Preventing override of @SUBJECT-ID field.
            return;
        }

        // Getting subject ID value.
        if (!$subject_id = $this->buildSubjectId($record, $event_id)) {
            return;
        }

        // Saving subject ID before the user has the chance to leave the
        // form without saving.
        REDCap::saveData($project_id, 'array', [$record => [$event_id => [$subject_id_field => $subject_id]]]);
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
    function redcap_data_entry_form_top($project_id, $record = null, $instrument, $event_id, $group_id = null, $repeat_instance = 1) {
        if (!$target_field = $this->instrumentHasSubjectIdTag($instrument)) {
            return;
        }

        global $Proj;

        // don't allow any such field to be writeable
        $Proj->metadata[$target_field]['misc'] .= ' @READONLY';

        // don't modify the field if this form already has saved data
        if (Records::formHasData($record, $instrument, $event_id, $repeat_instance)) {
            return;
        }

        // Skip if subject ID cannot be calculated.
        if (!$subject_id = $this->buildSubjectId($record, $event_id)) {
            return;
        }

        // Use the @DEFAULT action tag to set the value we generated.
        $Proj->metadata[$target_field]['misc'] .= ' @DEFAULT="' . $subject_id . '"';
    }

    /**
     * Checks whether a given instrument has a @SUBJECT-ID field.
     *
     * @param string $instrument
     *   The instrument name.
     *
     * @return
     *   The @SUBJECT-ID field name if exists, FALSE otherwise.
     */
    function instrumentHasSubjectIdTag($instrument) {
        global $Proj;

        foreach (array_keys($Proj->forms[$instrument]['fields']) as $field_name) {
            // Check if action tag is present or not.
            if (strpos(' ' . $Proj->metadata[$field_name]['misc'] . ' ', ' @SUBJECT-ID ') !== false) {
                return $field_name;
            }
        }

        return false;
    }

    /**
     * Builds subject ID string.
     *
     * @param string $record
     *   The record ID.
     * @param int $event_id
     *   The event ID.
     *
     * @return string $subject_id
     *   The subject ID, formatted as follows:
     *   - If DAG exists: [Prefix][DAG]-[First & Last name initials][Record ID]
     *   - If DAG does not exist: [First & Last name initials][Record ID]
     *   Obs.:
     *   - Prefix can be configured via EM settings - defaults to "WAR".
     *   - [DAG] is zero-padded to 2 digits, and [Record ID] to 3 digits.
     *   Returns FALSE if subject ID cannot be calculated.
     */
    function buildSubjectId($record, $event_id) {
        global $Proj;

        // Read our settings from the project configuration.
        $settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $Proj->project['project_id']);
        $first_name_field = empty($settings['first_name']['value']) ? 'first_name' : $settings['first_name']['value'][0];
        $last_name_field = empty($settings['last_name']['value']) ? 'last_name' : $settings['last_name']['value'][0];

        // check if the required input fields are present in the project or not.
        $req_fields = [$first_name_field, $last_name_field];
        foreach ($req_fields as $req_field) {
            if (!isset($Proj->metadata[$req_field])) {
                return false;
            }
        }

        // get data from redcap.  if data is empty then return.
        $data = REDCap::getData($Proj->project['project_id'], 'array', $record, $req_fields);
        if (empty($data)) {
            return false;
        }

        $data = $data[$record][$event_id];
        $first_name = $data[$first_name_field];
        $last_name  = $data[$last_name_field];

        if (empty($first_name) || empty($last_name)) {
            return false;
        }

        $parts = explode('-', $record);

        // Format subject ID.
        $subject_id = '';
        $record_number = $parts[0];

        if (count($parts) == 2) {
            $subject_id = empty($settings['subject_id_prefix']['value']) ? 'WAR' : $settings['subject_id_prefix']['value'][0];
            $subject_id .= str_pad($parts[0], 2, '0', STR_PAD_LEFT) . '-';
            $record_number = $parts[1];
        }

        $subject_id .= strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1)) . str_pad($record_number, 3, '0', STR_PAD_LEFT);
        return $subject_id;
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
