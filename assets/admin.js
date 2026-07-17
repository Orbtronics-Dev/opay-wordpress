/**
 * Opay Admin — AJAX interactions for settings, transactions, and payment buttons.
 *
 * Depends on: jQuery, wp.ajax
 * Localised as: opayAdmin { ajaxUrl, nonce, backendUrl }
 */

/* global opayAdmin, jQuery */
(function ($) {
  "use strict";

  const { ajaxUrl, nonce } = opayAdmin;

  // -------------------------------------------------------------------------
  // Utility helpers
  // -------------------------------------------------------------------------

  function showNotice(message, type = "success") {
    const $notice = $("#opay-notice");
    if (!$notice.length) return;

    $notice
      .removeClass("notice-success notice-error notice-warning")
      .addClass("notice notice-" + type)
      .html("<p>" + message + "</p>")
      .show();

    if (type === "success") {
      setTimeout(() => $notice.fadeOut(), 4000);
    }
  }

  function ajaxPost(action, data, callback) {
    $.post(ajaxUrl, { ...data, action, nonce }, function (response) {
      if (response.success) {
        callback(null, response.data);
      } else {
        callback(response.data?.message || "An error occurred.");
      }
    }).fail(function () {
      callback("Network error. Please try again.");
    });
  }

  // -------------------------------------------------------------------------
  // General settings form
  // -------------------------------------------------------------------------

  $("#opay-general-form").on("submit", function (e) {
    e.preventDefault();
    const data = {
      backend_url: $("#opay-backend-url").val().trim(),
      environment: $("#opay-environment").val(),
    };

    ajaxPost("opay_save_settings", data, function (err, res) {
      if (err) {
        showNotice(err, "error");
      } else {
        showNotice(res.message || "Settings saved.");
      }
    });
  });

  // -------------------------------------------------------------------------
  // API Keys forms
  // -------------------------------------------------------------------------

  $(".opay-keys-form").on("submit", function (e) {
    e.preventDefault();
    const $form = $(this);
    const env = $form.data("env");
    const pk = $form.find('[name="pk"]').val().trim();
    const sk = $form.find('[name="sk"]').val().trim();

    // Don't send placeholder dots as a new secret key
    const skToSend = sk.startsWith("•") ? "" : sk;

    ajaxPost(
      "opay_save_keys",
      { environment: env, pk, sk: skToSend },
      function (err, res) {
        if (err) {
          showNotice(err, "error");
        } else {
          showNotice(res.message || "Keys saved.");
        }
      },
    );
  });

  // -------------------------------------------------------------------------
  // Login with Opay
  // -------------------------------------------------------------------------

  $("#opay-login-form").on("submit", function (e) {
    e.preventDefault();

    const $btn = $("#opay-login-btn");
    const $spinner = $("#opay-login-spinner");

    $btn.prop("disabled", true);
    $spinner.addClass("is-active");

    const data = {
      email: $("#opay-login-email").val().trim(),
      password: $("#opay-login-password").val(),
    };

    ajaxPost("opay_login", data, function (err, res) {
      $btn.prop("disabled", false);
      $spinner.removeClass("is-active");

      if (err) {
        showNotice(err, "error");
      } else {
        showNotice("Connected! Reloading…");
        setTimeout(() => window.location.reload(), 1200);
      }
    });
  });

  // -------------------------------------------------------------------------
  // Logout
  // -------------------------------------------------------------------------

  $("#opay-logout-btn").on("click", function () {
    ajaxPost("opay_logout", {}, function (err) {
      if (err) {
        showNotice(err, "error");
      } else {
        window.location.reload();
      }
    });
  });

  // -------------------------------------------------------------------------
  // Refresh API keys
  // -------------------------------------------------------------------------

  $("#opay-refresh-keys-btn").on("click", function () {
    ajaxPost("opay_refresh_api_keys", {}, function (err, data) {
      if (err) {
        showNotice(err, "error");
      } else {
        showNotice("API keys refreshed.");
        renderApiKeyInfo(data);
      }
    });
  });

  function renderApiKeyInfo(data) {
    const $info = $("#opay-account-info");
    if (!$info.length) return;

    if (!data) {
      $info.text("No key data available.");
      return;
    }

    const keys = Array.isArray(data) ? data : data.data || [];
    if (!keys.length) {
      $info.html(
        "<p>No API keys found. Generate keys from the Opay dashboard.</p>",
      );
      return;
    }

    let html =
      '<table class="widefat fixed opay-mini-table"><thead><tr><th>Type</th><th>Mode</th><th>Key (masked)</th></tr></thead><tbody>';
    keys.forEach((k) => {
      html += `<tr><td>${k.type || ""}</td><td>${k.mode || k.environment || ""}</td><td><code>${k.key || ""}</code></td></tr>`;
    });
    html += "</tbody></table>";
    $info.html(html);
  }

  // Auto-load account info if already connected
  if ($("#opay-account-info").length) {
    ajaxPost("opay_refresh_api_keys", {}, function (err, data) {
      if (!err) renderApiKeyInfo(data);
      else $("#opay-account-info").text("Could not load key information.");
    });
  }

  // -------------------------------------------------------------------------
  // Transactions page
  // -------------------------------------------------------------------------

  let txPage = 1;

  function loadTransactions(page) {
    txPage = page || 1;
    const $body = $("#opay-tx-body");
    if (!$body.length) return;

    $body.html('<tr><td colspan="7" class="opay-loading">Loading…</td></tr>');

    const data = {
      page: txPage,
      search: $("#opay-tx-search").val(),
      status: $("#opay-tx-status").val(),
      from: $("#opay-tx-from").val(),
      to: $("#opay-tx-to").val(),
    };

    ajaxPost("opay_load_transactions", data, function (err, res) {
      if (err) {
        $body.html(`<tr><td colspan="7">${err}</td></tr>`);
        return;
      }

      const rows = res?.data || res || [];
      const meta = res?.meta || {};

      if (!rows.length) {
        $body.html('<tr><td colspan="7">No transactions found.</td></tr>');
        renderPagination(meta);
        return;
      }

      let html = "";
      rows.forEach((tx) => {
        const amount =
          typeof tx.amount === "number"
            ? (tx.amount / 100).toFixed(2)
            : tx.amount || "—";

        const statusClass =
          tx.status === "succeeded"
            ? "opay-status-success"
            : tx.status === "failed"
              ? "opay-status-failed"
              : "opay-status-pending";

        html += `<tr>
                    <td><code>${tx.id || ""}</code></td>
                    <td>${tx.created_at || tx.date || ""}</td>
                    <td>${tx.customer_email || tx.customer?.email || ""}</td>
                    <td>${amount}</td>
                    <td>${(tx.currency || "").toUpperCase()}</td>
                    <td><span class="opay-badge ${statusClass}">${tx.status || ""}</span></td>
                    <td>${tx.mode || tx.environment || ""}</td>
                </tr>`;
      });

      $body.html(html);
      renderPagination(meta);
    });
  }

  function renderPagination(meta) {
    const $pag = $("#opay-tx-pagination");
    if (!$pag.length) return;

    const total = meta?.total || 0;
    const perPage = meta?.per_page || 15;
    const totalPages = Math.ceil(total / perPage);

    if (totalPages <= 1) {
      $pag.html("");
      return;
    }

    let html = '<div class="opay-pag-btns">';
    if (txPage > 1) {
      html += `<button class="button opay-pag-btn" data-page="${txPage - 1}">&#8592; Prev</button>`;
    }
    html += `<span class="opay-pag-info">Page ${txPage} of ${totalPages}</span>`;
    if (txPage < totalPages) {
      html += `<button class="button opay-pag-btn" data-page="${txPage + 1}">Next &#8594;</button>`;
    }
    html += "</div>";
    $pag.html(html);
  }

  $(document).on("click", ".opay-pag-btn", function () {
    loadTransactions(parseInt($(this).data("page"), 10));
  });

  $("#opay-tx-search-btn").on("click", () => loadTransactions(1));
  $("#opay-tx-search").on("keypress", function (e) {
    if (e.which === 13) loadTransactions(1);
  });

  // Auto-load on transactions page
  if ($("#opay-tx-body").length) {
    loadTransactions(1);
  }

  // -------------------------------------------------------------------------
  // Payment Buttons page
  // -------------------------------------------------------------------------

  function loadButtons() {
    const $body = $("#opay-buttons-body");
    if (!$body.length) return;

    $body.html('<tr><td colspan="6" class="opay-loading">Loading…</td></tr>');

    ajaxPost("opay_load_buttons", {}, function (err, res) {
      if (err) {
        $body.html(`<tr><td colspan="6">${err}</td></tr>`);
        return;
      }

      const buttons = res?.data || res || [];

      if (!buttons.length) {
        $body.html(
          '<tr><td colspan="6">No payment buttons found. Create one above.</td></tr>',
        );
        return;
      }

      let html = "";
      buttons.forEach((btn) => {
        const amount = ((btn.amount || 0) / 100).toFixed(2);
        const currency = (btn.currency || "USD").toUpperCase();
        const shortcode = `[opay_button id="${btn.id}" label="Pay Now"]`;

        html += `<tr>
                    <td>${btn.name || ""}</td>
                    <td>${amount}</td>
                    <td>${currency}</td>
                    <td>${btn.mode || btn.environment || ""}</td>
                    <td>
                        <code class="opay-shortcode">${shortcode}</code>
                        <button class="button button-small opay-copy-shortcode"
                                data-shortcode="${shortcode.replace(/"/g, "&quot;")}">
                            Copy
                        </button>
                    </td>
                    <td>
                        <button class="button button-small button-link-delete opay-delete-btn"
                                data-id="${btn.id}">
                            Delete
                        </button>
                    </td>
                </tr>`;
      });

      $body.html(html);
    });
  }

  // Copy shortcode to clipboard
  $(document).on("click", ".opay-copy-shortcode", function () {
    const text = $(this).data("shortcode");
    navigator.clipboard.writeText(text).then(() => {
      const $btn = $(this);
      $btn.text("Copied!");
      setTimeout(() => $btn.text("Copy"), 2000);
    });
  });

  // Delete button
  $(document).on("click", ".opay-delete-btn", function () {
    const id = $(this).data("id");
    const $row = $(this).closest("tr");

    if (!confirm("Delete this payment button? This cannot be undone.")) {
      return;
    }

    ajaxPost("opay_delete_button", { id }, function (err) {
      if (err) {
        showNotice(err, "error");
      } else {
        $row.fadeOut(300, () => $row.remove());
        showNotice("Button deleted.");
      }
    });
  });

  // Create button form
  $("#opay-create-button-form").on("submit", function (e) {
    e.preventDefault();

    const $btn = $("#opay-create-btn-submit");
    const $spinner = $("#opay-btn-spinner");

    $btn.prop("disabled", true);
    $spinner.addClass("is-active");

    const data = {
      name: $("#opay-btn-name").val().trim(),
      amount: parseInt($("#opay-btn-amount").val(), 10),
      currency: $("#opay-btn-currency").val().trim().toUpperCase(),
      description: $("#opay-btn-description").val().trim(),
      mode: $("#opay-btn-mode").val(),
    };

    ajaxPost("opay_create_button", data, function (err, res) {
      $btn.prop("disabled", false);
      $spinner.removeClass("is-active");

      if (err) {
        showNotice(err, "error");
      } else {
        showNotice("Payment button created!");
        $("#opay-create-button-form")[0].reset();
        loadButtons();
      }
    });
  });

  // Refresh buttons
  $("#opay-refresh-buttons").on("click", loadButtons);

  // Auto-load on buttons page
  if ($("#opay-buttons-body").length) {
    loadButtons();
  }
})(jQuery);
