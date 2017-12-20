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

MAJ.sourcesession = null;

// TODO: initialize this array from the PHP script on the server
//       blocks/maj_submissions/tools/setupschedule/action.php
MAJ.sessiontypes = "casestudy|lightningtalk|presentation|showcase|workshop";

// define selectors for session child nodes
MAJ.sessiontimeroom = ".time, .room";
MAJ.sessioncontent = ".title, .authors, .categorytype, .summary";

// the DOM id of the dialog box
MAJ.dialogid = "dialog";

MAJ.update_record = function(session) {
}

MAJ.set_schedule_html = function() {
    var html = $("#schedule").html();

    // remove YUI ids
    html = html.replace(new RegExp(' *\\bid="yui_[^"]*"', "g"), "");

    // remove jQuery CSS classes
    html = html.replace(new RegExp(' *\\bui-[a-z0-9_-]*', "g"), "");

    // reset hidden multilang SPANs
    html = html.replace(new RegExp(' *\\bdisplay: *none;*', "g"), "");

    // remove leading space from class/style values
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

MAJ.get_non_jquery_classes = function(elm) {
    var classes = $(elm).prop('class').split(new RegExp("\\s+"));
    var i_max = (classes.length - 1);
    for (var i=i_max; i>=0; i--) {
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
        dialog.dialog({"autoOpen": false, "modal": true, "close": MAJ.select_session});
    } else {
        if (dialog.dialog("isOpen")) {
            dialog.dialog("close");
        }
    }

    // update the dialog title
    dialog.dialog("option", "title", title);

    // update the dialog HTML
    dialog.html(html);

    // set default button text/icon/function

    // update the dialog buttons
    var buttons = [];
    if (showactionbutton) {
        if (actiontext==null) {
            actiontext = "OK";
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
        var canceltext = "Cancel";
        var cancelicon = "ui-icon-cancel";
        var cancelfunction = function(){
            $(this).dialog("close");
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
    dialog.dialog("option", "position", {"my" : my, "at": "center", "of": evt});

    // open the dialog box
    dialog.dialog("open");

    // prevent the current click causing
    // the parent element to be selected
    evt.stopPropagation();
}

MAJ.show_edit_dialog = function(evt, title, html, actionfunction) {
    MAJ.open_dialog(evt, title, html, "Update", null, actionfunction, true);
}

MAJ.show_remove_dialog = function(evt, title, html, actionfunction) {
    MAJ.open_dialog(evt, title, html, "Remove", null, actionfunction, true);
}

MAJ.select_session = function(id) {
    if (MAJ.sourcesession) {
        MAJ.click_session(MAJ.sourcesession);
    }
    if (id) {
        MAJ.click_session(document.getElementById(id));
    }
}

MAJ.edit_session = function(evt) {
    var id = $(this).closest(".session").prop("id");
    var recordid = id.replace(new RegExp("^id_recordid_(\\d+)$"), "$1");

    var title = "Session settings (rid=" + recordid + ")";
    var html = "<p>A form to edit a session</p>";
    var actionfunction = function(){
        var html = "<p>Session (rid=" + recordid + ") was updated</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.select_session(id);
    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.delete_session = function(evt) {
    var id = $(this).closest(".session").prop("id");
    var recordid = id.replace(new RegExp("^id_recordid_(\\d+)$"), "$1");

    var title = "Remove session (rid=" + recordid + ")";
    var html = "<p>A form to remove a session</p>";
    var actionfunction = function(){

        // add new empty session to #items
        var emptysession = document.createElement("DIV");
        emptysession.setAttribute("class", "session emptysession");
        MAJ.make_sessions_draggable(null, emptysession);
        MAJ.make_sessions_selectable(null, emptysession);
        $("#items").append(emptysession);

        // deselect current session
        MAJ.select_session();

        // swap the empty session and the target session
        MAJ.click_session(emptysession);
        MAJ.click_session(document.getElementById(id), true);
        
        var html = "<p>Session (rid=" + recordid + ") was removed</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.select_session(id);
    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.edit_room = function(evt) {
    var room = $(this).closest(".roomheading").prop("class");
    room = room.replace(new RegExp("^.*\\broom(\\d+)\\b.*$"), "$1");

    var title = "Room settings (room=" + room + ")";
    var html = "<p>A form to edit a room</p>";
    var actionfunction = function(){
        var html = "<p>Room " + room + " was updated</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.delete_room = function(evt) {
    var room = $(this).closest(".roomheading").prop("class");
    room = room.replace(new RegExp("^.*\\broom(\\d+)\\b.*$"), "$1");

    var title = "Remove room (room=" + room + ")";
    var html = "<p>A form to remove a room</p>";
    var actionfunction = function(){
        var html = "<p>Room " + room + " was removed</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.edit_slot = function(evt) {
    var slot = $(this).closest(".slot").prop("class");
    slot = slot.replace(new RegExp("^.*\\bslot(\\d+)\\b.*$"), "$1");

    var title = "Slot settings (slot=" + slot + ")";
    var html = "<p>A form to edit a slot</p>";
    var actionfunction = function(){
        var html = "<p>Slot " + slot + " was updated</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.delete_slot = function(evt) {
    var slot = $(this).closest(".slot").prop("class");
    slot = slot.replace(new RegExp("^.*\\bslot(\\d+)\\b.*$"), "$1");

    var title = "Remove slot (slot=" + slot + ")";
    var html = "<p>A form to remove a slot</p>";
    var actionfunction = function(){
        var html = "<p>Slot " + slot + " was removed</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.edit_day = function(evt) {
    var day = $(this).closest(".tab").prop("class");
    day = day.replace(new RegExp("^.*\\bday(\\d+)\\b.*$"), "$1");

    var title = "Day settings (day=" + day + ")";
    var html = "<p>A form to edit a day</p>";
    var actionfunction = function(){
        var html = "<p>Day " + day + " was updated</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.show_edit_dialog(evt, title, html, actionfunction);
}

MAJ.delete_day = function(evt) {
    var day = $(this).closest(".tab").prop("class");
    day = day.replace(new RegExp("^.*\\bday(\\d+)\\b.*$"), "$1");

    var title = "Remove day (day=" + day + ")";
    var html = "<p>A form to remove a day</p>";
    var actionfunction = function(){
        var html = "<p>Day " + day + " was removed</p>";
        MAJ.open_dialog(evt, title, html, "OK");
    };

    MAJ.show_remove_dialog(evt, title, html, actionfunction);
}

MAJ.create_icons = function(type, id) {
    var icons = document.createElement("SPAN");
    icons.setAttribute("class", "icons");

    var iconedit = document.createElement("IMG");
    iconedit.setAttribute("src", MAJ.iconedit);
    iconedit.setAttribute("title", "Edit this session");
    iconedit.setAttribute("class", "icon");
    if (MAJ["edit_" + type]) {
        $(iconedit).click(MAJ["edit_" + type]);
    }

    var icondelete = document.createElement("IMG");
    icondelete.setAttribute("src", MAJ.icondelete);
    icondelete.setAttribute("title", "Delete this session from the schedule");
    icondelete.setAttribute("class", "icon");
    if (MAJ["delete_" + type]) {
        $(icondelete).click(MAJ["delete_" + type]);
    }

    if (id) {
        icons.setAttribute("id", id + "_icons");
        iconedit.setAttribute("id", id + "_edit");
        icondelete.setAttribute("id", id + "_delete");
    }

    icons.appendChild(iconedit);
    icons.appendChild(icondelete);

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

    // we need to swap these two sessions
    // if (a) if "forceswap is TRUE
    // or (b), both sessions are non-empty
    // or (c), the sessions have the same tagName
    // i.e. they are both TD cells in TABLE.schedule
    // or they are both DIVs in the #items area of the form
    var swap = forceswap;
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
        $(MAJ.sourcesession).children(MAJ.sessiontimeroom).appendTo(tempsource);
        $(targetsession).children(MAJ.sessioncontent).appendTo(tempsource);
        $(MAJ.sourcesession).children(".capacity").appendTo(tempsource);

        // move children to temp target
        $(targetsession).children(MAJ.sessiontimeroom).appendTo(temptarget);
        $(MAJ.sourcesession).children(MAJ.sessioncontent).appendTo(temptarget);
        $(targetsession).children(".capacity").appendTo(temptarget);

        // switch the attendance nodes
        var sourceattendance = $(tempsource).find(".capacity .attendance").detach();
        var targetattendance = $(temptarget).find(".capacity .attendance").detach();
        $(temptarget).find(".capacity").append(sourceattendance);
        $(tempsource).find(".capacity").append(targetattendance);

        // move children to real source and target
        $(temptarget).children().appendTo(targetsession);
        $(tempsource).children().appendTo(MAJ.sourcesession);

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
    // move target to source, then delete target
    if (sourceIsEmpty && targetIsEmpty==false) {
        empty = MAJ.sourcesession;
        nonempty = targetsession;
    }

	// if target is empty and source is not, then
    // move source to target, then delete source
    if (sourceIsEmpty==false && targetIsEmpty) {
        empty = targetsession;
        nonempty = MAJ.sourcesession;
    }

    if (empty && nonempty) {

        $(empty).addClass("ui-selected");

        // remove "time" and "room" DIVs
        $(empty).find(MAJ.sessiontimeroom).remove();

        // set time DIV (includes duration)
        var div = $("<div></div>", {"class" : "time"});
        $(div).html($(empty).parent(".slot")
                            .find(".timeheading")
                            .html());
        $(div).appendTo(empty);

        // set room DIV (includes roomname, roomseats, roomtopic)
        var div = $("<div></div>", {"class" : "room"});
        $(div).html($(empty).parent(".slot")
                           .prevAll(".roomheadings")
                           .first() // most recent TR
                           .find("th, td")
                           .eq(empty.cellIndex)
                           .html());
        $(div).appendTo(empty);

        // transfer DOM "id"
        var id = $(nonempty).prop("id");
        $(empty).prop("id", id);

        // transfer CSS classes
        var emptyclasses = MAJ.get_non_jquery_classes(empty);
        var nonemptyclasses = MAJ.get_non_jquery_classes(nonempty);
        $(empty).removeClass(emptyclasses).addClass(nonemptyclasses);

        // transfer content elements
        $(nonempty).children(MAJ.sessioncontent).appendTo(empty);

        // deselect empty session
        $(empty).removeClass("ui-selected");

        // the nonempty session is now empty
        // and can be removed from the DOM
        $(nonempty).remove();
        nonempty = null;

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
    MAJ.get_items(container, room, ".roomheading").each(function(){
        var icons = MAJ.create_icons("room");
        $(this).prepend(icons);
    });
}

MAJ.make_slots_editable = function(container, slot) {
    MAJ.get_items(container, slot, ".slot .timeheading").each(function(){
        var icons = MAJ.create_icons("slot");
        var txt = document.createTextNode(" ");
        $(this).append(txt, icons);
    });
}

MAJ.make_days_editable = function(container, day) {
    MAJ.get_items(container, day, ".tab").each(function(){
        var icons = MAJ.create_icons("day");
        var txt = document.createTextNode(" ");
        $(this).append(txt, icons);
    });
}

MAJ.hide_multilang_spans = function(container) {
    // extract main language from body classes
    var regexp = new RegExp("lang-(\\w+)");
    var lang = $("body").attr('class').match(regexp)[1];

    // hide SPANs that are not for main language
    $(container).find("span.multilang[lang!=" + lang + "]").css("display", "none");
}

MAJ.setup_tools = function() {
    var missing = [];
    $("#tools .command").each(function(){
        var activecommand = true;
        $(this).find(".subcommand").each(function(){
            // extract c(ommand) and s(ubcommand)
            // from id, e.g. addslot-above
            $(this).click(function(evt){
                var id = $(this).prop("id");
                var i = id.indexOf("-");
                var c = id.substring(0, i);
                var s = id.substring(i + 1);
                MAJ[c](evt, s);
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

MAJ.setup_items = function() {
    var s = $("table.schedule");
    var i = $("#items");
    if (s.length && i.length) {
        var w = s.width();
        w = (50 * parseInt(w / 50));
        i.css("max-width", w + "px");
    } else {
        setTimeout(MAJ.setup_items, 500);
    }
}

MAJ.initializeschedule = function(evt, day) {

    // empty the current schedule
    MAJ.emptyschedule(evt, day);

    // initialize each required day
    $(MAJ.get_day_selector(day)).each(function(){
    });
}

MAJ.emptyschedule = function(evt, day) {

    // select all sessions on the selected day
    $(MAJ.get_day_selector(day, " .session")).each(function(){

        // remove classes used on templates
		$(this).removeClass("demo attending");

		// empty capacity info (but leave the DOM structure)
		$(this).find(".capacity").each(function(){
            $(this).find(".emptyseats").empty();
            $(this).find(".attendance").each(function(){
                $(this).find("input[type=checkbox]").removeAttr("checked");
                $(this).find("label").empty();
            });
		});

        // move/remove the contents of non-empty sessions
        if ($(this).not(".emptysession")) {

            // move session details to #items container
            var id = $(this).prop("id");
            if (id.indexOf("id_recordid_")==0) {
                // sessions with a recognized "id" are moved to the "#items" DIV
                var div = $("<div></div>", {
                    "id" : id,
                    "class" : MAJ.get_non_jquery_classes(this)
                });
                $(this).prop("id", "");
                $(this).children(MAJ.sessioncontent).appendTo(div);
                MAJ.make_sessions_draggable(null, div);
                MAJ.make_sessions_selectable(null, div);
                $("#items").append(div);
            } else {
                // remove sessions without a recognized "id"
                // i.e. "demo" sessions in schedule templates
                $(this).find(MAJ.sessioncontent).remove();
            }

            // mark this session as empty
            $(this).addClass("emptysession");
        }
    });

	// remove any "demo" sessions in the #items container
    $("#items .session").not("div[id^=id_record]").each(function(){
		$(this).remove();
    });
}

MAJ.populateschedule = function(evt, day) {

    // cancel previous clicks on sessions, if any
    MAJ.select_session();

    // select empty sessions on the selected day
    var empty = $(MAJ.get_day_selector(day, " .emptysession"));
    if (empty.length==0) {
        return true;
    }

    // select all unassigned sessions
    var items = $("#items .session");
    if (items.length==0) {
        return true;
    }

    // mimic clicks to assign sessions
    var i_max = Math.min(items.length,
                         empty.length);
    for (var i=(i_max-1); i>=0; i--) {
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

MAJ.addday = function(evt, pos) {
}

MAJ.addslot = function(evt, pos) {
}

MAJ.addroom = function(evt, pos) {
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

// main processing after page has loaded
$(document).ready(function(){

    // extract toolroot URL and block instance id from page URL
    var blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
    var toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
    var id = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");
    var iconroot = $("img.iconhelp").prop("src").replace(new RegExp("/[^/]+$"), "");

    MAJ.iconedit = iconroot + "/i/edit";
    MAJ.icondelete = iconroot + "/i/delete";

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
            MAJ.make_sessions_editable(this);
            MAJ.make_rooms_editable(this);
            MAJ.make_slots_editable(this);
            MAJ.make_days_editable(this);
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
            MAJ.setup_items();
            MAJ.make_sessions_draggable(this);
            MAJ.make_sessions_selectable(this);
            MAJ.make_sessions_editable(this);
        } else if (s=="error") {
            $(this).html(MAJ.format_ajax_error(p.action, r, x));
        }
    });

    // set "schedule_html" when form is submitted
    $("form.mform").submit(function(evt){
        MAJ.set_schedule_html();
    });
});
