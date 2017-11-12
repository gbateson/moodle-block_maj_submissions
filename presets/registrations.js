MAJ.onload_view = function() {
    MAJ.reduce_multilang_view();
    MAJ.reduce_sort_field_menu();
    MAJ.add_payment_link();
    MAJ.remove_empty_presenter();
    MAJ.remove_empty_paid();
    MAJ.remove_empty_receipt_names();
    MAJ.remove_empty_institution_members();
    MAJ.remove_empty_comments();
}

MAJ.onload_edit = function() {
    MAJ.reduce_multilang_edit();
    MAJ.position_img_tags();
    MAJ.setup_name_title();
    MAJ.setup_names();
    MAJ.setup_affiliation();
    MAJ.setup_institution_members();
    MAJ.setup_amount_due();
    MAJ.setup_paid_amount();
    MAJ.setup_membership_fees();
    MAJ.setup_conference_fees();
    MAJ.setup_dinner();
    MAJ.add_payment_link();
    MAJ.add_membership_link();
    MAJ.remove_empty_print_details();
}

MAJ.add_payment_link = function() {
    var en = 'Click here for details of how to pay';
    var ja = '支払方法の詳しくはこちらをクリックしてください。';
    MAJ.add_help_link(en, ja, MAJ.payment_link_url, "tr.amount_due");
}

MAJ.add_membership_link = function() {
    var en = 'Click here for details of membership types';
    var ja = '会員種目と会費の詳しくはこちらをクリックしてください。';
    MAJ.add_help_link(en, ja, MAJ.membership_link_url, "#id_membership_fees");
}

MAJ.add_help_link = function(en, ja, href, rows) {

    // locate required rows in table
    $(rows).each(function(){

        // locate last table cell in this row
        var td = $(this).find("td").get(-1);
        if (td) {

            // create elements to insert
            var txt = (MAJ.lang=="en" ? en : ja);
            txt = document.createTextNode(txt);

            var small = document.createElement("SMALL");
            small.appendChild(txt);

            var link = document.createElement("A");
            link.setAttribute("href", href);
            link.setAttribute("target", "_blank");
            link.appendChild(small);

            td.insertBefore(document.createElement("BR"), td.firstChild);
            td.insertBefore(link, td.firstChild);
        }
    });
}

MAJ.remove_empty_presenter = function() {
    MAJ.remove_empty_rows("tr#id_presenter");
}

MAJ.remove_empty_paid = function() {
    $("tr.paid").each(function(){
        var text = $(this).find("td").last().text();
        text = MAJ.trim(text);
        if (text=="" || text.indexOf("()")>=0) {
            $(this).remove();
        }
    });
}

MAJ.remove_empty_receipt_names = function() {
    MAJ.remove_empty_rows("tr.receipt_name");
}

MAJ.remove_empty_institution_members = function() {
    $("tr.institution_member.name").each(function(){
        var text = $(this).find("td").last().text();
        if (MAJ.trim(text)=="") {
            $(this).next("tr.institution_member.dinner").remove();
            $(this).remove();
        }
    });
}

MAJ.remove_empty_comments = function() {
    MAJ.remove_empty_rows("tr.comments_questions");
}

MAJ.remove_empty_print_details = function() {
    var remove_subheading = true;
    $("tr.print_details").each(function(){
        var text = $(this).find("td").last().text();
        var regexp = new RegExp("\\s+", "g");
        if (MAJ.trim(text)=="") {
            $(this).remove();
        } else {
            remove_subheading = false;
        }
    });
    if (remove_subheading) {
        $("#id_payment_confirmation_subheading").remove();
    }
}

MAJ.reduce_multilang_view = function() {
    MAJ.reduce_multilang_spans();
    var rows = "";
    rows += "#id_presenter,";
    rows += "#id_conference_fees,";
    rows += "#id_membership_fees,";
    rows += "tr.dinner,";
    rows += "tr.amount_due,";
    rows += "tr.payment_method,";
    rows += "tr.paid";
    MAJ.reduce_currency_rows(rows);
}

MAJ.reduce_multilang_edit = function() {

    var rows = "";
    rows += "#id_name_title,";
    rows += "#id_dinner_attend,";
    rows += "#id_dinner_food_drink,";
    rows += "tr.institution_member";
    MAJ.reduce_multilang_select(rows)

    var rows = "";
    rows += "#id_membership_fees,";
    rows += "#id_conference_fees,";
    rows += "#id_payment_method,";
    rows += "#id_paid_method";
    MAJ.reduce_multilang_radio(rows);

    MAJ.reduce_multilang_spans();
}

MAJ.reduce_currency_rows = function(rows) {
    $(rows).find("td").each(function(){
        var text = $(this).text();
        text = MAJ.reduce_currency_string(text);
        $(this).text(text);
    })
}

MAJ.reduce_currency_string = function(s) {
    if (MAJ.lang) {
        var m; // store RegExp matches
        var i; // index

        // set regexp and currency to detect
        // ascii/non-ascii strings to be removed
        // and set the curreny string to be deleted
        if (MAJ.lang=="en") {
            var regexp_chars = "[^ -~]+";
            var currency = "¥";
        } else {
            var regexp_chars = "[!-/:-~]+";
            var currency = " yen";
            // convert payment ID to double-byte
            var regexp_payment_id = new RegExp("^(.*)(ID: [a-zA-Z0-9_-]*)$");
            if (m = regexp_payment_id.exec(s)) {
                s = m[1] + MAJ.toDoubleByte(m[2]);
            }
        }
        regexp_chars = new RegExp(regexp_chars, "g")

        // search through s(tring), reducing all substrings
        var regexp_amount = new RegExp("\\(?¥[0-9]{1,3}(?:,[0-9]{3})* yen\\)?", "g");
        var ss = ""; // reduced string
        var i = 0; // character index
        while (m = regexp_amount.exec(s)) {
            var chars = s.substr(i, m.index - i);
            ss += chars.replace(regexp_chars, "");
            ss += m[0].replace(currency, "");
            i = regexp_amount.lastIndex;
        }
        ss += s.substr(i).replace(regexp_chars, "");
        s = MAJ.trim(ss);
    }
    return s;
}

MAJ.setup_name_title = function() {

    // conditionally disable input field for "name_title" elements
    $("tr.name_title").find("select").each(function(){
        $(this).on("change", function(evt){
            var value = $(this).val();
            var d = (value.indexOf("other") < 0 && value.indexOf("他") < 0);
            $(this).closest("tr").find("input.basefieldinput").prop('disabled', d);
        });
        $(this).trigger("change");
    });
}

MAJ.setup_names = function() {

    // set first and last name, if possible
    // from the user's name displayed elsewhere
    // on this page e.g. logout or profile links
    var fullname = $("span.usertext").text();
    if (fullname.match(new RegExp("^[ -~]+$"))) {
        var language = "english";
    } else {
        var language = "japanese";
    }
    if (fullname) {

        // determine name order from language
        var lang = $("html").first().prop("lang");
        var firstname = new RegExp("^(\\S+).*?$");
        var lastname  = new RegExp("^.*?(\\S+)$");
        if (lang=="en") {
            var givenname = firstname;
            var surname   = lastname;
        } else {
            var givenname = lastname;
            var surname   = firstname;
        }

        // fill in names if necessary
        var input = $("#id_name_" + language + "_given td.c2 input");
        if (input.val()=="") {
            var name = fullname.replace(givenname,"$1");
            input.val(name);
        }
        var input = $("#id_name_" + language + "_surname td.c2 input");
        if (input.val()=="") {
            var name = fullname.replace(surname,"$1");
            input.val(name.toUpperCase());
        }
    }

    // convert English names to single byte
    $("tr.name_english").find("input").on("change", MAJ.toSingleByte);

    // convert Given name(s) to Proper Case
    var rows = "";
    rows += "#id_name_given_en,";
    rows += "tr.institution_member.name";
    $(rows).each(function(){
        $(this).find("input").first().on("change", MAJ.toProperCase);
    });

    // convert Surname to UPPERCASE
    var rows = "";
    rows += "#id_name_surname_en,";
    rows += "tr.institution_member.name";
    $(rows).each(function(){
        $(this).find("input").last().on("change", MAJ.toUpperCase);
    });

    // disable attendance if there is no name
    $("tr.institution_member.name").each(function(){
        var input = $(this).find("input");
        input.on("change", MAJ.showhide_attendance);
        input.last().trigger("change");
    });
}

MAJ.showhide_attendance = function(evt) {
    var row = $(this).closest("tr.institution_member.name");

    var disabled = true;
    row.find("input").each(function(){
        if ($(this).val().length) {
            disabled = false;
        }
    });

    var id = row.prop("id");
    id = id.replace("name_english", "dinner_attend");
    $("#" + id + " select").prop("disabled", disabled);

    var id = row.prop("id");
    id = id.replace("name_english", "dinner_food_drink");
    $("#" + id + " select").prop("disabled", disabled);
}

MAJ.setup_institution_members = function() {
    var row = $("#id_institution_members_subheading");
    var rows = row.nextUntil("tr.subheading", "tr");
    var visible_rows = 0;
    $(rows).each(function(){
        var has_value = false;
        $(this).find("input").each(function(){
            if ($(this).val()) {
                has_value = true;
            }
        });
        if (has_value==false) {
            $(this).css("display", "none");
        } else {
            visible_rows++;
        }
    });
    var span = document.createElement("SPAN");
    span.setAttribute("id","id_showhide_institution_members");
    if (visible_rows==0) {
        var txt = (MAJ.lang=="ja" ? "表示" : "Show");
        $(span).attr("class","show_institution_members");
    } else {
        var txt = (MAJ.lang=="ja" ? "非表示" : "Hide");
        $(span).attr("class","hide_institution_members");
    }
    txt = document.createTextNode(txt);
    span.appendChild(txt);
    $(span).on("click", MAJ.toggle_institution_members);
    row.find("th").last().append(span);
}

MAJ.toggle_institution_members = function(e) {
    if ($(this).attr("class")=="show_institution_members") {
        // no rows are currently visible
        $(this).text(MAJ.lang=="ja" ? "非表示" : "Hide");
        $(this).attr("class", "hide_institution_members");
        var display = "";
    } else {
        // all rows are currently visible
        $(this).text(MAJ.lang=="ja" ? "表示" : "Show");
        $(this).attr("class", "show_institution_members");
        var display = "none";
    }
    var row = $(this).closest("tr");
    var rows = row.nextUntil("tr.subheading", "tr");
    $(rows).each(function(){
        $(this).css("display", display);
    });
}

MAJ.setup_affiliation = function() {

    // convert double-byte ascii chars to single byte
    var rows = "";
    rows += "#id_affiliation_en,";
    rows += "#id_affiliation_state,";
    rows += "#id_affiliation_country";
    $(rows).find("input").on("change", MAJ.toSingleByte);

    // convert Affiliation name and state to Proper Case
    var rows = "";
    rows += "#id_affiliation_en,";
    rows += "#id_affiliation_state";
    $(rows).find("input").on("change", MAJ.toProperCase);

    // convert country to UPPER CASE
    $("#id_affiliation_country").find("input").on("change", MAJ.toUpperCase);

    // tidy up Japanese
    $("#id_affiliation_state").find("input").on("change", function(evt){
        var s = $(this).val();
        switch (s) {
            case "愛知":
            case "愛知県": s = "Aichi"; break;
            case "秋田":
            case "秋田県": s = "Akita"; break;
            case "青森":
            case "青森県": s = "Aomori"; break;
            case "千葉":
            case "千葉県": s = "Chiba"; break;
            case "愛媛":
            case "愛媛県": s = "Ehime"; break;
            case "福井":
            case "福井県": s = "Fukui"; break;
            case "福岡":
            case "福岡県": s = "Fukuoka"; break;
            case "福島":
            case "福島県": s = "Fukushima"; break;
            case "岐阜":
            case "岐阜県": s = "Gifu"; break;
            case "群馬":
            case "群馬県": s = "Gunma"; break;
            case "広島":
            case "広島県": s = "Hiroshima"; break;
            case "北海道": s = "Hokkaido"; break;
            case "兵庫":
            case "兵庫県": s = "Hyogo"; break;
            case "茨城":
            case "茨城県": s = "Ibaraki"; break;
            case "石川":
            case "石川県": s = "Ishikawa"; break;
            case "岩手":
            case "岩手県": s = "Iwate"; break;
            case "香川":
            case "香川県": s = "Kagawa"; break;
            case "鹿児島":
            case "鹿児島県": s = "Kagoshima"; break;
            case "神奈川":
            case "神奈川県": s = "Kanagawa"; break;
            case "高知":
            case "高知県": s = "Kochi"; break;
            case "熊本":
            case "熊本県": s = "Kumamoto"; break;
            case "京都":
            case "京都府": s = "Kyoto"; break;
            case "三重":
            case "三重県": s = "Mie"; break;
            case "宮城":
            case "宮城県": s = "Miyagi"; break;
            case "宮崎":
            case "宮崎県": s = "Miyazaki"; break;
            case "長野":
            case "長野県": s = "Nagano"; break;
            case "長崎":
            case "長崎県": s = "Nagasaki"; break;
            case "奈良":
            case "奈良県": s = "Nara"; break;
            case "新潟":
            case "新潟県": s = "Niigata"; break;
            case "大分":
            case "大分県": s = "Oita"; break;
            case "岡山":
            case "岡山県": s = "Okayama"; break;
            case "沖縄":
            case "沖縄県": s = "Okinawa"; break;
            case "大阪":
            case "大阪府": s = "Osaka"; break;
            case "佐賀":
            case "佐賀県": s = "Saga"; break;
            case "埼玉":
            case "埼玉県": s = "Saitama"; break;
            case "滋賀":
            case "滋賀県": s = "Shiga"; break;
            case "島根":
            case "島根県": s = "Shimane"; break;
            case "静岡":
            case "静岡県": s = "Shizuoka"; break;
            case "栃木":
            case "栃木県": s = "Tochigi"; break;
            case "徳島":
            case "徳島県": s = "Tokushima"; break;
            case "東京":
            case "東京都": s = "Tokyo"; break;
            case "鳥取":
            case "鳥取県": s = "Tottori"; break;
            case "富山":
            case "富山県": s = "Toyama"; break;
            case "和歌山":
            case "和歌山県": s = "Wakayama"; break;
            case "山形":
            case "山形県": s = "Yamagata"; break;
            case "山口":
            case "山口県": s = "Yamaguchi"; break;
            case "山梨":
            case "山梨県": s = "Yamanashi"; break;
        }
        $(this).val(s);
    });

    $("#id_affiliation_country").find("input").on("change", function(evt){
        var s = $(this).val();
        switch (s) {
            case "JP":
            case "日本": s = "JAPAN";    break;
            case "CN":
            case "中国": s = "CHINA";    break;
            case "TW":
            case "台湾": s = "TAIWAN";   break;
            case "KO":
            case "韓国": s = "S. KOREA"; break;
        }
        $(this).val(s);
    });
}

MAJ.setup_paid_amount = function() {
    var regexp = new RegExp("[^0-9]", "g");
    MAJ.remove_empty_rows("tr.paid_details");
    $("#id_paid_amount td input[type=text]").each(function(){
        $(this).on("change", MAJ.toSingleByte)
        $(this).on("change", function(evt){
            var amount = $(this).val();
            amount = amount.replace(regexp, "");
            amount = MAJ.format_multilang_amount(amount);
            $(this).val(amount);
        });
    });
}

MAJ.setup_amount_due = function() {

    $("#id_amount_due td input[type=text]").each(function(){
        // hide the input element
        $(this).css("display","none");

        // extract and format the amount due
        var amount_due = $(this).val();
        amount_due = MAJ.reduce_currency_string(amount_due);
        //amount_due = MAJ.reduce_currency_string(amount_due);

        // store the id of the "amount_due" element
        // so we can find it easily later on
        MAJ.id_of_amount_due_element = $(this).prop("id");

        // set and store the id for the "amount_due_display" element
        MAJ.id_of_amount_display_element = MAJ.id_of_amount_due_element + "_display";

        // create a SPAN to display the amount due
        var span = document.createElement("SPAN");
        $(span).prop("id", MAJ.id_of_amount_display_element);
        $(span).prop("class","amount_display");
        $(span).text(amount_due);

        // insert the SPAN just before the INPUT
        $(this).before(span);
    });
}

MAJ.setup_membership_fees = function() {
    $("#id_membership_fees td input[type=radio]").each(function(){
        MAJ.name_of_membership_fees_element = $(this).prop("name");
        $(this).on("change", MAJ.set_conference_fees);
        $(this).on("change", MAJ.set_amount_due);
        $(this).on("change", MAJ.showhide_institution_members);
    });
    MAJ.showhide_institution_members();
}

MAJ.setup_conference_fees = function() {
    $("#id_conference_fees td input[type=radio]").each(function(){
        MAJ.name_of_conference_fees_element = $(this).prop("name");
        $(this).on("change", MAJ.set_amount_due);
    });
    MAJ.set_conference_fees();
}

MAJ.showhide_institution_members = function(evt) {
    var id = "input[name=" + MAJ.name_of_membership_fees_element + "]";
    var membership_fees = $(id).filter(':checked').val();

    // locate the value of "Fees" item for MAJ Institutions
    var institution_fees = $("#id_membership_fees td input[value~=Institution]").last().val();

    if (membership_fees==institution_fees) {
        var display = "table-row";
    } else {
        var display = "none";
    }
    $("tr.institution_member").css("display", display)
}

MAJ.setup_dinner = function() {
    $("tr.dinner_attend td select").each(function(){
        $(this).on("change", function(evt){
            var i = $(this).prop("selectedIndex");
            $(this).closest("tr").next("tr").find("select").prop("disabled", (i==1 ? false : true));
        });
        $(this).trigger("change");
        $(this).on("change", MAJ.set_amount_due);
    });
}

MAJ.set_conference_fees = function(evt) {
    var m = $("input[name=" + MAJ.name_of_membership_fees_element + "]");
    var c = $("input[name=" + MAJ.name_of_conference_fees_element + "]");
    if (m.last().prop("checked")) {
        // Non-member - all items except first are accessible
        if (c.eq(0).prop("checked")) {
            c.eq(1).prop("checked", true);
        }
        c.prop("disabled", false);
        c.first().prop("disabled", true);
    } else {
        // MAJ member - only first and last items are accessible
        if (c.last().prop("checked")==false) {
            c.first().prop("checked", true);
        }
        c.prop("disabled", true);
        c.first().prop("disabled", false);
        c.last().prop("disabled", false);
    }
    var display = "none";
    c.filter(':disabled').each(function(){
        $(this).nextUntil("input").css("display", display);
        $(this).css("display", display);
    });
    var display = "initial";
    c.filter(':enabled').each(function(){
        $(this).nextUntil("input").css("display", display);
        $(this).css("display", display);
    });
}

/*
 * convert numeric amount to multilang string
 */
MAJ.format_multilang_amount = function(amount) {
    if (amount===null || amount==="") {
        return "";
    }
    if (amount) {
        amount = MAJ.format_number(amount);
    }
    return "¥" + amount + " yen";
}

/*
 * set the amount_due field
 */
MAJ.set_amount_due = function(evt) {

    var id = "input[name=" + MAJ.name_of_membership_fees_element + "]";
    var membership_fees = $(id).filter(':checked').val();

    var id = "input[name=" + MAJ.name_of_conference_fees_element + "]";
    var conference_fees = $(id).filter(':checked').val();

    // get the amount due for membership and conference fees
    var amount_due = 0;
    amount_due += MAJ.extract_amount(membership_fees);
    amount_due += MAJ.extract_amount(conference_fees);

    // add the dinner amount(s)
    $("tr.dinner_attend td select").each(function(){
        if ($(this).prop("disabled")==false) {
            amount_due += MAJ.extract_amount($(this).val());
        }
    });

    // convert amount_due to formatted multilang string
    amount_due = MAJ.format_multilang_amount(amount_due);

    // update value of amount due in (hidden) input element
    var id = "#" + MAJ.id_of_amount_due_element;
    $(id).val(amount_due);

    // update amount in display element
    var id = "#" + MAJ.id_of_amount_display_element;
    $(id).text(MAJ.reduce_currency_string(amount_due));
    //$(id).text(MAJ.reduce_currency_string(amount_due));
}

/*
 * extract the numeric amount from a string, s
 */
MAJ.extract_amount = function(s) {
    if (s) {
        s = s.replace(new RegExp("^.*?([0-9,]+) yen.*$"), "$1");
        s = s.replace(new RegExp(",","g"), "");
        return parseInt(s);
    }
    return 0;
}

/*
 * format a number with commas
 * Note: this seems like a lot of code for a simple job :-)
 */
MAJ.format_number = function(n) {
    var i = parseInt("" + n);
    var d = parseFloat("" + n) - i;
    var s = ("" + i); // to string
    var i_max = s.length;
    var i = (i_max - 1);
    var o = ""; // output string
    while (i >= 0) {
        o = s.charAt(i) + o;
        if (i > 0 && ((i_max - i) % 3)==0) {
            o = "," + o;
        }
        i--;
    }
    if (d > 0.0) {
        o += "." + d
    }
    return o;
}
