(function ($) {
  $(function () {
    const $table = $("#wcqs-table");
    const $tbody = $("#wcqs-rows");
    const $add = $("#wcqs-add-row");

    function addRow() {
      const $tpl = $('tr.wcqs-row[data-template="1"]').first().clone();
      $tpl.removeAttr("data-template");

      // Générer un ID unique pour cette ligne
      const uniqueId =
        Date.now() + "_" + Math.random().toString(36).substr(2, 9);

      // Remplacer {INDEX} par l'ID unique dans tous les noms de champs
      $tpl.find("input").each(function () {
        const $input = $(this);
        const name = $input.attr("name");
        if (name && name.includes("{INDEX}")) {
          $input.attr("name", name.replace("{INDEX}", uniqueId));
        }
      });

      // Reset inputs
      $tpl.find('input[type="number"]').val("");
      $tpl.find('input[type="text"]').val("");
      $tpl.find('input[type="checkbox"]').prop("checked", true);
      $tbody.append($tpl);
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
