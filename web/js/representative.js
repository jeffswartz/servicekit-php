/* -----------------------------------------------------------------------------------------------
 * Representative Scripts
 * -----------------------------------------------------------------------------------------------*/
/* global jQuery, _, OT */

// Prevent leaking into global scope
!(function(exports, doc, $, _, OT, undefined) {

  // Service Provider Login
  var serviceProviderLogin = (function() {
    var $modal, $form, $fields, $submit, $accessInfo, $accessSuccess, $accessError, publisher;

    var init = function(modalSelector, publisherConfig) {
      $modal = $(modalSelector);
      $form = $modal.find('.login-form');
      $fields = $form.find('input');
      $submit = $modal.find('.login-submit');
      $accessInfo = $modal.find('.access-info');
      $accessSuccess = $modal.find('.access-success');
      $accessError = $modal.find('.access-error');

      $form.submit(submit);
      $submit.click(function() {
        $form.submit();
      });
      $modal.on('hidden.bs.modal', modalHidden);

      $modal.modal({
        backdrop: 'static',
        keyboard: false
      });

      publisher = OT.initPublisher(publisherConfig.el, publisherConfig.props);
      publisher.on('accessAllowed', publisherAllowed)
               .on('accessDenied', publisherDenied);
    };

    var submit = function(event) {
      var requestData = $fields.serialize();
      event.preventDefault();

      disableFields();

      if (!publisher.accessAllowed || validateForm() === false) {
        enableFields();
        return;
      }
    };

    var publisherAllowed = function() {
      $submit.prop('disabled', false);
      $accessInfo.hide();
      $accessSuccess.show();
    };

    var publisherDenied = function() {
      $accessInfo.hide();
      $accessError.show();
    };

    var modalHidden = function() {
      $form[0].reset();
      $accessInfo.show();
      $accessSuccess.hide();
      $accessError.hide();
    };

    var validateForm = function() {
      var result = true;
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
            result = false;
          }
        }
        if (_.has(requirements, 'required')) {
          if (!value) {
            $formGroup.addClass('has-error');
            $formGroup.append(
              '<span class="help-block validation-error">This field is required</span>'
            );
            result = false;
          }
        }
      });
      return result;
    };

    var validationRequirements = {
      '.representative-name': {
        maxLength: 50,
        required: true
      }
    };

    var disableFields = function() {
      $fields.prop('disabled', true);
    };

    var enableFields = function() {
      $fields.prop('disabled', false);
    };

    return {
      init: init
    };
  }());


  $(doc).ready(function() {
    serviceProviderLogin.init('#service-provider-login-modal', {
      el: $('.publisher')[0],
      props: {
        insertMode: 'append',
        width: '100%',
        height: '100%',
        style: {
          buttonDisplayMode: 'off'
        }
      }
    });
  });

}(window, window.document, jQuery, _, OT));
