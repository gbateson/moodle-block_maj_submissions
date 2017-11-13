MAJ.onload_edit = function() {
    MAJ.reduce_lang_strings(".edittemplate th");
    MAJ.reduce_multilang_select(".edittemplate .room_type")
    MAJ.reduce_multilang_radio(".edittemplate .equipment");
}
MAJ.onload_view = function() {
    MAJ.reduce_multilang_spans();
    MAJ.reduce_lang_strings(".listtemplate th:not(.actions), " +
                            ".listtemplate .room_type, " +
                            ".listtemplate .equipment, " +
                            ".singletemplate th:not(.actions), " +
                            ".singletemplate tr:not(.photo) td");
    MAJ.reduce_multiline_list(".listtemplate .equipment, " +
                              ".singletemplate .equipment td");
}

MAJ.reduce_multiline_list = function(elements) {
    var br1 = new RegExp("^(\\s|<br[^>]*>)+");
    var br2 = new RegExp("(\\s|<br[^>]*>)+$");
    var br3 = new RegExp("\\s*<br[^>]*>\\s*", "g");
    var html = $(elements).each(function(){
        var html = $(this).html();
        html = html.replace(br1, "");
        html = html.replace(br2, "");
        html = html.replace(br3, ", ");
        $(this).html(html);
    });
}