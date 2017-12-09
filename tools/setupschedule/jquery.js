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
MAJ.targetsession = null;

// TODO: initialize this array from the PHP script on the server
//       blocks/maj_submissions/tools/setupschedule/action.php
MAJ.sessiontypes = "casestudy|lightningtalk|presentation|showcase|workshop";

// define selectors for session child nodes
MAJ.sessiontimeroom = ".time, .room";
MAJ.sessioncontent = ".title, .authors, .categorytype, .summary";

MAJ.updaterecord = function(session) {
}

MAJ.clicksession = function(targetsession) {

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
    // if (a), both sessions are non-empty
    // or (b), the sessions have the same tagName
    // i.e. they are both TD cells in the schedule TABLE
    // or they are both DIVs in the #items area of the form
    var swap = (targetIsEmpty==false && sourceIsEmpty==false);
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
	// whereas the other is not empty

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

MAJ.droppable = function(container, item) {
    if (item) {
        var target = $(item);
    } else {
        var target = $(container).find("td.session");
    }
    target.droppable({
        "accept" : ".session",
        "drop" : function(evt, ui) {
            $(this).removeClass("ui-dropping");
            MAJ.clicksession(this);
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

MAJ.draggable = function(container, item) {
    if (item) {
        var target = $(item);
    } else {
        var target = $(container).find(".session");
    }
    target.draggable({
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

MAJ.selectable = function(container, item) {
    if (item) {
        var target = $(item);
    } else {
        var target = $(container).find(".session");
    }
    target.click(function(){
        MAJ.clicksession(this);
    });
}

MAJ.multilang = function(container) {
    // extract main language from body classes
    var regexp = new RegExp("lang-(\\w+)");
    var lang = $("body").attr('class').match(regexp)[1];

    // hide SPANs that are not for main language
    $(container).find("span.multilang[lang!=" + lang + "]").css("display", "none");
}

MAJ.setuptools = function() {
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

MAJ.setupitems = function() {
    var s = $("table.schedule");
    var i = $("#items");
    if (s.length && i.length) {
        var w = s.width();
        w = (50 * parseInt(w / 50));
        i.css("max-width", w + "px");
    } else {
        setTimeout(MAJ.setupitems, 500);
    }
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

MAJ.initializeschedule = function(evt, day) {
    // select all non-empty sessions on the selected day
    if (day=="alldays") {
        var dayselector = ".day";
    } else {
        var dayselector = "." + day;
    }
}

MAJ.emptyschedule = function(evt, day) {

    // select all non-empty sessions on the selected day
    if (day=="alldays") {
        var dayselector = ".day";
    } else {
        var dayselector = "." + day;
    }
    $(dayselector + " .session").not(".emptysession").each(function(){

        // remove classes used on templates
		$(this).removeClass("demo attending");

		// remove capacity info
		$(this).find(".capacity").remove();

		// move session details to #items container
		var id = $(this).prop("id");
		if (id.indexOf("id_recordid_")==0) {
			// sessions with a recognized "id" are moved to the "#items" DIV
			var div = $("<div></div>", {
				"id" : id,
				"style" : "display: inline-block;",
				"class" : MAJ.get_non_jquery_classes(this)
			});
			$(this).prop("id", "");
			$(this).children(MAJ.sessioncontent).appendTo(div);
			MAJ.draggable(null, div);
			MAJ.selectable(null, div);
			$("#items").append(div);
		} else {
			// remove sessions without a recognized "id"
			// i.e. "demo" sessions in schedule templates
			$(this).find(MAJ.sessioncontent).remove();
		}

        // mark this session as empty
		$(this).addClass("emptysession");
    });

	// remove any "demo" sessions in the #items container
    $("#items .session").not("div[id^=id_record]").each(function(){
		$(this).remove();
    });
}

MAJ.populateschedule = function(evt, day) {

    // cancel previous clicks on sessions, if any
    if (MAJ.sourcesession) {
        MAJ.clicksession(MAJ.sourcesession);
    }

    // select empty sessions on the selected day
    if (day=="alldays") {
        var dayselector = ".day";
    } else {
        var dayselector = "." + day;
    }
    var empty = $(dayselector + " .session.emptysession");

    if (empty.length==0) {
        // no empty sessions on the selected days
        return true;
    }

    // select all unassigned sessions
    var items = $("#items .session");

    if (items.length==0) {
        // no unassigned sessions
        return true;
    }

    // mimic clicks to assign sessions
    var i_max = Math.min(items.length,
                         empty.length);
    for (var i=(i_max-1); i>=0; i--) {
        MAJ.clicksession(items.get(i));
        MAJ.clicksession(empty.get(i));
    }
    return true;
}


MAJ.renumberschedule = function(evt, day) {

    // select all non-empty sessions on the selected day
    if (day=="alldays") {
        var dayselector = ".day";
    } else {
        var dayselector = "." + day;
    }

	// initialize RegExp's to extract info from CSS class
    var dayregexp = new RegExp("^.*day(\\d+).*$");
    var slotregexp = new RegExp("^.*slot(\\d+).*$");
    var roomregexp = new RegExp("^.*room(\\d+).*$");
    var typeregexp = new RegExp("^.*(" + MAJ.sessiontypes + ").*$");

    $(dayselector + " .session").not(".emptysession").not(".demo").each(function(){

        var day = $(this).closest(".day");
        day = day.prop("class").replace(dayregexp, "$1");

        var slot = $(this).closest(".slot");
        slot = slot.prop("class").replace(slotregexp, "$1");

        var room = $(this).closest(".slot").prevAll(".roomheadings");
        if (room.length==0 || $(this).hasClass("allrooms")) {
			var room = 0;
        } else {
			room = room.first().find("th, td").eq(this.cellIndex);
			room = room.prop("class").replace(roomregexp, "$1");
        }

        var type = $(this).prop("class").replace(typeregexp, "$1").charAt(0).toUpperCase();

        var schedulenumber = (day + slot + room + "-" + type);
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

// set hide all sections when document has loaded
$(document).ready(function(){

    // extract toolroot URL and block instance id from page URL
    var blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
    var toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
    var id = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");

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
            MAJ.setuptools();
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
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
            MAJ.multilang(this);
            MAJ.droppable(this);
            MAJ.draggable(this);
            MAJ.selectable(this);
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
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
            MAJ.setupitems();
            MAJ.draggable(this);
            MAJ.selectable(this);
        } else if (s=="error") {
            $(this).html("Error " + x.status + ": " + x.statusText)
        }
    });
});
