/* WCQS Admin Settings JS - version bulletproof
 * - Pare-chocs $.fn.dialog (si jQuery UI absent)
 * - Ajout / duplication / suppression sans éclater les lignes
 * - *** Reindexation des name= au SUBMIT *** (wcqs[lines][0..N]) pour garantir un POST propre
 */

// Pare-chocs jQuery UI Dialog supprimé : jQuery UI Dialog est maintenant chargé via Plugin.php

(function ($) {
  "use strict";

  // Variables globales pour validation
  var validationTimeouts = {};
  var ajaxNonce = wcqsAjax?.nonce || '';

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
      $submitBtn.prop("disabled", true).text("Enregistrement...");

      return true;
    });

    // *** VALIDATION INSTANTANÉE ***
    setupInstantValidation();

    // *** IMPORT/EXPORT CSV ***
    setupCsvHandlers();

    // *** CONTRÔLE LIVE ***
    setupLiveControl();
  });

  /**
   * Configuration de la validation instantanée avec toast
   */
  function setupInstantValidation() {
    // Validation produits
    $(document).on('input blur', 'input[name*="[product_id]"]', function() {
      var $input = $(this);
      var productId = parseInt($input.val());
      var fieldKey = $input.attr('name') + '_product';
      
      clearTimeout(validationTimeouts[fieldKey]);
      
      if (productId > 0) {
        validationTimeouts[fieldKey] = setTimeout(function() {
          validateProduct($input, productId);
        }, 800); // Délai de 800ms
      } else {
        clearValidationState($input);
      }
    });

    // Validation pages
    $(document).on('input blur', 'input[name*="[page_id]"]', function() {
      var $input = $(this);
      var pageId = parseInt($input.val());
      var fieldKey = $input.attr('name') + '_page';
      
      clearTimeout(validationTimeouts[fieldKey]);
      
      if (pageId > 0) {
        validationTimeouts[fieldKey] = setTimeout(function() {
          validatePage($input, pageId);
        }, 800);
      } else {
        clearValidationState($input);
      }
    });

    // Validation Gravity Forms
    $(document).on('input blur', 'input[name*="[gf_form_id]"]', function() {
      var $input = $(this);
      var formId = parseInt($input.val());
      var fieldKey = $input.attr('name') + '_gf';
      
      clearTimeout(validationTimeouts[fieldKey]);
      
      if (formId > 0) {
        validationTimeouts[fieldKey] = setTimeout(function() {
          validateGfForm($input, formId);
        }, 800);
      } else {
        clearValidationState($input);
      }
    });
  }

  /**
   * Validation AJAX d'un produit
   */
  function validateProduct($input, productId) {
    setValidationState($input, 'loading');
    
    $.post(wcqsAjax.ajaxurl, {
      action: 'wcqs_validate_product',
      product_id: productId,
      nonce: ajaxNonce
    })
    .done(function(response) {
      if (response.success) {
        setValidationState($input, 'success', response.data.message);
        showToast('success', response.data.message);
      } else {
        setValidationState($input, 'error', response.data.message);
        showToast('error', response.data.message);
      }
    })
    .fail(function() {
      setValidationState($input, 'error', 'Erreur de validation');
      showToast('error', 'Erreur de validation du produit');
    });
  }

  /**
   * Validation AJAX d'une page
   */
  function validatePage($input, pageId) {
    setValidationState($input, 'loading');
    
    $.post(wcqsAjax.ajaxurl, {
      action: 'wcqs_validate_page',
      page_id: pageId,
      nonce: ajaxNonce
    })
    .done(function(response) {
      if (response.success) {
        setValidationState($input, 'success', response.data.message);
        showToast('success', response.data.message);
      } else {
        setValidationState($input, 'error', response.data.message);
        showToast('error', response.data.message);
      }
    })
    .fail(function() {
      setValidationState($input, 'error', 'Erreur de validation');
      showToast('error', 'Erreur de validation de la page');
    });
  }

  /**
   * Validation AJAX d'un formulaire GF
   */
  function validateGfForm($input, formId) {
    setValidationState($input, 'loading');
    
    $.post(wcqsAjax.ajaxurl, {
      action: 'wcqs_validate_gf_form',
      form_id: formId,
      nonce: ajaxNonce
    })
    .done(function(response) {
      if (response.success) {
        setValidationState($input, 'success', response.data.message);
        showToast('info', response.data.message);
      } else {
        setValidationState($input, 'error', response.data.message);
        showToast('warning', response.data.message);
      }
    })
    .fail(function() {
      setValidationState($input, 'error', 'Erreur de validation');
      showToast('error', 'Erreur de validation du formulaire');
    });
  }

  /**
   * Met à jour l'état visuel d'un champ
   */
  function setValidationState($input, state, message) {
    $input.removeClass('wcqs-loading wcqs-success wcqs-error');
    
    switch(state) {
      case 'loading':
        $input.addClass('wcqs-loading');
        break;
      case 'success':
        $input.addClass('wcqs-success');
        break;
      case 'error':
        $input.addClass('wcqs-error');
        break;
    }
    
    // Tooltip avec message
    if (message) {
      $input.attr('title', message);
    }
  }

  /**
   * Efface l'état de validation
   */
  function clearValidationState($input) {
    $input.removeClass('wcqs-loading wcqs-success wcqs-error').removeAttr('title');
  }

  /**
   * Affiche un toast notification
   */
  function showToast(type, message) {
    // Crée le container de toasts s'il n'existe pas
    if ($('#wcqs-toast-container').length === 0) {
      $('body').append('<div id="wcqs-toast-container"></div>');
    }
    
    var $toast = $('<div class="wcqs-toast wcqs-toast-' + type + '">' + 
                   '<span class="wcqs-toast-message">' + message + '</span>' +
                   '<button class="wcqs-toast-close">&times;</button>' +
                   '</div>');
    
    $('#wcqs-toast-container').append($toast);
    
    // Animation d'entrée
    setTimeout(function() {
      $toast.addClass('wcqs-toast-show');
    }, 100);
    
    // Auto-fermeture après 4s
    setTimeout(function() {
      $toast.removeClass('wcqs-toast-show');
      setTimeout(function() {
        $toast.remove();
      }, 300);
    }, 4000);
    
    // Fermeture manuelle
    $toast.find('.wcqs-toast-close').on('click', function() {
      $toast.removeClass('wcqs-toast-show');
      setTimeout(function() {
        $toast.remove();
      }, 300);
    });
  }

  /**
   * Configuration des handlers CSV
   */
  function setupCsvHandlers() {
    // Export CSV
    $("#wcqs-export-csv").on("click", function() {
      showToast("info", "Génération du fichier CSV...");
      
      var form = $('<form method="post" action="' + wcqsAjax.ajaxurl + '">');
      form.append('<input type="hidden" name="action" value="wcqs_export_csv">');
      form.append('<input type="hidden" name="nonce" value="' + ajaxNonce + '">');
      $('body').append(form);
      form.submit();
      form.remove();
      
      setTimeout(function() {
        showToast("success", "Fichier CSV téléchargé !");
      }, 1000);
    });

    // Télécharger template
    $("#wcqs-download-template").on("click", function() {
      var csvContent = "Product ID;Page ID;GF Form ID;Active;Notes\n";
      csvContent += "123;456;0;Yes;Exemple de mapping\n";
      csvContent += "124;457;5;No;Mapping inactif avec GF\n";
      
      var blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
      var link = document.createElement("a");
      var url = URL.createObjectURL(blob);
      
      link.setAttribute("href", url);
      link.setAttribute("download", "wcqs_template.csv");
      link.style.visibility = 'hidden';
      
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      
      showToast("success", "Template CSV téléchargé !");
    });

    // Import CSV
    $("#wcqs-import-csv").on("click", function() {
      $("#wcqs-csv-file").click();
    });

    $("#wcqs-csv-file").on("change", function() {
      var file = this.files[0];
      if (!file) return;
      
      if (file.type !== "text/csv" && !file.name.endsWith('.csv')) {
        showToast("error", "Veuillez sélectionner un fichier CSV");
        return;
      }
      
      if (file.size > 1024 * 1024) {
        showToast("error", "Fichier trop volumineux (max 1MB)");
        return;
      }
      
      var formData = new FormData();
      formData.append('action', 'wcqs_import_csv');
      formData.append('nonce', ajaxNonce);
      formData.append('csv_file', file);
      
      showCsvProgress("Importation en cours...", 30);
      
      $.ajax({
        url: wcqsAjax.ajaxurl,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
          showCsvProgress("Finalisation...", 100);
          
          setTimeout(function() {
            hideCsvProgress();
            
            if (response.success) {
              showToast("success", response.data.message);
              
              // Recharge la page pour afficher les nouveaux mappings
              setTimeout(function() {
                window.location.reload();
              }, 2000);
            } else {
              showToast("error", response.data.message);
              
              if (response.data.error_details) {
                console.error("Détails des erreurs:", response.data.error_details);
              }
            }
          }, 500);
        },
        error: function() {
          hideCsvProgress();
          showToast("error", "Erreur lors de l'import CSV");
        }
      });
      
      // Reset input
      $(this).val('');
    });
  }

  /**
   * Affiche la barre de progression CSV
   */
  function showCsvProgress(message, percent) {
    var $progress = $("#wcqs-csv-progress");
    $progress.show();
    $progress.find("div div").css("width", percent + "%");
    $progress.find("p").text(message);
  }

  /**
   * Cache la barre de progression CSV
   */
  function hideCsvProgress() {
    $("#wcqs-csv-progress").hide();
  }

  /**
   * Configuration du contrôle live
   */
  function setupLiveControl() {
    // Charge les stats au démarrage
    loadLiveStats();

    // Actualiser stats
    $("#wcqs-refresh-stats").on("click", function() {
      loadLiveStats();
    });

    // Recherche rapide
    var searchTimeout;
    $("#wcqs-search-input").on("input", function() {
      var searchTerm = $(this).val().trim();
      
      clearTimeout(searchTimeout);
      
      if (searchTerm.length < 2) {
        $("#wcqs-search-results").hide();
        return;
      }
      
      searchTimeout = setTimeout(function() {
        performQuickSearch(searchTerm);
      }, 500);
    });

    // Auto-refresh des stats toutes les 30 secondes
    setInterval(function() {
      loadLiveStats();
    }, 30000);
  }

  /**
   * Charge les statistiques live
   */
  function loadLiveStats() {
    $("#wcqs-stats-content").html("🔄 Chargement...");
    
    $.post(wcqsAjax.ajaxurl, {
      action: 'wcqs_live_status',
      nonce: ajaxNonce
    })
    .done(function(response) {
      if (response.success) {
        displayLiveStats(response.data);
      } else {
        $("#wcqs-stats-content").html("❌ Erreur de chargement");
      }
    })
    .fail(function() {
      $("#wcqs-stats-content").html("❌ Erreur réseau");
    });
  }

  /**
   * Affiche les statistiques live
   */
  function displayLiveStats(data) {
    var html = '<div style="font-size: 13px; line-height: 1.6;">';
    
    html += '<div style="margin-bottom: 10px;">';
    html += '<strong>📈 Mappings:</strong> ' + data.stats.total_mappings + ' total<br>';
    html += '✅ Actifs: ' + data.stats.active_mappings + ' | ❌ Inactifs: ' + data.stats.inactive_mappings;
    html += '</div>';
    
    if (data.stats.products_with_issues > 0 || data.stats.pages_with_issues > 0) {
      html += '<div style="margin-bottom: 10px; color: #d63638;">';
      html += '<strong>⚠️ Problèmes détectés:</strong><br>';
      if (data.stats.products_with_issues > 0) {
        html += '🛍️ Produits: ' + data.stats.products_with_issues + '<br>';
      }
      if (data.stats.pages_with_issues > 0) {
        html += '📄 Pages: ' + data.stats.pages_with_issues + '<br>';
      }
      html += '</div>';
    } else {
      html += '<div style="margin-bottom: 10px; color: #00a32a;">';
      html += '<strong>✅ Tout fonctionne correctement</strong>';
      html += '</div>';
    }
    
    html += '<div style="font-size: 11px; color: #666;">';
    html += '🕐 Dernière vérification: ' + data.stats.last_check + '<br>';
    html += '🔧 WC: ' + (data.wc_active ? 'Actif' : 'Inactif') + ' | GF: ' + (data.gf_active ? 'Actif' : 'Inactif');
    html += '</div>';
    
    html += '</div>';
    
    $("#wcqs-stats-content").html(html);
  }

  /**
   * Effectue une recherche rapide
   */
  function performQuickSearch(searchTerm) {
    var searchType = $("#wcqs-search-type").val();
    
    $.post(wcqsAjax.ajaxurl, {
      action: 'wcqs_quick_search',
      nonce: ajaxNonce,
      search: searchTerm,
      type: searchType
    })
    .done(function(response) {
      if (response.success) {
        displaySearchResults(response.data.results);
      } else {
        displaySearchResults([]);
      }
    })
    .fail(function() {
      displaySearchResults([]);
    });
  }

  /**
   * Affiche les résultats de recherche
   */
  function displaySearchResults(results) {
    var $container = $("#wcqs-search-results");
    
    if (results.length === 0) {
      $container.html('<div style="padding: 10px; text-align: center; color: #666;">Aucun résultat</div>').show();
      return;
    }
    
    var html = '';
    results.forEach(function(result) {
      html += '<div class="wcqs-search-result" style="padding: 8px; border-bottom: 1px solid #eee; cursor: pointer;" data-id="' + result.id + '">';
      html += '<div style="font-weight: bold;">#' + result.id + ' - ' + result.title + '</div>';
      html += '<div style="font-size: 12px; color: #666;">' + result.subtitle + '</div>';
      html += '</div>';
    });
    
    $container.html(html).show();
    
    // Click sur résultat
    $container.find('.wcqs-search-result').on('click', function() {
      var id = $(this).data('id');
      var searchType = $("#wcqs-search-type").val();
      
      // Remplit le champ correspondant dans la première ligne vide
      var $emptyRow = $("#wcqs-rows .wcqs-row").not('[data-template="1"]').filter(function() {
        if (searchType === 'product') {
          return $(this).find('input[name*="[product_id]"]').val() === '';
        } else {
          return $(this).find('input[name*="[page_id]"]').val() === '';
        }
      }).first();
      
      if ($emptyRow.length === 0) {
        // Ajoute une nouvelle ligne
        addRow();
        $emptyRow = $("#wcqs-rows .wcqs-row").not('[data-template="1"]').last();
      }
      
      if (searchType === 'product') {
        $emptyRow.find('input[name*="[product_id]"]').val(id).trigger('blur');
      } else {
        $emptyRow.find('input[name*="[page_id]"]').val(id).trigger('blur');
      }
      
      $container.hide();
      $("#wcqs-search-input").val('');
      
      showToast('success', 'ID ' + id + ' ajouté à la ligne');
    });
  }

})(jQuery);
