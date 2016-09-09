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
$string['blockdescription'] = 'This block facilitates administration of conference submissions system based on a set of Database, Workshop and Assignment activities.';
$string['blockname'] = 'MAJ Submissions';
$string['blocknameplural'] = 'MAJ Submissions';

// roles strings
$string['maj_submissions:addinstance'] = 'Add a new MAJ Submissions block';

// more strings
$string['addfilterfields'] = 'Add a filter field';
$string['addreviewcmids'] = 'Add a review activity';
$string['addrevisecmids'] = 'Add a revision activity';
$string['closedon'] = 'Closed on {$a}';
$string['closeson'] = 'Closes on {$a}';
$string['collectcmid_help'] = 'The database activity that is used to collect submissions.';
$string['collectcmid'] = 'Submission database activity';
$string['collectsponsoredtime_help'] = 'The dates and times at which the online collection of sponsored submissions starts and finshes.';
$string['collectsponsoredtime'] = 'Call for sponsored proposals';
$string['collectsubmissions'] = 'Submit proposals';
$string['collecttime_help'] = 'The dates and times at which the online collection of individual submissions starts and finshes.';
$string['collecttime'] = 'Call for presentation proposals';
$string['collectworkshoptime_help'] = 'The dates and times at which the online collection of workshop submissions starts and finshes.';
$string['collectworkshoptime'] = 'Call for workshop proposals';
$string['conferenceevents'] = 'Conference events';
$string['conferencecmid_help'] = 'The page resource that displays information about the conference.';
$string['conferencecmid'] = 'Conference information';
$string['conferencetime_help'] = 'The start and finish dates of the main conference.';
$string['conferencetime'] = 'Main conference';
$string['conversiontools'] = 'Conversion tools';
$string['createnewactivity'] = 'Create new activity';
$string['createnewfield'] = 'Create new field';
$string['createnewsection'] = 'Create new section';
$string['currentstate_help'] = 'The current state of the submission process.';
$string['currentstate'] = 'Current state';
$string['customdatefmt_help'] = 'If you specify a date format here, it will be used in preference to any of the standard Moodle date formats.';
$string['customdatefmt'] = 'Custom date format string';
$string['dateformats'] = 'Date formats';
$string['exportsettings_help'] = 'This link allows you export the configuration settings for this block to a file that you can import into a similar block in another course.';
$string['exportsettings'] = 'Export settings';
$string['filterfields_help'] = 'This field is used to filter the collected submissions into one or more workshop activities for peer review.';
$string['filterfields'] = 'Filter field ({no})';
$string['fixdates_help'] = 'These settings control whether or not the leading zero on months, days and hours less than 10 are removed.';
$string['fixdates'] = 'Remove leading zeros';
$string['importantdates'] = 'Important dates';
$string['importsettings_help'] = 'This link takes you to a screen where you can import configuration settings from a MAJ submissions block configuration settings file.

A settings file is created using the export link on a MAJ submissions block configuration settings page.';
$string['importsettings'] = 'Import settings';
$string['invalidblockname'] = 'Invalid block name in block instance record: id={$a->id}, blockname={$a->blockname}';
$string['invalidcontextid'] = 'Invalid parentcontextid in block instance record: id = {$a->id}, parentcontextid = {$a->parentcontextid}';
$string['invalidcourseid'] = 'Invalid instanceid in course context record: id={$a->id}, instanceid={$a->instanceid}';
$string['invalidimportfile'] = 'Import file was missing, empty or invalid';
$string['invalidinstanceid'] = 'Invalid block instance id: id = {$a}';
$string['moodledatefmt_help'] = 'Start and finish dates will be formatted in a similar way to the date selected here.

If you click on the &quot;+&quot; sign next to one of the dates, the name of the date format string for that date will be displayed, along with its format codes. This is useful if you want to create your own date format string in the &quot;Custom date format string&quot; setting below.

Note that if the &quot;Show date last modified&quot; is set to &quot;No&quot; then no date will be displayed. Also, if a format is specified in the &quot;Custom date format string&quot; setting, then that will override the string selected here.';
$string['moodledatefmt'] = 'Moodle date format string';
$string['openedon'] = 'Opened on {$a}';
$string['openson'] = 'Opens on {$a}';
$string['publishcmid_help'] = 'The resource or database activity where submissions are published online.';
$string['publishcmid'] = 'Publication database activity';
$string['publishsubmissions'] = 'Publish schedule';
$string['publishtime_help'] = 'The start and finish dates of the period during which the conference schedule is available online to the public.';
$string['publishtime'] = 'Publication of schedule';
$string['receptioncmid_help'] = 'The page resource that displays information about the conference reception.';
$string['receptioncmid'] = 'Reception information';
$string['receptiontime_help'] = 'The start and finish times of the conference reception.';
$string['receptiontime'] = 'Conference reception';
$string['registercmid_help'] = 'The database activity where delegates register their intention to attend and participate in the conference.';
$string['registercmid'] = 'Registration database activity';
$string['registerparticipation'] = 'Register participation';
$string['registerpresentertime_help'] = 'The dates and times at which the online registration for presenters starts and finshes.';
$string['registerpresentertime'] = 'Registration for presenters';
$string['registertime_help'] = 'The dates and times at which the online registration for non-presenters starts and finshes.';
$string['registertime'] = 'Registration for non-presenters';
$string['reviewcmids_help'] = 'A workshop activity that is used to peer review submissions.';
$string['reviewcmids'] = 'Review activity ({no})';
$string['reviewsectionnum_help'] = 'The course section where the review activities are located.';
$string['reviewsectionnum'] = 'Review section';
$string['reviewsubmissions'] = 'Review submissions';
$string['reviewtime_help'] = 'The start and finish dates of the submission review period.';
$string['reviewtime'] = 'Review of submissions';
$string['revisecmids_help'] = 'An assignment activity that is used to revise submissions.';
$string['revisecmids'] = 'Revision activity ({no})';
$string['revisesectionnum_help'] = 'The course section in which the revision activities are located.';
$string['revisesectionnum'] = 'Revision section';
$string['revisesubmissions'] = 'Revise submissions';
$string['revisetime_help'] = 'The start and finish dates of the period during which submissions may be revised.';
$string['revisetime'] = 'Revision of submissions';
$string['shortentimes_help'] = 'If this setting is enabled, then times will not be shown if the start time is 00:00 and the end time is 23:55';
$string['shortentimes'] = 'Shorten time stamps';
$string['state'] = 'State';
$string['timefinish'] = 'Finish';
$string['timestart'] = 'Start';
$string['title_help'] = 'This is the string that will be displayed as the title of this block. If this field is blank, no title will be displayed for this block.';
$string['title'] = 'Title';
$string['toolassign2data_desc'] = 'Convert Assignments -> Database';
$string['toolassign2data'] = 'Add submissions to schedule';
$string['tooldata2workshop_desc'] = 'Convert Database -> Workshops';
$string['tooldata2workshop'] = 'Allocate submissions for review';
$string['toolworkshop2assign_desc'] = 'Convert Workshops -> Assignments';
$string['toolworkshop2assign'] = 'Allow submissions to be revised';
$string['validimportfile'] = 'Configuration settings were successfully imported';
$string['workshopscmid_help'] = 'The page resource that displays information about the conference workshops.';
$string['workshopscmid'] = 'Workshop information';
$string['workshopstime_help'] = 'The start and finish dates of the conference workshops.';
$string['workshopstime'] = 'Conference workshops';
