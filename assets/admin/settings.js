/* WCQS Admin Settings JS - version bulletproof
 * - Pare-chocs $.fn.dialog (si jQuery UI absent)
 * - Ajout / duplication / suppression sans éclater les lignes
 * - *** Reindexation des name= au SUBMIT *** (wcqs[lines][0..N]) pour garantir un POST propre
 */

(function($){
  // Pare-chocs jQuery UI Dialog (évite $(...).dialog is not a function)
  if ($ && !$.fn.dialog) { $.fn.dialog = function(){ return this; }; }
})(window.jQuery);

(function($){
  'use strict';

  function generateRowId() {
    return 'r_' + Date.now() + '_' + Math.random().toString(36).slice(2,6);
  }

  function buildRowHtmlFromTemplate(rowId) {
    var tpl = $('#wcqs-row-template').html();
    return tpl.replaceAll('{INDEX}', rowId);
  }

  function addRow() {
    var rid  = generateRowId();
    var html = buildRowHtmlFromTemplate(rid);
    var $row = $(html);
    $('#wcqs-rows').append($row);
    return $row;
  }

  function duplicateRow($sourceRow) {
    var oldId = $sourceRow.attr('data-row-id');
    var newId = generateRowId();
    var html  = $sourceRow.prop('outerHTML')
      .replaceAll('[' + oldId + ']', '[' + newId + ']')
      .replaceAll('wcqs-row-' + oldId, 'wcqs-row-' + newId)
      .replaceAll('data-row-id="' + oldId + '"', 'data-row-id="' + newId + '"');
    var $clone = $(html);

    // Nettoie les valeurs clonées pour éviter des confusions
    $clone.find('input[name$="[product_id]"]').val('');
    $clone.find('input[name$="[page_id]"]').val('');
    $clone.find('input[name$="[gf_form_id]"]').val('0');
    $clone.find('input[name$="[active]"]').prop('checked', false);
    $clone.find('input[name$="[notes]"]').val('');

    $('#wcqs-rows').append($clone);
    return $clone;
  }

  function deleteRow($row) {
    if ($('#wcqs-rows .wcqs-row').length <= 1) {
      $row.find('input[type="number"]').val('');
      $row.find('input[type="text"]').val('');
      $row.find('input[type="checkbox"]').prop('checked', false);
      return;
    }
    $row.remove();
  }

  /**
   * Reindexe tous les name= en wcqs[lines][0..N][field]
   * -> Élimine TOTALEMENT le risque de "champs séparés"
   */
  function reindexNames() {
    var i = 0;
    $('#wcqs-rows .wcqs-row').each(function(){
      var $row = $(this);
      var idx = i++;

      $row.attr('data-row-id', 'idx_' + idx).attr('id', 'wcqs-row-idx_' + idx);

      // product_id
      $row.find('input[name$="[product_id]"]').attr('name', 'wcqs[lines]['+idx+'][product_id]');
      // page_id
      $row.find('input[name$="[page_id]"]').attr('name', 'wcqs[lines]['+idx+'][page_id]');
      // gf_form_id
      $row.find('input[name$="[gf_form_id]"]').attr('name', 'wcqs[lines]['+idx+'][gf_form_id]');
      // active (checkbox)
      $row.find('input[name$="[active]"]').attr('name', 'wcqs[lines]['+idx+'][active]');
      // notes
      $row.find('input[name$="[notes]"]').attr('name', 'wcqs[lines]['+idx+'][notes]');
    });
  }

  $(document).ready(function(){

    // Si aucune ligne n’existe, on en crée une propre
    if ($('#wcqs-rows .wcqs-row').length === 0) {
      addRow();
    }

    // Ajouter / dupliquer / supprimer
    $('#wcqs-add-row').on('click', function(e){ e.preventDefault(); addRow(); });
    $(document).on('click', '.wcqs-duplicate-row', function(e){ e.preventDefault(); duplicateRow($(this).closest('.wcqs-row')); });
    $(document).on('click', '.wcqs-delete-row',    function(e){ e.preventDefault(); deleteRow($(this).closest('.wcqs-row')); });

    // *** Reindex au SUBMIT (clé de voûte) ***
    $('form').on('submit', function(){
      reindexNames();
      return true;
    });
  });

})(jQuery);
