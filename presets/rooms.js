MAJ.onload_edit = function() {
    MAJ.reduce_lang_strings(".defaulttemplate th");
    MAJ.reduce_multilang_select(".defaulttemplate .room_type")
    MAJ.reduce_multilang_radio(".defaulttemplate .equipment");
}
MAJ.onload_view = function() {
    MAJ.reduce_lang_strings(".defaulttemplate th:not(.actions)," +
                            ".defaulttemplate tr:not(.photo) td");
}

