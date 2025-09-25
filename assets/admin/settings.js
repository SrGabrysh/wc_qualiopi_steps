/* WCQS Admin Settings JS
 * - Pare-chocs pour $.fn.dialog (si jQuery UI absent quelque part)
 * - Génération d'un rowId unique PAR LIGNE
 * - Ajout / duplication / suppression sans jamais "éclater" les lignes
 * - Normalisation des lignes existantes au chargement (assignation d'un rowId cohérent si besoin)
 */

/* Pare-chocs jQuery UI Dialog (évite $(...).dialog is not a function) */
(function($){
  if ($ && !$.fn.dialog) {
    $.fn.dialog = function(){ return this; }; // no-op
  }
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

  // Normalise une ligne : force tous les name="wcqs[lines][{rid}][...]" à partager le même rid
  function normalizeRow($row, rowId) {
    if (!rowId) {
      rowId = $row.attr('data-row-id') || generateRowId();
      $row.attr('data-row-id', rowId).attr('id', 'wcqs-row-' + rowId);
    }
    // Replace tout ancien identifiant détecté dans le HTML
    var oldIdMatch = $row.prop('outerHTML').match(/wcqs\[lines]\[([^\]]+)\]/);
    if (oldIdMatch && oldIdMatch[1] !== rowId) {
      var oldId = oldIdMatch[1];
      var html  = $row.prop('outerHTML')
        .replaceAll('[' + oldId + ']', '[' + rowId + ']')
        .replaceAll('wcqs-row-' + oldId, 'wcqs-row-' + rowId)
        .replaceAll('data-row-id="' + oldId + '"', 'data-row-id="' + rowId + '"');
      var $fixed = $(html);
      $row.replaceWith($fixed);
      return $fixed;
    }
    return $row;
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
      .replaceAll('data-row-id="' + oldId + '"', 'data-row-id="' + newId + '"')
      .replace(/value=""/g, 'value=""'); // garde vide, l'admin remplira
    var $clone = $(html);
    $('#wcqs-rows').append($clone);
    return $clone;
  }

  function deleteRow($row) {
    // Minimum : ne pas supprimer la toute dernière ligne vide pour UX
    if ($('#wcqs-rows .wcqs-row').length <= 1) {
      // reset inputs
      $row.find('input[type="number"]').val('');
      $row.find('input[type="text"]').val('');
      $row.find('input[type="checkbox"]').prop('checked', false);
      return;
    }
    $row.remove();
  }

  $(document).ready(function(){

    // Normalise toutes les lignes présentes à l'ouverture
    $('#wcqs-rows .wcqs-row').each(function(){
      var $row = $(this);
      var rid  = $row.attr('data-row-id');
      if (!rid) {
        rid = generateRowId();
        $row.attr('data-row-id', rid).attr('id', 'wcqs-row-' + rid);
      }
      var $fixed = normalizeRow($row, rid);
      if ($fixed !== $row) {
        $row = $fixed;
      }
    });

    // Ajouter une ligne
    $('#wcqs-add-row').on('click', function(e){
      e.preventDefault();
      addRow();
    });

    // Dupliquer une ligne
    $(document).on('click', '.wcqs-duplicate-row', function(e){
      e.preventDefault();
      var $row = $(this).closest('.wcqs-row');
      duplicateRow($row);
    });

    // Supprimer une ligne
    $(document).on('click', '.wcqs-delete-row', function(e){
      e.preventDefault();
      var $row = $(this).closest('.wcqs-row');
      deleteRow($row);
    });

  });

})(jQuery);
