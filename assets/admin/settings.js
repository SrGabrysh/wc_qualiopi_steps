/* WCQS Admin Settings JS - version bulletproof
 * - Pare-chocs $.fn.dialog (si jQuery UI absent)
 * - Ajout / duplication / suppression sans éclater les lignes
 * - *** Reindexation des name= au SUBMIT *** (wcqs[lines][0..N]) pour garantir un POST propre
 */

// Pare-chocs jQuery UI Dialog supprimé : jQuery UI Dialog est maintenant chargé via Plugin.php

(function ($) {
  "use strict";

  function generateRowId() {
    return "r_" + Date.now() + "_" + Math.random().toString(36).slice(2, 6);
  }

  function buildRowHtmlFromTemplate(rowId) {
    // Le template est la ligne avec data-template="1"
    var $templateRow = $('tr.wcqs-row[data-template="1"]').first();
    if ($templateRow.length === 0) {
      console.error('Template row with data-template="1" not found');
      return "";
    }
    var html = $templateRow.prop("outerHTML");
    // Utilise replace avec regex global au lieu de replaceAll (compatibilité)
    return html.replace(/{INDEX}/g, rowId).replace(/data-template="1"/, "");
  }

  function addRow() {
    var rid = generateRowId();
    var html = buildRowHtmlFromTemplate(rid);
    var $row = $(html);
    $("#wcqs-rows").append($row);
    return $row;
  }

  function duplicateRow($sourceRow) {
    var oldId = $sourceRow.attr("data-row-id");
    var newId = generateRowId();
    var html = $sourceRow
      .prop("outerHTML")
      .replace(new RegExp("\\[" + oldId + "\\]", "g"), "[" + newId + "]")
      .replace(new RegExp("wcqs-row-" + oldId, "g"), "wcqs-row-" + newId)
      .replace(
        new RegExp('data-row-id="' + oldId + '"', "g"),
        'data-row-id="' + newId + '"'
      );
    var $clone = $(html);

    // Nettoie les valeurs clonées pour éviter des confusions
    $clone.find('input[name$="[product_id]"]').val("");
    $clone.find('input[name$="[page_id]"]').val("");
    $clone.find('input[name$="[gf_form_id]"]').val("0");
    $clone.find('input[name$="[active]"]').prop("checked", false);
    $clone.find('input[name$="[notes]"]').val("");

    $("#wcqs-rows").append($clone);
    return $clone;
  }

  function deleteRow($row) {
    // Ne compte que les lignes visibles (pas le template)
    var visibleRows = $("#wcqs-rows .wcqs-row").not('[data-template="1"]');

    if (visibleRows.length <= 1) {
      // Dernière ligne : juste vider au lieu de supprimer
      $row.find('input[type="number"]').val("");
      $row.find('input[type="text"]').val("");
      $row.find('input[type="checkbox"]').prop("checked", true); // Actif par défaut
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
    // Ne traite que les lignes visibles (pas le template)
    $("#wcqs-rows .wcqs-row")
      .not('[data-template="1"]')
      .each(function () {
        var $row = $(this);
        var idx = i++;

        $row
          .attr("data-row-id", "idx_" + idx)
          .attr("id", "wcqs-row-idx_" + idx);

        // product_id
        $row
          .find('input[name*="[product_id]"]')
          .attr("name", "wcqs[lines][" + idx + "][product_id]");
        // page_id
        $row
          .find('input[name*="[page_id]"]')
          .attr("name", "wcqs[lines][" + idx + "][page_id]");
        // gf_form_id
        $row
          .find('input[name*="[gf_form_id]"]')
          .attr("name", "wcqs[lines][" + idx + "][gf_form_id]");
        // active (checkbox)
        $row
          .find('input[name*="[active]"]')
          .attr("name", "wcqs[lines][" + idx + "][active]");
        // notes
        $row
          .find('input[name*="[notes]"]')
          .attr("name", "wcqs[lines][" + idx + "][notes]");
      });
  }

  $(document).ready(function () {
    // Si aucune ligne n’existe, on en crée une propre
    if ($("#wcqs-rows .wcqs-row").length === 0) {
      addRow();
    }

    // Ajouter ligne
    $("#wcqs-add-row").on("click", function (e) {
      e.preventDefault();
      addRow();
    });

    // Supprimer ligne (utilise le bouton existant wcqs-remove-row)
    $(document).on("click", ".wcqs-remove-row", function (e) {
      e.preventDefault();
      deleteRow($(this).closest(".wcqs-row"));
    });

    // *** Reindex au SUBMIT (clé de voûte) ***
    $("form").on("submit", function () {
      reindexNames();
      
      // UX: Désactive le bouton pour éviter double-submit
      var $submitBtn = $(this).find('button[type="submit"]');
      $submitBtn.prop('disabled', true).text('Enregistrement...');
      
      return true;
    });
  });
})(jQuery);
