## 2.4.1
- The "Add new record" button no longer displays if the user does not have "Create Record" user rights
- The Record Home button location in the display table can now be configured (None|First Column|**Last Column**)
- Updated Smarty to v5.3.0
- Updated Module Framework to v12
## 2.4.0
- SQL query updated to proactively support new `data_table_X` implementation
- Refactored the primary method that does the SQL search and filtering
- Minor code cleanup
## 2.3.7 (`2023-03-27`)
- Bump Smarty from 3.1.47 to 3.1.48
- Fixed bug where the "New Record" button did not work properly in a mobile portrait layout
- Fixed bug in module config that would warn of a missing required field when a checkbox was unchecked
## 2.3.6 (`2022-10-17`)
- Bump Smarty from 3.1.43 to 3.1.47
- Fixed PHP 8 issue where a display field exists on a repeating instrument that has no instances
## 2.3.5 (`2022-02-18`)
- Bump Smarty from 3.1.39 to 3.1.43
- Minor refactor on some defined constants to prevent cross-module conflicts
- Bumped EM framework from v1 to v6
   - As a result, minimum REDCap version to 10.4.1
   - Additionally, legacy bootstrap support (v3) was also able to be removed
## 2.3.4 (`2021-07-30`)
- Fixed a bug where searches using checkbox fields would return too many results
- Minor update for some PHP 8 support
## 2.3.3 (`2020-06-12`)
- Added support for multi-line rich-text-enabled field labels
## 2.3.2 (`2020-06-09`)
- Updated use of `getAutoId` based on the version of REDCap
  - From `v9.8.0` this function was moved to the `DataEntry` class
- Field Labels and Search Options will now be truncated if they exceed 60 characters
  - This is to avoid visual issues with dropdowns and table column headers
## 2.3.1 (`2020-04-01`)
- Removed usage of a javascript function that was removed in REDCap v9.8.0
  - This function was only a placeholder so no functional differences have occurred with this change.
## 2.3.0 (`2020-03-24`)
- Display field sorting
## 2.2.0 (`2019-12-5`)
- Support for Dynamic SQL fields has been improved
## 2.1.0 (`2019-11-18`)
- Added a project configuration option to disable empty searches
## 2.0.2 (`2019-11-1`)
- Fixed a bug where trying to search would instead send you to the first 'Advanced' Custom Application Link
## 2.0.1 (`2019-08-06`)
- Fixed a bug where searching '0' values would not return the proper results
  - This was primarily an issue with yesno and form status fields
- Fixed a bug where the "Add/Edit" override did not work properly for users that did not have "Create Record" rights
- Fixed a bug where instance badges were not displaying properly
- Added default options to search fields that used a dropdown.
  - Previously, if your search options were exclusively structured fields, you would not have the ability to do a 'blank' search to return all records
- Additional code cleanup and refactoring
## 2.0.0
- Added support for Bootstrap 4 in REDCap v8.7.0+
- Adjusted the display of instance badges
  - They are now pushed over to the right, to not interfere with the content
- Fixed an issue that would cause the module to error if a certain config setting was not set
  - A default value is now used if instance_search is not specified or the field being searched is not on a repeating instrument
- Additional improvements to error handling
- Miscellaneous fixes
## 1.2.3
- Added a workaround for a issue in earlier versions of REDCap that would allow hook functions of the module to execute outside of project context
- Fixed a bug where searching against a checkbox field would not return a result when more than one value was checked
- Overrides are now in place for field types of 'radio', 'select', and 'checkbox' to prevent searches from returning unintended results
- Updated the UI for better responsive support on smaller sized browsers 
## 1.2.2
- All users will see the link and have access to the module, instead of just those with design rights
## 1.2.1
- Adjusted the way the search page url is obtained to support older versions of REDCap
## 1.2.0
- Switched add/edit redirect with an href replace on every page top
- Added some html styling to config.json
- Added stylesheet for tooltip styling
- Added javascript file for tooltip and add/edit link modification
- Checkbox values now display properly in the search results
- User will be alerted if they navigate to the search page before it has been configured
- Altered the support/display of the instance badges, so they render decently for the new value types (i.e. checkbox lists)
- Fixed bug that disabled column sorting
## 1.1.0
- New configuration option that allows you to redirect from Add/Edit Records, to the module search page
## 1.0.3
- Fixed a bug where "Add new record" would not function correctly when the user was in a DAG
## 1.0.2
- Additional removal of PHP 7 syntax
## 1.0.1
- Modified a null check to support PHP versions lower than 7.0
- Removed legacy timer code
## 1.0.0
- Initial release
