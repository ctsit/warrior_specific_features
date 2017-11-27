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
    function hook_every_page_top($project_id) {
        include_once 'includes/set_subject_id.php';
        $project_settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);
        $first_name_field = $project_settings['first_name']['value'];
        $surname_field = $project_settings['surname']['value'];
        warrior_specific_features_set_subject_id($project_id, $first_name_field, $surname_field);
    }
}
