# WARRIOR Project Specific Features
This is REDCap module provides specific features for the Warrior project.

## Prerequisites
- REDCap >= 8.0.0 (for versions < 8.0.0, [REDCap Modules](https://github.com/vanderbilt/redcap-external-modules) is required).

## Installation
- Clone this repo into to `<redcap-root>/modules/warrior_specific_features_v<version_number>`.
- Go to **Control Center > Manage External Modules** and enable Linear Data Entry Workflow.
- For each project you want to use this module, go to the project home page, click on **Manage External Modules** link, and then enable Warrior Specific Features for that project.

## Features included

### @SUBJECT-ID action tag
A new action tag is provided: `@SUBJECT-ID`, which automatically:
- Sets the target field as read only
- Sets a subject ID value to the target field in the following format: `<DAG_ID>-<GIVEN_NAME_INITIAL><SURNAME_INITIAL><RECORD_ID>`, e.g. for `2-101` as record ID, `John` as first name, and `Smith` as last name, the result will be `2-JS101`

#### Configuration
By default, Automatic Subject ID looks for `first_name` and `last_name` fields. If your source fields are named differently, you may setup alternative sources. To do that, go to  **Manage External Modules** section of your project, click on WARRIOR Project Specific Feature's configure button, and fill fields under "Automatic subject ID" section.

#### Note
- If the DAG ID does not exist, then the DAG prefix will be ignored - e.g. the subject ID would be `JS101` instead of `2-JS101`
- Given name and surname values must be saved prior to subject ID field, i.e. these fields should be placed in instruments that will be filled before subject ID field. You can force users to a linear workflow by installing [Linear Data Entry Workflow module](https://github.com/ctsit/linear_data_entry_workflow)

### @DATE-MAX action Tag

A new action tag is provided: `@DATE-MAX`, which takes the maximum date from a bunch of date fields (across all events), and saves it into a field. How it works:

- The tagged field must be configured as a date in `Y-M-D` format.
- Once a field is tagged as `@DATE-MAX`, it will be hidden to end-users.
- A name prefix must be provided as argument in order to group the target fields. Example: `@DATE-MAX=date_example_` will extract the maximum from fields like `date_example_foo`, `date_example_bar`, etc.
- Every time a target field is updated, the maximum value is recalculated and saved again, no matter if the tagged field is in the current form.
- If the tagged field is present in multiple events, the maximum value is only stored in the first one. It means that the first event should be always referenced for Piping purposes (e.g. `[event_1_arm_1][max_date_example]`).
