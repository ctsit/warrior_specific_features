# WARRIOR project specific features
This is a redcap modules which contains specific features for warrior project.

## Prerequisites
- REDCap >= 8.0.0 (for versions < 8.0.0, [REDCap Modules](https://github.com/vanderbilt/redcap-external-modules) is required).

## Installation
- Clone this repo into to `<redcap-root>/modules/warrior_specific_features_v1.0`.
- Go to **Control Center > Manage External Modules** and enable Linear Data Entry Workflow.
- For each project you want to use this module, go to the project home page, click on **Manage External Modules** link, and then enable Warrior Specific Features for that project.

## Features included

### Automatic subject ID
A new action tag is provided: `@SUBJECT-ID`, which automatically:
- Sets the target field as read only
- Sets a subject ID value to the target field in the following format: `<DAG_ID>-<GIVEN_NAME_INITIAL><SURNAME_INITIAL><RECORD_ID>`, e.g. for `2-101` as record ID, `John` as first name, and `Smith` as last name, the result will be `2-JS101`

#### Configuration
By default, Automatic subjet ID looks for `first_name` and `last_name` fields. If your source fields are named diferently, you may setup alternative sources. To do that, go to  **Manage External Modules** section of your project, click on WARRIOR Project Specific Feature's configure button, and fill fields under "Automatic subject ID" section.

#### Note that:
- If DAG ID does not exist, then the DAG prefix will be ignored - e.g. `JS101` instead of `2-JS101`
- Given name and surname values must be saved prior to subject ID field, i.e. these fields should be placed in instruments that will be filled before subject ID field - you can force users to a linear workflow by installing [Linear Data Entry Workflow module](https://github.com/ctsit/linear_data_entry_workflow)
