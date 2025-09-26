/**
 * WCQS Log Viewer - Interface JavaScript
 *
 * ⚠️ WARNING: TB-Formation utilise WooCommerce BLOCKS
 * Cette interface permet de consulter les logs sans SSH.
 */

(function ($) {
  "use strict";

  let autoRefreshInterval = null;
  let isLoading = false;

  const LogViewer = {
    init: function () {
      this.bindEvents();
      this.loadLogs();
    },

    bindEvents: function () {
      // Filtres
      $("#wcqs-time-filter, #wcqs-level-filter, #wcqs-source-filter").on(
        "change",
        this.loadLogs.bind(this)
      );

      // Actions
      $("#wcqs-refresh-logs").on("click", this.loadLogs.bind(this));
      $("#wcqs-download-logs").on("click", this.downloadLogs.bind(this));
      $("#wcqs-clear-logs").on("click", this.clearLogs.bind(this));

      // Auto-refresh
      $("#wcqs-auto-refresh").on("change", this.toggleAutoRefresh.bind(this));

      // Tests de debug
      $("#wcqs-test-cart-guard").on("click", () => this.runTest("cart_guard"));
      $("#wcqs-verify-hooks").on("click", () => this.runTest("hooks"));
      $("#wcqs-system-info").on("click", () => this.runTest("system"));
      $("#wcqs-simulate-cart").on("click", () => this.runTest("simulate_cart"));
    },

    loadLogs: function () {
      if (isLoading) return;

      isLoading = true;
      this.showLoading();

      const data = {
        action: "wcqs_get_logs",
        nonce: wcqsLogViewer.nonce,
        time_filter: $("#wcqs-time-filter").val(),
        level_filter: $("#wcqs-level-filter").val(),
        source_filter: $("#wcqs-source-filter").val(),
      };

      $.post(wcqsLogViewer.ajax_url, data)
        .done(this.displayLogs.bind(this))
        .fail(this.showError.bind(this))
        .always(() => {
          isLoading = false;
        });
    },

    displayLogs: function (response) {
      if (!response.success) {
        this.showError(response.data?.message || "Erreur inconnue");
        return;
      }

      const { logs, stats } = response.data;

      // Debug: afficher dans la console ce qui est reçu
      console.log("WCQS Log Viewer - Données reçues:", { logs, stats });

      // Mettre à jour les statistiques
      this.updateStats(stats);

      // Afficher les logs
      const $content = $("#wcqs-log-content");
      $content.empty();

      if (!logs || logs.length === 0) {
        $content.html(
          '<div class="wcqs-no-logs">Aucun log trouvé pour les critères sélectionnés</div>'
        );
        return;
      }

      logs.forEach((log, index) => {
        // Debug: afficher chaque log dans la console
        if (index < 3) {
          // Afficher seulement les 3 premiers pour éviter le spam
          console.log(`WCQS Log ${index}:`, log);
        }

        const $logEntry = this.createLogEntry(log);
        $content.append($logEntry);
      });
    },

    createLogEntry: function (log) {
      const levelClass = `wcqs-log-level-${log.level}`;
      const sourceClass = `wcqs-log-source-${log.source}`;

      // S'assurer que toutes les valeurs sont des strings
      const datetime =
        typeof log.datetime === "string"
          ? log.datetime
          : JSON.stringify(log.datetime);
      const level = typeof log.level === "string" ? log.level : "unknown";
      const source = typeof log.source === "string" ? log.source : "unknown";
      const message =
        typeof log.message === "string"
          ? log.message
          : JSON.stringify(log.message);

      return $(`
                <div class="wcqs-log-entry ${levelClass} ${sourceClass}">
                    <span class="wcqs-log-time">${datetime}</span>
                    <span class="wcqs-log-level">
                        <span class="wcqs-level-badge wcqs-level-${level}">${level.toUpperCase()}</span>
                    </span>
                    <span class="wcqs-log-source">${this.formatSource(
                      source
                    )}</span>
                    <span class="wcqs-log-message">${this.formatMessage(
                      message
                    )}</span>
                </div>
            `);
    },

    formatSource: function (source) {
      const sources = {
        wc_logs: "WC",
        debug_log: "WP",
        wcqs_specific: "WCQS",
      };
      return sources[source] || source;
    },

    formatMessage: function (message) {
      // Mettre en évidence les mots-clés importants
      return message
        .replace(/(WCQS|Cart_Guard)/g, "<strong>$1</strong>")
        .replace(
          /(ERROR|FATAL|CRITICAL)/g,
          '<span class="wcqs-highlight-error">$1</span>'
        )
        .replace(
          /(WARNING|WARN)/g,
          '<span class="wcqs-highlight-warning">$1</span>'
        )
        .replace(
          /(SUCCESS|OK|PASS)/g,
          '<span class="wcqs-highlight-success">$1</span>'
        );
    },

    updateStats: function (stats) {
      $("#wcqs-total-logs").text(stats.total || 0);
      $("#wcqs-error-count").text(stats.errors || 0);
      $("#wcqs-warning-count").text(stats.warnings || 0);
      $("#wcqs-last-activity").text(stats.last_activity || "Aucune");
    },

    downloadLogs: function () {
      const data = {
        action: "wcqs_download_logs",
        nonce: wcqsLogViewer.nonce,
        time_filter: $("#wcqs-time-filter").val(),
        level_filter: $("#wcqs-level-filter").val(),
        source_filter: $("#wcqs-source-filter").val(),
      };

      // Créer un formulaire temporaire pour le téléchargement
      const $form = $("<form>", {
        method: "POST",
        action: wcqsLogViewer.ajax_url,
      });

      Object.keys(data).forEach((key) => {
        $form.append(
          $("<input>", {
            type: "hidden",
            name: key,
            value: data[key],
          })
        );
      });

      $("body").append($form);
      $form.submit();
      $form.remove();

      this.showNotice("Téléchargement des logs en cours...", "info");
    },

    clearLogs: function () {
      if (!confirm(wcqsLogViewer.strings.confirm_clear)) {
        return;
      }

      const data = {
        action: "wcqs_clear_logs",
        nonce: wcqsLogViewer.nonce,
      };

      $.post(wcqsLogViewer.ajax_url, data)
        .done((response) => {
          if (response.success) {
            this.showNotice(wcqsLogViewer.strings.cleared, "success");
            this.loadLogs();
          } else {
            this.showError(
              response.data?.message || "Erreur lors de la suppression"
            );
          }
        })
        .fail(() => {
          this.showError("Erreur de communication avec le serveur");
        });
    },

    runTest: function (testType) {
      const data = {
        action: "wcqs_test_hooks",
        nonce: wcqsLogViewer.nonce,
        test_type: testType,
      };

      const $button = $(`#wcqs-${testType.replace("_", "-")}`);
      const originalText = $button.text();

      $button.prop("disabled", true).text("Test en cours...");

      $.post(wcqsLogViewer.ajax_url, data)
        .done((response) => {
          if (response.success) {
            this.displayTestResults(response.data);
            // Recharger les logs pour voir les résultats des tests
            setTimeout(() => this.loadLogs(), 1000);
          } else {
            this.showError("Erreur lors du test");
          }
        })
        .fail(() => {
          this.showError("Erreur de communication");
        })
        .always(() => {
          $button.prop("disabled", false).text(originalText);
        });
    },

    displayTestResults: function (results) {
      let html = `<div class="wcqs-test-results">
                <h4>🔬 ${results.test_name}</h4>
                <p><strong>Timestamp:</strong> ${results.timestamp}</p>`;

      if (results.results) {
        html += "<ul>";
        results.results.forEach((result) => {
          const statusIcon =
            result.status === "PASS"
              ? "✅"
              : result.status === "FAIL"
              ? "❌"
              : "ℹ️";
          html += `<li>${statusIcon} <strong>${result.test}:</strong> ${result.message}</li>`;
        });
        html += "</ul>";
      }

      if (results.info) {
        html += "<h5>Informations système:</h5><ul>";
        Object.keys(results.info).forEach((key) => {
          html += `<li><strong>${key}:</strong> ${results.info[key]}</li>`;
        });
        html += "</ul>";
      }

      if (results.message) {
        html += `<p>${results.message}</p>`;
      }

      html += "</div>";

      // Afficher dans une modal ou une zone dédiée
      this.showModal("Résultats du test", html);
    },

    toggleAutoRefresh: function () {
      const isEnabled = $("#wcqs-auto-refresh").is(":checked");

      if (isEnabled) {
        autoRefreshInterval = setInterval(() => {
          if (!isLoading) {
            this.loadLogs();
          }
        }, 30000); // 30 secondes
        this.showNotice("Auto-refresh activé (30s)", "info");
      } else {
        if (autoRefreshInterval) {
          clearInterval(autoRefreshInterval);
          autoRefreshInterval = null;
        }
        this.showNotice("Auto-refresh désactivé", "info");
      }
    },

    showLoading: function () {
      $("#wcqs-log-content").html(`
                <div class="wcqs-log-loading">
                    <span class="spinner is-active"></span>
                    ${wcqsLogViewer.strings.loading}
                </div>
            `);
    },

    showError: function (message) {
      $("#wcqs-log-content").html(`
                <div class="wcqs-log-error">
                    <span class="dashicons dashicons-warning"></span>
                    ${message || wcqsLogViewer.strings.error}
                </div>
            `);
    },

    showNotice: function (message, type = "info") {
      const $notice = $(`
                <div class="notice notice-${type} is-dismissible wcqs-temp-notice">
                    <p>${message}</p>
                </div>
            `);

      $(".wcqs-log-viewer-section").prepend($notice);

      // Auto-dismiss après 5 secondes
      setTimeout(() => {
        $notice.fadeOut(() => $notice.remove());
      }, 5000);
    },

    showModal: function (title, content) {
      // Créer une modal simple
      const $modal = $(`
                <div class="wcqs-modal-overlay">
                    <div class="wcqs-modal">
                        <div class="wcqs-modal-header">
                            <h3>${title}</h3>
                            <button class="wcqs-modal-close">&times;</button>
                        </div>
                        <div class="wcqs-modal-content">
                            ${content}
                        </div>
                        <div class="wcqs-modal-footer">
                            <button class="button wcqs-modal-close">Fermer</button>
                        </div>
                    </div>
                </div>
            `);

      $("body").append($modal);

      // Fermeture
      $modal.on(
        "click",
        ".wcqs-modal-close, .wcqs-modal-overlay",
        function (e) {
          if (e.target === this) {
            $modal.remove();
          }
        }
      );
    },
  };

  // Initialisation au chargement de la page
  $(document).ready(function () {
    if ($(".wcqs-log-viewer-section").length) {
      LogViewer.init();
    }
  });
})(jQuery);
