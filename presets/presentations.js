MAJ.onload_view = function() {
    MAJ.reduce_multilang_view();
    MAJ.reduce_sort_field_menu();
    MAJ.remove_empty_other_presenters();
    MAJ.remove_empty_rows("tr.presentation_file");
    MAJ.remove_empty_rows("tr.comments_questions");
    MAJ.remove_empty_rows("tr.peer_review");
    MAJ.remove_empty_rows("tr.schedule");
}

MAJ.onload_edit = function() {
    MAJ.reduce_multilang_edit();
    MAJ.position_img_tags();
    MAJ.setup_affiliation();
    MAJ.setup_other_presenters();
    MAJ.remove_empty_rows("tr.schedule");
    MAJ.remove_empty_rows("tr.peer_review");
    MAJ.remove_empty_section("#id_schedule_subheading", "tr.subheading");
    MAJ.remove_empty_section("#id_peer_review_subheading", "tr.subheading");
}

MAJ.remove_empty_other_presenters = function() {
    $("tr.other_presenter.name").each(function(){
        var text = $(this).find("td").last().text();
        if (MAJ.trim(text)=="") {
            $(this).next("tr.other_presenter.dinner").remove();
            $(this).remove();
        }
    });
}

MAJ.reduce_multilang_view = function() {
    MAJ.reduce_multilang_spans();
    MAJ.reduce_lang_strings("tr.multilang td");
    MAJ.reduce_lang_strings("tr.submission_record td.multilang");
}

MAJ.reduce_multilang_edit = function() {

    var rows = "";
    rows += "#id_name_title,";
    rows += "tr.other_presenter,";
    rows += "#id_presentation_language,";
    rows += "#id_presentation_type,";
    rows += "tr.schedule";
    MAJ.reduce_multilang_select(rows)

    var rows = "";
    rows += "tr.multilang";
    MAJ.reduce_multilang_radio(rows);

    MAJ.reduce_multilang_spans();
}

MAJ.setup_other_presenters = function() {
    var row = $("#id_copresenters_subheading");
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
    span.setAttribute("id","id_showhide_copresenters");
    if (visible_rows==0) {
        var txt = (MAJ.lang=="ja" ? "表示" : "Show");
        $(span).attr("class","show_copresenters");
    } else {
        var txt = (MAJ.lang=="ja" ? "非表示" : "Hide");
        $(span).attr("class","hide_copresenters");
    }
    txt = document.createTextNode(txt);
    span.appendChild(txt);
    $(span).on("click", MAJ.showhide_copresenters);
    row.find("th").last().append(span);
}

MAJ.showhide_copresenters = function(e) {
    if ($(this).attr("class")=="show_copresenters") {
        // no rows are currently visible
        $(this).text(MAJ.lang=="ja" ? "非表示" : "Hide");
        $(this).attr("class", "hide_copresenters");
        var display = "";
    } else {
        // all rows are currently visible
        $(this).text(MAJ.lang=="ja" ? "表示" : "Show");
        $(this).attr("class", "show_copresenters");
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
