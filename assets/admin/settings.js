(function ($) {
  $(function () {
    const $table = $("#wcqs-table");
    const $tbody = $("#wcqs-rows");
    const $add = $("#wcqs-add-row");

    function addRow() {
      const $tpl = $('tr.wcqs-row[data-template="1"]').first().clone();
      $tpl.removeAttr("data-template");

      // CORRECTIF: Générer UN SEUL ID unique pour TOUTE la ligne
      const uniqueId = 'r_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6);

      // Remplacer {INDEX} par l'ID unique dans TOUT le HTML de la ligne
      const newHtml = $tpl.prop('outerHTML').replace(/{INDEX}/g, uniqueId);
      const $newRow = $(newHtml);
      
      // Reset inputs
      $newRow.find('input[type="number"]').val("");
      $newRow.find('input[type="text"]').val("");
      $newRow.find('input[type="checkbox"]').prop("checked", true);
      $tbody.append($newRow);
    }

    $add.on("click", addRow);

    $tbody.on("click", ".wcqs-remove-row", function () {
      const $row = $(this).closest("tr");
      if ($tbody.find("tr").length > 1) {
        $row.remove();
      } else {
        // Dernière ligne : juste vider
        $row.find('input[type="number"], input[type="text"]').val("");
        $row.find('input[type="checkbox"]').prop("checked", true);
      }
    });
  });
})(jQuery);
