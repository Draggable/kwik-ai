!function(i) {
  "use strict";
  i(document).ready(function() {
    var t = i("#kwik-ai-refresh-models"), e = i("#kwik-ai-test-connection"), a = i("#kwik-ai-model-loading"), n = i("#kwik-ai-model-select"), s = i("#kwik-ai-connection-result");
    function o() {
      var t, e = i("#kwik-ai-provider-select").val();
      return t = "openrouter" === e ? "kwik_ai_openrouter_api_key" : "openai" === e ? "kwik_ai_openai_api_key" : "kwik_ai_custom_api_key", 
      {
        provider: e,
        endpoint: i('input[name="kwik_ai_api_endpoint"]').val() || "",
        api_key: i('input[name="' + t + '"]').val() || ""
      };
    }
    function k(t, e) {
      n.empty(), t.forEach(function(t) {
        var a = t.name;
        t.has_vision && (a += " " + kwikAiSettings.strings.vision);
        var s = i("<option>", {
          value: t.name,
          text: a
        });
        t.name === e && s.prop("selected", !0), t.has_vision && s.addClass("vision-model"), 
        n.append(s);
      });
    }
    function r(i, t) {
      s.removeClass("kwik-ai-tags-status-connected kwik-ai-tags-status-error").addClass(t ? "kwik-ai-tags-status-error" : "kwik-ai-tags-status-connected").text((t ? "✗ " : "✓ ") + i).show();
    }
    function c() {
      var t = i("#kwik-ai-provider-select").val();
      i(".kwik-ai-api-key-row").hide(), "custom" === t ? i("#kwik-ai-custom-api-key-row").show() : "openrouter" === t ? i("#kwik-ai-openrouter-api-key-row").show() : "openai" === t && i("#kwik-ai-openai-api-key-row").show();
    }
    t.length && t.on("click", function(e) {
      e.preventDefault();
      var s = n.val(), c = o();
      c.action = "kwik_ai_tags_fetch_models", c.nonce = kwikAiSettings.nonce, t.prop("disabled", !0), 
      a.show(), t.text(kwikAiSettings.strings.loading), i.ajax({
        url: kwikAiSettings.ajaxUrl,
        type: "POST",
        data: c,
        success: function(i) {
          i.success && i.data.models ? (k(i.data.models, s || i.data.selected), t.text("✓ " + kwikAiSettings.strings.refresh), 
          setTimeout(function() {
            t.text(kwikAiSettings.strings.refresh);
          }, 1500)) : r(i.data || kwikAiSettings.strings.error, !0);
        },
        error: function() {
          r(kwikAiSettings.strings.error, !0);
        },
        complete: function() {
          t.prop("disabled", !1), a.hide(), t.text() === kwikAiSettings.strings.loading && t.text(kwikAiSettings.strings.refresh);
        }
      });
    }), e.length && e.on("click", function(t) {
      t.preventDefault();
      var a = o();
      a.action = "kwik_ai_tags_test_connection", a.nonce = kwikAiSettings.nonce, a.model = n.val() || "", 
      e.prop("disabled", !0), s.removeClass("kwik-ai-tags-status-connected kwik-ai-tags-status-error").text(kwikAiSettings.strings.testing).show(), 
      i.ajax({
        url: kwikAiSettings.ajaxUrl,
        type: "POST",
        data: a,
        success: function(i) {
          i.success ? (r(i.data.message, !1), i.data.models && i.data.models.length && k(i.data.models, a.model)) : r(i.data || kwikAiSettings.strings.testError, !0);
        },
        error: function() {
          r(kwikAiSettings.strings.testError, !0);
        },
        complete: function() {
          e.prop("disabled", !1);
        }
      });
    }), i('tr:has(input[name="kwik_ai_custom_api_key"])').attr("id", "kwik-ai-custom-api-key-row").addClass("kwik-ai-api-key-row"), 
    i('tr:has(input[name="kwik_ai_openrouter_api_key"])').attr("id", "kwik-ai-openrouter-api-key-row").addClass("kwik-ai-api-key-row"), 
    i('tr:has(input[name="kwik_ai_openai_api_key"])').attr("id", "kwik-ai-openai-api-key-row").addClass("kwik-ai-api-key-row"), 
    c(), i("#kwik-ai-provider-select").on("change", function() {
      c(), s.hide().empty();
    });
    // FAL.AI "Refresh Models": refetch the live catalog over AJAX and repopulate
    // the dropdown in place, instead of doing a full nonce-protected page reload.
    var f = i("#kwik-ai-fal-refresh-models"), F = i("#kwik-ai-fal-model-select"), L = i("#kwik-ai-fal-model-loading"), D = i("#kwik-ai-fal-model-description");
    f.length && f.on("click", function(e) {
      e.preventDefault();
      var t = F.val();
      f.prop("disabled", !0), L.show(), f.text(kwikAiSettings.strings.loading), i.ajax({
        url: kwikAiSettings.ajaxUrl,
        type: "POST",
        data: {
          action: "kwik_ai_fal_refresh_models",
          nonce: kwikAiSettings.nonce
        },
        success: function(e) {
          if (e.success && e.data.models) {
            var a = e.data.models, n = e.data.selected || t;
            F.empty(), t && !a[t] && F.append(i("<option>", {
              value: t,
              text: t,
              selected: !0
            }));
            i.each(a, function(e, t) {
              var s = i("<option>", {
                value: e,
                text: t
              });
              e === n && s.prop("selected", !0), F.append(s);
            }), D.text(kwikAiSettings.strings.falDescription + " " + kwikAiSettings.strings.falShowing.replace("%d", e.data.count));
          } else D.text(kwikAiSettings.strings.falDescription + " " + (e.data || kwikAiSettings.strings.falError));
        },
        error: function() {
          D.text(kwikAiSettings.strings.falDescription + " " + kwikAiSettings.strings.falError);
        },
        complete: function() {
          f.prop("disabled", !1), L.hide(), f.text(kwikAiSettings.strings.refresh);
        }
      });
    });
  });
}(jQuery);
