// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the term of the GNU General Public License as published by
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
 * JS required to setup schedule
 *
 * @module      block_maj_submissions/tool_setupschedule
 * @category    output
 * @copyright   Gordon Bateson
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since       2.9
 */
define(["jquery", "jqueryui", "core/str", // split this define statement because
        "block_maj_submissions/html", // "grunt" doesn't like long lines of code
        "block_maj_submissions/unicode"], function($, JUI, STR, HTML, UNICODE) {

    /** @alias module:block_maj_submissions/tool_setupschedule */
    var TOOL = {};

    // constants
    TOOL.APPLY_NONE    = 0;
    TOOL.APPLY_CURRENT = 1;
    TOOL.APPLY_THISDAY = 2;
    TOOL.APPLY_ALLDAYS = 3;

    TOOL.sourcesession = null;

    // TODO: initialize this array from the PHP script on the server
    //       blocks/maj_submissions/tools/setupschedule/action.php
    TOOL.sessiontypes = "case|event|featured|keynote|lightning|paper|poster|presentation|showcase|virtual|workshop";

    // define selectors for session child nodes
    TOOL.sessiontimeroom = ".time, .room";
    TOOL.sessioncontent = ".title, .authors, .categorytypetopic, .summary, .scheduleinfo";

    // define the selectors for room content
    TOOL.details = {"roomname": null, "roomseats": null, "roomtopic": null};

    // the DOM id of the dialog box
    TOOL.dialogid = "dialog";

    // initialize string cache
    TOOL.str = {};

    TOOL.init = function(opts) {

        // cache the opts, if any, passed from the server
        if (opts) {
            for (var i in opts) {
                TOOL[i] = opts[i];
            }
        }

        // extract URL and block instance id from page URL
        TOOL.wwwroot = location.href.replace(new RegExp("^(.*?)/blocks.*$"), "$1");
        TOOL.blockroot = location.href.replace(new RegExp("^(.*?)/tools.*$"), "$1");
        TOOL.toolroot = location.href.replace(new RegExp("^(.*?)/tool.php.*$"), "$1");
        TOOL.pageid = location.href.replace(new RegExp("^.*?\\bid=([0-9]+)\\b.*$"), "$1");
        if ($("img.iconhelp").length==0) {
            TOOL.iconroot = TOOL.wwwroot + "/pix"; // Moodle >= 3.7
            TOOL.iconedit = TOOL.iconroot + "/i/edit.svg";
            TOOL.iconremove = TOOL.iconroot + "/i/delete.svg";
        } else {
            TOOL.iconroot = $("img.iconhelp").prop("src").replace(new RegExp("/[^/]+$"), "");
            TOOL.iconedit = TOOL.iconroot + "/i/edit";
            TOOL.iconremove = TOOL.iconroot + "/i/delete";
        }

        // hide "Session information" section of form
        $("#id_sessioninfo").css("display", "none");

        // fetch CSS and JS files
        $("<link/>", {
            rel: "stylesheet", type: "text/css",
            href: TOOL.toolroot + "/styles.css"
        }).appendTo("head");

        $("<link/>", {
            rel: "stylesheet", type: "text/css",
            href: TOOL.blockroot + "/templates/template.css"
        }).appendTo("head");

        $.getScript(TOOL.blockroot + "/templates/template.js");

        // In order to delay making the Schedule/Items elements editable
        // until after all the  strings have loaded, we created a Deferred
        // object that is resolved when the strings are available
        var loadstrings = $.Deferred();

        // load the language strings
        $.ajax({
            "url": TOOL.toolroot + "/action.php",
            "data": {
                "id": TOOL.pageid,
                "action": "loadstrings"
            },
            "dataType": "text",
            "method": "GET",
            "success": function(txt) {
                var regexp = new RegExp("MAJ", "g");
                // eslint-disable-next-line no-eval
                eval(txt.replace(regexp, "TOOL"));
                loadstrings.resolve();
            }
        });

        // create Tools area
        var tools = $("<div></div>", {"id": "tools"}).insertAfter("#id_sessioninfo");

        // populate Tools area
        var p = {"id": TOOL.pageid, "action": "loadtools"};
        tools.load(TOOL.toolroot + "/action.php", p, function(r, s, x){
            // r : response text
            // s : status text ("success" or "error")
            // x : XMLHttpRequest object
            if (s=="success") {
                $(this).html(r);
                TOOL.setup_tools(this);
            } else if (s=="error") {
                $(this).html(TOOL.format_ajax_error(p.action, r, x));
            }
        });

        // create Schedule area
        var schedule = $("<div></div>", {"id": "schedule"}).insertAfter("#tools");

        // populate Schedule area
        var p = {"id": TOOL.pageid, "action": "loadschedule"};
        schedule.load(TOOL.toolroot + "/action.php", p, function(r, s, x){
            // r : response text
            // s : status text ("success" or "error")
            // x : XMLHttpRequest object
            if (s=="success") {
                $(this).html(r);
                TOOL.setup_schedule(this, loadstrings);
            } else if (s=="error") {
                $(this).html(TOOL.format_ajax_error(p.action, r, x));
            }
        });

        // create Items area
        var items = $("<div></div>", {"id": "items", "class": "schedule"}).insertAfter("#schedule");

        // populate Items area
        var p = {"id": TOOL.pageid, "action": "loaditems"};
        items.load(TOOL.toolroot + "/action.php", p, function(r, s, x){
            // r : response text
            // s : status text ("success" or "error")
            // x : XMLHttpRequest object
            if (s=="success") {
                $(this).html(r);
                TOOL.setup_items(this, loadstrings);
            } else if (s=="error") {
                $(this).html(TOOL.format_ajax_error(p.action, r, x));
            }
        });

        // set "schedule_html" when form is submitted
        $("form.mform").submit(function(){
            TOOL.set_schedule_html();
            TOOL.set_schedule_unassigned();
        });
    };

    // ==========================================
    // setup tools, schedule and items
    // ==========================================

    TOOL.setup_tools = function(tools) {
        $(tools).find(".command").each(function(){
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
                    if (TOOL[a]) {
                        TOOL[a](evt);
                    } else if (TOOL[c]) {
                        TOOL[c](evt, s);
                    }
                });
                activecommand = false;
            });
            if (activecommand) {
                $(this).click(function(evt){
                    var c = $(this).prop("id");
                    TOOL[c](evt);
                });
            }
        });
    };

    TOOL.setup_schedule = function(schedule, loadstrings){
        TOOL.clean_emptysessions(schedule);
        TOOL.setup_table_rooms(schedule);
        TOOL.fix_times_and_rooms(schedule);
        TOOL.fix_multislot_times(schedule);
        TOOL.hide_multilang_spans(schedule);
        TOOL.make_sessions_droppable(schedule);
        TOOL.make_sessions_draggable(schedule);
        TOOL.make_sessions_selectable(schedule);
        $.when(loadstrings).done(function(){
            var x = document.querySelector("table.schedule");
            TOOL.make_sessions_editable(x);
            TOOL.make_rooms_editable(x);
            TOOL.make_slots_editable(x);
            TOOL.make_days_editable(x);
        });
    };

    TOOL.setup_items = function(items, loadstrings){
        TOOL.make_sessions_draggable(items);
        $.when(loadstrings).done(function(){
            var x = document.getElementById("items");
            TOOL.make_sessions_selectable(x);
            TOOL.make_sessions_editable(x);
        });
    };

    TOOL.format_ajax_error = function(action, r, x) {
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
    };

    TOOL.clean_emptysessions = function(container) {
        $(container).find(".emptysession").each(function(){
            $(this).removeClass(TOOL.get_non_jquery_classes(this)).addClass("session emptysession");
        });
    };

    TOOL.setup_table_rooms = function(table) {
        var count = 0;
        $(table).find("tbody").each(function(){
            TOOL.setup_tbody_rooms(this);
            count = Math.max(count, $(this).data("roomcount"));
        });
        $(table).data("roomcount", count);
    };

    TOOL.setup_tbody_rooms = function(tbody) {
        var i = [];
        var count = 0;
        $(tbody).find("tr").each(function(){
            var roomx = new RegExp("\\broom\\d+\\b", "g");
            $(this).find("th.roomheading").each(function(r){
                $(this).prop("class", $(this).prop("class").replace(roomx, ""));
                $(this).addClass("room" + (r + 1));
            });
            var r = 0;
            $(this).find("th, td").each(function(){
                while (i[r]) {
                    i[r]--;
                    r++;
                }
                $(this).data("room", r);
                count = Math.max(count, r);
                var rs = $(this).prop("rowspan") || 1;
                var cs = $(this).prop("colspan") || 1;
                while (cs > 0) {
                    i[r] = (rs - 1) + (i[r] || 0);
                    r++;
                    cs--;
                }
                if ($(this).is(":last-child")) {
                    while (r < i.length) {
                        if (i[r]) {
                            i[r]--;
                        }
                        r++;
                    }
                }
            });
        });
        $(tbody).data("roomcount", count);
    };

    TOOL.fix_times_and_rooms = function(container) {
        $(container).find(".session").not(".multiroom").each(function(){
            TOOL.insert_timeroom(this);
        });
    };

    TOOL.hide_multilang_spans = function(container) {
        var lang = TOOL.extract_main_language();
        $(container).find("span.multilang[lang!=" + lang + "]").css("display", "none");
    };

    TOOL.make_sessions_droppable = function(container, session) {
        TOOL.get_items(container, session, "td.session").droppable({
            "accept": ".session",
            "drop": function() {
                $(this).removeClass("ui-dropping");
                TOOL.click_session(this);
            },
            "out": function() {
                $(this).removeClass("ui-dropping");
            },
            "over": function() {
                $(this).addClass("ui-dropping");
            },
            "tolerance": "pointer"
        });
    };

    TOOL.make_sessions_draggable = function(container, session) {
        TOOL.get_items(container, session, ".session").draggable({
            "cursor": "move",
            "scroll": true,
            "stack": ".session",
            "start": function() {
                TOOL.sourcesession = this;
                $(this).addClass("ui-dragging");
                $(this).removeClass("ui-selected");
                $(this).data("startposition", {
                    "top": $(this).css("top"),
                    "left": $(this).css("left")
                });
            },
            "stop": function() {
                $(this).removeClass("ui-dragging");
                var p = $(this).data("startposition");
                if (p) {
                    $(this).addClass("ui-dropping");
                    $(this).animate({
                        "top": p.top,
                        "left": p.left
                    }, function(){
                        $(this).removeClass("ui-dropping");
                    });
                }
            }
        });
    };

    TOOL.make_sessions_selectable = function(container, session) {
        TOOL.get_items(container, session, ".session").click(function(){
            TOOL.click_session(this);
        });
    };

    TOOL.make_sessions_editable = function(container, session) {
        TOOL.get_items(container, session, ".session").each(function(){
            var id = $(this).prop("id");
            if (id.indexOf("id_record")==0) {
                var icons = TOOL.icons("session");
                $(this).find(".title").prepend(icons);
            }
        });
    };

    TOOL.make_rooms_editable = function(container, room) {
        TOOL.get_items(container, room, ".roomheadings").each(function(){
            $(this).find(".timeheading").each(function(){
                var icons = TOOL.icons("roomheadings");
                $(this).prepend(icons);
            });
            $(this).find(".roomheading").each(function(){
                var icons = TOOL.icons("room");
                $(this).prepend(icons);
            });
        });
    };

    TOOL.make_slots_editable = function(container, slot) {
        TOOL.get_items(container, slot, ".slot").each(function(){
            $(this).find(".timeheading").each(function(){
                var icons = TOOL.icons("slot");
                var txt = document.createTextNode(" ");
                $(this).append(txt, icons);
            });
        });
    };

    TOOL.make_days_editable = function(container, day) {
        TOOL.get_items(container, day, ".tab").each(function(){
            TOOL.make_day_editable(this);
        });
    };

    TOOL.make_day_editable = function(elm) {
        var icons = TOOL.icons("day");
        var txt = document.createTextNode(" ");
        $(elm).append(txt, icons);
    };

    TOOL.get_items = function(container, item, selector) {
        if (item) {
            return $(item);
        }
        return $(container).find(selector);
    };

    // ==========================================
    // event handlers for form submit
    // ==========================================

    TOOL.set_schedule_html = function() {
        var html = TOOL.trim($("#schedule").html());

        // remove YUI ids
        html = html.replace(new RegExp(' *\\bid="yui_[^"]*"', "g"), "");

        // remove jQuery CSS classes
        html = html.replace(new RegExp(' *\\bui-[a-z0-9_-]*', "g"), "");

        // reset inline styles for hidden multilang SPANs and jquery items
        html = html.replace(new RegExp(' *\\b(display|position|z-index): *[^;"]*;', "g"), "");

        // remove leading space from class/style counts
        html = html.replace(new RegExp('(\\b(class|style)=") +', "g"), "$1");

        // remove empty class/style attributes
        html = html.replace(new RegExp(' *\\b(class|style)=" *"', "g"), "");

        // standardize attribute order in multilang SPANs
        html = html.replace(new RegExp('(lang="[^"]*") (class="multilang")', "g"), "$2 $1");

        // remove added attributes e.g. style="", from multilang SPANs
        html = html.replace(new RegExp('(class="multilang") (lang="[^"]*")[^>]*', "g"), "$1 $2");

        // remove info about xml namespaces
        html = html.replace(new RegExp(' *\\bxml:\\w+="[^"]*"', "g"), "");

        // remove "icons" elements
        html = html.replace(new RegExp('<span\\b[^>]*class="icons"[^>]*>.*?</span>', "g"), "");

        // remove the "schedulechooser" element
        html = html.replace(new RegExp('<div\\b[^>]*class="schedulechooser"[^>]*>.*?</div>', "g"), "");

        $("input[name=schedule_html]").val(html);
    };

    TOOL.set_schedule_unassigned = function() {
        var ids = [];
        $("#items .session[id^=id_recordid]").each(function(){
            ids.push($(this).prop("id").substr(12));
        });
        $("input[name=schedule_unassigned]").val(ids.join(","));
    };

    // ==========================================
    // handlers for tool commands
    // ==========================================

    TOOL.initializeschedule = function(evt, day) {

        // empty the current schedule
        TOOL.emptyschedule(evt, day);

        // initialize each required day
        $(TOOL.get_day_selector(day)).each(function(){
        });
    };

    TOOL.emptyschedule = function(evt, day) {
        // process all sessions in all slots on the selected day
        $(TOOL.get_day_selector(day, " .slot")).each(function(){
            var r = 1;
            $(this).find(".session").each(function(){
                TOOL.unassign_session(this);
                r++;
                if ($(this).is(":last-child")) {
                    TOOL.insert_sessions(this, r);
                }
            });
        });

        // remove any "demo" sessions in the #items container
        $("#items .session").not("div[id^=id_record]").each(function(){
            $(this).remove();
        });
    };

    TOOL.populateschedule = function(evt) {

        var title = TOOL.get_string("populateschedule");
        title = TOOL.force_single_line(title);

        // start HTML for dialog
        var html = "";
        html += "<table><tbody>";

        // add menus for category, type, topic
        var menus = {"selectrooms": "",
                     "day":      "days",
                     "room":     "rooms",
                     "selectitems": "",
                     "category": "categories",
                     "type":     "types",
                     "topic":    "topics",
                     "time":     "times",
                     "keyword":  "keywords"};
        for (var name in menus) {
            var create_menu = TOOL[menus[name]]; // TOOL method to create menu
            if (TOOL.empty(create_menu)) {
                // heading
                var heading = HTML.tag("h4", TOOL.str[name]);
                html += HTML.starttag("tr", {"valign": "top"})
                     + HTML.tag("td", heading, {"colspan": "3"})
                     + HTML.endtag("tr");
            } else {
                var menu = create_menu(name, "", {"size": 3});
                if (menu) {
                    html += HTML.starttag("tr", {"valign": "top"})
                         + HTML.tag("th", TOOL.str[name])
                         + HTML.tag("td", menu, {"colspan": "2"})
                         + HTML.endtag("tr");
                }
            }
        }

        // finish HTML
        html += "</tbody></table>";

        var actionfunction = function(){

            var days = TOOL.form_values(this, "day");
            var rooms = TOOL.form_values(this, "room");
            var categories = TOOL.form_values(this, "category");
            var types = TOOL.form_values(this, "type");
            var topics = TOOL.form_values(this, "topic");
            var times = TOOL.form_values(this, "time");
            var keywords = TOOL.form_values(this, "keyword");

            // construct days selector
            if (days===undefined || days===null || days==="") {
                days = "";
            } else if (typeof(days)=="string") {
                days = ".date:contains('" + days + "')";
            } else {
                for (var i=0; i<days.length; i++) {
                    days[i] = ".date:contains('" + days[i] + "')";
                }
                days = days.join(", ");
            }

            // construct rooms selector
            if (rooms===undefined || rooms===null || rooms==="") {
                rooms = "";
            } else if (typeof(rooms)=="string") {
                rooms = ".roomname:contains('" + TOOL.extract_roomname_txt(rooms) + "')";
            } else {
                for (var i=0; i<rooms.length; i++) {
                    rooms[i] = ".roomname:contains('" + TOOL.extract_roomname_txt(rooms[i]) + "')";
                }
                rooms = rooms.join(", ");
            }

            // extract empty sessions (in the schedule)
            var empty = $(".day");
            if (days) {
                empty = empty.find(days).closest(".day");
            }
            empty = empty.find(".session");
            if (rooms) {
                empty = empty.find(rooms).closest(".session");
            }

            // remove multi-column and multiroom sessions
            empty = empty.filter(function(){
                return (this.colSpan===undefined ||
                        this.colSpan===null ||
                        this.colSpan===0 ||
                        this.colSpan===1);
            }).filter(".emptysession:not(.multiroom)");

            // construct categories selector
            if (categories===undefined || categories===null || categories==="") {
                categories = "";
            } else if (typeof(categories)=="string") {
                categories = ".category:contains('" + categories + "')";
            } else {
                for (var i=0; i<categories.length; i++) {
                    categories[i] = ".category:contains('" + categories[i] + "')";
                }
                categories = categories.join(", ");
            }

            // construct types selector
            if (types===undefined || types===null || types==="") {
                types = "";
            } else if (typeof(types)=="string") {
                types = ".type:contains('" + types + "')";
            } else {
                for (var i=0; i<types.length; i++) {
                    types[i] = ".type:contains('" + types[i] + "')";
                }
                types = types.join(", ");
            }

            // construct topics selector
            if (topics===undefined || topics===null || topics==="") {
                topics = "";
            } else if (typeof(topics)=="string") {
                topics = ".topic:contains('" + topics + "')";
            } else {
                for (var i=0; i<topics.length; i++) {
                    topics[i] = ".topic:contains('" + topics[i] + "')";
                }
                topics = topics.join(", ");
            }

            // construct times selector
            if (times===undefined || times===null || times==="") {
                times = "";
            } else if (typeof(times)=="string") {
                times = ".times .text:contains('" + times + "')";
            } else {
                for (var i=0; i<times.length; i++) {
                    times[i] = ".times .text:contains('" + times[i] + "')";
                }
                times = times.join(", ");
            }

            // construct keywords selector
            if (keywords===undefined || keywords===null || keywords==="") {
                keywords = "";
            } else if (typeof(keywords)=="string") {
                keywords = ".keywords .text:contains('" + keywords + "')";
            } else {
                for (var i=0; i<keywords.length; i++) {
                    keywords[i] = ".keywords .text:contains('" + keywords[i] + "')";
                }
                keywords = keywords.join(", ");
            }

            // extract target items (to be inserted)
            var items = $("#items .session");
            if (categories) {
                items = items.find(categories).closest(".session");
            }
            if (types) {
                items = items.find(types).closest(".session");
            }
            if (topics) {
                items = items.find(topics).closest(".session");
            }
            if (times) {
                items = items.find(times).closest(".session");
            }
            if (keywords) {
                items = items.find(keywords).closest(".session");
            }

            var added = false;

            var max = Math.min(items.length,
                               empty.length);

            for (var i=(max-1); i>=0; i--) {
                TOOL.click_session(items.get(i));
                TOOL.click_session(empty.get(i));
                added = true;
            }

            TOOL.redraw_schedule(added);
            TOOL.open_dialog(evt, title, TOOL.get_string("populatedschedule", max), TOOL.str.ok);
        };

        TOOL.show_add_dialog(evt, title, html, actionfunction);
    };

    TOOL.populateschedule_old = function(evt, day) {

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
        TOOL.select_session();

        // select empty sessions on the selected day
        var empty = $(TOOL.get_day_selector(day, " .emptysession:not(.multiroom)"));
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
            TOOL.click_session(items.get(i));
            TOOL.click_session(empty.get(i));
        }
        return true;
    };

    TOOL.renumberschedule = function(evt, day) {

        // initialize array of indexes, counts and multipliers
        var index = [];
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
        var typeregexp = new RegExp("^.*(" + TOOL.sessiontypes + ").*$");

        // select all non-empty sessions on the selected day
        $(TOOL.get_day_selector(day, " .session:not(.emptysession):not(.demo)")).each(function(){

            var day = $(this).closest(".day");
            day = day.prop("class").replace(dayregexp, "$1");

            var type = $(this).prop("class").replace(typeregexp, "$1").charAt(0).toUpperCase();

            if (this.className.indexOf("sharedsession") >= 0) {
                var items = this.querySelectorAll(".item");
            } else {
                var items = [this];
            }

            for (var i=0; i<items.length; i++) {

                if (smallschedule) {

                    var slot = $(this).closest(".slot");
                    slot = slot.prop("class").replace(slotregexp, "$1");

                    var room = $(this).closest(".slot").prevAll(".roomheadings");
                    if (room.length==0 || $(this).hasClass("multiroom")) {
                        var room = 0;
                    } else {
                        room = room.first().find("th, td").eq(this.cellIndex);
                        room = room.prop("class").replace(roomregexp, "$1");
                    }

                    var schedulenumber = (day + slot + room + "-" + type);

                } else {

                    if (TOOL.empty(index[day])) {
                        index[day] = 1;
                    } else {
                        index[day]++;
                    }

                    var schedulenumber = ((day * multiply.slots) + index[day]);
                    schedulenumber = (schedulenumber + "-" + type);
                }

                $(items[i]).find(".schedulenumber").text(schedulenumber);
            }
        });
    };

    TOOL.get_day_selector = function(day, details) {
        return "." + (day=="alldays" ? "day" : day) + (details ? details : "");
    };

    TOOL.scheduleinfo_add = function() {

        // remove all previous scheduleinfo
        TOOL.scheduleinfo_remove();

        // request scheduling info from server: "loadinfo"
        var p = {"id": TOOL.pageid, "action": "loadinfo"};
        $.getJSON(TOOL.toolroot + "/action.php", p, function(info){
            TOOL.info = info;
            TOOL.infoicons = {};
            //var multilang = new RegExp('<span[^>]*class="multilang"[^>]*>(.*?)</span>', "g");
            for (var type in TOOL.info.icons) {
                TOOL.infoicons[type] = {};
                for (var value in TOOL.info[type]) {
                    var i_max = TOOL.info[type][value].length;
                    for (var i=0; i<i_max; i++) {
                        var rid = TOOL.info[type][value][i];
                        var session = $("#id_recordid_" + rid);
                        if (session.length==0) {
                            continue; // shouldn't happen !!
                        }
                        var icon = TOOL.infoicons[type][value];
                        if (TOOL.empty(icon)) {
                            var count = 0;
                            for (icon in TOOL.infoicons[type]) {
                                count++;
                            }
                            if (count >= TOOL.info.icons[type].length) {
                                icon = value.charAt(0);
                            } else {
                                icon = TOOL.info.icons[type][count];
                            }
                            TOOL.infoicons[type][value] = icon;
                        }
                        var scheduleinfo = session.find(".scheduleinfo");
                        if (scheduleinfo.length==0) {
                            scheduleinfo = HTML.tag("div", "", {"class": "scheduleinfo"});
                            scheduleinfo = $(scheduleinfo).appendTo(session);
                        }
                        var div = scheduleinfo.find("." + type);
                        if (div.length==0) {
                            div = HTML.tag("div", "", {"class": type});
                            div = $(div).appendTo(scheduleinfo);
                        }
                        var span = false;
                        // eslint-disable-next-line no-loop-func
                        div.find(".text").each(function(){
                            if (this.innerHTML==value) {
                                span = true;
                            }
                        });
                        if (span==false) {
                            span = HTML.tag("span", icon, {"class": "icon",}) + HTML.tag("span", value, {"class": "text"});
                            span = HTML.tag("span", span, {"class": "icontext"});
                            $(span).appendTo(div);
                        }
                    }
                }
            }
        });
    };

    TOOL.scheduleinfo_remove = function() {
        $(".scheduleinfo").remove();
    };

    TOOL.update = function(evt, type) {
        var p = {"id": TOOL.pageid, "action": "update", "type": type};
        $.getJSON(TOOL.toolroot + "/action.php", p, function(records){
            for (var rid in records) {
                for (type in records[rid]) {
                    var newelm = $(records[rid][type]);
                    var oldelm = $("#id_recordid_" + rid + " ." + type);
                    oldelm.find(".icons").prependTo(newelm);
                    oldelm.replaceWith(newelm);
                }
            }
        });
    };

    // ==========================================
    // handlers to add, edit and remove day
    // ==========================================

    TOOL.add_day = function(evt) {
        // specify title
        var title = TOOL.str.addday;

        var position = TOOL.position("day", $(".tabs .tab").length);
        var daytext = TOOL.days("daytext");
        var slotstart = TOOL.hoursmins("start", 9, 0);

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

            TOOL.increment(slotcounts, $(this).find(".slot").length);

            $(this).find(".roomheadings").each(function(){
                TOOL.increment(roomcounts, $(this).find(".roomheading").length);
            });

            $(this).find(".timeheading .duration").each(function(){
                TOOL.increment(slotlengths, TOOL.extract_duration($(this).prop("class")));
            });

            var finishtime = null;
            $(this).find(".timeheading .startfinish").each(function(){
                var m = $(this).text().match(startfinish);
                if (m && m.length > 4) {
                    if (typeof(finishtime)=="number") {
                        var starttime = (60 * parseInt(m[1]) + parseInt(m[2]));
                        TOOL.increment(slotintervals, Math.abs(starttime - finishtime));
                    }
                    finishtime = (60 * parseInt(m[3]) + parseInt(m[4]));
                }
            });
        });

        // set default values for form elements
        var roomcount = TOOL.mode(roomcounts) || 5;
        var slotcount = TOOL.mode(slotcounts) || 10;
        var slotlength = TOOL.mode(slotlengths) || 25;
        var slotinterval = TOOL.mode(slotintervals) || 5;

        // create form elements
        roomcount = TOOL.range("roomcount", roomcount, 1, Math.max(10, TOOL.max(roomcounts)));
        slotcount = TOOL.range("slotcount", slotcount, 1, Math.max(20, TOOL.max(slotcounts)));
        slotlength = TOOL.mins("slotlength", slotlength, 10, Math.max(120, TOOL.max(slotlengths)));
        slotinterval = TOOL.mins("slotinterval", slotinterval, 0, Math.max(60, TOOL.max(slotintervals)));

        // create HTML for dialog
        var html = "";
        html += "<table><tbody>";
        html += "<tr>" + HTML.tag("th", TOOL.str.position) + HTML.tag("td", position) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.daytext) + HTML.tag("td", daytext) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.roomcount) + HTML.tag("td", roomcount) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.slotcount) + HTML.tag("td", slotcount) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.slotstart) + HTML.tag("td", slotstart) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.slotlength) + HTML.tag("td", slotlength) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.slotinterval) + HTML.tag("td", slotinterval) + "</tr>";
        html += "</tbody></table>";

        // specify action function for dialog button
        var actionfunction = function(){

            var position     = TOOL.form_value(this, "position",     true);
            var daytext      = TOOL.form_value(this, "daytext");
            var roomcount    = TOOL.form_value(this, "roomcount",    true);
            var slotcount    = TOOL.form_value(this, "slotcount",    true);
            var starthours   = TOOL.form_value(this, "starthours",   true);
            var startmins    = TOOL.form_value(this, "startmins",    true);
            var slotlength   = TOOL.form_value(this, "slotlength",   true);
            var slotinterval = TOOL.form_value(this, "slotinterval", true);

            var slotstart = (60 * parseInt(starthours) + parseInt(startmins));

            var oldclass = new RegExp("\\bday\\d+");
            $("table.schedule").each(function(){

                var d = 1;
                var added = false;
                $(this).find("tbody.day").each(function(index){
                    if (added==false && position <= index) {
                        added = true;
                        $(this).before(TOOL.day(d++, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval));
                    }
                    var cssclass = $(this).prop("class").replace(oldclass, "");
                    $(this).prop("class", TOOL.trim(cssclass) + " day" + d++);
                });
                if (added==false) {
                    added = true;
                    $(this).append(TOOL.day(d++, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval));
                }

                var tabtext = daytext;
                $(this).find(".tabs td").each(function(){
                    var d = 1;
                    var added = false;
                    $(this).find("div.tab").each(function(index){
                        if (added==false && position <= index) {
                            added = true;
                            $(this).before(TOOL.tab(d++, tabtext));
                        }
                        var cssclass = $(this).prop("class").replace(oldclass, "");
                        $(this).prop("class", TOOL.trim(cssclass) + " day" + d++);
                    });
                    if (added==false) {
                        added = true;
                        $(this).append(TOOL.tab(d++, tabtext));
                    }
                });
            });

            // renumber all slots on the new day
            TOOL.renumberslots(position);

            // set colspan of scheduletitle and tabs
            TOOL.set_schedule_colspan();

            TOOL.redraw_schedule(true);

            TOOL.open_dialog(evt, title, TOOL.str.addedday, TOOL.str.ok);
        };

        TOOL.show_add_dialog(evt, title, html, actionfunction);
    };

    TOOL.edit_day = function(evt) {
        var day = TOOL.extract_parent_tabday(this);

        var tab = $(this).closest(".tab");
        tab.addClass("ui-selected");

        var title = TOOL.str.editday + TOOL.str.labelsep + day;

        var html = "";
        html += "<table><tbody>";

        var name = "daytext";

        var span = tab.find("span.multilang");
        if (span.length==0) {
            var value = tab.contents().filter(TOOL.textnodes).text();
            html += "<tr>"
                 + HTML.tag("th", TOOL.str[name])
                 + HTML.tag("td", HTML.text(name, TOOL.trim(value)))
                 + "</tr>";
        } else {
            html += "<tr>"
                 + HTML.tag("td", "")
                 + TOOL.boldcenter("td", TOOL.str[name])
                 + "</tr>";
            span.each(function(){
                var lang = $(this).prop("lang");
                if (lang) {
                    html += "<tr>"
                         + HTML.tag("th", TOOL.str[lang] ? TOOL.str[lang] : lang)
                         + HTML.tag("td", HTML.text(name + "_" + lang, TOOL.trim($(this).html())))
                         + "</tr>";
                }
            });
        }

        html += "</tbody></table>";
        html += HTML.hidden("day", day);

        var actionfunction = function(){
            var html = TOOL.multilangs(this, "daytext");
            var day = TOOL.form_value(this, "day", true);

            $(".tab.day" + day).each(function(){
                $(this).contents().not(".icons").remove();
                $(this).prepend(html);
            });
            $(".day.day" + day + " .date td:first-child").each(function(){
                $(this).contents().remove();
                $(this).prepend(TOOL.force_single_line(html));
            });

            // update the day display on the Tools submenus
            $(".subcommand[id$=day" + day + "]").html(html);

            TOOL.open_dialog(evt, title, TOOL.str.editedday, TOOL.str.ok);
        };

        TOOL.show_edit_dialog(evt, title, html, actionfunction);
    };

    TOOL.remove_day = function(evt) {
        var day = TOOL.extract_parent_tabday(this);

        var tab = $(this).closest(".tab");
        tab.addClass("ui-selected");

        var lang = TOOL.extract_main_language();
        var daytext = tab.find(".multilang[lang=" + lang + "]").html();
        if (TOOL.empty(daytext)) {
            var regexp = new RegExp("\\s*<span[^>]*>.*?</span>", "g");
            daytext = tab.html().replace(regexp, "");
        }
        daytext = TOOL.force_single_line(daytext);

        var title = TOOL.str.removeday + TOOL.str.labelsep + day;

        var html = HTML.tag("p", TOOL.str.confirmday);
        html += HTML.alist("ul", [daytext]);
        html += HTML.hidden("targetday", day);

        var actionfunction = function(){
            var targetday = TOOL.form_value(this, "targetday", true);

            // remove tab for this day
            var d = 1;
            var activatetab = false;
            var oldclass = new RegExp("day\\d+");
            $(".tab[class*=day]").each(function(){
                var cssclass = $(this).prop("class");
                var day = TOOL.extract_day(cssclass);
                if (day==targetday) {
                    if ($(this).hasClass("active")) {
                        activatetab = true;
                    }
                    $(this).remove();
                } else {
                    cssclass = TOOL.trim(cssclass.replace(oldclass, ""));
                    $(this).prop("class", cssclass + " day" + d++);
                }
            });

            // unassign sessions on the target day
            $(".day.day" + targetday).each(function(){
                $(this).find(".session").each(function(){
                    TOOL.unassign_session(this, true);
                });
            });

            // if this day was active, then make another day active instead
            if (activatetab) {
                $(".tab").first().trigger("click");
            }

            // set colspan of scheduletitle and tabs
            TOOL.set_schedule_colspan();

            // remove this day from the Tools subcommands
            $(".subcommand[id$=day" + targetday + "]").remove();

            TOOL.open_dialog(evt, title, TOOL.str.removedday, TOOL.str.ok);
        };

        TOOL.show_remove_dialog(evt, title, html, actionfunction);
    };

    // ==========================================
    // helper functions to count frequencies
    // when adding a new day
    // ==========================================

    TOOL.increment = function(a, i) {
        if (TOOL.empty(a[i])) {
            a[i] = 1;
        } else{
            a[i]++;
        }
    };

    TOOL.mode = function(a) {
        var mode = null;
        var count = null;
        for (var i in a) {
            i = parseInt(i);
            if (TOOL.empty(count) || count < a[i] || (count==a[i] && mode < i)) {
                count = a[i];
                mode = i;
            }
        }
        return (TOOL.empty(mode) ? 0 : mode);
    };

    TOOL.max = function(a) {
        var max = null;
        for (var i in a) {
            i = parseInt(i);
            if (TOOL.empty(max) || max < i) {
                max = i;
            }
        }
        return (TOOL.empty(max) ? 0 : max);
    };

    // ==========================================
    // handlers to add, edit and remove room headings
    // ==========================================

    TOOL.add_roomheadings = function(evt) {

        var title = TOOL.str.addroomheadings;

        // start HTML for dialog
        var html = "";
        html += "<table><tbody>";

        // add checkboxes for days
        html += TOOL.days_checkbox("days");

        // add HTML for room names and topics
        var roomcount = TOOL.extract_max_roomcount();
        for (var r=1; r<=roomcount; r++) {
            if (r==1) {
                html += TOOL.html_roomheadings_toprow();
            }
            html += TOOL.html_roomheadings_datarow(null, r);
        }

        // finish HTML
        html += "</tbody></table>";

        var actionfunction = function(){

            var days = TOOL.form_values(this, "days_", true);
            var rooms = TOOL.form_values(this, "room_");
            var topics = TOOL.form_values(this, "topic_");

            var added = false;

            $(".day").each(function(){
                var day = TOOL.extract_day($(this).prop("class"));
                if (days[day]) {
                    var add = true;
                    $(this).find("tr").not(".date").each(function(){
                        if (TOOL.has_multiroom($(this))) {
                            add = true;
                        } else if (add) {
                            if ($(this).is(":not(.roomheadings)")) {
                                TOOL.roomheadings(day, rooms, topics).insertBefore(this);
                                added = true;
                            }
                            add = false;
                        }
                    });
                }
            });

            TOOL.redraw_schedule(added);
            TOOL.open_dialog(evt, title, TOOL.str.addedroomheadings, TOOL.str.ok);
        };

        TOOL.show_add_dialog(evt, title, html, actionfunction);

    };

    TOOL.edit_roomheadings = function(evt) {
        // extract day number and day text
        var day = TOOL.extract_parent_day(this);
        var row = TOOL.extract_parent_row(this);
        //var daytext = TOOL.extract_parent_daytext(this);

        var title = TOOL.str.editroomheadings;

        // start HTML for dialog
        var html = "";
        html += "<table><tbody>";

        // add HTML for room names and topics
        var r = 1;
        $(this).closest("th").nextAll(".roomheading").each(function(){
            if (r==1) {
                html += TOOL.html_roomheadings_toprow();
            }
            html += TOOL.html_roomheadings_datarow(this, r++);
        });

        if (r > 1) {
            html += TOOL.html_roomheadings_lastrow();
        }

        // finish HTML
        html += "</tbody></table>";

        html += HTML.hidden("day", day);
        html += HTML.hidden("row", row);

        var updated = false;
        var actionfunction = function(){
            var day = TOOL.form_value(this, "day", true);
            var row = TOOL.form_value(this, "row", true);

            var rooms = TOOL.form_values(this, "room_");
            var topics = TOOL.form_values(this, "topic_");
            var applyto = TOOL.form_value(this, "applyto");

            $("tbody.day").each(function(){
                var d = TOOL.extract_day($(this).prop("class"));
                if (applyto==TOOL.APPLY_CURRENT || applyto==TOOL.APPLY_THISDAY) {
                    var apply = (d==day);
                } else {
                    var apply = (applyto==TOOL.APPLY_ALLDAYS);
                }
                if (apply) {
                    $(this).find(".roomheadings").each(function(){
                        if (applyto==TOOL.APPLY_CURRENT) {
                            apply = (row==TOOL.extract_parent_row(this));
                        } else {
                            apply = true;
                        }
                        if (apply) {
                            $(this).replaceWith($(TOOL.html_roomheadings(day, rooms, topics)).each(function(){
                                TOOL.make_rooms_editable(null, this);
                            }));
                            updated = true;
                        }
                    });
                }
            });

            TOOL.redraw_schedule(updated);

            TOOL.open_dialog(evt, title, TOOL.str.editedroomheadings, TOOL.str.ok);
        };

        TOOL.show_edit_dialog(evt, title, html, actionfunction);
    };

    TOOL.remove_roomheadings = function(evt) {
        // extract day number and day text
        var day = TOOL.extract_parent_day(this);
        var row = TOOL.extract_parent_row(this);
        var daytext = TOOL.extract_parent_daytext(this);

        var title = TOOL.str.removeroomheadings;

        var html = HTML.tag("p", TOOL.str.confirmroomheadings);
        html += HTML.alist("ul", [daytext]);
        html += HTML.hidden("day", day);
        html += HTML.hidden("row", row);

        var actionfunction = function(){
            var day = TOOL.form_value(this, "day", true);
            var row = TOOL.form_value(this, "row", true);
            $(".day.day" + day).closest("table").find("tr:eq(" + row + ")").remove();
            TOOL.open_dialog(evt, title, TOOL.str.removedroomheadings, TOOL.str.ok);
        };

        TOOL.show_remove_dialog(evt, title, html, actionfunction);
    };

    // ==========================================
    // handlers to add, edit and remove room
    // ==========================================

    TOOL.add_room = function(evt) {

        var title = TOOL.str.addroom;

        // start HTML for dialog
        var html = "";
        html += "<table><tbody>";

        // add checkboxes for days
        html += TOOL.days_checkbox("days");

        // fetch array of room positions
        var position = TOOL.position("room", TOOL.extract_max_roomcount());

        html += "<tr>" + HTML.tag("th", TOOL.str.position) + HTML.tag("td", position) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.roomname) + HTML.tag("td", TOOL.rooms("roomtxt")) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.roomtopic) + HTML.tag("td", HTML.text("roomtopic")) + "</tr>";

        // finish HTML
        html += "</tbody></table>";

        var actionfunction = function(){

            var days = TOOL.form_values(this, "days_", true);
            var roomtxt = TOOL.form_value(this, "roomtxt");
            var roomtopic = TOOL.form_value(this, "roomtopic");
            var position = TOOL.form_value(this, "position", true);

            var added = false;

            $(".day").each(function(){
                var day = TOOL.extract_day($(this).prop("class"));
                if (days[day]) {

                    // increase colspan for this day
                    $(this).find(".date td").each(function(){
                        var colspan = $(this).prop("colspan") || 1;
                        $(this).prop("colspan", colspan + 1);
                    });

                    // increase colspan for this day
                    $(this).find(".roomheadings").each(function(){
                        var r = 0;
                        var added = false;
                        var oldclass = new RegExp("\\broom\\d+");
                        $(this).find(".roomheading").each(function(){
                            r++;
                            if (added==false && position <= r) {
                                added = true;
                                $(this).before(TOOL.roomheading(r, roomtxt, roomtopic));
                                r++;
                            }
                            var cssclass = $(this).prop("class").replace(oldclass, "");
                            $(this).prop("class", TOOL.trim(cssclass) + " room" + r);
                        });
                        if (added==false) {
                            r++;
                            $(this).append(TOOL.roomheading(r, roomtxt, roomtopic));
                        }
                    });

                    var html = HTML.tag("td", "", {"class": "session emptysession"});
                    $(this).find(".slot").each(function(){
                        var added = false;
                        $(this).find(".session").each(function(){
                            var r = $(this).data("room");
                            if (added==false) {
                                if (this.colSpan && this.colSpan > 1) {
                                    $(this).prop("colspan", this.colSpan + 1);
                                    added = true;
                                } else if (position <= r) {
                                    TOOL.insert_session(html, "insertBefore", this);
                                    added = true;
                                }
                            }
                        });
                        if (added==false) {
                            added = true;
                            TOOL.insert_session(html, "appendTo", this);
                        }
                    });
                }
            });

            // set colspan of scheduletitle and tabs
            TOOL.set_schedule_colspan();

            TOOL.redraw_schedule(added);

            TOOL.open_dialog(evt, title, TOOL.str.addedroom, TOOL.str.ok);
        };

        TOOL.show_add_dialog(evt, title, html, actionfunction);
    };

    TOOL.edit_room = function(evt) {

        // extract day and room
        var day = TOOL.extract_parent_day(this);
        var room = TOOL.extract_parent_room(this);

        var langs = [];
        var details = {};

        var heading = $(this).closest(".roomheading");
        heading.addClass("ui-selected");

        for (var name in TOOL.details) {
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
            // eslint-disable-next-line no-loop-func
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

        var title = TOOL.str.editroom + TOOL.str.labelsep + room;

        var html = "";
        html += "<table><tbody>";

        if (langs.length) {
            html += "<tr>" + HTML.tag("td", "");
            for (var i=0; i<langs.length; i++) {
                var lang = langs[i];
                var langtext = (TOOL.str[lang] ? TOOL.str[lang] : lang);
                html += TOOL.boldcenter("td", langtext);
            }
            html += "</tr>";
        }

        for (var name in details) {
            html += "<tr>" + HTML.tag("th", TOOL.str[name]);
            if (langs.length==0) {
                var value = TOOL.trim(details[name]);
                html += HTML.tag("td", HTML.text(name, value, 12));
            } else {
                for (var i=0; i<langs.length; i++) {
                    var lang = langs[i];
                    var value = "";
                    if (typeof(details[name])=="string") {
                        value = TOOL.trim(details[name]);
                    } else if (details[name][lang]) {
                        value = TOOL.trim(details[name][lang]);
                    }
                    html += HTML.tag("td", HTML.text(name + "_" + lang, value));
                }
            }
            html += "</tr>";
        }

        html += "</tbody></table>";
        html += HTML.hidden("day", day);
        html += HTML.hidden("room", room);

        var actionfunction = function(){
            var day = TOOL.form_value(this, "day", true);
            var room = TOOL.form_value(this, "room", true);
            var heading = $(".day" + day + " .roomheading.room" + room);
            var session = $(".day" + day + " .session .room" + room);
            for (var name in TOOL.details) {
                var html = TOOL.multilangs(this, name);
                heading.find("." + name).html(html);
                session.find("." + name).html(html);
            }

            TOOL.open_dialog(evt, title, TOOL.str.editedroom, TOOL.str.ok);
        };

        TOOL.show_edit_dialog(evt, title, html, actionfunction);
    };

    TOOL.remove_room = function(evt) {
        // extract day/room number and day text
        var day = TOOL.extract_parent_day(this);
        var room = TOOL.extract_parent_room(this);
        var daytext = TOOL.extract_parent_daytext(this);

        var heading = $(this).closest(".roomheading");
        heading.addClass("ui-selected");

        var title = TOOL.str.removeroom + TOOL.str.labelsep + room;

        var html = HTML.tag("p", TOOL.str.confirmroom);
        html += "<table><tbody>";
        html += "<tr>" + HTML.tag("th", TOOL.str.day) + HTML.tag("td", daytext) + "</tr>";
        for (var name in TOOL.details) {
            var detail = heading.find("." + name);
            if (detail.html()) {
                html += "<tr>" + HTML.tag("th", TOOL.str[name]) + HTML.tag("td", detail.html()) + "</tr>";
            }
        }
        html += "</tbody></table>";
        html += HTML.hidden("day", day);
        html += HTML.hidden("room", room);

        var actionfunction = function(){
            var day = TOOL.form_value(this, "day", true);
            var room = TOOL.form_value(this, "room", true);

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

                    if (this.colSpan && this.colSpan > 1) {
                        var cellcolspan = (this.colSpan - 1);
                        if (cellcolspan==1) {
                            $(this).removeAttr("colspan");
                        } else {
                            $(this).prop("colspan", cellcolspan);
                        }
                        rowcolspan += cellcolspan;
                        removed = true;
                        return true;
                    }

                    if ($(this).index()==roomindex) {
                        TOOL.unassign_session(this, true);
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
            TOOL.set_schedule_colspan();

            TOOL.open_dialog(evt, title, TOOL.str.removedroom, TOOL.str.ok);
        };

        TOOL.show_remove_dialog(evt, title, html, actionfunction);
    };

    // ==========================================
    // add, edit and remove time slot
    // ==========================================

    TOOL.add_slot = function(evt) {
        // specify title
        var title = TOOL.str.addslot;

        // get form elements
        var start = TOOL.hoursmins("start");
        var finish = TOOL.hoursmins("finish");
        var targetday = TOOL.days_select("targetday");

        // create HTML for dialog
        var html = "";
        html += "<table><tbody>";
        html += "<tr>" + HTML.tag("th", TOOL.str.day) + HTML.tag("td", targetday) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.starttime) + HTML.tag("td", start) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.finishtime) + HTML.tag("td", finish) + "</tr>";
        html += "</tbody></table>";

        // specify action function for dialog button
        var actionfunction = function(){

            var targetday   = TOOL.form_value(this, "targetday",   true);
            var starthours  = TOOL.form_value(this, "starthours",  true);
            var startmins   = TOOL.form_value(this, "startmins",   true);
            var finishhours = TOOL.form_value(this, "finishhours", true);
            var finishmins  = TOOL.form_value(this, "finishmins",  true);

            var duration = TOOL.form_duration(starthours, startmins, finishhours, finishmins);
            var startfinish = TOOL.form_startfinish(starthours, startmins, finishhours, finishmins);

            // create new slot
            var slot = TOOL.slot(targetday, startfinish, duration);

            // insert html for new slot in the targetday
            $(".day.day" + targetday).find(".slot").each(function(){
                var slotstartfinish = $(this).find(".timeheading .startfinish").text();
                if (slotstartfinish > startfinish) {
                    slot.insertBefore(this);
                    slot = null;
                }
                // return false to stop each() loop
                return (TOOL.empty(slot) ? false : true);
            });
            if (slot) {
                // append the new slot at the end of targetday
                $(".day.day" + targetday).append(slot);
                slot = null;
            }

            TOOL.redraw_schedule(true);

            // renumber all slots on this day
            TOOL.renumberslots(targetday);

            TOOL.open_dialog(evt, title, TOOL.str.addedslot, TOOL.str.ok);
        };

        TOOL.show_add_dialog(evt, title, html, actionfunction);
    };

    TOOL.edit_slot = function(evt) {

        // extract day/slot number
        var day = TOOL.extract_parent_day(this);
        var slot = TOOL.extract_parent_slot(this);
        var daytext = TOOL.extract_parent_daytext(this);

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
        var title = TOOL.str.editslot;

        // get form elements
        var start = TOOL.hoursmins("start", starthours, startmins);
        var finish = TOOL.hoursmins("finish", finishhours, finishmins);
        var checkbox = HTML.checkbox("multiroom", TOOL.has_multiroom(s)) + " " + TOOL.rooms("roomtxt", roomname);

        var roomname = TOOL.extract_sessionroom(dayslot + " .multiroom", "name");

        // create HTML for dialog
        var html = "";
        html += "<table><tbody>";
        html += "<tr>" + HTML.tag("th", TOOL.str.day) + HTML.tag("td", daytext) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.starttime) + HTML.tag("td", start) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.finishtime) + HTML.tag("td", finish) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.largeroom) + HTML.tag("td", checkbox) + "</tr>";
        html += "</tbody></table>";
        html += HTML.hidden("day", day);
        html += HTML.hidden("slot", slot);

        // specify action function for dialog button
        var actionfunction = function(){

            var day  = TOOL.form_value(this, "day", true);
            //var slot = TOOL.form_value(this, "slot", true);
            var roomtxt = TOOL.form_value(this, "roomtxt");
            var multiroom = TOOL.form_value(this, "multiroom", true);
            var starthours  = TOOL.form_value(this, "starthours", true);
            var startmins   = TOOL.form_value(this, "startmins", true);
            var finishhours = TOOL.form_value(this, "finishhours", true);
            var finishmins  = TOOL.form_value(this, "finishmins", true);

            var duration = TOOL.form_duration(starthours, startmins, finishhours, finishmins);
            var startfinish = TOOL.form_startfinish(starthours, startmins, finishhours, finishmins);

            // update the start/finish times for this slot
            $(dayslot + " .startfinish").html(startfinish);
            $(dayslot + " .duration").html(TOOL.get_string("durationtxt", duration));

            // update duration class for this slot
            var oldclass = new RegExp("\\bduration\\w+");
            var cssclass = $(dayslot).prop("class").replace(oldclass, "");
            $(dayslot).prop("class",  cssclass + " duration" + duration);

            var r = 1;
            var firstsession = true;
            var roomcount = TOOL.extract_roomcount(day);
            $(dayslot + " .session").each(function(){
                if (multiroom) {
                    // convert to multiroom
                    if (firstsession) {
                        firstsession = false;
                        $(this).addClass("multiroom");
                        if (roomcount > 1) {
                            $(this).prop("colspan", roomcount);
                        }
                        TOOL.insert_timeroom(this, roomtxt);
                    } else {
                        TOOL.unassign_session(this, true);
                    }
                } else {
                    // revert to single rooms
                    if ($(this).is(".multiroom")) {
                        $(this).removeClass("multiroom");
                    }
                    if (this.colSpan) {
                        $(this).removeAttr("colspan");
                    }
                    if ($(this).is(":not(.emptysession)")) {
                        TOOL.insert_timeroom(this);
                    } else {
                        $(this).find(TOOL.sessiontimeroom).remove();
                    }
                    r++;
                    if ($(this).is(":last-child")) {
                        TOOL.insert_sessions(this, r, day);
                    }
                }
            });

            TOOL.open_dialog(evt, title, TOOL.str.editedslot, TOOL.str.ok);
        };

        TOOL.show_edit_dialog(evt, title, html, actionfunction);
    };

    TOOL.remove_slot = function(evt) {

        // extract day/slot number and day text
        var day = TOOL.extract_parent_day(this);
        var slot = TOOL.extract_parent_slot(this);
        var daytext = TOOL.extract_parent_daytext(this);

        // locate timeheading for this day + slot
        var dayslot = ".day" + day + " .slot" + slot;
        var heading = $(dayslot + " .timeheading");
        heading.addClass("ui-selected");

        // extract start/finish time text and duration text
        var startfinish = heading.find(".startfinish").text();
        var duration = heading.find(" .duration").html();

        var title = TOOL.str.removeslot + TOOL.str.labelsep + slot;

        var html = HTML.tag("p", TOOL.str.confirmslot);
        html += "<table><tbody>";
        html += "<tr>" + HTML.tag("th", TOOL.str.day) + HTML.tag("td", daytext) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.time) + HTML.tag("td", startfinish) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.duration) + HTML.tag("td", duration) + "</tr>";
        html += "</tbody></table>";
        html += HTML.hidden("day", day);
        html += HTML.hidden("slot", slot);

        var actionfunction = function(){
            var day  = TOOL.form_value(this, "day", true);
            //var slot  = TOOL.form_value(this, "slot", true);

            // unassign any active sessions
            $(dayslot +  " .session").each(function(){
                TOOL.unassign_session(this, true);
            });

            // remove this slot
            $(dayslot).remove();

            // renumber remaining slots on this page
            TOOL.renumberslots(day);

            TOOL.open_dialog(evt, title, TOOL.str.removedslot, TOOL.str.ok);
        };

        TOOL.show_remove_dialog(evt, title, html, actionfunction);
    };

    // ==========================================
    // utility functions used to add/remove
    // days, room-headings and rooms
    // ==========================================

    TOOL.redraw_schedule = function(redraw) {
        // some browsers (at least Chrome on Mac)
        // need to redraw the schedule after adding tr
        // rows to the main TABLE holding the schedule
        if (redraw) {
            $("#schedule").hide().show(50);
        }
    };

    TOOL.set_schedule_colspan = function(colspan) {
        if (TOOL.empty(colspan)) {
            colspan = TOOL.extract_max_roomcount() + 1;
        }
        $(".scheduletitle, .tabs").find("td").prop("colspan", colspan);
    };

    TOOL.renumberslots = function(day) {
        var slotnumber = 0;
        $(".day.day" + day).find(".slot").each(function(){
            $(this).prop("class", "slot slot" + (slotnumber++));
        });
    };


    // ==========================================
    // edit and remove session
    // ==========================================

    TOOL.edit_session = function(evt) {

        var session = $(this).closest(".session");

        // create form elements
        var title          = HTML.text("title", session.find(".title").text(), 50);
        var authornames    = HTML.text("authornames", session.find(".authornames").html(), 50);
        var schedulenumber = HTML.text("schedulenumber", session.find(".schedulenumber").text(), 5);
        var category       = TOOL.categories("category", session.find(".category").html());
        var type           = TOOL.types("type", session.find(".type").html());
        var topic          = TOOL.topics("topic", session.find(".topic").html());
        var rowspan        = TOOL.range("rowspan", session.prop("rowspan"));
        var colspan        = TOOL.range("colspan", session.prop("colspan"));
        var id             = HTML.hidden("id", session.prop("id"));

        // create HTML for dialog
        var html = "";
        html += "<table><tbody>";
        html += "<tr>" + HTML.tag("th", TOOL.str.title)          + HTML.tag("td", title)          + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.authornames)    + HTML.tag("td", authornames)    + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.schedulenumber) + HTML.tag("td", schedulenumber) + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.category)       + HTML.tag("td", category)       + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.type)           + HTML.tag("td", type)           + "</tr>";
        html += "<tr>" + HTML.tag("th", TOOL.str.topic)          + HTML.tag("td", topic)          + "</tr>";
        if (session.parent().hasClass("slot")) {
            html += "<tr>" + HTML.tag("th", TOOL.str.rowspan)    + HTML.tag("td", rowspan)        + "</tr>";
            html += "<tr>" + HTML.tag("th", TOOL.str.colspan)    + HTML.tag("td", colspan)        + "</tr>";
        }
        html += "</tbody></table>";
        html += id;

        // specify action function for dialog button
        var actionfunction = function(){

            // get form values
            var id             = TOOL.form_value(this, "id");
            var title          = TOOL.form_value(this, "title");
            var authornames    = TOOL.form_value(this, "authornames");
            var schedulenumber = TOOL.form_value(this, "schedulenumber");
            var category       = TOOL.form_value(this, "category");
            var type           = TOOL.form_value(this, "type");
            var topic          = TOOL.form_value(this, "topic");
            var rowspan        = TOOL.form_value(this, "rowspan", true);
            var colspan        = TOOL.form_value(this, "colspan", true);

            // update values
            var session = $("#" + id);
            session.find(".title").contents().filter(TOOL.textnodes).remove();
            session.find(".title").append(title);
            session.find(".authornames").html(authornames);
            session.find(".schedulenumber").text(schedulenumber);
            session.find(".category").html(category);
            session.find(".type").html(type);
            session.find(".topic").html(topic);

            TOOL.change_rowspan_colspan(session, rowspan, colspan);

            TOOL.open_dialog(evt, TOOL.str.editsession, TOOL.str.editedsession, TOOL.str.ok);
        };

        TOOL.select_session(id);
        TOOL.show_edit_dialog(evt, TOOL.str.editsession, html, actionfunction);
    };

    TOOL.remove_session = function(evt) {
        var id = $(this).closest(".session").prop("id");
        var recordid = TOOL.extract_recordid(id);

        var title = TOOL.str.removesession + ": rid=" + recordid;
        var html = HTML.tag("p", TOOL.str.confirmsession);
        var actionfunction = function(){

            // add new empty session to #items
            var item = TOOL.item(null, "session emptysession").appendTo("#items");

            // deselect current session
            TOOL.select_session();

            // swap the empty session and the target session
            TOOL.click_session(item);
            TOOL.click_session(document.getElementById(id), true);

            TOOL.open_dialog(evt, title, TOOL.str.removedsession, TOOL.str.ok);
        };

        TOOL.select_session(id);
        TOOL.show_remove_dialog(evt, title, html, actionfunction);
    };

    // ==========================================
    // select/click session
    // ==========================================

    TOOL.select_session = function(id) {
        if (TOOL.sourcesession) {
            TOOL.click_session(TOOL.sourcesession);
        } else {
            $(".ui-selected").removeClass("ui-selected");
        }
        if (id) {
            TOOL.click_session(document.getElementById(id));
        }
    };

    TOOL.click_session = function(targetsession, forceswap) {

        // select source
        if (TOOL.empty(TOOL.sourcesession)) {
            TOOL.sourcesession = targetsession;
            $(targetsession).addClass("ui-selected");
            return true;
        }

        // cache flags if target/source is empty
        var targetIsEmpty = $(targetsession).hasClass("emptysession");
        var sourceIsEmpty = $(TOOL.sourcesession).hasClass("emptysession");

        // deselect source
        if (TOOL.sourcesession==targetsession || (sourceIsEmpty && targetIsEmpty)) {
            $(TOOL.sourcesession).removeClass("ui-selected");
            $(targetsession).removeClass("ui-selected");
            TOOL.sourcesession = null;
            return true;
        }

        var targetAllRooms = $(targetsession).hasClass("multiroom");
        var sourceAllRooms = $(TOOL.sourcesession).hasClass("multiroom");

        var targetAssigned = $(targetsession).closest("table.schedule").length;
        var sourceAssigned = $(TOOL.sourcesession).closest("table.schedule").length;

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
        if (targetsession.tagName==TOOL.sourcesession.tagName) {
            swap = true;
        }

        if (swap) {
            $(targetsession).addClass("ui-selected");

            // create temp elements to store child nodes
            var temptarget = document.createElement("DIV");
            var tempsource = document.createElement("DIV");

            // transfer DOM ids
            var sourceid = $(TOOL.sourcesession).prop("id");
            var targetid = $(targetsession).prop("id");
            $(TOOL.sourcesession).prop("id", targetid);
            $(targetsession).prop("id", sourceid);

            // transfer CSS classes
            var sourceclasses = TOOL.get_non_jquery_classes(TOOL.sourcesession);
            var targetclasses = TOOL.get_non_jquery_classes(targetsession);
            $(TOOL.sourcesession).removeClass(sourceclasses).addClass(targetclasses);
            $(targetsession).removeClass(targetclasses).addClass(sourceclasses);

            // move children to temp source
            if (sourceAssigned) {
                if (targetIsEmpty && sourceAllRooms==false) {
                    $(TOOL.sourcesession).children(TOOL.sessiontimeroom).remove();
                } else {
                    $(TOOL.sourcesession).children(TOOL.sessiontimeroom).appendTo(tempsource);
                }
            }
            if (targetIsEmpty==false) {
                $(targetsession).children(TOOL.sessioncontent).appendTo(tempsource);
            }

            // move children to temp target
            if (targetAssigned) {
                if (sourceIsEmpty && targetAllRooms==false) {
                    $(targetsession).children(TOOL.sessiontimeroom).remove();
                } else {
                    $(targetsession).children(TOOL.sessiontimeroom).appendTo(temptarget);
                }
            }
            if (sourceIsEmpty==false) {
                $(TOOL.sourcesession).children(TOOL.sessioncontent).appendTo(temptarget);
            }

            // move children to real source and target
            $(temptarget).children().appendTo(targetsession);
            $(tempsource).children().appendTo(TOOL.sourcesession);

            if (sourceAssigned) {
                if (sourceAllRooms) {
                    $(TOOL.sourcesession).addClass("multiroom");
                } else {
                    $(TOOL.sourcesession).removeClass("multiroom");
                    if (targetIsEmpty==false) {
                        TOOL.insert_timeroom(TOOL.sourcesession);
                    }
                }
            }
            if (targetAssigned) {
                if (targetAllRooms) {
                    $(targetsession).addClass("multiroom");
                } else {
                    $(targetsession).removeClass("multiroom");
                    if (targetIsEmpty==false) {
                        TOOL.insert_timeroom(targetsession);
                    }
                }
            }

            tempsource = null;
            temptarget = null;

            // deselect TOOL.sourcesession
            $(TOOL.sourcesession).removeClass("ui-selected");
            TOOL.sourcesession = null;

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
            empty = TOOL.sourcesession;
            nonempty = targetsession;
        }

        // if target is empty and source is not, then
        // move source to target, then remove source
        if (sourceIsEmpty==false && targetIsEmpty) {
            empty = targetsession;
            nonempty = TOOL.sourcesession;
        }

        if (empty && nonempty) {

            $(empty).addClass("ui-selected");

            // transfer DOM "id"
            $(empty).prop("id", $(nonempty).prop("id"));

            // set flag if empty session uses All Rooms
            var emptyAllRooms = $(empty).is(".multiroom");

            // transfer CSS classes
            var emptyclasses = TOOL.get_non_jquery_classes(empty);
            var nonemptyclasses = TOOL.get_non_jquery_classes(nonempty);
            $(empty).removeClass(emptyclasses).addClass(nonemptyclasses);

            // remove child nodes and transfer time/room details
            if (emptyAllRooms) {
                $(empty).addClass("multiroom");
                $(empty).children().not(TOOL.sessiontimeroom).remove();
            } else {
                $(empty).removeClass("multiroom");
                $(empty).children().remove();
                TOOL.insert_timeroom(empty);
            }

            // transfer content elements
            $(nonempty).children(TOOL.sessioncontent).appendTo(empty);

            // deselect empty session
            $(empty).removeClass("ui-selected");

            // the nonempty session is now empty
            // and can be removed from the DOM
            $(nonempty).remove();

            // release TOOL.sourcesession
            TOOL.sourcesession = null;

            return true;
        }
    };

    // ==========================================
    // open/close dialog
    // ==========================================

    TOOL.show_add_dialog = function(evt, title, html, actionfunction) {
        TOOL.open_dialog(evt, title, html, TOOL.str.add, null, actionfunction, true);
    };

    TOOL.show_edit_dialog = function(evt, title, html, actionfunction) {
        TOOL.open_dialog(evt, title, html, TOOL.str.update, null, actionfunction, true);
    };

    TOOL.show_remove_dialog = function(evt, title, html, actionfunction) {
        TOOL.open_dialog(evt, title, html, TOOL.str.remove, null, actionfunction, true);
    };

    TOOL.open_dialog = function(evt, title, html, actiontext, actionicon, actionfunction, showcancelbutton) {
        var showactionbutton = true;

        // locate dialog box in DOM
        // (create it, if necessary)
        var dialogbox = document.getElementById(TOOL.dialogid);
        if (TOOL.empty(dialogbox)) {
            dialogbox = document.createElement("DIV");
            dialogbox.setAttribute("id", TOOL.dialogid);
            $("body").append(dialogbox);
        }

        // cache jQuery object for dialog
        var dialog = $(dialogbox);

        // create/close the dialog element
        if (TOOL.empty(dialog.dialog("instance"))) {
            dialog.dialog({"autoOpen": false, "close": TOOL.select_session, "width": "auto"});
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
            if (TOOL.empty(actiontext)) {
                actiontext = TOOL.str.ok;
            }
            if (TOOL.empty(actionicon)) {
                actionicon = "ui-icon-check";
            }
            if (TOOL.empty(actionfunction)) {
                actionfunction = function(){
                    $(this).dialog("close");
                };
            }
            buttons.push({"text": actiontext,"click": actionfunction}); // "icon": actionicon
        }
        if (showcancelbutton) {
            var canceltext = TOOL.str.cancel;
            //var cancelicon = "ui-icon-cancel";
            var cancelfunction = function(){
                $(this).dialog("close");
                TOOL.select_session();
            };
            buttons.push({"text": canceltext, "click": cancelfunction}); // "icon": cancelicon
        }
        dialog.dialog("option", "buttons", buttons);

        // open the dialog box
        dialog.dialog("open");

        // prevent the current click causing
        // the parent element to be selected
        evt.stopPropagation();
    };

    TOOL.close_dialog = function() {
        $("#" + TOOL.dialogid).dialog("close");
    };

    // ==========================================
    // helper functions to extract info from DOM
    // ==========================================

    TOOL.has_multiroom = function(slot) {
        return (slot.find(".session").filter(TOOL.colspan).length > 0);
    };

    TOOL.extract_main_language = function() {
        var regexp = new RegExp("lang-(\\w+)");
        return $("body").attr('class').match(regexp)[1];
    };

    TOOL.extract_parent_daytext = function(elm) {
        var daytext = $(elm).closest(".day").find(".date td");
        if (daytext.length==0) {
            return "";
        }
        return TOOL.force_single_line(daytext.first().html());
    };

    TOOL.extract_parent_row = function(elm) {
        return $(elm).closest("tr").prop("rowIndex");
    };

    TOOL.extract_parent_day = function(elm) {
        return TOOL.extract_parent_number(elm, "day", "day");
    };

    TOOL.extract_parent_tabday = function(elm) {
        return TOOL.extract_parent_number(elm, "tab", "day");
    };

    TOOL.extract_parent_room = function(elm) {
        return TOOL.extract_parent_number(elm, "roomheading", "room");
    };

    TOOL.extract_parent_slot = function(elm) {
        return TOOL.extract_parent_number(elm, "slot", "slot");
    };

    TOOL.extract_parent_number = function(elm, parentclass, type) {
        var str = $(elm).closest("." + parentclass).prop("class");
        return TOOL.extract_type_number(str, type);
    };

    TOOL.extract_recordid = function(id) {
        return TOOL.extract_number(id, "id_recordid_", "");
    };

    TOOL.extract_active_day = function() {
        return TOOL.extract_day($(".tab.active").prop("class"));
    };

    TOOL.extract_day = function(str) {
        return TOOL.extract_type_number(str, "day");
    };

    TOOL.extract_room = function(str) {
        return TOOL.extract_type_number(str, "room");
    };

    TOOL.extract_slot = function(str) {
        return TOOL.extract_type_number(str, "slot");
    };

    TOOL.extract_duration = function(str) {
        return TOOL.extract_number(str, ".*\\bduration", "\\b.*");
    };

    TOOL.extract_type_number = function(str, type) {
        return TOOL.extract_number(str, ".*\\b" + type, "\\b.*");
    };

    TOOL.extract_number = function(str, prefix, suffix) {
        if (typeof(str)=="string") {
            var pattern = "^" + prefix + "(\\d+)" + suffix + "$";
            var number = str.replace(new RegExp(pattern), "$1");
            return (isNaN(number) ? 0 : parseInt(number));
        }
        return 0;
    };

    TOOL.extract_roomcount = function(day) {
        return $(".day.day" + day).data("roomcount");
    };

    TOOL.extract_max_roomcount = function(day) {
        var max = 0, days = ".day";
        if (day) {
            days += ".day" + day;
        }
        $(days).each(function(){
            var day = TOOL.extract_parent_day(this);
            max = Math.max(max, TOOL.extract_roomcount(day));
        });
        return max;
    };

    TOOL.extract_roomname_txt = function(txt) {
        return TOOL.extract_room_txt(txt);
    };

    TOOL.extract_roomseats_txt = function(txt) {
        return TOOL.extract_room_txt(txt, true);
    };

    TOOL.extract_room_txt = function(txt, returnnumber) {
        // e.g. Room 101 (20 seats)
        if (txt==TOOL.trim(txt)) {
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
    };

    TOOL.extract_startfinish_html = function(day, slot) {
        return TOOL.extract_time_html(day, slot, "startfinish");
    };

    TOOL.extract_duration_html = function(day, slot) {
        return TOOL.extract_time_html(day, slot, "duration");
    };

    TOOL.extract_time_html = function(day, slot, name) {
        return $(".day.day" + day + " .slot" + slot + " .timeheading ." + name).html();
    };

    TOOL.extract_roomname_html = function(day, r) {
        return TOOL.extract_room_html(day, r, "roomname");
    };

    TOOL.extract_roomseats_html = function(day, r) {
        return TOOL.extract_room_html(day, r, "roomseats");
    };

    TOOL.extract_roomtopic_html = function(day, r) {
        return TOOL.extract_room_html(day, r, "roomtopic");
    };

    TOOL.extract_room_html = function(day, r, name) {
        return $(".day.day" + day + " .roomheadings .room" + r + " ." + name).html();
    };

    TOOL.extract_sessionroom = function(session, returndetail) {
        var details = [];
        if (detail) {
            details.push(detail);
        } else {
            details.push("name", "seats", "topic");
        }
        var room = {};
        var lang = TOOL.extract_main_language();
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
                    if (TOOL[extract]) {
                        value = TOOL[extract](value);
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
    };

    TOOL.colspan = function(){
        return (this.colSpan && this.colSpan > 1);
    };

    TOOL.textnodes = function(){
        return (this.nodeType && this.nodeType===3);
    };

    // ==========================================
    // helper functions to insert sessions
    // ==========================================

    TOOL.change_rowspan_colspan = function(session, newrowspan, newcolspan) {

        var cellindex = session.prop("cellIndex");
        var oldrowspan = session.prop("rowspan");
        var oldcolspan = session.prop("colspan");

        if (oldrowspan==newrowspan) {
            return true;
        }

        if (oldcolspan===undefined) {
            oldcolspan = 0;
        }
        if (newcolspan===undefined) {
            newcolspan = 0;
        }

        if (oldrowspan < newrowspan) {
            var i = 0;
            session.closest("tr").nextAll().each(function(){
                i++;
                if (i < oldrowspan) {
                    return true;
                }
                if (i >= newrowspan) {
                    return false;
                }
                $(this).find("th, td").eq(cellindex).each(function(){
                    TOOL.unassign_session(this, true);
                });
            });
        }

        if (oldrowspan > newrowspan) {
            var i = 0;
            //var day = TOOL.extract_parent_day(this);
            //var roomcount = TOOL.extract_roomcount(day);
            session.closest("tr").nextAll().each(function(){
                i++;
                if (i < newrowspan) {
                    return true;
                }
                if (i >= oldrowspan) {
                    return false;
                }
                var html = TOOL.html_sessions(cellindex, cellindex);
                if (session.is(":last-child")) {
                    $(this).append(html);
                } else {
                    $(this).find("th, td").eq(cellindex).each(function(){
                        TOOL.insert_session(html, "insertBefore", this);
                    });
                }
            });
        }

        session.prop("rowspan", newrowspan);
    };

    TOOL.unassign_session = function(session, remove) {
        // sessions with a recognized "id" are moved to the "#items" DIV
        var id = $(session).prop("id");
        if (id.indexOf("id_recordid_")==0) {
            $(session).removeAttr("id");
            TOOL.item(id, TOOL.get_non_jquery_classes(session))
               .append($(session).children(TOOL.sessioncontent))
               .appendTo("#items");
        }
        if (remove) {
            $(session).remove();
        } else {
            $(session).children().remove();
            TOOL.insert_timeroom(session);
            $(session).removeAttr("id colspan")
                      .removeClass("demo attending multiroom")
                      .addClass("emptysession");
        }
    };

    TOOL.insert_sessions = function(elm, r, day, roomcount) {
        if (TOOL.empty(day)) {
            day = TOOL.extract_parent_day(elm);
        }
        if (TOOL.empty(roomcount)) {
            roomcount = TOOL.extract_roomcount(day);
        }
        if (r <= roomcount) {
            var html = TOOL.html_sessions(r, roomcount);
            TOOL.insert_session(html, "insertAfter", elm);
        }
    };

    TOOL.insert_session = function(html, insert, elm) {
        $(html).each(function(){
            TOOL.make_sessions_editable(null, this);
            TOOL.make_sessions_droppable(null, this);
            TOOL.make_sessions_draggable(null, this);
            TOOL.make_sessions_selectable(null, this);
        })[insert](elm);
    };

    TOOL.insert_timeroom = function(elm, roomtxt) {
        var day = TOOL.extract_parent_day(elm);
        var slot = TOOL.extract_parent_slot(elm);
        var room = $(elm).data("room");
        if (TOOL.empty(room)) {
            room = $(elm).index();
        }
        TOOL.insert_time(elm, day, slot);
        TOOL.insert_room(elm, day, room, roomtxt);
    };

    TOOL.insert_time = function(elm, day, slot) {
        var html = TOOL.html_time(day, slot);
        if ($(elm).find(".time").length) {
            $(elm).find(".time").replaceWith(html);
        } else {
            $(elm).prepend(html);
        }
    };

    TOOL.insert_room = function(elm, day, room, roomtxt) {
        var html = TOOL.html_room(day, room, roomtxt);
        if ($(elm).find(".room").length) {
            $(elm).find(".room").replaceWith(html);
        } else if ($(elm).find(".time").length) {
            $(elm).find(".time").after(html);
        } else {
            $(elm).prepend(html);
        }
    };

    TOOL.get_non_jquery_classes = function(elm) {
        var classes = $(elm).prop('class').split(new RegExp("\\s+"));
        var max = (classes.length - 1);
        for (var i=max; i>=0; i--) {
            if (classes[i].indexOf("ui-")==0) {
                classes.splice(i, 1);
            }
        }
        return classes.join(" ");
    };

    // ==========================================
    // helper functions to create jQuery objects
    // ==========================================

    TOOL.tab = function(day, tabtext) {
        var html = TOOL.html_tab(day, tabtext);
        return $(html).each(function(){
            TOOL.make_day_editable(this);
        });
    };

    TOOL.day = function(day, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval) {
        var html = TOOL.html_day(day, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval);
        return $(html).each(function(){
            TOOL.make_day_editable(this);
        });
    };

    TOOL.roomheadings = function(day, rooms, topics) {
        // if possible we clone another row of roomheadings
        var headings = $(".day.day" + day + " .roomheadings");
        if (headings.length) {
            return headings.first().clone();
        }
        headings = $(TOOL.html_roomheadings(day, rooms, topics));
        TOOL.make_rooms_editable(null, headings);
        return headings;
    };

    TOOL.roomheading = function(r, roomtxt, roomtopic) {
        var roomname = TOOL.extract_roomname_txt(roomtxt);
        var roomseats = TOOL.extract_roomseats_txt(roomtxt);
        var heading = $(TOOL.html_roomheading(r, roomname, roomseats, roomtopic));
        heading.prepend(TOOL.icons("room"));
        return heading;
    };

    TOOL.slot = function(targetday, startfinish, duration) {
        var slot = $(TOOL.html_slot(targetday, startfinish, duration, TOOL.extract_roomcount(targetday)));
        TOOL.make_slots_editable(null, slot);
        TOOL.make_sessions_droppable(slot);
        TOOL.make_sessions_draggable(slot);
        TOOL.make_sessions_selectable(slot);
        return slot;
    };

    TOOL.item = function(id, classess){
        var item = $(TOOL.html_item(id, classess));
        TOOL.make_sessions_draggable(null, item);
        TOOL.make_sessions_selectable(null, item);
        return item;
    };

    TOOL.icons = function(type, actions) {
        var icons = document.createElement("SPAN");
        icons.setAttribute("class", "icons");
        if (TOOL.empty(actions)) {
            actions = ["edit", "remove"];
        } else if (typeof(actions)=="string") {
            actions = [actions];
        }
        for (var i in actions) {
            var action = actions[i];
            var clickhandler = action + "_" + type;
            var icon = document.createElement("IMG");
            icon.setAttribute("src", TOOL["icon" + action]);
            icon.setAttribute("title", TOOL.str[action + type]);
            icon.setAttribute("class", "icon");
            icon.setAttribute("clickhandler", clickhandler);
            if (TOOL[clickhandler]) {
                $(icon).click(TOOL[clickhandler]);
            } else {
                $(icon).click(TOOL.noclickhandler);
            }
            icons.appendChild(icon);
        }
        return icons;
    };

    TOOL.noclickhandler = function(){
        alert("Oops, click handler is missing: TOOL." + this.getAttribute("clickhandler") + "()");
    };

    // ==========================================
    // helper functions to create HTML elements
    // ==========================================

    TOOL.html_day = function(day, daytext, roomcount, slotcount, slotstart, slotlength, slotinterval) {
        var html = "";
        html += HTML.starttag("tbody", {"class": "day day" + day});

        // day text
        html += HTML.starttag("tr", {"class": "date"});
        html += HTML.tag("td", daytext, {"colspan": roomcount});
        html += HTML.endtag("tr");

        // room headings
        html += TOOL.html_roomheadings(day, null, null, roomcount);

        // slots
        var starttime = null;
        var finishtime = null;
        var startfinish = null;
        for (var s=1; s<=slotcount; s++) {
            if (TOOL.empty(starttime)) {
                starttime = slotstart;
            } else {
                starttime = finishtime + slotinterval;
            }
            finishtime = starttime + slotlength;
            startfinish = TOOL.form_startfinish(Math.floor(starttime / 60),
                                               (starttime % 60),
                                               Math.floor(finishtime / 60),
                                               (finishtime % 60));
            html += TOOL.html_slot(day, startfinish, slotlength, roomcount);
        }

        html += HTML.endtag("tbody");
        return html;
    };

    TOOL.html_tab = function(day, tabtext) {
        return HTML.tag("div", tabtext, {"class": "tab day" + day});
    };

    TOOL.html_roomheadings_toprow = function() {
        return HTML.starttag("tr")
               + HTML.tag("td", "")
               + TOOL.boldcenter("td", TOOL.str.room)
               + TOOL.boldcenter("td", TOOL.str.roomtopic)
               + HTML.endtag("tr");
    };

    TOOL.html_roomheadings_datarow = function(elm, r) {
        var room = TOOL.extract_sessionroom(elm);
        return HTML.starttag("tr")
               + HTML.tag("th", r)
               + HTML.tag("td", TOOL.rooms("room_" + r, room.name))
               + HTML.tag("td", HTML.text("topic_" + r, room.topic))
               + HTML.endtag("tr");
    };

    TOOL.html_roomheadings_lastrow = function() {
        var options = {};
        options[TOOL.APPLY_CURRENT] = TOOL.str.currentheadings;
        options[TOOL.APPLY_THISDAY] = TOOL.str.allheadingsthisday;
        options[TOOL.APPLY_ALLDAYS] = TOOL.str.allheadingsalldays;
        return HTML.starttag("tr")
               + HTML.tag("th", TOOL.str.applyto)
               + HTML.tag("td", HTML.select("applyto", options, TOOL.APPLY_CURRENT, {}))
               + HTML.endtag("tr");
    };

    TOOL.html_roomheading = function(r, roomname, roomseats, roomtopic) {
        return HTML.starttag("th", {"class": "roomheading room" + r})
             + HTML.tag("span", roomname,  {"class": "roomname"})
             + HTML.tag("span", roomseats, {"class": "roomseats"})
             + HTML.tag("div",  roomtopic, {"class": "roomtopic"})
             + HTML.endtag("th");
    };

    TOOL.html_roomheadings = function(day, rooms, topics, roomcount) {
        if (TOOL.empty(roomcount)) {
            roomcount = TOOL.extract_roomcount(day);
        }
        var html = "";
        html += HTML.starttag("tr", {"class": "roomheadings"});
        html += HTML.tag("th", "", {"class": "timeheading"});
        for (var r=1; r<=roomcount; r++) {
            if (TOOL.empty(rooms) || TOOL.empty(rooms[r]) || rooms[r]=="0") {
                var roomname = TOOL.str.roomname + " (" + r + ")";
                var roomseats = 40;
                var roomtopic = TOOL.str.roomtopic + " (" + r + ")";
            } else {
                var roomname = TOOL.extract_roomname_txt(rooms[r]);
                var roomseats = TOOL.extract_roomseats_txt(rooms[r]);
                var roomtopic = TOOL.trim(topics[r]);
            }
            html += TOOL.html_roomheading(r, roomname, roomseats, roomtopic);
        }
        html += HTML.endtag("tr");
        return html;
    };

    TOOL.html_timeroom = function(day, slot, room, roomtxt) {
        return TOOL.html_time(day, slot) + TOOL.html_room(day, room, roomtxt);
    };

    TOOL.html_time = function(day, slot) {
        var startfinish = TOOL.extract_startfinish_html(day, slot);
        var duration = TOOL.extract_duration_html(day, slot);
        var html = HTML.tag("span", startfinish, {"class": "startfinish"})
                 + HTML.tag("span", duration, {"class": "duration"});
        return HTML.tag("div", html, {"class": "time"});
    };

    TOOL.html_room = function(day, room, roomtxt) {
        if (roomtxt=="0") {
            return "";
        }
        if (TOOL.empty(roomtxt)) {
            var roomname = TOOL.extract_roomname_html(day, room);
            var roomseats = TOOL.extract_roomseats_html(day, room);
            var roomtopic = TOOL.extract_roomtopic_html(day, room);
        } else {
            var roomname = TOOL.extract_roomname_txt(roomtxt);
            var roomseats = TOOL.extract_roomseats_txt(roomtxt);
            var roomtopic = "";
        }
        var html = HTML.tag("span", roomname, {"class": "roomname"})
                 + HTML.tag("span", roomseats, {"class": "roomseats"})
                 + HTML.tag("div", roomtopic, {"class": "roomtopic"});
        return HTML.tag("div", html, {"class": "room"});
    };

    TOOL.html_item = function(id, classes) {
        if (TOOL.empty(classes)) {
            classes = "session";
        }
        var attr = {"class": classes};
        if (id) {
            attr.id = id;
        }
        return HTML.tag("div", "", attr);
    };

    TOOL.html_sessions = function(r_min, r_max) {
        var html = "";
        for (var r=r_min; r<=r_max; r++) {
            html += HTML.tag("td", "", {"class": "session emptysession"});
        }
        return html;
    };

    TOOL.html_slot = function(day, startfinish, duration, roomcount) {
        var html = "";
        var durationtxt = TOOL.get_string("durationtxt", duration);
        html += HTML.starttag("tr", {"class": "slot duration" + duration});
        html += HTML.starttag("td", {"class": "timeheading"});
        html += HTML.tag("span", startfinish, {"class": "startfinish"});
        html += HTML.tag("span", durationtxt, {"class": "duration"});
        html += HTML.endtag("td");
        html += TOOL.html_sessions(1, roomcount);
        html += HTML.endtag("tr");
        return html;
    };

    TOOL.get_string = function(name, insert) {
        if (TOOL.empty(name) || TOOL.empty(TOOL.str[name])) {
            return "";
        }
        var str = TOOL.str[name];
        if (insert || insert===0) {
            if (typeof(insert)=="string" || typeof(insert)=="number") {
                var a = new RegExp("\\{\\$a\\}", "g");
                str = TOOL.str[name].replace(a, insert);
            } else {
                for (var i in insert) {
                    var a = new RegExp("\\{\\$a->" + i + "\\}", "g");
                    str = str.replace(a, insert[i]);
                }
            }
        }
        return str;
    };

    TOOL.hours = function(name, selected, min, max, attr) {
        var pad = (TOOL.empty(min) && TOOL.empty(max));
        if (TOOL.empty(min)) {
            min = 0;
        }
        if (TOOL.empty(max)) {
            max = 23;
        }
        if (TOOL.empty(attr)) {
            attr = {};
        }
        if (TOOL.empty(attr.class)) {
            attr.class = "select" + name;
        }
        var options = {};
        for (var i=min; i<=max; i++) {
            if (pad) {
                options[i] = TOOL.pad(i);
            } else {
                options[i] = TOOL.get_string("numhours", i);
            }
        }
        return HTML.select(name, options, selected, attr);
    };

    TOOL.mins = function(name, selected, min, max, attr) {
        var pad = (TOOL.empty(min) && TOOL.empty(max));
        if (TOOL.empty(min)) {
            min = 0;
        }
        if (TOOL.empty(max)) {
            max = 59;
        }
        if (TOOL.empty(attr)) {
            attr = {};
        }
        if (TOOL.empty(attr.class)) {
            attr.class = "select" + name;
        }
        var options = {};
        for (var i=min; i<=max; i+=5) {
            if (pad) {
                options[i] = TOOL.pad(i);
            } else {
                options[i] = TOOL.get_string("nummins", i);
            }
        }
        return HTML.select(name, options, selected, attr);
    };

    TOOL.hoursmins = function(name, hours, mins) {
        var hours  = TOOL.hours(name + "hours", hours);
        var mins = TOOL.mins(name + "mins", mins);
        return hours + ' : ' + mins;
    };

    TOOL.range = function(name, selected, min, max, attr) {
        if (TOOL.empty(name)) {
            return "";
        }
        if (TOOL.empty(min)) {
            min = 1;
        }
        if (TOOL.empty(max)) {
            max = 10;
        }
        if (TOOL.empty(selected)) {
            selected = max;
        }
        if (TOOL.empty(attr)) {
            attr = {};
        }
        if (attr.class) {
            attr.class = "select" + name;
        }
        var options = {};
        for (var i=min; i<=max; i++) {
            options[i] = i;
        }
        return HTML.select(name, options, selected, attr);
    };


    TOOL.position = function(type, max, name, attr) {
        if (type) {
            type = TOOL.get_string(type).toLowerCase();
        }
        if (TOOL.empty(name)) {
            name = "position";
        }
        if (TOOL.empty(attr)) {
            attr = {};
        }
        var positions = {};
        for (var i=1; i<=max; i++) {
            if (TOOL.empty(type)) {
                positions[i] = i;
            } else {
                var a = {"type": type, "num": i};
                positions[i] = TOOL.get_string("positionbefore", a);
            }
        }
        positions[i] = TOOL.str.positionlast;
        return HTML.select(name, positions, i, attr);
    };

    TOOL.days_select = function(name) {
        var selected = TOOL.extract_active_day();
        var lang = TOOL.extract_main_language();
        var options = {};
        $(".tab").each(function(){
            var day = TOOL.extract_day($(this).prop("class"));
            var html = $(this).find("span.multilang[lang=" + lang + "]");
            if (html.length) {
                html = html.html();
            } else {
                html = $(this).html();
            }
            options[day] = TOOL.force_single_line(html);
        });
        return HTML.select(name, options, selected, {});
    };

    TOOL.days_checkbox = function(name) {
        var html = "";
        var heading = TOOL.str.day;
        var checked = TOOL.extract_active_day();
        $(".day").each(function(){
            var day = TOOL.extract_parent_day(this);
            var dayname = name + "_" + day;
            var daytext = TOOL.extract_parent_daytext(this);
            daytext = HTML.tag("label", daytext, {"for": "id_" + dayname});
            daytext = HTML.checkbox(dayname, (day==checked)) + " " + daytext;
            html += HTML.starttag("tr", {"valign": "top"})
                 + HTML.tag("th", heading)
                 + HTML.tag("td", daytext, {"colspan": "2"})
                 + HTML.endtag("tr");
            heading = "";
        });
        return html;
    };

    TOOL.days = function(name, value, attr) {
        return TOOL.clone_menu("schedule_day", name, value, attr);
    };

    TOOL.rooms = function(name, value, attr) {
        return TOOL.clone_menu("schedule_roomname", name, value, attr);
    };

    TOOL.categories = function(name, value, attr) {
        return TOOL.clone_menu("presentation_category", name, value, attr);
    };

    TOOL.types = function(name, value, attr) {
        return TOOL.clone_menu("presentation_type", name, value, attr);
    };

    TOOL.topics = function(name, value, attr) {
        return TOOL.clone_menu("presentation_topic", name, value, attr);
    };

    TOOL.clone_menu = function(menuname, name, value, attr) {
        var menu = $("select[name=" + menuname + "]");
        if (menu.length==0) {
            return "";
        }
        menu = menu.first().clone();
        return TOOL.dialog_menu(menu, name, value, attr);
    };

    TOOL.times = function(name, value, attr) {
        return TOOL.generate_menu("times", name, value, attr);
    };

    TOOL.keywords = function(name, value, attr) {
        return TOOL.generate_menu("keywords", name, value, attr);
    };

    TOOL.generate_menu = function(selector, name, value, attr) {
        var i = 0;
        var count = {};
        $("#items .scheduleinfo ." + selector + " .text").each(function(){
            var txt = $(this).text();
            if (count[txt]) {
                count[txt]++;
            } else {
                count[txt] = 1;
            }
            i++;
        });
        if (i==0) {
            return "";
        }
        // prepare menu for sorting
        var menu = [];
        for (var txt in count) {
            menu.push([txt, count[txt]]);
        }
        // sort by count DESC, txt ASC
        menu.sort(function(a, b) {
            return (a[1] < b[1] ? 1 : (a[1] > b[1] ? -1 : (a[0] < b[0] ? -1 : (a[0] > b[0] ? 1 : 0))));
        });
        // generate OPTION tags
        for (var i=0; i<menu.length; i++) {
            var txt = menu[i][0] + " (" + menu[i][1] + ")";
            menu[i] = HTML.tag("option", txt, {"value": txt});
        }
        // generate SELECT element
        menu = $(HTML.tag("select", menu.join("")));
        return TOOL.dialog_menu(menu, name, value, attr);
    };

    TOOL.dialog_menu = function(menu, name, value, attr) {
        menu.prop("name", name);
        menu.prop("id", "id_" + name);

        // tidy up option values
        // =========================================
        // THIS CODE WAS COMMENTED OUT ON 2019-07-22
        // because changing the menu values is not
        // desirable for a menu of rooms
        // =========================================
        //var info = new RegExp("\\([^()]*\\)$");
        //menu.find("option").each(function(){
        //    var v = TOOL.trim($(this).val().replace(info, ""));
        //    if (v==="0") {
        //        v = "";
        //    }
        //    this.setAttribute("value", v);
        //});

        // set selected item, if any
        if (value) {
            value = value.replace(new RegExp(' style="[^"]*"', "g"), "");
            value = value.replace(new RegExp("[\\r\\n]+", "g"), "");
            value = TOOL.trim(value);
            var found = false;
            menu.find("option").each(function(){
                var v = $(this).val();
                window.console.log("v="+v+", value="+value);
                if (found==false && v && (v.indexOf(value)==0 || value.indexOf(v)==0)) {
                    found = true;
                    this.selected = true;
                    this.setAttribute("selected", "selected");
                } else {
                    this.selected = false;
                    this.removeAttribute("selected");
                }
            });
        }

        // set attributes
        if (attr) {
            if (attr.size) {
                var size = menu.find("option:not(:empty)").length;
                size = Math.min(size, attr.size);
                if (size <= 1) {
                    delete(attr.size);
                    delete(attr.multiple);
                } else {
                    attr.size = size;
                    attr.multiple = "multiple";
                    menu.find("option:empty").remove();
                }
            }
            for (var a in attr) {
                menu.prop(a, attr[a]);
            }
        }

        // shorten displayed text
        var i = 0;
        menu.find("option").each(function(){
            var txt = $(this).text();
            $(this).prop("title", txt);
            $(this).text(UNICODE.shorten(txt));
            if (txt) {
                i++;
            }
        });

        if (i==0) {
            return "";
        }

        return menu.prop("outerHTML");
    };

    TOOL.boldcenter =function(tag, content) {
        var attr = {"align": "center"};
        return HTML.tag("td", HTML.tag("b", content), attr);
    };

    TOOL.multilang =function(content, lang) {
        var attr = {"class": "multilang",
                    "lang": lang};
        return HTML.tag("span", content, attr);
    };

    TOOL.multilangs = function(elm, name) {
        var values = TOOL.form_multilang_values(elm, name);
        var value = "";
        var multilangs = [];
        for (var lang in values) {
            value = values[lang];
            multilangs.push(TOOL.multilang(value, lang));
        }
        if (multilangs.length < 2) {
            return value;
        }
        return multilangs.join("");
    };

    // ==========================================
    // helper functions to manipulate strings
    // ==========================================

    TOOL.force_simple_html = function(html) {
        // remove all tags, except those allowed for formatting
        var tags = "br|hr|b|i|u|em|strong|big|small|sup|sub|tt|var";
        return html.replace(new RegExp("<(/?\\w+)\\b[^>]*>", "g"), "<$1>")
                   .replace(new RegExp("</([^>]*)>", "g"), "<$1/>")
                   .replace(new RegExp("<(?!" + tags + ")[^>]*>", "g"), "")
                   .replace(new RegExp("<([^>]*)/>", "g"), "</$1>");
    };

    TOOL.force_single_line = function(html) {
        var linebreak = new RegExp("<br\\b[^>]*>", "g");
        return TOOL.trim(html.replace(linebreak, " "));
    };

    TOOL.pad = function(txt, padlength, padchar, padleft) {
        if (TOOL.empty(txt)) {
            return "";
        } else {
            txt += "";
        }
        if (TOOL.empty(padlength)) {
            padlength = 2;
        }
        if (TOOL.empty(padchar)) {
            padchar = (isNaN(txt) ? " " : "0");
        }
        if (TOOL.empty(padleft)) {
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
    };

    TOOL.trim = function(str) {
        if (TOOL.empty(str)) {
            return "";
        } else {
            str += "";
        }
        var inner = new RegExp("\\s+", "g");
        var outer = new RegExp("(^\\s+)|(\\s+$)", "g");
        return str.replace(outer, "").replace(inner, " ");
    };

    // ==========================================
    // get form values
    // ==========================================

    TOOL.form_startfinish = function(starthours, startmins, finishhours, finishmins) {
        return TOOL.pad(starthours) + TOOL.str.labelsep + TOOL.pad(startmins) +
               TOOL.str.durationseparator +
               TOOL.pad(finishhours) + TOOL.str.labelsep + TOOL.pad(finishmins);
    };

    TOOL.form_duration = function(starthours, startmins, finishhours, finishmins) {
        var duration = 0;
        if (finishhours < starthours) {
            duration = 23;
        }
        if (finishhours == starthours && finishmins <= startmins) {
            duration = 23; // very unusual, probably a mistake
        }
        duration = (60 * (duration + finishhours - starthours));
        duration = TOOL.pad(duration + finishmins - startmins);
        return duration;
    };

    TOOL.form_value = function(elm, name, returnnumber) {
        var x = $(elm).find("[name=" + name + "]");
        if (x.length) {
            switch (true) {
                case x.is("select"): x = x.find("option:selected"); break;
                case x.is("input[type=radio]"): x = x.find("input:checked"); break;
                case x.is("input[type=checkbox]"): x = (x.is(":checked") ? x : ""); break;
            }
        }
        if (x.length==0) {
            return (returnnumber ? 0 : "");
        }
        if (x.length==1) {
            x = x.val();
            return (returnnumber ? parseInt(x) : x);
        }
        var values = [];
        for (var i=0; i<x.length; i++) {
            values.push(returnnumber ? parseInt(x[i].value) : x[i].value);
        }
        return values;
    };

    TOOL.form_values = function(elm, prefix, returnnumber) {
        var values = [];
        $(elm).find("[name^=" + prefix + "]").each(function(){
            var name = $(this).prop("name");
            if (name==prefix) {
                values = TOOL.form_value(elm, prefix, returnnumber);
            } else {
                var i = parseInt(name.substr(prefix.length));
                values[i] = TOOL.form_value(elm, name, returnnumber);
            }
        });
        return values;
    };

    TOOL.form_multilang_values = function(elm, name) {
        var values = {};
        var lang, value;
        $(elm).find("input[name^=" + name + "]").each(function(){
            lang = $(this).prop("name").substr(name.length + 1);
            value = value = TOOL.trim($(this).val());
            if (value) {
                values[lang] = TOOL.force_simple_html(value);
            }
        });
        return values;
    };

    TOOL.empty = function(v) {
        return (v===undefined || v===null);
    };

    TOOL.fix_multislot_times = function(schedule){

        var r1 = new RegExp("^ *([0-9]+) *: *([0-9]+) *");
        var r2 = new RegExp(" *([0-9]+) *: *([0-9]+) *$");
        var m = null; // matched time parts

        schedule.querySelectorAll(".session[rowspan]").forEach(function(s){
            // remove superfluous rowspan
            if (s.rowSpan == 1) {
                s.removeAttribute("rowspan");
                return;
            }
            // s1 (heading of first slot)
            // s2 (heading of last slot)
            var times = new Array();
            var s1 = s.parentNode;
            var s2 = s1;
            var i = 1;
            while (s2 && i < s.rowSpan) {
                s2 = s2.nextElementSibling;
                i++;
            }
            // get time range for first slot
            if (s1) {
                s1 = s1.querySelector(".timeheading .startfinish");
                if (s1) {
                    s1 = s1.innerText;
                }
            } else {
                s1 = "";
            }
            // get time range for last slot
            if (s2) {
                s2 = s2.querySelector(".timeheading .startfinish");
                if (s2) {
                    s2 = s2.innerText;
                }
            } else {
                s2 = "";
            }
            // extract start time of first slot
            m = r1.exec(s1);
            if (m) {
                times.push(m[1] + ":" + m[2]);
                s1 = parseInt(60 * m[1]) + parseInt(m[2]);
            }
            // extract end time of last slot
            m = r2.exec(s2);
            if (m) {
                times.push(m[1] + ":" + m[2]);
                s2 = parseInt(60 * m[1]) + parseInt(m[2]);
            }
            if (s1 && s2) {
                // fix end time just after midnight
                if (s2 < s1) {
                    s2 += parseInt(24 * 60); // 24 hours
                }
                // set accurate duration for this session
                var elm = s.querySelector(".duration");
                if (elm) {
                    elm.innerText = (s2 - s1) + " mins";
                }
                // set accurate startfinish for this session
                var elm = s.querySelector(".startfinish");
                if (elm) {
                    elm.innerText = times.join(" - ");
                }
            }
        });
    };

    return TOOL;
});
