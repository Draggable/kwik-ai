/**
 * KWIK AI Tags Settings Page JavaScript
 *
 * @package KwikAI
 */

(function ($) {
  'use strict';

  $(document).ready(function () {
    // Refresh models button
    var refreshBtn = $('#kwik-ai-refresh-models');
    var loadingIndicator = $('#kwik-ai-model-loading');
    var modelSelect = $('#kwik-ai-model-select');

    if (refreshBtn.length) {
      refreshBtn.on('click', function (e) {
        e.preventDefault();
        refreshBtn.prop('disabled', true);
        loadingIndicator.show();
        refreshBtn.text(kwikAiSettings.strings.loading);

        $.ajax({
          url: kwikAiSettings.ajaxUrl,
          type: 'POST',
          data: {
            action: 'kwik_ai_tags_fetch_models',
            nonce: kwikAiSettings.nonce
          },
          success: function (response) {
            if (response.success && response.data.models) {
              modelSelect.empty();
              response.data.models.forEach(function (model) {
                var text = model.name;
                if (model.has_vision) {
                  text += ' ' + kwikAiSettings.strings.vision;
                }
                var option = $('<option>', {
                  value: model.name,
                  text: text,
                  selected: model.name === response.data.selected
                });
                if (model.has_vision) {
                  option.addClass('vision-model');
                }
                modelSelect.append(option);
              });
              refreshBtn.text('\u2713 ' + kwikAiSettings.strings.refresh);
              setTimeout(function () {
                refreshBtn.text(kwikAiSettings.strings.refresh);
              }, 1500);
            } else {
              alert(kwikAiSettings.strings.error);
            }
          },
          error: function () {
            alert(kwikAiSettings.strings.error);
          },
          complete: function () {
            refreshBtn.prop('disabled', false);
            loadingIndicator.hide();
          }
        });
      });
    }

    // Show/hide API key fields based on selected provider
    function toggleApiKeyFields() {
      var provider = $('#kwik-ai-provider-select').val();

      // Hide all API key rows
      $('.kwik-ai-api-key-row').hide();

      // Show the appropriate one
      if (provider === 'custom') {
        $('#kwik-ai-custom-api-key-row').show();
      } else if (provider === 'openrouter') {
        $('#kwik-ai-openrouter-api-key-row').show();
      } else if (provider === 'openai') {
        $('#kwik-ai-openai-api-key-row').show();
      }
    }

    // Wrap API key fields in identifiable rows
    $('tr:has(input[name="kwik_ai_custom_api_key"])').attr('id', 'kwik-ai-custom-api-key-row').addClass('kwik-ai-api-key-row');
    $('tr:has(input[name="kwik_ai_openrouter_api_key"])').attr('id', 'kwik-ai-openrouter-api-key-row').addClass('kwik-ai-api-key-row');
    $('tr:has(input[name="kwik_ai_openai_api_key"])').attr('id', 'kwik-ai-openai-api-key-row').addClass('kwik-ai-api-key-row');

    // Initial toggle
    toggleApiKeyFields();

    // Toggle on provider change
    $('#kwik-ai-provider-select').on('change', toggleApiKeyFields);
  });
})(jQuery);
