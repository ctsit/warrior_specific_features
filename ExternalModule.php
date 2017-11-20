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
        
        warrior_specific_features_set_subject_id($project_id);
    }
}
