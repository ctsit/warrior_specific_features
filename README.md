# WARRIOR Project Specific Features
This is REDCap module provides specific features for the Warrior project.

## Prerequisites
- REDCap >= 8.0.3

## Installation
- Clone this repo into to `<redcap-root>/modules/warrior_specific_features_v<version_number>`.
- Go to **Control Center > Manage External Modules** and enable Warrior Specific Features.
- For each project you want to use this module, go to the project home page, click on **Manage External Modules** link, and then enable Warrior Specific Features for that project.

## Action tags included

### @SUBJECT-ID
This action tag automatically:
- Sets the target field as read only
- Sets a subject ID value to the target field in the following format: `<DAG_ID>-<GIVEN_NAME_INITIAL><SURNAME_INITIAL><RECORD_ID>`, e.g. for `2-101` as record ID, `John` as first name, and `Smith` as last name, the result will be `2-JS101`

#### Note
- If the DAG ID does not exist, then the DAG prefix will be ignored - e.g. the subject ID would be `JS101` instead of `2-JS101`
- Given name and surname values must be saved prior to subject ID field, i.e. these fields should be placed in instruments that will be filled before subject ID field. You can force users to a linear workflow by installing [Linear Data Entry Workflow module](https://github.com/ctsit/linear_data_entry_workflow)

#### Configuration
By default, Automatic Subject ID looks for `first_name` and `last_name` fields. If your source fields are named differently, you may setup alternative sources. To do that, go to  **Manage External Modules** section of your project, click on WARRIOR Project Specific Feature's configure button, and fill fields under "Automatic subject ID" section.

### @DEFAULT-DAG
This action tag sets the current DAG ID (aka group ID) as the field's default value.
