# Warrior project specific features
This is a redcap modules which contains specific features for warrior project.

Prerequisites

## Prerequisites
- [REDCap Modules](https://github.com/vanderbilt/redcap-external-modules)


## Installation
- Clone this repo into to `<redcap-root>/modules/warrior_specific_features_v1.0.0`.
- Go to **Control Center > Manage External Modules** and enable Linear Data Entry Workflow.
- For each project you want to use this module, go to the project home page, click on **Manage External Modules** link, and then enable Warrior Specific Features for that project.

## Features included

### Set subject id:
- This feature sets subject_id field with following format <DAG_ID> . "_" . <GIVEN_NAME_INITIAL> . <SURNAME_INITIAL> . <RECORD_ID>. 
- If it does not have DAG_ID then subject_id is saved as <GIVEN_NAME_INITIAL> . <SURNAME_INITIAL> . <RECORD_ID>.
- GIVEN_NAME and SURNAME should be saved in the previous forms of the same event. 
- Then this hook pulls those data and concatenate in the format that is mentioned above and saves it as a subject_id field (or another name specified in the action tags).

## How to use?
Add action tags @SET-SUBJECT-ID and @READONLY for the field whose value needs to be saved with the above format.

@READONLY action tag make sure that the field is not editable.
@SET-SUBJECT-ID defaults the subject_id value to be in the format given above. 

Example:
@SET-SUBJECT-ID="record_id=record_id,given_name=first_name, surname=last_name"
If the above action tag is given then the redcap fetches the data from record_id, first_name and last_name fields and saves the field with value in the above mentioned format.
If record_id = 001_101, first_name = "Bruce", last_name = "Wayne", then subject_id will be "001_BW101".
