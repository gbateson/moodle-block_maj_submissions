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
 * blocks/maj_submissions/tools/setupschedule.js
 *
 * @package    blocks
 * @subpackage maj_submissions
 * @copyright  2016 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.3
 */

if (window.MAJ==null) {
    window.MAJ = {};
}

if (MAJ.str==null) {
    MAJ.str = {};
}

// constants
MAJ.APPLY_NONE    = 0;
MAJ.APPLY_CURRENT = 1;
MAJ.APPLY_THISDAY = 2;
MAJ.APPLY_ALLDAYS = 3;

MAJ.sourcesession = null;

// TODO: initialize this array from the PHP script on the server
//       blocks/maj_submissions/tools/setupschedule/action.php
MAJ.sessiontypes = "casestudy|lightningtalk|presentation|showcase|workshop";

// define selectors for session child nodes
MAJ.sessiontimeroom = ".time, .room";
MAJ.sessioncontent = ".title, .authors, .categorytype, .summary";

// define the selectors for room content
MAJ.details = {"roomname" : null, "roomseats" : null, "roomtopic" : null};

// the DOM id of the dialog box
MAJ.dialogid = "dialog";

MAJ.update_record = function(session) {
}

MAJ.set_schedule_html = function() {
    var html = MAJ.trim($("#schedule").html());

    // remove YUI ids
    html = html.replace(new RegExp(' *\\bid="yui_[^"]*"', "g"), "");

    // remove jQuery CSS classes
    html = html.replace(new RegExp(' *\\bui-[a-z0-9_-]*', "g"), "");

    // reset hidden multilang SPANs
    html = html.replace(new RegExp(' *\\bdisplay: *none;*', "g"), "");

    // remove leading space from class/style counts
    html = html.replace(new RegExp('(\\b(class|style)=") +', "g"), "$1");

    // remove empty class/style attributes
    html = html.replace(new RegExp(' *\\b(class|style)=" *"', "g"), "");

    // standardize attribute order in multilang SPANs
    html = html.replace(new RegExp('(lang="[^"]*") (class="multilang")', "g"), "$2 $1");

    // remove info about xml namespaces
    html = html.replace(new RegExp(' *\\bxml:\\w+="[^"]*"', "g"), "");

    // remove "icons" elements
    html = html.replace(new RegExp('<span\\b[^>]*class="icons"[^>]*>.*?</span>', "g"), "");

    // remove the "schedulechooser" element
    html = html.replace(new RegExp('<div\\b[^>]*class="schedulechooser"[^>]*>.*?</div>', "g"), "");

    $("input[name=schedule_html]").val(html);
}

MAJ.set_schedule_unassigned = function() {
    var ids = [];
    $("#items .session[id^=id_recordid]").each(function(){
        ids.push($(this).prop("id").substr(12));
    });
    $("input[name=schedule_unassigned]").val(ids.join(","));
}

MAJ.get_non_jquery_classes = function(elm) {
    var classes = $(elm).prop('class').split(new RegExp("\\s+"));
    var max = (classes.length - 1);
    for (var i=max; i>=0; i--) {
        if (classes[i].indexOf("ui-")==0) {
            classes.splice(i, 1);
        }
    }
    return classes.join(" ");
}

MAJ.get_day_selector = function(day, details) {
    return "." + (day=="alldays" ? "day" : day) + (details ? details : "");
}

MAJ.get_items = function(container, item, selector) {
    if (item) {
        return $(item);
    }
    return $(container).find(selector);
}

MAJ.close_dialog = function() {
    $("#" + MAJ.dialogid).dialog("close");
}

MAJ.open_dialog = function(evt, title, html, actiontext, actionicon, actionfunction, showcancelbutton) {
    var showactionbutton = true;

    // locate dialog box in DOM
    // (create it, if necessary)
    var dialogbox = document.getElementById(MAJ.dialogid);
    if (dialogbox==null) {
        dialogbox = document.createElement("DIV");
        dialogbox.setAttribute("id", MAJ.dialogid);
        $("body").append(dialogbox);
    }

    // cache jQuery object for dialog
    var dialog = $(dialogbox);

    // create/close the dialog element
    if (dialog.dialog("instance")==null) {
        dialog.dialog({"autoOpen": false, "close": MAJ.select_session, "width" : "auto"});
    } else {
        if (dialog.dialog("isOpen")) {
            dialog.dialog("close");
        }
    }

    // update the dialog title
    dialog.dialog("option", "title", title);

    // update the dialog HTML
    dialog.html(html);

    // set the dialog mode
    dialog.dialog("option", "modal", showcancelbutton);

    // update the dialog buttons
    var buttons = [];
    if (showactionbutton) {
        if (actiontext==null) {
            actiontext = MAJ.str.ok;
        }
        if (actionicon==null) {
            actionicon = "ui-icon-check";
        }
        if (actionfunction==null) {
            actionfunction = function(){
                $(this).dialog("close");
            };
        }
        buttons.push({"text": actiontext,"click": actionfunction}); // "icon": actionicon
    }
    if (showcancelbutton) {
        var canceltext = MAJ.str.cancel;
        var cancelicon = "ui-icon-cancel";
        var cancelfunction = function(){
            $(this).dialog("close");
            MAJ.select_session();
        };
        buttons.push({"text": canceltext, "click": cancelfunction}); // "icon": cancelicon
    }
    dialog.dialog("option", "buttons", buttons);

    // update the dialog position
    if (showcancelbutton) {
        var my = "left-96px; bottom+40px";
    } else {
        var my = "left-144px; bottom+36px";
    }
    //dialog.dialog("option", "position", {"my" : my, "at": "center", "of": evt});

    // open the dialog box
    dialog.dialog("open");

    // prevent the current click causing
    // the parent element to be selected
    evt.stopPropagation();
}

MAJ.show_add_dialog = function(evt, title, html, actionfunction) {
    MAJ.open_dialog(evt, title, html, MAJ.str.add, null, actionfunction, true);
}

MAJ.show_edit_dialog = function(evt, title, html, actionfunction) {
    MAJ.open_dialog(evt, title, html, MAJ.str.update, null, actionfunction, true);
}

MAJ.show_remove_dialog = function(evt, title, html, actionfunction) {
    MAJ.open_dialog(evt, title, html, MAJ.str.remove, null, actionfunction, true);
}

MAJ.select_session = function(id) {
    if (MAJ.sourcesession) {
        MAJ.click_session(MAJ.sourcesession);
    } else {
        $(".ui-selected").removeClass("ui-selected");
    }
    if (id) {
        MAJ.click_session(document.getElementById(id));
    }
}

MAJ.edit_session = function(evt) {
    var id = $(this).closest(".session").prop("id");
    var recordid = MAJ.extract_recordid(id);

    var title = MAJ.str.editsession + ": rid=" + recordid;
    var html = "<p>Edit a session</p>";
    var actionfunction = function(){
        MAJ.open_dialog(evt, title, MAJ.str.editedsession, MAJ.str.ok);
    };

    MAJ.select_session(id);
    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.remove_session = function(evt) {
    var id = $(this).closest(".session").prop("id");
    var recordid = MAJ.extract_recordid(id);

    var title = MAJ.str.removesession + ": rid=" + recordid;
    var html = MAJ.tag("p", MAJ.str.confirmsession);
    var actionfunction = function(){

        // add new empty session to #items
        var item = MAJ.item(null, "session emptysession").appendTo("#items");

        // deselect current session
        MAJ.select_session();

        // swap the empty session and the target session
        MAJ.click_session(item);
        MAJ.click_session(document.getElementById(id), true);

        MAJ.open_dialog(evt, title, MAJ.str.removedsession, MAJ.str.ok);
    };

    MAJ.select_session(id);
    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.add_room = function(evt) {
    var title = MAJ.str.addrooms;

	// start HTML for dialog
    var html = "";
    html += "<table><tbody>";

    // add checkboxes for days
    html += MAJ.days_checkbox("days");

    // fetch array of room positions
    var position = MAJ.position("room", MAJ.extract_max_roomcount());

    html += "<tr>" + MAJ.tag("th", MAJ.str.position) + MAJ.tag("td", position) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.roomname) + MAJ.tag("td", MAJ.rooms("roomtxt")) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.roomtopic) + MAJ.tag("td", MAJ.text("roomtopic")) + "</tr>";

    // finish HTML
    html += "</tbody></table>";

    var actionfunction = function(){

        var days = MAJ.form_values(this, "days_", true);
        var roomtxt = MAJ.form_value(this, "roomtxt");
        var roomtopic = MAJ.form_value(this, "roomtopic");
        var position = MAJ.form_value(this, "position", true);

        var added = false;

        $(".day").each(function(){
            var day = MAJ.extract_day($(this).prop("class"));
            if (days[day]) {

                $(this).find(".date td").each(function(){
                    var colspan = $(this).prop("colspan") || 1;
                    $(this).prop("colspan", colspan + 1);
                });

                $(this).find(".roomheadings").each(function(){
                    var r = 1;
                    var added = false;
                    var oldclass = new RegExp("\\broom\\d+");
                    $(this).find(".roomheading").each(function(index){
                        if (added==false && position <= index) {
                            added = true;
                            $(this).before(MAJ.roomheading(r++, roomtxt, roomtopic));
                        }
                        var cssclass = $(this).prop("class").replace(oldclass, "");
                        $(this).prop("class", MAJ.trim(cssclass) + " room" + r++);
                    });
                    if (added==false) {
                        added = true;
                        $(this).append(MAJ.roomheading(r++, roomtxt, roomtopic));
                    }
                });

                var html = MAJ.tag("td", "", {"class" : "session emptysession"});
                $(this).find(".slot").each(function(){
                    var allrooms = MAJ.has_allrooms($(this));
                    var added = false;
                    $(this).find(".session").each(function(index){
                        if (added==false) {
                            if ($(this).hasClass("allrooms")) {
                                added = true;
                                var colspan = $(this).prop("colspan") || 1;
                                $(this).prop("colspan", colspan + 1);
                            } else if (position <= index) {
                                added = true;
                                MAJ.insert_session(html, "insertBefore", this);
                            }
                        }
                    });
                    if (added==false) {
                        added = true;
                        MAJ.insert_session(html, "appendTo", this);
                    }
                });
            }
        });

        // set colspan of scheduletitle and tabs
        MAJ.set_schedule_colspan();

        MAJ.redraw_schedule(added);

        MAJ.open_dialog(evt, title, MAJ.str.addedrooms, MAJ.str.ok);
    };

    MAJ.show_add_dialog(evt, title, html, actionfunction);
}

MAJ.edit_room = function(evt) {

	// extract day and room
	var day = MAJ.extract_parent_day(this);
    var room = MAJ.extract_parent_room(this);

    var langs = [];
    var details = {};

    var heading = $(this).closest(".roomheading");
    heading.addClass("ui-selected");

    for (var name in MAJ.details) {
        var detail = heading.find("." + name);
        if (detail.length==0) {
            continue;
        }
        details[name] = {};
        var span = detail.find("span.multilang");
        if (span.length==0) {
            details[name] = detail.text();
            continue;
        }
        span.each(function(){
            var lang = $(this).prop("lang");
            if (lang) {
                if (langs.indexOf(lang) < 0) {
                    langs.push(lang);
                }
                details[name][lang] = $(this).text();
            }
        });
    }

    var title = MAJ.str.editroom + MAJ.str.labelsep + room;

    var html = "";
    html += "<table><tbody>";

    if (langs.length) {
        html += "<tr>" + MAJ.tag("td", "");
        for (var i=0; i<langs.length; i++) {
            var lang = langs[i];
            var langtext = (MAJ.str[lang] ? MAJ.str[lang] : lang);
            html += MAJ.boldcenter("td", langtext);
        }
        html += "</tr>";
    }

    for (var name in details) {
        html += "<tr>" + MAJ.tag("th", MAJ.str[name]);
        if (langs.length==0) {
            var value = MAJ.trim(details[name]);
            html += MAJ.tag("td", MAJ.text(name, value, 12));
        } else {
            for (var i=0; i<langs.length; i++) {
                var lang = langs[i];
                var value = "";
                if (typeof(details[name])=="string") {
                    value = MAJ.trim(details[name]);
                } else if (details[name][lang]) {
                    value = MAJ.trim(details[name][lang]);
                }
                html += MAJ.tag("td", MAJ.text(name + "_" + lang, value));
            }
        }
        html += "</tr>";
    }

    html += "</tbody></table>";
    html += MAJ.hidden("day", day);
    html += MAJ.hidden("room", room);

    var actionfunction = function(){
        var day = MAJ.form_value(this, "day", true);
        var room = MAJ.form_value(this, "room", true);
        var heading = $(".day" + day + " .roomheading.room" + room);
        var session = $(".day" + day + " .session .room" + room);
        for (var name in MAJ.details) {
            var html = MAJ.multilangs(this, name);
            heading.find("." + name).html(html);
            session.find("." + name).html(html);
        }

        MAJ.open_dialog(evt, title, MAJ.str.editedroom, MAJ.str.ok);
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.remove_room = function(evt) {
	// extract day/room number and day text
	var day = MAJ.extract_parent_day(this);
    var room = MAJ.extract_parent_room(this);
    var daytext = MAJ.extract_parent_daytext(this);

    var heading = $(this).closest(".roomheading");
    heading.addClass("ui-selected");

    var title = MAJ.str.removeroom + MAJ.str.labelsep + room;

    var html = MAJ.tag("p", MAJ.str.confirmroom);
    html += "<table><tbody>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.day) + MAJ.tag("td", daytext) + "</tr>";
    for (var name in MAJ.details) {
        var detail = heading.find("." + name);
        if (detail.html()) {
            html += "<tr>" + MAJ.tag("th", MAJ.str[name]) + MAJ.tag("td", detail.html()) + "</tr>";
        }
    }
    html += "</tbody></table>";
    html += MAJ.hidden("day", day);
    html += MAJ.hidden("room", room);

    var actionfunction = function(){
        var day = MAJ.form_value(this, "day", true);
        var room = MAJ.form_value(this, "room", true);

        var daydate = ".day" + day + " .date td";
        var dayslots = ".day" + day + " .slot";
        var dayroomheading = ".day" + day + " .roomheading.room" + room;

        // get col index of the target room
        var roomindex = $(dayroomheading).index();

        // remove this roomheading
        $(dayroomheading).remove();

        // unassign any active sessions
        var daycolspan = 0;
        $(dayslots).each(function(){
            var rowcolspan = 0;
            var removed = false;
            $(this).find("th, td").each(function(){

                if (removed) {
                    rowcolspan++;
                    return true;
                }

                if ($(this).is(".allrooms")) {
                    var cellcolspan = $(this).prop("colspan");
                    if (cellcolspan && cellcolspan > 2) {
                        cellcolspan = (cellcolspan - 1);
                        $(this).prop("colspan", cellcolspan);
                    } else {
                        cellcolspan = 1;
                        $(this).removeAttr("colspan");
                    }
                    rowcolspan += cellcolspan;
                    removed = true;
                    return true;
                }

                if ($(this).index()==roomindex) {
                    MAJ.unassign_session(this, true);
                    removed = true;
                    return true;
                }

                rowcolspan++;

            });
            daycolspan = Math.max(daycolspan, rowcolspan);
        });

        // reset the colspan on the date cell for this day
        if (daycolspan > 1) {
            $(daydate).prop("colspan", daycolspan);
        } else {
            $(daydate).removeAttr("colspan");
        }

        // set colspan of scheduletitle and tabs
        MAJ.set_schedule_colspan();

        MAJ.open_dialog(evt, title, MAJ.str.removedroom, MAJ.str.ok);
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.add_roomheadings = function(evt) {

    var title = MAJ.str.addroomheadings;

	// start HTML for dialog
    var html = "";
    html += "<table><tbody>";

    // add checkboxes for days
    html += MAJ.days_checkbox("days");

    // add HTML for room names and topics
    var roomcount = MAJ.extract_max_roomcount();
    for (var r=1; r<=roomcount; r++) {
        if (r==1) {
            html += MAJ.html_roomheadings_toprow();
        }
        html += MAJ.html_roomheadings_datarow(null, r);
    }

    // finish HTML
    html += "</tbody></table>";

    var actionfunction = function(){

        var days = MAJ.form_values(this, "days_", true);
        var rooms = MAJ.form_values(this, "room_");
        var topics = MAJ.form_values(this, "topic_");

        var added = false;

        $(".day").each(function(){
            var day = MAJ.extract_day($(this).prop("class"));
            if (days[day]) {
                var add = true;
                $(this).find("tr").not(".date").each(function(){
                    if (MAJ.has_allrooms($(this))) {
                        add = true;
                    } else if (add) {
                        if ($(this).is(":not(.roomheadings)")) {
                            MAJ.roomheadings(day, rooms, topics).insertBefore(this);
                            added = true;
                        }
                        add = false;
                    }
                });
            }
        });

        MAJ.redraw_schedule(added);
        MAJ.open_dialog(evt, title, MAJ.str.addedroomheadings, MAJ.str.ok);
    };

    MAJ.show_add_dialog(evt, title, html, actionfunction);

}

MAJ.redraw_schedule = function(redraw) {
    // some browsers (at least Chrome on Mac)
    // need to redraw the schedule after adding tr
    // rows to the main TABLE holding the schedule
    if (redraw) {
        $("#schedule").hide().show(50);
    }
}

MAJ.edit_roomheadings = function(evt) {
	// extract day number and day text
	var day = MAJ.extract_parent_day(this);
	var row = MAJ.extract_parent_row(this);
    var daytext = MAJ.extract_parent_daytext(this);

    var title = MAJ.str.editroomheadings;

	// start HTML for dialog
    var html = "";
    html += "<table><tbody>";

    // add HTML for room names and topics
    var r = 1;
    $(this).closest("th").nextAll(".roomheading").each(function(){
        if (r==1) {
            html += MAJ.html_roomheadings_toprow();
        }
        html += MAJ.html_roomheadings_datarow(this, r++);
    });

    if (r > 1) {
        html += MAJ.html_roomheadings_lastrow();
    }

    // finish HTML
    html += "</tbody></table>";

    html += MAJ.hidden("day", day);
    html += MAJ.hidden("row", row);

    var updated = false;
    var actionfunction = function(){
        var day = MAJ.form_value(this, "day", true);
        var row = MAJ.form_value(this, "row", true);

        var rooms = MAJ.form_values(this, "room_");
        var topics = MAJ.form_values(this, "topic_");
        var applyto = MAJ.form_value(this, "applyto");

        $("tbody.day").each(function(){
            var d = MAJ.extract_day($(this).prop("class"));
            if (applyto==MAJ.APPLY_CURRENT || applyto==MAJ.APPLY_THISDAY) {
                var apply = (d==day);
            } else {
                var apply = (applyto==MAJ.APPLY_ALLDAYS);
            }
            if (apply) {
                $(this).find(".roomheadings").each(function(){
                    if (applyto==MAJ.APPLY_CURRENT) {
                        apply = (row==MAJ.extract_parent_row(this))
                    } else {
                        apply = true;
                    }
                    if (apply) {
                        $(this).replaceWith($(MAJ.html_roomheadings(day, rooms, topics)).each(function(){
                            MAJ.make_rooms_editable(null, this);
                        }));
                        updated = true;
                    }
                });
            }
        });

        MAJ.redraw_schedule(updated);

        MAJ.open_dialog(evt, title, MAJ.str.editedroomheadings, MAJ.str.ok);
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.remove_roomheadings = function(evt) {
	// extract day number and day text
	var day = MAJ.extract_parent_day(this);
	var row = MAJ.extract_parent_row(this);
    var daytext = MAJ.extract_parent_daytext(this);

    var title = MAJ.str.removeroomheadings;

    var html = MAJ.tag("p", MAJ.str.confirmroomheadings);
    html += MAJ.alist("ul", [daytext]);
    html += MAJ.hidden("day", day);
    html += MAJ.hidden("row", row);

    var actionfunction = function(){
        var day = MAJ.form_value(this, "day", true);
        var row = MAJ.form_value(this, "row", true);
        $(".day.day" + day).closest("table").find("tr:eq(" + row + ")").remove();
        MAJ.open_dialog(evt, title, MAJ.str.removedroomheadings, MAJ.str.ok);
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.add_slot = function(evt) {
	// specify title
    var title = MAJ.str.addslot;

	// get form elements
	var start = MAJ.hoursmins("start");
	var finish = MAJ.hoursmins("finish");
    var targetday = MAJ.days_select("targetday");

	// create HTML for dialog
    var html = "";
    html += "<table><tbody>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.day) + MAJ.tag("td", targetday) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.starttime) + MAJ.tag("td", start) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.finishtime) + MAJ.tag("td", finish) + "</tr>";
    html += "</tbody></table>";

	// specify action function for dialog button
    var actionfunction = function(){

        var targetday   = MAJ.form_value(this, "targetday",   true);
        var starthours  = MAJ.form_value(this, "starthours",  true);
        var startmins   = MAJ.form_value(this, "startmins",   true);
        var finishhours = MAJ.form_value(this, "finishhours", true);
        var finishmins  = MAJ.form_value(this, "finishmins",  true);

        var duration = MAJ.form_duration(starthours, startmins, finishhours, finishmins);
        var startfinish = MAJ.form_startfinish(starthours, startmins, finishhours, finishmins);

        // create new slot
        var slot = MAJ.slot(targetday, startfinish, duration);

        // insert html for new slot in the targetday
        $(".day.day" + targetday).find(".slot").each(function(){
            var slotstartfinish = $(this).find(".timeheading .startfinish").text();
            if (slotstartfinish > startfinish) {
                slot.insertBefore(this);
                slot = null;
            }
            // return false to stop each() loop
            return (slot==null ? false : true);
        });
        if (slot) {
            // append the new slot at the end of targetday
            $(".day.day" + targetday).append(slot);
            slot = null;
        }

        MAJ.redraw_schedule(true);

        // renumber all slots on this day
        MAJ.renumberslots(targetday);

        MAJ.open_dialog(evt, title, MAJ.str.addedslot, MAJ.str.ok);
    };

    MAJ.show_add_dialog(evt, title, html, actionfunction);
}

MAJ.edit_slot = function(evt) {

	// extract day/slot number
	var day = MAJ.extract_parent_day(this);
    var slot = MAJ.extract_parent_slot(this);
    var daytext = MAJ.extract_parent_daytext(this);

    var heading = $(this).closest(".timeheading");
    heading.addClass("ui-selected");

    // extract start/finish times
    var startfinish = heading.find(".startfinish").text();
    var regexp = new RegExp("^.*(\\d{2}).*(\\d{2}).*(\\d{2}).*(\\d{2}).*$");
    var starthours  = parseInt(startfinish.replace(regexp, "$1"));
    var startmins   = parseInt(startfinish.replace(regexp, "$2"));
    var finishhours = parseInt(startfinish.replace(regexp, "$3"));
    var finishmins  = parseInt(startfinish.replace(regexp, "$4"));

    // create JQuery object for the selected slot
    var dayslot = ".day" + day + " .slot" + slot;
    var s = $(dayslot);

	// specify title
    var title = MAJ.str.editslot;

	// get form elements
	var start = MAJ.hoursmins("start", starthours, startmins);
	var finish = MAJ.hoursmins("finish", finishhours, finishmins);
	var checkbox = MAJ.checkbox("allrooms", MAJ.has_allrooms(s));

    var roomname = MAJ.extract_sessionroom(dayslot + " .allrooms", "name");

	// create HTML for dialog
    var html = "";
    html += "<table><tbody>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.day) + MAJ.tag("td", daytext) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.starttime) + MAJ.tag("td", start) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.finishtime) + MAJ.tag("td", finish) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.allrooms) + MAJ.tag("td", checkbox + " "+ MAJ.rooms("roomtxt", roomname)) + "</tr>";
    html += "</tbody></table>";
    html += MAJ.hidden("day", day);
    html += MAJ.hidden("slot", slot);

	// specify action function for dialog button
    var actionfunction = function(){

        var day  = MAJ.form_value(this, "day", true);
        var slot = MAJ.form_value(this, "slot", true);
        var roomtxt = MAJ.form_value(this, "roomtxt");
        var allrooms = MAJ.form_value(this, "allrooms", true);
        var starthours  = MAJ.form_value(this, "starthours", true);
        var startmins   = MAJ.form_value(this, "startmins", true);
        var finishhours = MAJ.form_value(this, "finishhours", true);
        var finishmins  = MAJ.form_value(this, "finishmins", true);

        var duration = MAJ.form_duration(starthours, startmins, finishhours, finishmins);
        var startfinish = MAJ.form_startfinish(starthours, startmins, finishhours, finishmins);

        // update the start/finish times for this slot
        $(dayslot + " .startfinish").html(startfinish);
        $(dayslot + " .duration").html(MAJ.get_string("durationtxt", duration));

        // update duration class for this slot
        var oldclass = new RegExp("\\bduration\\w+");
        var cssclass = $(dayslot).prop("class").replace(oldclass, "");
        $(dayslot).prop("class",  cssclass + " duration" + duration);

        var r = 1;
        var firstsession = true;
        var roomcount = MAJ.extract_roomcount(day);
        $(dayslot + " .session").each(function(){
            if (allrooms) {
                // convert to allrooms
                if (firstsession) {
                    firstsession = false;
                    $(this).addClass("allrooms");
                    if (roomcount > 1) {
                        $(this).prop("colspan", roomcount);
                    }
                    MAJ.insert_timeroom(this, roomtxt);
                } else {
                    MAJ.unassign_session(this, true);
                }
            } else {
                // revert to single rooms
                if ($(this).is(".allrooms")) {
                    $(this).removeAttr("colspan")
                           .removeClass("allrooms");
                    if ($(this).is(":not(.emptysession)")) {
                        MAJ.insert_timeroom(this);
                    } else {
                        $(this).find(MAJ.sessiontimeroom).remove();
                    }
                }
                r++;
                if ($(this).is(":last-child")) {
                    MAJ.insert_sessions(this, r, day);
                }
            }
        });

        MAJ.open_dialog(evt, title, MAJ.str.editedslot, MAJ.str.ok);
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.remove_slot = function(evt) {

	// extract day/slot number and day text
	var day = MAJ.extract_parent_day(this);
    var slot = MAJ.extract_parent_slot(this);
    var daytext = MAJ.extract_parent_daytext(this);

    // locate timeheading for this day + slot
    var dayslot = ".day" + day + " .slot" + slot;
    var heading = $(dayslot + " .timeheading");
    heading.addClass("ui-selected");

	// extract start/finish time text and duration text
    var startfinish = heading.find(".startfinish").text();
    var duration = heading.find(" .duration").html();

    var title = MAJ.str.removeslot + MAJ.str.labelsep + slot;

    var html = MAJ.tag("p", MAJ.str.confirmslot);
    html += "<table><tbody>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.day) + MAJ.tag("td", daytext) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.time) + MAJ.tag("td", startfinish) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.duration) + MAJ.tag("td", duration) + "</tr>";
    html += "</tbody></table>";
    html += MAJ.hidden("day", day);
    html += MAJ.hidden("slot", slot);

    var actionfunction = function(){
        var day  = MAJ.form_value(this, "day", true);
        var slot  = MAJ.form_value(this, "slot", true);

        // unassign any active sessions
        $(dayslot +  " .session").each(function(){
            MAJ.unassign_session(this, true);
        });

        // remove this slot
        $(dayslot).remove();

        // renumber remaining slots on this page
        MAJ.renumberslots(day);

        MAJ.open_dialog(evt, title, MAJ.str.removedslot, MAJ.str.ok);
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.renumberslots = function(day) {
    var slotnumber = 0;
    $(".day.day" + day).find(".slot").each(function(){
        $(this).prop("class", "slot slot" + (slotnumber++));
    });
}

MAJ.add_day = function(evt) {
	// specify title
    var title = MAJ.str.addday;

    var position = MAJ.position("day", $(".tabs .tab").length);
    var daytext = MAJ.days("daytext");
    var slotstart = MAJ.hoursmins("start", 9, 0);

    // determine number of rooms/slots
    // and average slot length/interval
    var roomcounts = {};
    var slotcounts = {};
    var slotlengths = {};
    var slotintervals = {};

    // RegExp to parse start/finish times
    var startfinish = new RegExp("(\\d+)\\s*:\\s*(\\d+)\\s*-\\s*(\\d+)\\s*:\\s*(\\d+)");

    // search the current schedule and pick out default values for the new day
    $("tbody.day").each(function(){

        MAJ.increment(slotcounts, $(this).find(".slot").length);

        $(this).find(".roomheadings").each(function(){
            MAJ.increment(roomcounts, $(this).find(".roomheading").length);
        });

        $(this).find(".timeheading .duration").each(function(){
            MAJ.increment(slotlengths, MAJ.extract_duration($(this).prop("class")));
        });

        var finishtime = null;
        $(this).find(".timeheading .startfinish").each(function(){
            var m = $(this).text().match(startfinish);
            if (m && m.length > 4) {
                if (typeof(finishtime)=="number") {
                    var starttime = (60 * parseInt(m[1]) + parseInt(m[2]));
                    MAJ.increment(slotintervals, Math.abs(starttime - finishtime));
                }
                finishtime = (60 * parseInt(m[3]) + parseInt(m[4]));
            }
        });
    });

    // set default values for form elements
    var roomcount = MAJ.mode(roomcounts) || 5;
    var slotcount = MAJ.mode(slotcounts) || 10;
    var slotlength = MAJ.mode(slotlengths) || 25;
    var slotinterval = MAJ.mode(slotintervals) || 5;

    // create form elements
    roomcount = MAJ.range("roomcount", roomcount, 1, Math.max(10, MAJ.max(roomcounts)));
    slotcount = MAJ.range("slotcount", slotcount, 1, Math.max(20, MAJ.max(slotcounts)));
    slotlength = MAJ.mins("slotlength", slotlength, 10, Math.max(120, MAJ.max(slotlengths)));
    slotinterval = MAJ.mins("slotinterval", slotinterval, 0, Math.max(60, MAJ.max(slotintervals)));

	// create HTML for dialog
    var html = "";
    html += "<table><tbody>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.position) + MAJ.tag("td", position) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.daytext) + MAJ.tag("td", daytext) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.roomcount) + MAJ.tag("td", roomcount) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.slotcount) + MAJ.tag("td", slotcount) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.slotstart) + MAJ.tag("td", slotstart) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.slotlength) + MAJ.tag("td", slotlength) + "</tr>";
    html += "<tr>" + MAJ.tag("th", MAJ.str.slotinterval) + MAJ.tag("td", slotinterval) + "</tr>";
    html += "</tbody></table>";

	// specify action function for dialog button
    var actionfunction = function(){

        var position     = MAJ.form_value(this, "position",     true);
        var daytext      = MAJ.form_value(this, "daytext");
        var roomcount    = MAJ.form_value(this, "roomcount",    true);
        var slotcount    = MAJ.form_value(this, "slotcount",    true);
        var starthours   = MAJ.form_value(this, "starthours",   true);
        var startmins    = MAJ.form_value(this, "startmins",    true);
        var slotlength   = MAJ.form_value(this, "slotlength",   true);
        var slotinterval = MAJ.form_value(this, "slotinterval", true);

        var slotstart = (60 * parseInt(starthours) + parseInt(startmins));

        var d = 1;
        var added = false;
        var oldclass = new RegExp("\\bday\\d+");
        $("table.schedule").each(function(){
            $(this).find("tbody.day").each(function(index){
                if (added==false && position <= index) {
                    added = true;
                    $(this).before(MAJ.day(d++, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval));
                }
                var cssclass = $(this).prop("class").replace(oldclass, "");
                $(this).prop("class", MAJ.trim(cssclass) + " day" + d++);
            });
            if (added==false) {
                added = true;
                $(this).append(MAJ.day(d++, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval));
            }
        });

        // renumber all slots on the new day
        MAJ.renumberslots(position);

        // set colspan of scheduletitle and tabs
        MAJ.set_schedule_colspan();

        MAJ.redraw_schedule(true);

        MAJ.open_dialog(evt, title, MAJ.str.addedday, MAJ.str.ok);
    };

    MAJ.show_add_dialog(evt, title, html, actionfunction);
}

MAJ.edit_day = function(evt) {
    var day = MAJ.extract_parent_tabday(this);

    var tab = $(this).closest(".tab");
    tab.addClass("ui-selected");

    var title = MAJ.str.editday + MAJ.str.labelsep + day;

    var html = "";
    html += "<table><tbody>";

    var name = "daytext";

    var span = tab.find("span.multilang");
    if (span.length==0) {
        var value = tab.contents().filter(MAJ.textnodes).text();
        html += "<tr>"
             + MAJ.tag("th", MAJ.str[name])
             + MAJ.tag("td", MAJ.text(name, MAJ.trim(value)))
             + "</tr>";
    } else {
        html += "<tr>"
             + MAJ.tag("td", "")
             + MAJ.boldcenter("td", MAJ.str[name])
             + "</tr>";
        span.each(function(){
            var lang = $(this).prop("lang");
            if (lang) {
                html += "<tr>"
                     + MAJ.tag("th", MAJ.str[lang] ? MAJ.str[lang] : lang)
                     + MAJ.tag("td", MAJ.text(name + "_" + lang, MAJ.trim($(this).html())))
                     + "</tr>";
            }
        });
    }

    html += "</tbody></table>";
    html += MAJ.hidden("day", day);

    var actionfunction = function(){
        var html = MAJ.multilangs(this, "daytext");
        var day = MAJ.form_value(this, "day", true);

        $(".tab.day" + day).each(function(){
            $(this).contents().not(".icons").remove();
            $(this).prepend(html);
        });
        $(".day.day" + day + " .date td:first-child").each(function(){
            $(this).contents().remove();
            $(this).prepend(MAJ.force_single_line(html));
        });

        // update the day display on the Tools submenus
        $(".subcommand[id$=day" + day + "]").html(html);

        MAJ.open_dialog(evt, title, MAJ.str.editedday, MAJ.str.ok);
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.remove_day = function(evt) {
    var day = MAJ.extract_parent_tabday(this);

    var tab = $(this).closest(".tab");
    tab.addClass("ui-selected");

    var lang = MAJ.extract_main_language();
    var daytext = tab.find(".multilang[lang=" + lang + "]").html();
    daytext = MAJ.force_single_line(daytext);

    var title = MAJ.str.removeday + MAJ.str.labelsep + day;

    var html = MAJ.tag("p", MAJ.str.confirmday);
    html += MAJ.alist("ul", [daytext]);
    html += MAJ.hidden("targetday", day);

    var actionfunction = function(){
        var targetday = MAJ.form_value(this, "targetday", true);

        // remove tab for this day
        var d = 1;
        var activatetab = false;
        var oldclass = new RegExp("day\\d+");
        $(".tab[class*=day]").each(function(){
            var cssclass = $(this).prop("class");
            var day = MAJ.extract_day(cssclass);
            if (day==targetday) {
                if ($(this).hasClass("active")) {
                    activatetab = true;
                }
                $(this).remove();
            } else {
                cssclass = MAJ.trim(cssclass.replace(oldclass, ""));
                $(this).prop("class", cssclass + " day" + d++);
            }
        });

        // unassign sessions on the target day
        $(".day.day" + targetday).each(function(){
            $(this).find(".session").each(function(){
                MAJ.unassign_session(this, true);
            });
        });

        // if this day was active, then make another day active instead
        if (activatetab) {
            $(".tab").first().trigger("click");
        }

        // set colspan of scheduletitle and tabs
        MAJ.set_schedule_colspan();

        // remove this day from the Tools subcommands
        $(".subcommand[id$=day" + targetday + "]").remove();

        MAJ.open_dialog(evt, title, MAJ.str.removedday, MAJ.str.ok);
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.set_schedule_colspan = function(colspan) {
    if (colspan==null) {
        colspan = MAJ.extract_max_roomcount() + 1;
    }
    $(".scheduletitle, .tabs").find("td").prop("colspan", colspan);
}

MAJ.create_icons = function(type, actions) {
    var icons = document.createElement("SPAN");
    icons.setAttribute("class", "icons");
    if (actions==null) {
        actions = ["edit", "remove"];
    } else if (typeof(actions)=="string") {
        actions = [actions];
    }
    for (var i in actions) {
        var action = actions[i];
        var icon = document.createElement("IMG");
        icon.setAttribute("src", MAJ["icon" + action]);
        icon.setAttribute("title", MAJ.str[action + type]);
        icon.setAttribute("class", "icon");
        var clickhandler = action + "_" + type;
        if (MAJ[clickhandler]) {
            $(icon).click(MAJ[clickhandler]);
        } else {
            $(icon).click(function(){
                alert("TODO: MAJ." + clickhandler + "()");
            });
        }
        icons.appendChild(icon);
    }
    return icons;
}

MAJ.click_session = function(targetsession, forceswap) {

    // select source
    if (MAJ.sourcesession==null) {
        MAJ.sourcesession = targetsession;
        $(targetsession).addClass("ui-selected");
        return true;
    }

    // cache flags if target/source is empty
    var targetIsEmpty = $(targetsession).hasClass("emptysession");
    var sourceIsEmpty = $(MAJ.sourcesession).hasClass("emptysession");

    // deselect source
    if (MAJ.sourcesession==targetsession || (sourceIsEmpty && targetIsEmpty)) {
        $(MAJ.sourcesession).removeClass("ui-selected");
        $(targetsession).removeClass("ui-selected");
        MAJ.sourcesession = null;
        return true;
    }

    var targetAllRooms = $(targetsession).hasClass("allrooms");
    var sourceAllRooms = $(MAJ.sourcesession).hasClass("allrooms");

    var targetAssigned = $(targetsession).closest("table.schedule").length;
    var sourceAssigned = $(MAJ.sourcesession).closest("table.schedule").length;

    // we need to swap these two sessions
    // if (a) if "forceswap is TRUE
    // or (b), both sessions are non-empty
    // or (c), the sessions have the same tagName
    // i.e. they are both TD cells in TABLE.schedule
    // or they are both DIVs in the #items area of the form
    var swap = (forceswap ? true : false);
    if (targetIsEmpty==false && sourceIsEmpty==false) {
        swap = true;
    }
    if (targetsession.tagName==MAJ.sourcesession.tagName) {
        swap = true;
    }

    if (swap) {
        $(targetsession).addClass("ui-selected");

        // create temp elements to store child nodes
        var temptarget = document.createElement("DIV");
        var tempsource = document.createElement("DIV");

        // transfer DOM ids
        var sourceid = $(MAJ.sourcesession).prop("id");
        var targetid = $(targetsession).prop("id");
        $(MAJ.sourcesession).prop("id", targetid);
        $(targetsession).prop("id", sourceid);

        // transfer CSS classes
        var sourceclasses = MAJ.get_non_jquery_classes(MAJ.sourcesession);
        var targetclasses = MAJ.get_non_jquery_classes(targetsession);
        $(MAJ.sourcesession).removeClass(sourceclasses).addClass(targetclasses);
        $(targetsession).removeClass(targetclasses).addClass(sourceclasses);

        // move children to temp source
        if (sourceAssigned) {
            if (targetIsEmpty && sourceAllRooms==false) {
                $(MAJ.sourcesession).children(MAJ.sessiontimeroom).remove();
            } else {
                $(MAJ.sourcesession).children(MAJ.sessiontimeroom).appendTo(tempsource);
            }
        }
        if (targetIsEmpty==false) {
            $(targetsession).children(MAJ.sessioncontent).appendTo(tempsource);
        }

        // move children to temp target
        if (targetAssigned) {
            if (sourceIsEmpty && targetAllRooms==false) {
                $(targetsession).children(MAJ.sessiontimeroom).remove();
            } else {
                $(targetsession).children(MAJ.sessiontimeroom).appendTo(temptarget);
            }
        }
        if (sourceIsEmpty==false) {
            $(MAJ.sourcesession).children(MAJ.sessioncontent).appendTo(temptarget);
        }

        // move children to real source and target
        $(temptarget).children().appendTo(targetsession);
        $(tempsource).children().appendTo(MAJ.sourcesession);

        if (sourceAssigned) {
            if (sourceAllRooms) {
                $(MAJ.sourcesession).addClass("allrooms");
            } else {
                $(MAJ.sourcesession).removeClass("allrooms");
                if (targetIsEmpty==false) {
                    MAJ.insert_timeroom(MAJ.sourcesession);
                }
            }
        }
        if (targetAssigned) {
            if (targetAllRooms) {
                $(targetsession).addClass("allrooms");
            } else {
                $(targetsession).removeClass("allrooms");
                if (targetIsEmpty==false) {
                    MAJ.insert_timeroom(targetsession);
                }
            }
        }

        tempsource = null;
        temptarget = null;

        // deselect MAJ.sourcesession
        $(MAJ.sourcesession).removeClass("ui-selected");
        MAJ.sourcesession = null;

        // deselect targetsession
        $(targetsession).removeClass("ui-selected");

        // finish here
        return true;
    }

	// otherwise, one session is empty
	// and the other is not empty

    var empty = null;
    var nonempty = null;

	// if source is empty and target is not, then
    // move target to source, then remove target
    if (sourceIsEmpty && targetIsEmpty==false) {
        empty = MAJ.sourcesession;
        nonempty = targetsession;
    }

	// if target is empty and source is not, then
    // move source to target, then remove source
    if (sourceIsEmpty==false && targetIsEmpty) {
        empty = targetsession;
        nonempty = MAJ.sourcesession;
    }

    if (empty && nonempty) {

        $(empty).addClass("ui-selected");

        // transfer DOM "id"
        $(empty).prop("id", $(nonempty).prop("id"));

        // set flag if empty session uses All Rooms
        var emptyAllRooms = $(empty).is(".allrooms");

        // transfer CSS classes
        var emptyclasses = MAJ.get_non_jquery_classes(empty);
        var nonemptyclasses = MAJ.get_non_jquery_classes(nonempty);
        $(empty).removeClass(emptyclasses).addClass(nonemptyclasses);

        // remove child nodes and transfer time/room details
        if (emptyAllRooms) {
            $(empty).addClass("allrooms");
            $(empty).children().not(MAJ.sessiontimeroom).remove();
        } else {
            $(empty).removeClass("allrooms");
            $(empty).children().remove();
            MAJ.insert_timeroom(empty);
        }

        // transfer content elements
        $(nonempty).children(MAJ.sessioncontent).appendTo(empty);

        // deselect empty session
        $(empty).removeClass("ui-selected");

        // the nonempty session is now empty
        // and can be removed from the DOM
        $(nonempty).remove();

        // release MAJ.sourcesession
        MAJ.sourcesession = null;

        return true;
    }
}

MAJ.make_sessions_droppable = function(container, session) {
    MAJ.get_items(container, session, "td.session").droppable({
        "accept" : ".session",
        "drop" : function(evt, ui) {
            $(this).removeClass("ui-dropping");
            MAJ.click_session(this);
        },
        "out" : function(evt, ui) {
            $(this).removeClass("ui-dropping");
        },
        "over" : function(evt, ui) {
            $(this).addClass("ui-dropping");
        },
        "tolerance" : "pointer"
    });
};

MAJ.make_sessions_draggable = function(container, session) {
    MAJ.get_items(container, session, ".session").draggable({
        "cursor" : "move",
        "scroll" : true,
        "stack" : ".session",
        "start" : function(evt, ui) {
            MAJ.sourcesession = this;
            $(this).addClass("ui-dragging");
            $(this).removeClass("ui-selected");
            $(this).data("startposition", {
                "top" : $(this).css("top"),
                "left" : $(this).css("left")
            });
        },
        "stop" : function(evt, ui) {
            $(this).removeClass("ui-dragging");
            var p = $(this).data("startposition");
            if (p) {
                $(this).addClass("ui-dropping");
                $(this).animate({
                    "top" : p.top,
                    "left" : p.left
                }, function(){
                    $(this).removeClass("ui-dropping");
                });
            }
        }
    });
};

MAJ.make_sessions_selectable = function(container, session) {
    MAJ.get_items(container, session, ".session").click(function(evt){
        MAJ.click_session(this);
    });
}

MAJ.make_sessions_editable = function(container, session) {
    MAJ.get_items(container, session, ".session").each(function(){
        var id = $(this).prop("id");
        if (id.indexOf("id_record")==0) {
            var icons = MAJ.create_icons("session");
            $(this).find(".title").prepend(icons);
        }
    });
}

MAJ.make_rooms_editable = function(container, room) {
    MAJ.get_items(container, room, ".roomheadings").each(function(){
        $(this).find(".timeheading").each(function(){
            var icons = MAJ.create_icons("roomheadings");
            $(this).prepend(icons);
        });
        $(this).find(".roomheading").each(function(){
            var icons = MAJ.create_icons("room");
            $(this).prepend(icons);
        });
    });
}

MAJ.make_slots_editable = function(container, slot) {
    MAJ.get_items(container, slot, ".slot").each(function(){
        $(this).find(".timeheading").each(function(){
            var icons = MAJ.create_icons("slot");
            var txt = document.createTextNode(" ");
            $(this).append(txt, icons);
        });
    });
}

MAJ.make_days_editable = function(container, day) {
    MAJ.get_items(container, day, ".tab").each(function(){
        MAJ.make_day_editable(this);
    });
}

MAJ.make_day_editable = function(elm) {
    var icons = MAJ.create_icons("day");
    var txt = document.createTextNode(" ");
    $(elm).append(txt, icons);
}

MAJ.hide_multilang_spans = function(container) {
    var lang = MAJ.extract_main_language();
    $(container).find("span.multilang[lang!=" + lang + "]").css("display", "none");
}

MAJ.setup_tools = function() {
    var missing = [];
    $("#tools .command").each(function(){
        var activecommand = true;
        $(this).find(".subcommand").each(function(){
            // extract c(ommand) and s(ubcommand)
            // from id, e.g. add-slot
            $(this).click(function(evt){
                var id = $(this).prop("id");
                var i = id.indexOf("-");
                var c = id.substring(0, i);
                var s = id.substring(i + 1);
                var a = c + "_" + s;
                if (MAJ[a]) {
                    MAJ[a](evt);
                } else if (MAJ[c]) {
                    MAJ[c](evt, s);
                }
            });
            activecommand = false;
        });
        if (activecommand) {
            $(this).click(function(evt){
                var c = $(this).prop("id");
                MAJ[c](evt);
            });
        }
    });
}

MAJ.initializeschedule = function(evt, day) {

    // empty the current schedule
    MAJ.emptyschedule(evt, day);

    // initialize each required day
    $(MAJ.get_day_selector(day)).each(function(){
    });
}

MAJ.emptyschedule = function(evt, day) {
    // process all sessions in all slots on the selected day
    $(MAJ.get_day_selector(day, " .slot")).each(function(){
        var r = 1;
        $(this).find(".session").each(function(){
            MAJ.unassign_session(this);
            r++;
            if ($(this).is(":last-child")) {
                MAJ.insert_sessions(this, r);
            }
        });
    });

	// remove any "demo" sessions in the #items container
    $("#items .session").not("div[id^=id_record]").each(function(){
		$(this).remove();
    });
}

MAJ.populateschedule = function(evt, day) {

    // Slot allocation rules
    // =====================
    // https://stackoverflow.com/questions/2746309/best-fit-scheduling-algorithm
    // https://www.codeproject.com/Articles/23111/Making-a-Class-Schedule-Using-a-Genetic-Algorithm

    // Hard requirements (if you break one of these, then the schedule is infeasible):
    // (1) presenters cannot teach twice in the same slot

    // Some soft requirements (can be broken, but the schedule is still feasible):
    // (2) presenters should not present in consecutive slots
    // (3) distribute languages equally throughout schedule
    // (4) distribute sponsors equally throughout schedule
    // (5) slot should match presentation_times
    // (6) try to have same topics as previous/next session
    // (7) try to have same language as previous/next session
    // (8) try to have same keywords as previous/next session

    // SETTINGS SCREEN:
    // preference_time_1 => day first_slot last_slot
    // preference_time_2 => day first_slot last_slot
    // preference_time_3 => day first_slot last_slot
    // preference_time_4 => day first_slot last_slot

    // cancel previous clicks on sessions, if any
    MAJ.select_session();

    // request scheduling info from server: "loadinfo"
    // (only send info about submission whose status is accepeted)
    // - presentation_language
    // - presentation_topics
    // - presentation_keywords
    // - presentation_times
    // - presenter userids (including co-presenters)

    // select empty sessions on the selected day
    var empty = $(MAJ.get_day_selector(day, " .emptysession:not(.allrooms)"));
    if (empty.length==0) {
        return true;
    }

    // select all unassigned sessions
    var items = $("#items .session");
    if (items.length==0) {
        return true;
    }

    // mimic clicks to assign sessions
    var max = Math.min(items.length,
                       empty.length);
    for (var i=(max-1); i>=0; i--) {
        MAJ.click_session(items.get(i));
        MAJ.click_session(empty.get(i));
    }
    return true;
}

MAJ.renumberschedule = function(evt, day) {

    // initialize array of i(ndexes), counts and multipliers
    var i = [];
    var count = {
        "days"  : $(".day").length,
        "slots" : 0,
        "rooms" : 0
    };
    $(".day").each(function(){
        var countslots = $(this).find(".slot").length;
        count.slots = Math.max(count.slots, countslots);
    });
    $(".day").each(function(){
        $(this).find(".roomheadings").each(function(){
            var countrooms = $(this).find(".roomheading").length;
            count.rooms = Math.max(count.rooms, countrooms);
        });
    });
    var multiply = {
        "days"  : Math.pow(10, Math.ceil(count.days  / 10)),
        "slots" : Math.pow(10, Math.ceil(count.slots / 10)),
        "rooms" : Math.pow(10, Math.ceil(count.rooms / 10))
    };
    var smallschedule = (count.days < 10 && count.slots < 10 && count.rooms < 10);

	// initialize RegExp's to extract info from CSS class
    var dayregexp = new RegExp("^.*day(\\d+).*$");
    var slotregexp = new RegExp("^.*slot(\\d+).*$");
    var roomregexp = new RegExp("^.*room(\\d+).*$");
    var typeregexp = new RegExp("^.*(" + MAJ.sessiontypes + ").*$");

    // select all non-empty sessions on the selected day
    $(MAJ.get_day_selector(day, " .session:not(.emptysession):not(.demo)")).each(function(){

        var day = $(this).closest(".day");
        day = day.prop("class").replace(dayregexp, "$1");

        var type = $(this).prop("class").replace(typeregexp, "$1").charAt(0).toUpperCase();

        if (smallschedule) {

            var slot = $(this).closest(".slot");
            slot = slot.prop("class").replace(slotregexp, "$1");

            var room = $(this).closest(".slot").prevAll(".roomheadings");
            if (room.length==0 || $(this).hasClass("allrooms")) {
                var room = 0;
            } else {
                room = room.first().find("th, td").eq(this.cellIndex);
                room = room.prop("class").replace(roomregexp, "$1");
            }

            var schedulenumber = (day + slot + room + "-" + type);

        } else {

            if (i[day]==null) {
                i[day] = 1;
            } else {
                i[day]++;
            }

            var schedulenumber = ((day * multiply.slots) + i[day]);
            schedulenumber = (schedulenumber + "-" + type);
        }

        $(this).find(".schedulenumber").text(schedulenumber);
    });
}

MAJ.editcss = function(evt) {
}

MAJ.format_ajax_error = function(action, r, x) {
    var moodleerror = false;
    var i = r.indexOf('<footer id="page-footer"');
    if (i >= 0) {
        r = r.substr(0, i);
        moodleerror = true;
    }
    var i = r.indexOf('<div id="page-content"');
    if (i >= 0) {
        r = r.substr(i);
        moodleerror = true;
    }
    if (moodleerror) {
        // debugging error from Moodle
        r = r.replace(new RegExp('<form[^>]*>.*?</form>', "g"), "");
        r = r.replace(new RegExp('<p class="errorcode">.*?</p>'), "");
        r = r.replace(new RegExp('<div class="continuebutton">.*?</div>'), "");
        return r;
    }
    return "Error (" + action + ") " + x.status + ": " + x.statusText;
}

// ==========================================
// helper functions to count frequencies
// ==========================================

MAJ.increment = function(a, i) {
    if (a[i]==null) {
        a[i] = 1;
    } else{
        a[i]++;
    }
}

MAJ.mode = function(a) {
    var mode = null;
    var count = null;
    for (var i in a) {
        i = parseInt(i);
        if (count===null || count < a[i] || (count==a[i] && mode < i)) {
            count = a[i];
            mode = i;
        }
    }
    return (mode==null ? 0 : mode);
}

MAJ.max = function(a) {
    var max = null;
    for (var i in a) {
        i = parseInt(i);
        if (max===null || max < i) {
            max = i;
        }
    }
    return (max==null ? 0 : max);
}

// ==========================================
// helper functions to filter selected nodes
// ==========================================

MAJ.colspan = function(){
    return (this.colSpan && this.colSpan > 1);
}

MAJ.textnodes = function(){
    return (this.nodeType && this.nodeType===3);
}

// ==========================================
// helper functions to extract info from DOM
// ==========================================

MAJ.has_allrooms = function(slot) {
    return (slot.find(".allrooms").filter(MAJ.colspan).length > 0);
}

MAJ.extract_main_language = function() {
    var regexp = new RegExp("lang-(\\w+)");
    return $("body").attr('class').match(regexp)[1];
}

MAJ.extract_parent_daytext = function(elm) {
    var daytext = $(elm).closest(".day").find(".date td");
    if (daytext.length==0) {
        return "";
    }
    return MAJ.force_single_line(daytext.first().html());
}

MAJ.extract_parent_row = function(elm) {
    return $(elm).closest("tr").prop("rowIndex");
}

MAJ.extract_parent_day = function(elm) {
    return MAJ.extract_parent_number(elm, "day", "day");
}

MAJ.extract_parent_tabday = function(elm) {
    return MAJ.extract_parent_number(elm, "tab", "day");
}

MAJ.extract_parent_room = function(elm) {
    return MAJ.extract_parent_number(elm, "roomheading", "room");
}

MAJ.extract_parent_slot = function(elm) {
    return MAJ.extract_parent_number(elm, "slot", "slot");
}

MAJ.extract_parent_number = function(elm, parentclass, type) {
    var str = $(elm).closest("." + parentclass).prop("class");
    return MAJ.extract_type_number(str, type);
}

MAJ.extract_recordid = function(id) {
    return MAJ.extract_number(id, "id_recordid_", "");
}

MAJ.extract_active_day = function() {
    return MAJ.extract_day($(".tab.active").prop("class"));
}

MAJ.extract_day = function(str) {
    return MAJ.extract_type_number(str, "day");
}

MAJ.extract_room = function(str) {
    return MAJ.extract_type_number(str, "room");
}

MAJ.extract_slot = function(str) {
    return MAJ.extract_type_number(str, "slot");
}

MAJ.extract_duration = function(str) {
    return MAJ.extract_number(str, ".*\\bduration", "\\b.*");
}

MAJ.extract_type_number = function(str, type) {
    return MAJ.extract_number(str, ".*\\b" + type, "\\b.*");
}

MAJ.extract_number = function(str, prefix, suffix) {
    if (typeof(str)=="string") {
        var pattern = "^" + prefix + "(\\d+)" + suffix + "$";
        var number = str.replace(new RegExp(pattern), "$1");
        return (isNaN(number) ? 0 : parseInt(number));
    }
    return 0;
}

MAJ.extract_roomcount = function(day) {
    var daytext = $(".day.day" + day + " tr.date td");
    if (daytext.length==0) {
        return 0;
    }
    return (daytext.prop("colspan") - 1);
}

MAJ.extract_max_roomcount = function() {
    var max = 0;
    $(".day").each(function(){
        var day = MAJ.extract_parent_day(this);
        max = Math.max(max, MAJ.extract_roomcount(day));
    });
    return max;
}

MAJ.extract_roomname_txt = function(txt) {
    return MAJ.extract_room_txt(txt);
}

MAJ.extract_roomseats_txt = function(txt) {
    return MAJ.extract_room_txt(txt, true);
}

MAJ.extract_room_txt = function(txt, returnnumber) {
    // e.g. Room 101 (20 seats)
    if (txt==MAJ.trim(txt)) {
        if (txt.indexOf('class="multilang"') < 0) {
            var onebyte = new RegExp("^(.*)\\((.*?)\\)(.*?)$");
            var twobyte = new RegExp("^(.*)\uff08(.*?)\uff09(.*?)$");
            var replace = (returnnumber ? "$2" : "$1$3");
        } else {
            var onebyte = new RegExp("(<span[^>]*>)([^<]*?)\\((.*?)\\)(.*?)(</span>)");
            var twobyte = new RegExp("(<span[^>]*>)([^<]*?)\uff08(.*?)\uff09(.*?)(</span>)");
            var replace = (returnnumber ? "$1$3$5" : "$1$2$4$5");
        }
        txt = txt.replace(onebyte, replace).replace(twobyte, replace);
    }
    return txt;
}

MAJ.extract_duration_class = function(day, slot) {
    var duration = $(".day.day" + day + " .slot" + slot + " .duration");
    if (duration.length==0) {
        return "";
    }
    return MAJ.duration_class(MAJ.extract_duration(duration.prop("class")));
}

MAJ.extract_startfinish_html = function(day, slot) {
    return MAJ.extract_time_html(day, slot, "startfinish");
}

MAJ.extract_duration_html = function(day, slot) {
    return MAJ.extract_time_html(day, slot, "duration");
}

MAJ.extract_time_html = function(day, slot, name) {
    return $(".day.day" + day + " .slot" + slot + " .timeheading ." + name).html();
}

MAJ.extract_roomname_html = function(day, r) {
    return MAJ.extract_room_html(day, r, "roomname");
}

MAJ.extract_roomseats_html = function(day, r) {
    return MAJ.extract_room_html(day, r, "roomseats");
}

MAJ.extract_roomtopic_html = function(day, r) {
    return MAJ.extract_room_html(day, r, "roomtopic");
}

MAJ.extract_room_html = function(day, r, name) {
    return $(".day.day" + day + " .roomheadings .room" + r + " ." + name).html();
}

MAJ.extract_sessionroom = function(session, returndetail) {
    var details = [];
    if (detail) {
        details.push(detail);
    } else {
        details.push("name", "seats", "topic");
    }
    var room = {};
    var lang = MAJ.extract_main_language();
    for (var i in details) {
        var value = "";
        var detail = details[i];
        var extract = "extract_room" + detail + "_txt";
        if (session) {
            var span = $(session).find(".room" + detail);
            if (span.length) {
                if (span.find("span.multilang").length) {
                    span = span.find("span.multilang");
                    if (span.find("[lang=" + lang + "]").length) {
                        span = span.find("[lang=" + lang + "]");
                    }
                }
                value = span.first().html();
                if (MAJ[extract]) {
                    value = MAJ[extract](value);
                }
            }
        }
        room[detail] = value;
    }
    if (returndetail) {
        return room[returndetail];
    } else {
        return room;
    }
}

// ==========================================
// helper functions to insert/remove sessions
// ==========================================

MAJ.unassign_session = function(session, remove) {
    // sessions with a recognized "id" are moved to the "#items" DIV
    var id = $(session).prop("id");
    if (id.indexOf("id_recordid_")==0) {
        $(session).removeAttr("id");
        MAJ.item(id, MAJ.get_non_jquery_classes(session))
           .append($(session).children(MAJ.sessioncontent))
           .appendTo("#items");
    }
    if (remove) {
        $(session).remove();
    } else {
        $(session).children().remove();
        $(session).removeAttr("id colspan")
                  .removeClass("demo attending allrooms")
                  .addClass("emptysession");
    }
}

MAJ.insert_sessions = function(elm, r, day, roomcount) {
    if (day==null) {
        day = MAJ.extract_parent_day(elm);
    }
    if (roomcount==null) {
        roomcount = MAJ.extract_roomcount(day);
    }
    if (r <= roomcount) {
        var html = MAJ.html_sessions(r, roomcount);
        MAJ.insert_session(html, "insertAfter", elm);
    }
}

MAJ.insert_session = function(html, insert, elm) {
    $(html).each(function(){
        MAJ.make_sessions_editable(null, this);
        MAJ.make_sessions_droppable(null, this);
        MAJ.make_sessions_draggable(null, this);
        MAJ.make_sessions_selectable(null, this);
    })[insert](elm);
}

MAJ.insert_timeroom = function(elm, roomtxt) {
    var day = MAJ.extract_parent_day(elm);
    var slot = MAJ.extract_parent_slot(elm);
    var room = $(elm).index();
    MAJ.insert_time(elm, day, slot);
    MAJ.insert_room(elm, day, room, roomtxt);
}

MAJ.insert_time = function(elm, day, slot) {
    var html = MAJ.html_time(day, slot);
    if ($(elm).find(".time").length) {
        $(elm).find(".time").replaceWith(html);
    } else {
        $(elm).prepend(html);
    }
}

MAJ.insert_room = function(elm, day, room, roomtxt) {
    var html = MAJ.html_room(day, room, roomtxt);
    if ($(elm).find(".room").length) {
        $(elm).find(".room").replaceWith(html);
    } else if ($(elm).find(".time").length) {
        $(elm).find(".time").after(html);
    } else {
        $(elm).prepend(html);
    }
}

// ==========================================
// helper functions to create jQuery objects
// ==========================================

MAJ.day = function(d, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval) {
    var html = MAJ.html_day(d, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval);
    return $(html).each(function(){
        MAJ.make_day_editable(this);
    });
}

MAJ.roomheadings = function(day, rooms, topics) {
    // if possible we clone another row of roomheadings
    var headings = $(".day.day" + day + " .roomheadings");
    if (headings.length) {
        return headings.first().clone();
    }
    headings = $(MAJ.html_roomheadings(day, rooms, topics));
    MAJ.make_rooms_editable(null, headings);
    return headings;
}

MAJ.roomheading = function(r, roomtxt, roomtopic) {
    var roomname = MAJ.extract_roomname_txt(roomtxt);
    var roomseats = MAJ.extract_roomseats_txt(roomtxt);
    var heading = $(MAJ.html_roomheading(r, roomname, roomseats, roomtopic));
    heading.prepend(MAJ.create_icons("room"));
    return heading;
}

MAJ.slot = function(targetday, startfinish, duration) {
    var slot = $(MAJ.html_slot(targetday, startfinish, duration));
    MAJ.make_slots_editable(null, slot);
    MAJ.make_sessions_droppable(slot);
    MAJ.make_sessions_draggable(slot);
    MAJ.make_sessions_selectable(slot);
    return slot;
}

MAJ.item = function(id, classess){
    var item = $(MAJ.html_item(id, classess));
    MAJ.make_sessions_draggable(null, item);
    MAJ.make_sessions_selectable(null, item);
    return item;
}

// ==========================================
// helper functions to create HTML elements
// ==========================================

MAJ.html_day = function(day, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval) {
    var html = "";
    html += MAJ.starttag("tbody", {"class" : "day day" + day});

    // day text
    html += MAJ.starttag("tr", {"class" : "date"});
    html += MAJ.tag("td", daytext, {"colspan" : roomcount});
    html += MAJ.endtag("tr");

    // room headings
    html += MAJ.html_roomheadings(day, null, null, roomcount);

    // slots
    var starttime = null;
    var finishtime = null;
    for (var s=1; s<=slotcount; s++) {
        if (starttime===null) {
            starttime = slotstart;
        } else {
            starttime = finishtime + slotinterval;
        }
        finishtime = starttime + slotlength;
        startfinish = MAJ.form_startfinish(Math.floor(starttime / 60),
                                           (starttime % 60),
                                           Math.floor(finishtime / 60),
                                           (finishtime % 60));
        html += MAJ.html_slot(day, startfinish, slotlength);
    }

    html += MAJ.endtag("tbody");
    return html;
}

MAJ.html_roomheadings_toprow = function() {
    return MAJ.starttag("tr")
           + MAJ.tag("td", "")
           + MAJ.boldcenter("td", MAJ.str.room)
           + MAJ.boldcenter("td", MAJ.str.roomtopic)
           + MAJ.endtag("tr");
}

MAJ.html_roomheadings_datarow = function(elm, r) {
    var room = MAJ.extract_sessionroom(elm);
    return MAJ.starttag("tr")
           + MAJ.tag("th", r)
           + MAJ.tag("td", MAJ.rooms("room_" + r, room.name))
           + MAJ.tag("td", MAJ.text("topic_" + r, room.topic))
           + MAJ.endtag("tr");
}

MAJ.html_roomheadings_lastrow = function() {
    var options = {};
    options[MAJ.APPLY_CURRENT] = MAJ.str.currentheadings;
    options[MAJ.APPLY_THISDAY] = MAJ.str.allheadingsthisday;
    options[MAJ.APPLY_ALLDAYS] = MAJ.str.allheadingsalldays;
    return MAJ.starttag("tr")
           + MAJ.tag("th", MAJ.str.applyto)
           + MAJ.tag("td", MAJ.select("applyto", options, MAJ.APPLY_CURRENT, {}))
           + MAJ.endtag("tr");
}

MAJ.html_roomheading = function(r, roomname, roomseats, roomtopic) {
    return MAJ.starttag("th", {"class" : "roomheading room" + r})
         + MAJ.tag("span", roomname,  {"class" : "roomname"})
         + MAJ.tag("span", roomseats, {"class" : "roomseats"})
         + MAJ.tag("div",  roomtopic, {"class" : "roomtopic"})
         + MAJ.endtag("th");
}

MAJ.html_roomheadings = function(day, rooms, topics, roomcount) {
    if (roomcount==null) {
        roomcount = MAJ.extract_roomcount(day);
    }
    var html = "";
    html += MAJ.starttag("tr", {"class" : "roomheadings"});
    html += MAJ.tag("th", "", {"class" : "timeheading"});
    for (var r=1; r<=roomcount; r++) {
        if (rooms==null || rooms[r]==null || rooms[r]=="" || rooms[r]=="0") {
            var roomname = MAJ.str.roomname + " (" + r + ")";
            var roomseats = 40;
            var roomtopic = MAJ.str.roomtopic + " (" + r + ")";
        } else {
            var roomname = MAJ.extract_roomname_txt(rooms[r]);
            var roomseats = MAJ.extract_roomseats_txt(rooms[r]);
            var roomtopic = MAJ.trim(topics[r]);
        }
        html += MAJ.html_roomheading(r, roomname, roomseats, roomtopic);
    }
    html += MAJ.endtag("tr");
    return html;
}

MAJ.html_timeroom = function(day, slot, room, roomtxt) {
    return MAJ.html_time(day, slot) + MAJ.html_room(day, room, roomtxt);
}

MAJ.html_time = function(day, slot) {
    var startfinish = MAJ.extract_startfinish_html(day, slot);
    var duration = MAJ.extract_duration_html(day, slot);
    var html = MAJ.tag("span", startfinish, {"class" : "startfinish"})
             + MAJ.tag("span", duration, {"class" : "duration"});
    return MAJ.tag("div", html, {"class" : "time"});
}

MAJ.html_room = function(day, room, roomtxt) {
    if (roomtxt=="0") {
        return "";
    }
    if (roomtxt==null || roomtxt=="") {
        var roomname = MAJ.extract_roomname_html(day, room);
        var roomseats = MAJ.extract_roomseats_html(day, room);
        var roomtopic = MAJ.extract_roomtopic_html(day, room);
    } else {
        var roomname = MAJ.extract_roomname_txt(roomtxt);
        var roomseats = MAJ.extract_roomseats_txt(roomtxt);
        var roomtopic = "";
    }
    var html = MAJ.tag("span", roomname, {"class" : "roomname"})
             + MAJ.tag("span", roomseats, {"class" : "roomseats"})
             + MAJ.tag("div", roomtopic, {"class" : "roomtopic"});
    return MAJ.tag("div", html, {"class" : "room"});
}

MAJ.html_item = function(id, classes) {
    if (classes==null) {
        classes = "session";
    }
    var attr = {"class" : classes};
    if (id) {
        attr.id = id;
    }
    return MAJ.tag("div", "", attr);
}

MAJ.html_sessions = function(r_min, r_max) {
    var html = "";
    for (var r=r_min; r<=r_max; r++) {
        html += MAJ.tag("td", "", {"class" : "session emptysession"});
    }
    return html;
}

MAJ.html_slot = function(day, startfinish, duration) {
    var html = "";
    var durationtxt = MAJ.get_string("durationtxt", duration);
    html += MAJ.starttag("tr", {"class" : "slot duration" + duration});
    html += MAJ.starttag("td", {"class" : "timeheading"});
    html += MAJ.tag("span", startfinish, {"class" : "startfinish"});
    html += MAJ.tag("span", durationtxt, {"class" : "duration"});
    html += MAJ.endtag("td");
    html += MAJ.html_sessions(1, MAJ.extract_roomcount(day));
    html += MAJ.endtag("tr");
    return html;
}

MAJ.get_string = function(name, insert) {
    if (name==null || name=="" || MAJ.str[name]==null) {
        return "";
    }
    var str = MAJ.str[name];
    if (insert || insert===0) {
        if (typeof(insert)=="string" || typeof(insert)=="number") {
            var a = new RegExp("\\{a\\}", "g");
            str = MAJ.str[name].replace(a, insert);
        } else {
            for (var i in insert) {
                var a = new RegExp("\\{a->" + i + "\\}", "g");
                str = str.replace(a, insert[i]);
            }
        }
    }
    return str;
}

MAJ.htmlescape = function(value) {
	value += ""; // convert to String
	return value.replace(new RegExp("&", "g"), "&amp;")
                .replace(new RegExp("'", "g"), "&apos;")
                .replace(new RegExp('"', "g"), "&quot;")
                .replace(new RegExp("<", "g"), "&lt;")
                .replace(new RegExp(">", "g"), "&gt;");
}

MAJ.attribute = function(name, value) {
	if (name = name.replace(new RegExp("^a-zA-Z0-9_-"), "g")) {
		name = " " + name + '="' + MAJ.htmlescape(value) + '"';
	}
	return name;
}

MAJ.attributes = function(attr) {
	var html = "";
    if (attr) {
        for (var name in attr) {
            html += MAJ.attribute(name, attr[name]);
        }
    }
	return html;
}

MAJ.starttag = function(tag, attr) {
	return "<" + tag + MAJ.attributes(attr) + ">";
}

MAJ.endtag = function(tag) {
	return "</" + tag + ">";
}

MAJ.emptytag = function(tag, attr) {
	return "<" + tag + MAJ.attributes(attr) + "/>";
}

MAJ.tag = function(tag, content, attr) {
	return (MAJ.starttag(tag, attr) + content + MAJ.endtag(tag));
}

MAJ.input = function(name, type, attr) {
    attr.type = type;
    attr.name = name;
    attr.id = "id_" + name;
    return MAJ.emptytag("input", attr);
}

MAJ.hidden = function(name, value) {
    var attr = {"value" : (value || "")};
    return MAJ.input(name, "hidden", attr);
}

MAJ.text = function(name, value, size) {
    var attr = {"value" : (value || ""),
                "size" : (size || "15")};
    return MAJ.input(name, "text", attr);
}

MAJ.checkbox = function(name, checked) {
    var attr = {"value" : "1"};
    if (checked) {
        attr.checked = "checked";
    }
    return MAJ.input(name, "checkbox", attr);
}

MAJ.alist = function(tag, items) {
    var alist = "";
    for (var i in items) {
        alist += MAJ.tag("li", items[i]);
    }
    return MAJ.tag(tag, alist, {});
}

MAJ.select = function(name, options, selected, attr) {
    var html = "";
	for (var value in options) {
        var a = {"value" : value};
        if (value==selected) {
            a["selected"] = "selected";
        }
		html += MAJ.tag("option", options[value], a);
	}
	attr.name = name;
	attr.id = "id_" + name;
	return MAJ.tag("select", html, attr);
}

MAJ.hours = function(name, selected, min, max, attr) {
    var pad = (min==null && max==null);
    if (min==null) {
        min = 0;
    }
    if (max==null) {
        max = 23;
    }
	if (attr==null) {
	    attr = {};
	}
	if (attr.class==null) {
        attr.class = "select" + name;
	}
	var options = {};
	for (var i=min; i<=max; i++) {
	    if (pad) {
            options[i] = MAJ.pad(i);
	    } else {
            options[i] = MAJ.get_string("numhours", i);
	    }
	}
	return MAJ.select(name, options, selected, attr);
}

MAJ.mins = function(name, selected, min, max, attr) {
    var pad = (min==null && max==null);
    if (min==null) {
        min = 0;
    }
    if (max==null) {
        max = 59;
    }
	if (attr==null) {
	    attr = {};
	}
	if (attr.class==null) {
        attr.class = "select" + name;
	}
	var options = {};
	for (var i=min; i<=max; i+=5) {
	    if (pad) {
            options[i] = MAJ.pad(i);
	    } else {
            options[i] = MAJ.get_string("nummins", i);
	    }
	}
	return MAJ.select(name, options, selected, attr);
}

MAJ.hoursmins = function(name, hours, mins) {
    var hours  = MAJ.hours(name + "hours", hours);
    var mins = MAJ.mins(name + "mins", mins);
	return hours + MAJ.str.labelsep + mins;
}

MAJ.range = function(name, selected, min, max, attr) {
    if (name==null) {
        return "";
    }
    if (min==null) {
        min = 1;
    }
    if (max==null) {
        max = 10;
    }
    if (selected==null) {
        selected = max;
    }
	if (attr==null) {
	    attr = {};
	}
	if (attr.class) {
	    attr.class = "select" + name;
	}
	var options = {};
	for (var i=min; i<=max; i++) {
		options[i] = i;
	}
	return MAJ.select(name, options, selected, attr);
}


MAJ.position = function(type, max, name, attr) {
    if (type) {
        type = MAJ.get_string(type).toLowerCase();
    }
    if (name==null) {
        name = "position";
    }
    if (attr==null) {
        attr = {};
    }
    var positions = {};
    for (var i=1; i<=max; i++) {
        if (type==null) {
            positions[i] = i;
        } else {
            var a = {"type" : type, "num" : i};
            positions[i] = MAJ.get_string("positionbefore", a);
        }
    }
    positions[i] = MAJ.str.positionlast;
    return MAJ.select(name, positions, i, attr);
}

MAJ.days_select = function(name) {
    var selected = MAJ.extract_active_day();
	var lang = MAJ.extract_main_language();
	var options = {};
	$(".tab").each(function(){
	    var day = MAJ.extract_day($(this).prop("class"));
        var html = $(this).find("span.multilang[lang=" + lang + "]");
        if (html.length) {
            html = html.html();
        } else {
            html = $(this).html();
        }
        options[day] = MAJ.force_single_line(html);
	});
    return MAJ.select(name, options, selected, {});
}

MAJ.days_checkbox = function(name) {
    var html = "";
    var heading = MAJ.str.day;
    var checked = MAJ.extract_active_day();
    $(".day").each(function(){
        var day = MAJ.extract_parent_day(this);
        var dayname = name + "_" + day;
        var daytext = MAJ.extract_parent_daytext(this);
        daytext = MAJ.tag("label", daytext, {"for" : "id_" + dayname});
        daytext = MAJ.checkbox(dayname, (day==checked)) + " " + daytext;
        html += "<tr>"
             + MAJ.tag("th", heading)
             + MAJ.tag("td", daytext, {"colspan" : "2"})
             + "</tr>";
        heading = "";
    });
    return html;
}

MAJ.days = function(name) {
    var days = $("select[name=schedule_day]");
    if (days.length) {
        days = days.first().clone();
        days.prop("name", name);
        days.prop("id", "id_" + name);
        return days.prop('outerHTML');
    }
}

MAJ.rooms = function(name, currentroom) {
    var rooms = $("select[name=schedule_roomname]");
    if (rooms.length) {
        rooms = rooms.first().clone();
        rooms.prop("name", name);
        rooms.prop("id", "id_" + name);
        if (currentroom) {
            var found = (currentroom==null ? true : false);
            rooms.find("option").each(function(){
                if (found || $(this).val().indexOf(currentroom) < 0) {
                    this.selected = false;
                    this.removeAttribute("selected");
                } else {
                    found = true;
                    this.selected = true;
                    this.setAttribute("selected", "selected");
                }
            });
        }
        return rooms.prop('outerHTML');
    }
}

MAJ.boldcenter =function(tag, content) {
    var attr = {"align" : "center"};
    return MAJ.tag("td", MAJ.tag("b", content), attr);
}

MAJ.multilang =function(content, lang) {
    var attr = {"class" : "multilang",
                "lang" : lang};
    return MAJ.tag("span", content, attr);
}

MAJ.multilangs = function(elm, name) {
    var values = MAJ.form_multilang_values(elm, name);
    var value = "";
    var multilangs = [];
    for (var lang in values) {
        value = values[lang];
        multilangs.push(MAJ.multilang(value, lang));
    }
    if (multilangs.length < 2) {
        return value;
    }
    return multilangs.join("");
}

// ==========================================
// helper functions to manipulate strings
// ==========================================

MAJ.force_simple_html = function(html) {
    // remove all tags, except those allowed for formatting
    var tags = "br|hr|b|i|u|em|strong|big|small|sup|sub|tt|var";
    return html.replace(new RegExp("<(/?\\w+)\\b[^>]*>", "g"), "<$1>")
               .replace(new RegExp("</([^>]*)>", "g"), "<$1/>")
               .replace(new RegExp("<(?!" + tags + ")[^>]*>", "g"), "")
               .replace(new RegExp("<([^>]*)/>", "g"), "</$1>");
}

MAJ.force_single_line = function(html) {
    var linebreak = new RegExp("<br\\b[^>]*>", "g");
    return MAJ.trim(html.replace(linebreak, " "));
}

MAJ.pad = function(txt, padlength, padchar, padleft) {
    if (txt==null) {
        return "";
    } else {
        txt += "";
    }
    if (padlength==null) {
        padlength = 2;
    }
    if (padchar==null) {
        padchar = (isNaN(txt) ? " " : "0");
    }
    if (padleft==null) {
        padleft = (isNaN(txt) ? false : true);
    }
    while (txt.length < padlength) {
        if (padleft) {
            txt = (padchar + txt);
        } else {
            txt = (txt + padchar);
        }
    }
    return txt;
}

MAJ.trim = function(str) {
    if (str==null) {
        return "";
    } else {
        str += "";
    }
    var inner = new RegExp("\\s+", "g");
    var outer = new RegExp("(^\\s+)|(\\s+$)", "g");
    return str.replace(outer, "").replace(inner, " ");
}

// ==========================================
// get form values
// ==========================================

MAJ.form_startfinish = function(starthours, startmins, finishhours, finishmins) {
    return MAJ.pad(starthours) + MAJ.str.labelsep + MAJ.pad(startmins) +
           MAJ.str.durationseparator +
           MAJ.pad(finishhours) + MAJ.str.labelsep + MAJ.pad(finishmins);
}

MAJ.form_duration = function(starthours, startmins, finishhours, finishmins) {
    var duration = 0;
    if (finishhours < starthours) {
        duration = 23;
    }
    if (finishhours == starthours && finishmins <= startmins) {
        duration = 23; // very unusual, probably a mistake
    }
    duration = (60 * (duration + finishhours - starthours));
    duration = MAJ.pad(duration + finishmins - startmins);
    return duration;
}

MAJ.form_value = function(elm, name, returnnumber) {
    var x = $(elm).find("[name=" + name + "]");
    if (x.length) {
        switch (true) {
            case x.is("select"): x = x.find("option:checked"); break;
            case x.is("input[type=radio]"): x = x.find("input:checked"); break;
            case x.is("input[type=checkbox]"): x = (x.is(":checked") ? x : ""); break;
        }
    }
    if (x.length==0) {
        return (returnnumber ? 0 : "");
    }
    x = x.val();
    return (returnnumber ? parseInt(x) : x);
}

MAJ.form_values = function(elm, prefix, returnnumber) {
    var values = [];
    $(elm).find("[name^=" + prefix + "]").each(function(){
        var name = $(this).prop("name");
        var i = parseInt(name.substr(prefix.length));
        values[i] = MAJ.form_value(elm, name, returnnumber);
    });
    return values;
}

MAJ.form_multilang_values = function(elm, name) {
    var values = {};
    var lang, value;
    $(elm).find("input[name^=" + name + "]").each(function(){
        lang = $(this).prop("name").substr(name.length + 1);
        if (value = MAJ.trim($(this).val())) {
            values[lang] = MAJ.force_simple_html(value);
        }
    });
    return values;
}

// ==========================================
// main processing after page has loaded
// ==========================================

$(document).ready(function(){

    // extract toolroot URL and block instance id from page URL
    var blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
    var toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
    var id = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");
    var iconroot = $("img.iconhelp").prop("src").replace(new RegExp("/[^/]+$"), "");

    MAJ.iconedit = iconroot + "/i/edit";
    MAJ.iconremove = iconroot + "/i/delete";

    // hide "Session information" section of form
    $("#id_sessioninfo").css("display", "none");

    // fetch CSS and JS files
    $("<link/>", {
        rel: "stylesheet", type: "text/css",
        href: toolroot + "/styles.css"
    }).appendTo("head");

    $("<link/>", {
        rel: "stylesheet", type: "text/css",
        href: blockroot + "/templates/template.css"
    }).appendTo("head");

    $.getScript(blockroot + "/templates/template.js")

    // In order to delay making the Schedule/Items elements editable
    // until after all the  strings have loaded, we created a Deferred
    // object that is resolved when the strings are available
    var loadstrings = $.Deferred();

    // load the language strings
    var p = "?id=" + id + "&action=loadstrings"
    $.getScript(toolroot + "/action.php" + p).done(function(){
        loadstrings.resolve();
    });

    // create Tools area
    var tools = $("<div></div>", {"id" : "tools"}).insertAfter("#id_sessioninfo");

    // populate Tools area
    var p = {"id" : id, "action" : "loadtools"};
    tools.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
            MAJ.setup_tools();
        } else if (s=="error") {
            $(this).html(MAJ.format_ajax_error(p.action, r, x));
        }
    });

    // create Schedule area
    var schedule = $("<div></div>", {"id" : "schedule"}).insertAfter("#tools");

    // populate Schedule area
    var p = {"id" : id, "action" : "loadschedule"};
    schedule.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
            MAJ.hide_multilang_spans(this);
            MAJ.make_sessions_droppable(this);
            MAJ.make_sessions_draggable(this);
            MAJ.make_sessions_selectable(this);
            $.when(loadstrings).done(function(){
                var x = document.querySelector("table.schedule");
                MAJ.make_sessions_editable(x);
                MAJ.make_rooms_editable(x);
                MAJ.make_slots_editable(x);
                MAJ.make_days_editable(x);
            });
        } else if (s=="error") {
            $(this).html(MAJ.format_ajax_error(p.action, r, x));
        }
    });

    // create Items area
    var items = $("<div></div>", {"id" : "items", "class" : "schedule"}).insertAfter("#schedule");

    // populate Items area
    var p = {"id" : id, "action" : "loaditems"};
    items.load(toolroot + "/action.php", p, function(r, s, x){
        // r : response text
        // s : status text ("success" or "error")
        // x : XMLHttpRequest object
        if (s=="success") {
            $(this).html(r);
            MAJ.make_sessions_draggable(this);
            $.when(loadstrings).done(function(){
                var x = document.getElementById("items");
                MAJ.make_sessions_selectable(x);
                MAJ.make_sessions_editable(x);
            });
        } else if (s=="error") {
            $(this).html(MAJ.format_ajax_error(p.action, r, x));
        }
    });

    // set "schedule_html" when form is submitted
    $("form.mform").submit(function(evt){
        MAJ.set_schedule_html();
        MAJ.set_schedule_unassigned();
    });
});
