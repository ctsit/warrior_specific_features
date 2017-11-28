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

    /**
     * @inheritdoc
     */
    function redcap_data_entry_form_top($project_id, string $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        global $Proj;

        $settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);

        $first_name_field = empty($settings['first_name']['value']) ? 'first_name' : $settings['first_name']['value'][0];
        $last_name_field = empty($settings['last_name']['value']) ? 'last_name' : $settings['last_name']['value'][0];

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

        $Proj->metadata[$target_field]['misc'] .= ' @READONLY';

        if (Records::formHasData($record, $instrument, $event_id, $repeat_instance)) {
            return;
        }

        // check if the required fields are present in the project or not.
        $req_fields = array($first_name_field, $last_name_field);
        foreach ($req_fields as $req_field) {
            if (!isset($Proj->metadata[$req_field])) {
                return;
            }
        }

        // get data from redcap if data is empty then return .
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
        $Proj->metadata[$target_field]['misc'] .= ' @DEFAULT="' . $res . '"';
    }
}
