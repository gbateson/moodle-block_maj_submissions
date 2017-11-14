MAJ.onload_edit = function() {
    MAJ.reduce_lang_strings(".addtemplate th");
    MAJ.reduce_multilang_select(".addtemplate .room_type")
    MAJ.reduce_multilang_radio(".addtemplate .equipment");
}
MAJ.onload_view = function() {
    MAJ.reduce_lang_strings(".listtemplate .room_type, " +
                            ".singletemplate .room_type, " +
                            ".singletemplate .notes");
}
