jQuery(function(e) {
  "use strict";
  if ("undefined" != typeof kwikAiFeaturedImage) {
    var t = kwikAiFeaturedImage, a = t.strings || {}, i = e("#kwik-ai-featured-image-container");
    if (i.length) {
      var r = e("#kwik-ai-featured-image-guidance"), o = e("#kwik-ai-featured-image-prompt-generate"), n = e("#kwik-ai-featured-image-prompt-loading"), d = e("#kwik-ai-featured-image-prompt-input"), u = e("#kwik-ai-featured-image-prompt-source"), s = e("#kwik-ai-featured-image-generate"), c = e("#kwik-ai-featured-image-regenerate"), p = e("#kwik-ai-featured-image-apply"), l = e("#kwik-ai-featured-image-loading"), f = e("#kwik-ai-featured-image-status"), m = e("#kwik-ai-featured-image-elapsed"), g = e("#kwik-ai-featured-image-preview"), k = e("#kwik-ai-featured-image-img"), w = e("#kwik-ai-featured-image-error"), v = null, x = null, _ = null, b = 0;
      i.on("click", "#kwik-ai-featured-image-prompt-generate", function(i) {
        i.preventDefault(), t.postId ? (w.hide(), u.text(""), o.prop("disabled", !0), n.show(), 
        e.ajax({
          url: t.ajaxUrl,
          type: "POST",
          data: {
            action: "kwik_ai_featured_image_prompt",
            nonce: t.nonce,
            post_id: t.postId,
            guidance: r.val() || ""
          },
          timeout: 12e4,
          success: function(e) {
            e && e.success && e.data && e.data.prompt ? (d.val(e.data.prompt), function(e, t) {
              if ("fallback" === e) {
                var i = a.sourceFallback || "";
                t && (i += " [text provider tried: " + t + "]"), u.text(i).css("color", "#b32d2e");
              } else {
                u.text(a.sourceAi || "").css("color", "#646970");
              }
            }(e.data.source, e.data.provider), I("Prompt generated", e.data.source, e.data.provider)) : y(e && e.data || a.error);
          },
          error: function(e, t) {
            y(S(e, t));
          },
          complete: function() {
            o.prop("disabled", !1), n.hide();
          }
        })) : y(a.noPost);
      }), i.on("click", "#kwik-ai-featured-image-generate", function(e) {
        e.preventDefault(), P();
      }), i.on("click", "#kwik-ai-featured-image-regenerate", function(e) {
        e.preventDefault(), P();
      }), i.on("click", "#kwik-ai-featured-image-apply", function(r) {
        r.preventDefault(), v && (p.prop("disabled", !0).text(a.applying || "Setting featured image…"), 
        e.ajax({
          url: t.ajaxUrl,
          type: "POST",
          data: {
            action: "kwik_ai_featured_image_apply",
            nonce: t.nonce,
            request_id: v
          },
          timeout: 6e4,
          success: function(t) {
            var r, o;
            t && t.success && t.data ? (function(e) {
              if (e) {
                try {
                  "undefined" != typeof wp && wp.data && wp.data.dispatch("core/editor") && (wp.data.dispatch("core/editor").editPost({
                    featured_media: parseInt(e, 10)
                  }), I("Synced featured_media to editor", e));
                } catch (e) {
                  I("Could not sync featured image to editor", e);
                }
              }
            }(t.data.attachment_id), r = t.data && t.data.message || a.success, o = e('<div class="kwik-ai-tags-success"></div>').text(r), 
            i.prepend(o), setTimeout(function() {
              o.fadeOut(function() {
                o.remove();
              });
            }, 3e3)) : (p.prop("disabled", !1).text(a.setFeatured || "Set as Featured Image"), 
            y(t && t.data || a.error));
          },
          error: function(e, t) {
            p.prop("disabled", !1).text(a.setFeatured || "Set as Featured Image"), y(S(e, t));
          }
        }));
      });
    }
  }
  function I() {
    t.debug && window.console && console.log.apply(console, [ "KWIK AI Featured Image:" ].concat([].slice.call(arguments)));
  }
  function h() {
    x && (clearTimeout(x), x = null), _ && (clearInterval(_), _ = null);
  }
  function y(e) {
    h(), l.hide(), s.prop("disabled", !1).text(a.generate || "Generate Image"), c.prop("disabled", !1), 
    w.find(".error-message").text(e || a.error), w.show();
  }
  function S(e, t) {
    if ("timeout" === t) {
      return a.timeout;
    }
    if (e && e.responseText) {
      try {
        var i = JSON.parse(e.responseText);
        if (i && i.data) {
          return i.data;
        }
      } catch (t) {
        return "Server error: " + (e.status || "");
      }
    }
    return a.error;
  }
  function P() {
    if (t.postId) {
      var i = (d.val() || "").trim();
      if (!i) {
        return y(a.noPrompt), void d.focus();
      }
      v = null, w.hide(), g.hide(), l.show(), f.text(a.generating || "Generating image…"), 
      s.prop("disabled", !0).text(a.generating || "Generating…"), c.prop("disabled", !0), 
      b = Date.now(), m.text("0s"), _ = setInterval(function() {
        var e = Math.round((Date.now() - b) / 1e3);
        m.text(e + "s");
      }, 1e3), e.ajax({
        url: t.ajaxUrl,
        type: "POST",
        data: {
          action: "kwik_ai_featured_image_generate",
          nonce: t.nonce,
          post_id: t.postId,
          prompt: i
        },
        timeout: 6e4,
        success: function(e) {
          e && e.success && e.data && e.data.request_id ? (I("Job submitted", v = e.data.request_id), 
          F()) : y(e && e.data || a.error);
        },
        error: function(e, t) {
          y(S(e, t));
        }
      });
    } else {
      y(a.noPost);
    }
  }
  function F() {
    x = setTimeout(j, t.pollInterval || 3e3);
  }
  function j() {
    v && (Date.now() - b > (t.maxPollMs || 3e5) ? y(a.timeout) : e.ajax({
      url: t.ajaxUrl,
      type: "POST",
      data: {
        action: "kwik_ai_featured_image_status",
        nonce: t.nonce,
        request_id: v
      },
      timeout: 3e4,
      success: function(e) {
        var t;
        if (e && e.success && e.data) {
          if ("completed" === e.data.status) {
            I("Job completed"), t = e.data.image_url, h(), l.hide(), s.prop("disabled", !1).text(a.generate || "Generate Image"), 
            c.prop("disabled", !1), k.attr("src", t), p.prop("disabled", !1).text(a.setFeatured || "Set as Featured Image"), 
            g.show(), w.hide();
          } else {
            var i = "IN_PROGRESS" === e.data.state ? a.inProgress || "Generating…" : a.queued || "Queued…";
            f.text(i), F();
          }
        } else {
          y(e && e.data || a.error);
        }
      },
      error: function(e, t) {
        y(S(e, t));
      }
    }));
  }
});
