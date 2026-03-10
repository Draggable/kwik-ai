/**
 * Settings page JavaScript
 *
 * @package KwikAI
 */

(function ($) {
  'use strict';

  $(document).ready(function () {
    var $refreshButton = $('#kwik-ai-refresh-models');
    var $loadingIndicator = $('#kwik-ai-model-loading');
    var $modelSelect = $('#kwik-ai-model-select');

    if (!$refreshButton.length) {
      return;
    }

    $refreshButton.on('click', function (e) {
      e.preventDefault();

      // Show loading state
      $refreshButton.prop('disabled', true);
      $loadingIndicator.show();
      $refreshButton.text(kwikAiSettings.strings.loading);

      // Make AJAX request
      $.ajax({
        url: kwikAiSettings.ajaxUrl,
        type: 'POST',
        data: {
          action: 'kwik_ai_tags_fetch_models',
          nonce: kwikAiSettings.nonce
        },
        success: function (response) {
          if (response.success && response.data.models) {
            // Clear current options
            $modelSelect.empty();

            // Add new options
            var models = response.data.models;
            var selectedModel = response.data.selected;

            models.forEach(function (model) {
              var optionText = model.name;
              if (model.has_vision) {
                optionText += ' ' + kwikAiSettings.strings.vision;
              }

              var $option = $('<option>', {
                value: model.name,
                text: optionText,
                selected: model.name === selectedModel
              });

              if (model.has_vision) {
                $option.addClass('vision-model');
              }

              $modelSelect.append($option);
            });

            // Show success feedback briefly
            $refreshButton.text('✓ ' + kwikAiSettings.strings.refresh);
            setTimeout(function () {
              $refreshButton.text(kwikAiSettings.strings.refresh);
            }, 1500);
          } else {
            alert(kwikAiSettings.strings.error);
          }
        },
        error: function () {
          alert(kwikAiSettings.strings.error);
        },
        complete: function () {
          // Reset button state
          $refreshButton.prop('disabled', false);
          $loadingIndicator.hide();
        }
      });
    });
  });
})(jQuery);
