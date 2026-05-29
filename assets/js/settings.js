/**
 * KWIK AI Tags Settings Page JavaScript
 *
 * @package KwikAI
 */

(function ($) {
  'use strict';

  $(document).ready(function () {
    var refreshBtn = $('#kwik-ai-refresh-models');
    var testBtn = $('#kwik-ai-test-connection');
    var loadingIndicator = $('#kwik-ai-model-loading');
    var modelSelect = $('#kwik-ai-model-select');
    var resultBox = $('#kwik-ai-connection-result');

    /**
     * Gather the connection values currently entered in the form so they can
     * be tested without saving.
     */
    function getConnectionParams() {
      var provider = $('#kwik-ai-provider-select').val();
      var keyField;

      if (provider === 'openrouter') {
        keyField = 'kwik_ai_openrouter_api_key';
      } else if (provider === 'openai') {
        keyField = 'kwik_ai_openai_api_key';
      } else {
        keyField = 'kwik_ai_custom_api_key';
      }

      return {
        provider: provider,
        endpoint: $('input[name="kwik_ai_api_endpoint"]').val() || '',
        api_key: $('input[name="' + keyField + '"]').val() || ''
      };
    }

    /**
     * Replace the contents of the model dropdown, preserving the current
     * selection when possible.
     */
    function populateModels(models, selected) {
      modelSelect.empty();

      models.forEach(function (model) {
        var text = model.name;
        if (model.has_vision) {
          text += ' ' + kwikAiSettings.strings.vision;
        }

        var option = $('<option>', {
          value: model.name,
          text: text
        });

        if (model.name === selected) {
          option.prop('selected', true);
        }
        if (model.has_vision) {
          option.addClass('vision-model');
        }

        modelSelect.append(option);
      });
    }

    function showResult(message, isError) {
      resultBox
        .removeClass('kwik-ai-tags-status-connected kwik-ai-tags-status-error')
        .addClass(isError ? 'kwik-ai-tags-status-error' : 'kwik-ai-tags-status-connected')
        .text((isError ? '✗ ' : '✓ ') + message)
        .show();
    }

    // Refresh the model list from the provider using the current form values.
    if (refreshBtn.length) {
      refreshBtn.on('click', function (e) {
        e.preventDefault();

        var currentSelected = modelSelect.val();
        var params = getConnectionParams();
        params.action = 'kwik_ai_tags_fetch_models';
        params.nonce = kwikAiSettings.nonce;

        refreshBtn.prop('disabled', true);
        loadingIndicator.show();
        refreshBtn.text(kwikAiSettings.strings.loading);

        $.ajax({
          url: kwikAiSettings.ajaxUrl,
          type: 'POST',
          data: params,
          success: function (response) {
            if (response.success && response.data.models) {
              populateModels(response.data.models, currentSelected || response.data.selected);
              refreshBtn.text('✓ ' + kwikAiSettings.strings.refresh);
              setTimeout(function () {
                refreshBtn.text(kwikAiSettings.strings.refresh);
              }, 1500);
            } else {
              showResult(response.data || kwikAiSettings.strings.error, true);
            }
          },
          error: function () {
            showResult(kwikAiSettings.strings.error, true);
          },
          complete: function () {
            refreshBtn.prop('disabled', false);
            loadingIndicator.hide();
            if (refreshBtn.text() === kwikAiSettings.strings.loading) {
              refreshBtn.text(kwikAiSettings.strings.refresh);
            }
          }
        });
      });
    }

    // Test the connection using the current (unsaved) form values.
    if (testBtn.length) {
      testBtn.on('click', function (e) {
        e.preventDefault();

        var params = getConnectionParams();
        params.action = 'kwik_ai_tags_test_connection';
        params.nonce = kwikAiSettings.nonce;
        params.model = modelSelect.val() || '';

        testBtn.prop('disabled', true);
        resultBox
          .removeClass('kwik-ai-tags-status-connected kwik-ai-tags-status-error')
          .text(kwikAiSettings.strings.testing)
          .show();

        $.ajax({
          url: kwikAiSettings.ajaxUrl,
          type: 'POST',
          data: params,
          success: function (response) {
            if (response.success) {
              showResult(response.data.message, false);

              // Populate the dropdown with the models we just discovered.
              if (response.data.models && response.data.models.length) {
                populateModels(response.data.models, params.model);
              }
            } else {
              showResult(response.data || kwikAiSettings.strings.testError, true);
            }
          },
          error: function () {
            showResult(kwikAiSettings.strings.testError, true);
          },
          complete: function () {
            testBtn.prop('disabled', false);
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

    // Toggle on provider change; clear any stale connection result.
    $('#kwik-ai-provider-select').on('change', function () {
      toggleApiKeyFields();
      resultBox.hide().empty();
    });
  });
})(jQuery);
