MAJ.onload = function() {

    // check that JQuery is available
    if (typeof(window.$)=="undefined") {

        if (typeof(MAJ.onload_count)=="undefined") {
            MAJ.onload_count = 1;
            var src = MAJ.wwwroot + MAJ.jquery_js;
            var obj = document.createElement("script");
            obj.setAttribute("type","text/javascript");
            obj.setAttribute("src", src);
            document.getElementsByTagName("head")[0].appendChild(obj);
        } else {
            MAJ.onload_count ++;
        }
        if (MAJ.onload_count < MAJ.onload_max_count) {
            setTimeout(MAJ.onload, MAJ.onload_count * 100);
        }
        return false;
    }

    // JQuery is available, so we can continue ...

    // extract info from URL
    MAJ.extract_url_info();

    // extract info from page
    MAJ.extract_page_info();

    // hide the Database module's navigation tabs
    MAJ.hide_nav_tabs();

    // show the login and enrolment explanations
    MAJ.show_explanations();

    // detect the main language for this page
    MAJ.lang = $("html").first().prop("lang");

    // regular expressions to detect ascii/non-ascii chars
    MAJ.en = new RegExp("[ -~]","g");
    MAJ.ja = new RegExp("[^ -~]","g");

    // reset logo width. This has two effects
    // (1) aligns the logo on the left of the header area
    // (2) allows main content to fill the whole page on smartphones
    $("div.logo").css("width", "auto");

    // set URL of payment and membership details
    if (MAJ.payment_link_cm=="") {
        MAJ.payment_link_url = "";
    } else {
        MAJ.payment_link_url = MAJ.wwwroot + MAJ.payment_link_cm;
    }
    if (MAJ.membership_link_cm=="") {
        MAJ.membership_link_url = "";
    } else {
        MAJ.membership_link_url = MAJ.wwwroot + MAJ.membership_link_cm;
    }

    switch (MAJ.script) {

        // list template
        // single template
        case "view.php":
            MAJ.onload_view();
            break;

        // add template
        case "edit.php":
            MAJ.onload_edit();
            break;

        // these pages do not actually include this JS :-(
        case "export.php":
        case "field.php":
        case "preset.php":
        case "templates.php":
            MAJ.reduce_multilang_spans();
            break;
    }
}

MAJ.extract_url_info = function() {
    var regexp = new RegExp("^(.*?)/mod/data/(.*?\\.php)(.*)");
    var m = location.href.match(regexp);
    if (m && m[0]) {
        MAJ.wwwroot = m[1]; // base url of this Moodle site
        MAJ.script  = m[2]; // script name (under "/mod/data/")
        MAJ.query   = m[3]; // URL query string
    } else {
        MAJ.wwwroot = "";
        MAJ.script  = "";
        MAJ.query   = "";
    }
    MAJ.extract_query_attributes();
}

MAJ.extract_query_attributes = function() {
    var i = MAJ.query.indexOf("?");
    if (i < 0) {
        var q = [];
    } else {
        var q = MAJ.query.substr(i+1).split("&");
    }
    MAJ.query = {};
    var plus = new RegExp("\\+", "g");
    for (var i=0; i<q.length; i++) {
        var p = q[i].split("=", 2);
        var name = p[0];
        if (p.length == 1) {
            MAJ.query[name] = "";
        } else {
            var value = p[1].replace(plus, " ");
            MAJ.query[name] = decodeURIComponent(value);
        }
    }
}

MAJ.extract_page_info = function() {

    var x = $("div.usermenu a[href*='/course/switchrole.php']").length;
    MAJ.is_switchrole = (x>=1 ? true : false);

    // watch out for admin who has switched roles
    if (MAJ.is_switchrole) {

        // locate the switched role span
        var span = $("div.usermenu span.meta.role").first();

        // store the localised name of the switched role
        MAJ.is_switchrole = span.text();

        // set login and enrol status according to the switched role
        switch (true) {
            case span.hasClass("role-manager"):
            case span.hasClass("role-course-creator"):
            case span.hasClass("role-teacher"):
            case span.hasClass("role-non-editing-teacher"):
            case span.hasClass("role-student"):
            case span.hasClass("role-participant"):
                MAJ.is_loggedin = true;
                MAJ.is_enrolled = true;
                break;

            case span.hasClass("role-authenticated-user"):
            case span.hasClass("role-restricted-user"):
                MAJ.is_loggedin = true;
                MAJ.is_enrolled = false;
                break;

            case span.hasClass("role-guest"):
            default:
                MAJ.is_loggedin = false;
                MAJ.is_enrolled = false;
                break;
        }
    } else {
        var x = $("div.usermenu span.login");
        MAJ.is_loggedin = (x.length==0 ? true : false);

        x = x.find("a[href$='/login/index.php']");
        MAJ.is_guestuser = (x.length==0 ? false : true);

        var x = $("#settingsnav a[href*='/enrol/index.php']").length;
        MAJ.is_enrolled = (x==0 ? true : false);
    }

    var x = $("ul.nav-tabs li").length;
    MAJ.is_adminuser = (x>=8 ? true : false);

    var x = $("ul.nav-tabs a[href*='/data/edit.php']").length;
    MAJ.can_addrecord = (x>=1 ? true : false);

    if (MAJ.script=="view.php") {
        if ($("div.defaulttemplate").length==0) {
            MAJ.can_viewrecord = false;
            MAJ.can_editrecord = false;
            MAJ.can_deleterecord = false;
        } else {
            MAJ.can_viewrecord = true;
            MAJ.can_editrecord = ($("div.defaulttemplate img[src$=edit]").length > 0);
            MAJ.can_deleterecord = ($("div.defaulttemplate img[src$=delete]").length > 0);
        }
    } else {
        var x = $("ul.nav-tabs a[href*='/data/view.php']").length;
        MAJ.can_viewrecord = (x>=1 ? true : false);
        MAJ.can_editrecord = (MAJ.can_addrecord && MAJ.can_viewrecord);
        MAJ.can_deleterecord = MAJ.can_editrecord;
    }

}

MAJ.hide_nav_tabs = function() {

    // if this is not an admin
    // hide Search and View List
    // === GENERAL TABS ===
    // 1 : View list    view.php (default mode)
    // 2 : View single  view.php mode=single OR rid=999
    // 3 : Search       view.php mode=asearch
    // 4 : Add entry    edit.php
    // === ADMIN TABS =====
    // 5 : Export       export.php
    // 6 : Templates    templates.php
    // 7 : Fields       field.php
    // 8 : Presets      preset.php

    // admin user can see all tabs
    if (MAJ.is_adminuser) {
        $("form#options").css("display", "block");
        return true;
    }

    // users who are not logged or not enrolled cannot see any tabs
    if (MAJ.is_loggedin==false || MAJ.is_enrolled==false || MAJ.is_guestuser) {
        $("ul.nav-tabs").remove();
        return true;
    }

    // redirect from default tab, "List", to "Single" tab
    var redirect = false;
    if (MAJ.script=="view.php") {
        if (MAJ.can_viewrecord==false && MAJ.can_addrecord) {
            redirect = true;
        } else if (MAJ.query.mode) {
            redirect = (MAJ.query.mode=="asearch");
        } else {
            // e.g. confirmation pages when deleting a record
            redirect = (MAJ.query.rid==null && MAJ.query.delete==null);
        }
    }

    if (redirect) {
        var href = "";
        var query = new Array();
        if (MAJ.can_viewrecord) {
            href = "/mod/data/view.php";
            query.push("mode=single");
        } else if (MAJ.can_addrecord) {
            href = "/mod/data/edit.php";
        }
        if (href) {
            if (MAJ.query.d) {
                query.push("d=" + MAJ.query.d);
            } else if (MAJ.query.id) {
                query.push("id=" + MAJ.query.id);
            }
            if (query = query.join("&")) {
                href += "?" + query;
            }
            window.location.replace(MAJ.wwwroot + href);
        }
        return true;
    }

    // remove the "Search" tab
    $("ul.nav-tabs a[href*='mode=asearch']").closest("li").remove();

    // remove the "List all" tab
    $("ul.nav-tabs li").eq(0).remove();
}

MAJ.show_explanations = function() {
    // do NOT show explanations to teachers and admins
    if (MAJ.is_adminuser) {
        return true;
    }

    // detect admin user who has switched roles
    if (MAJ.is_switchrole) {
        // insert switched role into <i>...</i> of explanation
        $("div.howto.switchrole i").text(MAJ.is_switchrole);
    }

    // detect guest user and user who is NOT logged in
    if (MAJ.is_guestuser || MAJ.is_loggedin==false) {
        $("div.howto.begin").css("display", "block");
        $("div.howto.login").css("display", "block");
        $("div.howto.signup").css("display", "block");
        return true;
    }

    // detect user who is NOT enrolled
    if (MAJ.is_enrolled==false) {
        $("div.howto.begin").css("display", "block");
        $("div.howto.enrol").css("display", "block");
        return true;
    }

    // detect user who CAN add new record
    if (MAJ.can_addrecord) {
        $("div.howto.add").css("display", "block");
    }

    // detect user who CAN edit/delete a record
    if (MAJ.can_editrecord) {
        $("div.howto.edit").css("display", "block");
        $("div.howto.delete").css("display", "block");
    }
}

MAJ.reduce_multilang_spans = function() {

    // remove all multilang spans that do NOT use current lang
    if (MAJ.lang) {
        $("span.multilang[lang='" + MAJ.lang + "']").each(function(){
            $(this).replaceWith($(this).html());
        });
        $("span.multilang").remove();
    }

    // locate links for switching language
    $("a.switchlanguage").each(function (){

        var regexp = new RegExp("^.*\\(([a-z0-9_]+)\\).*$");
        var lang = $(this).text().replace(regexp, "$1");

        if (lang.match(new RegExp("^[a-z0-9_]+$"))) {

            // extract href of this link (or page)
            var href = $(this).prop("href");
            if (href=="" || href.indexOf(href.length - 1)=="/") {
                href = location.href;
            }

            // remove previous lang attribute, if any, from href
            href = href.replace(new RegExp("\\&lang=[a-z0-9_]+"), "");

            // adjust href for this link
            $(this).prop("href", href + "&lang=" + lang);
        }
    });
}

MAJ.reduce_sort_field_menu = function() {
    $("#pref_sortby option").each(function(){
        if ($.inArray($(this).text(), MAJ.sortable_fields)<0) {
            $(this).remove();
        }
    });
}

////////////////////////////////

/*
 * convert all single-byte ascii chars
 * to double-byte chars
 */
MAJ.toDoubleByte = function(s){
    var i_max = (s.length - 1);
    for (var i=i_max; i>=0; i--) {
        var c = s.charCodeAt(i);
        if (c >= 33 && c <= 126) {
            c = String.fromCharCode(c + 65248);
            s = s.substr(0, i) + c + s.substr(i + 1);
        }
    }
    return s;
}

/*
 * convert an input field to UPPER CASE
 */
MAJ.toUpperCase = function(evt){
    var s = $(this).val();
    s = MAJ.trim(s);
    s = s.toUpperCase();
    $(this).val(s);
}

/*
 * convert an input field to Proper Case
 *
 * The first character, as well as characters preceded by
 * space, hyphen, backslash or left parenthesis, will be
 * converted to UPPERCASE and the backslash will be removed
 */
MAJ.toProperCase = function(evt){
    var s = $(this).val();
    s = MAJ.trim(s);
    s = s.toLowerCase();
    var regexp = new RegExp("(^|[ (\\\\-])[a-z]","g");
    s = s.replace(regexp, function(m, index){
        return m.replace("\\", "").toUpperCase();
    });
    $(this).val(s);
}

/*
 * convert all double-byte ascii chars
 * in an input field to single-byte
 */
MAJ.toSingleByte = function(evt){
    var s = $(this).val();
    var i_max = (s.length - 1);
    for (var i=i_max; i>=0; i--) {
        var c = s.charCodeAt(i);
        if (c >= 65280 && c <= 65374) {
            c = String.fromCharCode(c - 65248);
            s = s.substr(0, i) + c + s.substr(i + 1);
        } else if (c==12288) { // double-byte space
            s = s.substr(0, i) + " " + s.substr(i + 1);
        }
    }
    $(this).val(s);
}

/*
 * trim white space from the beginning and end of a string
 */
MAJ.trim = function(s) {
    s = s.replace(String.fromCharCode(12288)," ");
    var regexp = new RegExp("(^\\s+)|(\\s+$)","g");
    return s.replace(regexp, "");
}

/*
 * remove rows with nothing in the rightmost column
 */
MAJ.remove_empty_rows = function(rowselector) {
    $(rowselector).each(function(){
        var text = $(this).find("td").last().text();
        if (MAJ.trim(text)=="") {
            var cell = $(this).prev().find(".c0").first();
            var rowspan = cell.attr("rowspan");
            if (rowspan && rowspan > 1) {
                cell.attr("rowspan", rowspan - 1);
            }
            $(this).remove();
        }
    });
}

/*
 * remove section headings for sections that contain no rows
 */
MAJ.remove_empty_section = function(sectionselector, subheading) {
    var section = $(sectionselector);
    if (section.nextUntil(subheading).length==0) {
        section.remove();
    }
}

/*
 * reduce lang strings in specified elements
 */
MAJ.reduce_lang_strings = function(elements) {
    var newline = new RegExp("[\\r\\n]+");
    $(elements).each(function(){
        var text = $(this).text();
        text = text.split(newline);
        for (var i in text) {
            text[i] = MAJ.reduce_lang_string(text[i]);
        }
        $(this).html(text.join("<br />"));
    });
}

/*
 * reduce specified lang string
 */
MAJ.reduce_lang_string = function(s) {
    // assume Japanese chars followed by English chars
    // e.g. 日本語の文字 English chars
    if (MAJ.lang) {
        var chars = "\\x00-\\x7F";
        if (MAJ.lang=="en") {
            chars = " *([" + chars + "]+)$";
        } else {
            chars = "^([^\\x00-\\x29\\x40-\\x7F]*[^" + chars + "]) *";
        }
        chars = new RegExp(chars);
        var m = s.match(chars);
        if (m && m[1]) {
            s = m[1];
        }
    }
    return s;
}

/*
 * determine whether or not the specified string is a multilang string
 */
MAJ.is_multilang = function(s) {
    return (s.match(MAJ.en) && s.match(MAJ.ja));
}

/*
 * reduce multilang string in select element texts
 */
MAJ.reduce_multilang_select = function(rows) {
    $(rows).find("option").each(function(){
        var value = $(this).val();
        if (MAJ.is_multilang(value)) {
            if (MAJ.reduce_currency_string) {
                // registrations
                value = MAJ.reduce_currency_string(value);
            } else {
                // presentations, rooms, events
                value = MAJ.reduce_lang_string(value);
            }
            $(this).text(value);
            // Note: we don't change the "value" of this element
            // because that would confuse the database module
            // when the modified value is sent back to the server
        }
    });
}

/*
 * reduce multilang string in radio element texts
 */
MAJ.reduce_multilang_radio = function(rows) {
    $(rows).find("label").each(function(){
        var value = $(this).text();
        if (MAJ.is_multilang(value)) {
            if (MAJ.reduce_currency_string) {
                // registrations
                value = MAJ.reduce_currency_string(value);
            } else {
                // presentations, rooms, events
                value = MAJ.reduce_lang_string(value);
            }
            $(this).text(value);
        }
    });
}

/*
 * change position of "required" image elements
 */
MAJ.position_img_tags = function() {
    $("img.req").each(function (){
        $(this).parent().css("display", "block");
    });
}
