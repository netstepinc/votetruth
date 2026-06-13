# Legiscan Import Process

## V2 Workflow

1. Main page shows dataset list
2. "Add Session" syncs/creates session
3. "View Data Set" expands and shows subtask buttons
4. Selecting a subtask calls getLegiscan(['op' => 'getDataset', ...]) which:
	Fetches from API (or cache)
	Unzips the dataset
	Returns file tree via dir_tree()
5. Partial files use that file tree to display data

## V3 Workflow

1. Main page: Show dataset list
2. Dataset List Button: Fetch Session Data
	Fetches from API (or cache)
	Unzips the dataset
	Returns file tree via dir_tree()
3. If data is fetched and extracted
	Dataset List Button: Add Session
4. If session exists: 
	Dataset List Button: View Data
		Show subtasks





## NOTES



## SQL ADJUSTMENTS

UPDATE `jbsfi_sessions` SET `legiscan_id` = '77' WHERE `jbsfi_sessions`.`id` = 6;
UPDATE `jbsfi_sessions` SET `legiscan_id` = '84' WHERE `jbsfi_sessions`.`id` = 7;
UPDATE `jbsfi_sessions` SET `legiscan_id` = '1026' WHERE `jbsfi_sessions`.`id` = 8;
UPDATE `jbsfi_sessions` SET `legiscan_id` = '1156' WHERE `jbsfi_sessions`.`id` = 9;
UPDATE `jbsfi_sessions` SET `legiscan_id` = '1435' WHERE `jbsfi_sessions`.`id` = 10;
UPDATE `jbsfi_sessions` SET `legiscan_id` = '1658' WHERE `jbsfi_sessions`.`id` = 11; 
UPDATE `jbsfi_sessions` SET `legiscan_id` = '1823' WHERE `jbsfi_sessions`.`id` = 12; 
UPDATE `jbsfi_sessions` SET `legiscan_id` = '2041' WHERE `jbsfi_sessions`.`id` = 13;
UPDATE `jbsfi_sessions` SET `legiscan_id` = '2199' WHERE `jbsfi_sessions`.`id` = 14;
