## 1.2.3
- Added a workaround for an issue in earlier versions of REDCap (i.e. 8.2.2) that would allow hook functions of the module to execute outside of project context
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