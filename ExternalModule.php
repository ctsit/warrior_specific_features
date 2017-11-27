<?php
/**
 * @file
 * Provides ExternalModule class for Warrior Specific Features.
 */

namespace WarriorSpecificFeatures\ExternalModule;

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

/**
 * ExternalModule class for Warrior Specific Features.
 */
class ExternalModule extends AbstractExternalModule {

    /**
     * @inheritdoc
     */
    function redcap_data_entry_form_top($project_id, string $record = NULL, $instrument, $event_id, $group_id = NULL, $repeat_instance = 1) {
        include_once 'includes/set_subject_id.php';
        $settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);
        $first_name_field = empty($settings['first_name']['value']) ? 'first_name' : $settings['first_name']['value'][0];
        $last_name_field = empty($settings['last_name']['value']) ? 'last_name' : $settings['last_name']['value'][0];
        warrior_specific_features_set_subject_id($record, $instrument, $event_id, $repeat_instance, $first_name_field, $last_name_field);
    }
}
