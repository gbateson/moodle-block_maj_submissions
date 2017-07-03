<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * blocks/maj_submissions/lang/en_utf8/block_maj_submissions.php
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// essential strings
$string['pluginname'] = 'MAJ Submissions';
$string['blockdescription'] = 'This block facilitates administration of a conference submissions system based on a set of Database and Workshop activities.';
$string['blockname'] = 'MAJ Submissions';
$string['blocknameplural'] = 'MAJ Submissions';

// roles strings
$string['maj_submissions:addinstance'] = 'Add a new MAJ Submissions block';

// more strings
$string['addevents'] = 'Add a conference event';
$string['addfilterconditions'] = 'Add a filter condition';
$string['addreviewcmids'] = 'Add a review activity';
$string['addrevisecmids'] = 'Add a revision activity';
$string['autoincrementsettings_help'] = 'These settings define the starting values and output format strings for the auto-increment fields in the registration database.

When a new record is added to the registration database, each of the auto-increment fields in the new record is automatically assigned a value that is one higher than the highest value of that field in any other record in the database. Thus, each record will have a unique value for each of these settings.';
$string['autoincrementsettings'] = 'Auto-increment settings';
$string['badgenumber_help'] = 'The starting value for the auto-increment badge numbers.';
$string['badgenumber'] = 'Badge number';
$string['certificatedate_help'] = 'The date, as a text string, that appears on the participation certificates for this conference.';
$string['certificatedate'] = 'Certificate date';
$string['certificatenumber_help'] = 'The starting value for the auto-increment certificate numbers.';
$string['certificatenumber'] = 'Certificate number';
$string['collectpresentationscmid_help'] = 'The database activity that is used to collect presentation proposals.';
$string['collectpresentationscmid'] = 'Presentation proposals';
$string['collectpresentationstime_help'] = 'The dates and times at which the online collection of proposals for presentations starts and finshes.';
$string['collectpresentationstime'] = 'Call for presentation proposals';
$string['collectsponsoredscmid_help'] = 'The database activity that is used to collect proposals for sponsored presentations.';
$string['collectsponsoredscmid'] = 'Sponsored proposals';
$string['collectsponsoredstime_help'] = 'The dates and times at which the online collection of proposals for sponsored presentations starts and finshes.';
$string['collectsponsoredstime'] = 'Call for sponsored proposals';
$string['collectsubmissions'] = 'Submit proposals';
$string['collectworkshopscmid_help'] = 'The database activity that is used to collect proposals for workshops.';
$string['collectworkshopscmid'] = 'Workshop proposals';
$string['collectworkshopstime_help'] = 'The dates and times at which the online collection of proposals for workshops starts and finshes.';
$string['collectworkshopstime'] = 'Call for workshop proposals';
$string['conferencecmid_help'] = 'The page resource that displays information about the conference.';
$string['conferencecmid'] = 'Conference information';
$string['conferencedates_help'] = 'The conference start and end dates, as a text string, that appear on the documents and webpages for this conference.';
$string['conferencedates'] = 'Conference dates';
$string['conferenceevents'] = 'Conference events';
$string['conferencename_help'] = 'The conference name that appears on the documents and webpages for this conference.';
$string['conferencename'] = 'Conference name';
$string['conferencestrings_help'] = 'These strings and values are used in the databases, documents and webpages for registration, presentations, and workshops.';
$string['conferencestrings'] = 'Conference strings';
$string['conferencetime_help'] = 'The start and finish dates of the main conference.';
$string['conferencetime'] = 'Main conference';
$string['conferencetools'] = 'Conference tools';
$string['conferencevenue_help'] = 'The conference venue name that appears on the documents and webpages for this conference.';
$string['conferencevenue'] = 'Conference venue';
$string['countrecords'] = '({$a} so far)';
$string['coursesection_help'] = 'The course section in which to create a new activity or activities. If you select "Create new section" here, remember to give a name for the new section in the text box.';
$string['coursesection'] = 'Course section';
$string['createnewactivity'] = 'Create new activity';
$string['createnewdatabase'] = 'Create new database';
$string['createnewfield'] = 'Create new field';
$string['createnewsection'] = 'Create new section';
$string['currentstate_help'] = 'The current state of the submission process.';
$string['currentstate'] = 'Current state';
$string['customdatefmt_help'] = 'If you specify a date format here, it will be used in preference to any of the standard Moodle date formats.';
$string['customdatefmt'] = 'Custom date format string';
$string['databaseactivity_help'] = 'Either choose a specific database activity that you wish to setup, or choose "Create new activity", and specify the course section in which the new database should be created.';
$string['databaseactivity'] = 'Database activity';
$string['dateclosedon'] = 'closed on {$a}';
$string['datecloseson'] = 'closes on {$a}';
$string['dateformats'] = 'Date formats';
$string['dateopenclose'] = '{$a->open} - {$a->close}';
$string['dateopenedon'] = 'opened on {$a}';
$string['dateopenson'] = 'opens on {$a}';
$string['dinnerdate_help'] = 'The conference dinner date, as a text string, that appears on the documents and webpages for this conference.';
$string['dinnerdate'] = 'Dinner date';
$string['dinnername_help'] = 'The conference dinner name that appears on the documents and webpages for this conference.';
$string['dinnername'] = ' Dinner name';
$string['dinnerreceiptnumber_help'] = 'The starting value for the auto-increment dinner receipt numbers.';
$string['dinnerreceiptnumber'] = 'Dinner receipt number';
$string['dinnerticketnumber_help'] = 'The starting value for the auto-increment dinner ticket numbers.';
$string['dinnerticketnumber'] = 'Dinner ticket number';
$string['dinnertime_help'] = 'The conference dinner start and end times, as a string, that appear on the documents and webpages for this conference.';
$string['dinnertime'] = 'Dinner time';
$string['dinnervenue_help'] = 'The conference dinner venue name that appears on the documents and webpages for this conference.';
$string['dinnervenue'] = 'Dinner venue';
$string['displaydates_help'] = 'If this setting is enabled, any dates that are enabled on this settings page will be formatted and displayed in this MAJ Submissions block on the course page.

Otherwise, the dates will not be displayed on the course page. Ordinary users will not see this block at all, while course managers will see only the list of conference tools.';
$string['displaydates'] = 'Display dates';
$string['displaylangs_help'] = 'Enter the language codes, separated by commas, for languages you wish to use on this conference system.';
$string['displaylangs'] = 'Display languages';
$string['displaystats_help'] = 'If this setting is enabled, the number of submissions and registrations received so far will be displayed in this MAJ Submissions block on the course page.';
$string['displaystats'] = 'Display statistics';
$string['events_help'] = 'Enter the name of an event that can be added to the schedule.';
$string['events'] = 'Conference event [{no}]';
$string['eventscmid_help'] = 'Select the database of conference events, such as registration and food breaks, that can be added to the schedule.';
$string['eventscmid'] = 'Events database';
$string['exportcontent'] = 'Export content';
$string['exportsettings_help'] = 'This link allows you export the configuration settings for this block to a file that you can import into a similar block in another course.';
$string['exportsettings'] = 'Export settings';
$string['feereceiptnumber_help'] = 'The starting value for the auto-increment fee receipt numbers.';
$string['feereceiptnumber'] = 'Fee receipt number';
$string['filterconditions_help'] = 'This filter is used to decide which submissions from the source database should be transferred to the target workshop activity for vetting.';
$string['filterconditions'] = 'Filter condition [{no}]';
$string['fixdates_help'] = 'These settings control whether or not the leading zero on months, days and hours less than 10 are removed.';
$string['fixdates'] = 'Remove leading zeros';
$string['importantdates'] = 'Important dates';
$string['importcontent'] = 'Import content';
$string['importsettings_help'] = 'This link takes you to a screen where you can import configuration settings from a MAJ submissions block configuration settings file.

A settings file is created using the "Export settings" link on a MAJ submissions block configuration settings page.';
$string['importsettings'] = 'Import settings';
$string['isinlist'] = 'is in list';
$string['invalidblockname'] = 'Invalid block name in block instance record: id={$a->id}, blockname={$a->blockname}';
$string['invalidcontextid'] = 'Invalid parentcontextid in block instance record: id = {$a->id}, parentcontextid = {$a->parentcontextid}';
$string['invalidcourseid'] = 'Invalid instanceid in course context record: id={$a->id}, instanceid={$a->instanceid}';
$string['invalidimportfile'] = 'Import file was missing, empty or invalid';
$string['invalidinstanceid'] = 'Invalid block instance id: id = {$a}';
$string['manageevents_help'] = 'If this option is enabled, then calendar events for start and finish times will automatically be added, updated or removed when this block\'s settings are saved.';
$string['manageevents'] = 'Manage calendar events';
$string['missingdatabaseactivitynum'] = 'Please select a database';
$string['missingdatabaseactivityname'] = 'Please give a name for the new database';
$string['missingcoursesectionnum'] = 'Please select a section';
$string['missingcoursesectionname'] = 'Please give a name for the new course section';
$string['missingpreset'] = 'Please upload a preset file or select a preset from the list.';
$string['moodledatefmt_help'] = 'Start and finish dates will be formatted in a similar way to the date selected here.

If you click on the &quot;+&quot; sign next to one of the dates, the name of the date format string for that date will be displayed, along with its format codes. This is useful if you want to create your own date format string in the &quot;Custom date format string&quot; setting below.

Note that if the &quot;Show date last modified&quot; is set to &quot;No&quot; then no date will be displayed. Also, if a format is specified in the &quot;Custom date format string&quot; setting, then that will override the string selected here.';
$string['moodledatefmt'] = 'Moodle date format string';
$string['preset_help'] = 'A "preset" is a template for creating a Moodle database activity. It includes specifications of the database fields, and the layout of the webpages to edit and display records. However, it does not include any actual data.

You can either choose one of the presets that is already available on this Moodle site, or you can import a preset from a zip file. Preset zip files are created using the "export" tab within a Moodle database that already exists.';
$string['notendswith'] = 'does not end with';
$string['notisempty'] = 'is not empty';
$string['notisequalto'] = 'is not equal to';
$string['notisinlist'] = 'is not in list';
$string['notstartswith'] = 'does not start with';
$string['preset'] = 'Database preset';
$string['presetfile_help'] = 'Upload the zip file of a preset that has been exported from a Moodle database activity.';
$string['presetfile'] = 'Preset zip file';
$string['presetfolder_help'] = 'Select one of the database presets that is available on this Moodle site.';
$string['presetfolder'] = 'Preset';
$string['presetnameevents'] = 'MAJ events database';
$string['presetnamepresentations'] = 'MAJ presentations database';
$string['presetnameregistrations'] = 'MAJ registrations database';
$string['presetnameworkshops'] = 'MAJ workshops database';
$string['publishcmid_help'] = 'The resource or database activity where accepted submissions are published online.';
$string['publishcmid'] = 'Schedule activity';
$string['publishsubmissions'] = 'Publish schedule';
$string['publishtime_help'] = 'The start and finish dates of the period during which the conference schedule is available online to the public.';
$string['publishtime'] = 'Publication of schedule';
$string['receptioncmid_help'] = 'The page resource that displays information about the conference reception.';
$string['receptioncmid'] = 'Reception information';
$string['receptiontime_help'] = 'The start and finish times of the conference reception.';
$string['receptiontime'] = 'Conference reception';
$string['registerdelegatescmid_help'] = 'The database activity where delegates register their intention to attend and participate in the conference.';
$string['registerdelegatescmid'] = 'Delegate registrations';
$string['registerdelegatestime_help'] = 'The dates and times at which the online registration for delegates starts and finshes.';
$string['registerdelegatestime'] = 'Registration for delegates';
$string['registerparticipation'] = 'Register participation';
$string['registerpresenterscmid_help'] = 'The database activity where presenters register their intention to attend and participate in the conference.';
$string['registerpresenterscmid'] = 'Presenter registrations';
$string['registerpresenterssectionnum_help'] = 'The course section in which to create the new registration database.';
$string['registerpresenterssectionnum'] = 'Registration section';
$string['registerpresenterstime_help'] = 'The dates and times at which the online registration for presenters starts and finshes.';
$string['registerpresenterstime'] = 'Registration for presenters';
$string['removeyear_help'] = 'If this option is enabled, then the current year will be removed from dates.';
$string['removeyear'] = 'Remove current year';
$string['resetworkshop_help']= 'This setting specifies whether or not to reset the target workshop before transferring submissions from the source database.

**No**
: Data that is currently in the workshop will be left untouched and new data from the source database will be added to it.

**Yes**
: All data will be removed from the target workshop before submissions are transferred from the source database. In addition, any links from other databases to the workshop will be removed.';
$string['resetworkshop']= 'Reset workshop';
$string['reviewcmids_help'] = 'A workshop activity that is used to peer review submissions.';
$string['reviewcmids'] = 'Review activity [{no}]';
$string['reviewsectionnum_help'] = 'The course section where the activities for reviewing submissions are located.';
$string['reviewsectionnum'] = 'Review section';
$string['reviewsubmissions'] = 'Review submissions';
$string['reviewtime_help'] = 'The start and finish dates of the submission selection period.';
$string['reviewtime'] = 'Selection of submissions';
$string['revisecmids_help'] = 'A database activity that is used to revise submissions after they have been peer reviewed. Usually this is the same database that was used to collect submissions initially.';
$string['revisecmids'] = 'Revision activity [{no}]';
$string['revisesectionnum_help'] = 'The course section where the activities for revising submissions are located.';
$string['revisesectionnum'] = 'Revision section';
$string['revisesubmissions'] = 'Revise submissions';
$string['revisetime_help'] = 'The start and finish dates of the period during which accepted submissions may be edited.';
$string['revisetime'] = 'Final editing of submissions';
$string['schedule_audience_help'] = 'The intended audience for the selected presentation.';
$string['schedule_audience'] = 'Schedule audience';
$string['schedule_day_help'] = 'The day on which the selected presentation will take place.';
$string['schedule_day'] = 'Schedule day';
$string['schedule_duration_help'] = 'The duration of the selected presentation.';
$string['schedule_duration'] = 'Schedule duration';
$string['schedule_event_help'] = 'Select an event, workshop or presentation that you wish to appear on the conference schedule.';
$string['schedule_event'] = 'Schedule event';
$string['schedule_room_help'] = 'The room in which the selected presentation will take place.';
$string['schedule_room'] = 'Schedule room';
$string['schedule_time_help'] = 'The time at which the selected presentation will take place.';
$string['schedule_time'] = 'Schedule time';
$string['shortentimes_help'] = 'If this setting is enabled, then times will not be shown if the start time is 00:00 and the end time is 23:55';
$string['shortentimes'] = 'Shorten time stamps';
$string['sourcedatabase_help'] = 'Select the database which contains the submissions to be vetted.';
$string['sourcedatabase'] = 'Source database';
$string['state'] = 'State';
$string['targetworkshop_help'] = 'Select the workshop to which the submissions matching the above conditions are to be copied for vetting.';
$string['targetworkshop'] = 'Target workshop';
$string['timefinish'] = 'Finish';
$string['timestart'] = 'Start';
$string['title_help'] = 'This is the string that will be displayed as the title of this block. If this field is blank, no title will be displayed for this block.';
$string['title'] = 'Title';
$string['toolcreateusers_desc'] = 'Setup groups of anonymous users';
$string['toolcreateusers'] = 'Create vetting users and groups';
$string['tooldata2workshop_desc'] = 'Convert Database -> Workshops';
$string['tooldata2workshop_help'] = 'On this page you can select records from a submissions database and copy them to a workshop activity where they can be assessed and reviewed anonymously. You can use a currently existing workshop activity or create a new one.

**To use an existing workshop** in this course, select it from the "Target workshop" menu below.

**To create a new workshop**, give a name for the new workshop and specify the section in which it should be created. This can be an existing section, or a new section. If you specify a new section you will need to give a name for the new section.';
$string['tooldata2workshop'] = 'Prepare submissions for review';
$string['toolsetup_help'] = 'On this page you can set up a database for a conference. You can overwrite a currently existing database or create a new one.

**To use an existing database** in this course, select it from the drop down menu below.

**To create a new database**, give a name for the new database and specify the section in which it should be created. This can be an existing section, or a new section. If you specify a new section you will need to give a name for the new section.';
$string['toolsetup'] = 'Setup a database for a conference.';
$string['toolsetupevents_desc'] = 'Setup a database for extra events';
$string['toolsetupevents'] = 'Add conference events';
$string['toolsetuppresentations_desc'] = 'Setup a database for presentations';
$string['toolsetuppresentations'] = 'Collect presentation proposals';
$string['toolsetupregistrations_desc'] = 'Setup a database for registrations';
$string['toolsetupregistrations'] = 'Collect registration data';
$string['toolsetupschedule_desc'] = 'Assign time slots in the schedule';
$string['toolsetupschedule_help'] = 'On this page you can specify when and where each event, workshop and presentation will be held at the conference.';
$string['toolsetupschedule'] = 'Setup the schedule';
$string['toolsetupvetting_desc'] = 'Assign users to vet submissions';
$string['toolsetupvetting_help'] = 'On this page you can designate which group of anonymous users will vet and review the submissions in a specified workshop activity.';
$string['toolsetupvetting'] = 'Setup vetting responsibilties';
$string['toolsetupworkshops_desc'] = 'Setup a database for workshops';
$string['toolsetupworkshops'] = 'Collect workshop proposals';
$string['toolworkshop2data_desc'] = 'Convert Workshops -> Database';
$string['toolworkshop2data'] = 'Publish vetting results';
$string['unknownlanguage'] = 'Unknown language';
$string['uploadpreset'] = 'Upload preset zip file';
$string['validimportfile'] = 'Configuration settings were successfully imported';
$string['vettinggroup_help'] = 'Select the group of users who are to review and vet the submissions in the target workshop activity.';
$string['vettinggroup'] = 'Vetting group';
$string['workshopscmid_help'] = 'The page resource that displays information about the conference workshops.';
$string['workshopscmid'] = 'Workshop information';
$string['workshopstime_help'] = 'The start and finish dates of the conference workshops.';
$string['workshopstime'] = 'Conference workshops';
