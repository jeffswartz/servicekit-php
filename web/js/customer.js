/* -----------------------------------------------------------------------------------------------
 * Customer Scripts
 * -----------------------------------------------------------------------------------------------*/
/* global jQuery, _ */

// Prevent leaking into global scope
!(function(exports, doc, $, _, undefined) {

  // Service Request Modal Form
  var validationRequirements = {
    '.customer-name': {
      maxLength: 50
    },
    '.problem-text': {
      maxLength: 200
    }
  };
  var requestSubmit = function(event) {
    var $form = $(event.target),
        $fields = $form.find('input, textarea');
    event.preventDefault();
    console.log('service request modal form submitted');

    // Helper functions
    var disableFields = function() {
      $fields.prop('disabled', true);
    };
    var enableFields = function() {
      $fields.prop('disabled', false);
    };

    disableFields();

    // Validation
    var validationResult = true;

    $form.find('.has-error').removeClass('has-error');
    $form.find('.validation-error').remove();
    _.each(validationRequirements, function(requirements, selector) {
      var $element = $form.find(selector);
      var $formGroup = $element.parents('.form-group');
      var value = $element.val();
      if (_.has(requirements, 'maxLength')) {
        if (value.length > requirements.maxLength) {
          $formGroup.addClass('has-error');
          $formGroup.append(
            '<span class="help-block validation-error">The maximum length is ' +
            requirements.maxLength + '</span>'
          );
          validationResult = false;
        }
      }
    });

    if (validationResult === false) {
      enableFields();
      return;
    }

    //$.post('/help/session', $form.serialize(), 'json')
    //  .then()
    //  .done()
    //  .fail(function() {
    //  })
    //  .always(function() {
    //    enableFields();
    //  });
  };

  $(doc).ready(function() {
    var serviceRequestModal = $('#service-request-modal');
    var requestForm = serviceRequestModal.find('.request-form');
    requestForm.submit(requestSubmit);
    serviceRequestModal.find('.request-submit').click(function() {
      requestForm.submit();
    });
  });

}(window, window.document, jQuery, _));
