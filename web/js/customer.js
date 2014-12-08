/* -----------------------------------------------------------------------------------------------
 * Customer Scripts
 * -----------------------------------------------------------------------------------------------*/
/* global jQuery, _, OT */
// TODO: how to i explicitly declare dependency on jsPanel?

// Prevent leaking into global scope
!(function(exports, doc, $, _, OT, undefined) {

  // State
  var servicePanel,
      serviceSession,
      serviceQueueId,
      publisher;

  // Service Request Modal and Form
  //
  //
  // Using a module pattern to encapsulate the functionality. The object returned at the end of this
  // function presents the API for using this module.
  //
  // This code is meant to be treated as a singleton, as there will not be a need for more than one
  // of these to exist.
  var serviceRequest = (function() {
    var $modal, $form, $fields, sessionDataCallback;

    var init = function(modalSelector, callback) {
      $modal = $(modalSelector);
      $form = $modal.find('.request-form');
      $fields = $form.find('input, textarea'),
      sessionDataCallback = callback;

      $form.submit(submit);
      $modal.find('.request-submit').click(function() {
        $form.submit();
      });
      $modal.on('hidden.bs.modal', modalHidden);
    };

    var submit = function(event) {
      var requestData = $fields.serialize();
      event.preventDefault();

      disableFields();

      if (validateForm() === false) {
        enableFields();
        return;
      }

      $.post('/help/session', requestData, 'json')
        .done(function(data) {
          // TODO: pass requestData too?
          sessionDataCallback({
            apiKey: data.apiKey,
            sessionId: data.sessionId,
            token: data.token
          });
          $modal.hide();
        })
        .fail(function() {
          // TODO: error handling
          // show a flash message that says the request failed, try again later
        })
        .always(function() {
          enableFields();
        });
    };

    var modalHidden = function() {
      $form[0].reset();
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
      '.customer-name': {
        maxLength: 50,
        required: true
      },
      '.problem-text': {
        maxLength: 200,
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

  // Service Panel
  var initializeServicePanel = function (serviceSessionData) {
    var publisherEl = $('#service-panel .publisher'),
        publisherProperties = {
          insertMode: 'append',
          width: '100%',
          height: '100%',
          style: {
            buttonDisplayMode: 'off'
          }
        },
        $waitingHardwareAccess = $('.waiting .hardware-access'),
        $waitingRepresentative = $('.waiting .representative'),
        $cancelButton = $('.cancel-button'),
        apiKey = serviceSessionData.apiKey,
        sessionId = serviceSessionData.sessionId,
        token = serviceSessionData.token;

    var servicePublisherAllowed = function() {
      $waitingHardwareAccess.hide();
      serviceSession.connect(token);
      $waitingRepresentative.show();
    };

    var servicePublisherDenied = function() {
      // TODO: prompt user to reset their denial of the camera and try again
    };

    var serviceSessionConnected = function() {
      serviceSession.publish(publisher);
      $.post('/help/queue', { session_id: sessionId }, 'json')
        .done(function(data) {
          // TODO: install a synchronous http request to remove from queue in case the page is
          // closed
          serviceQueueId = data.queueId;
        })
        .fail(function() {
          // TODO: error handling
          // show a flash message that says the request failed, try again later
        });
    };

    var serviceSessionStreamCreated = function(event) {
      // TODO: subscribe
      // TODO: change cancel button to end button
      $waitingRepresentative.hide();
    };

    var cancelService = function() {
      publisher.destroy();
      servicePanel.hide();
      if (serviceQueueId) {
        // TODO: dequeue
        console.log('dequeue');
      }
    };

    serviceSession = OT.initSession(apiKey, sessionId);
    serviceSession.on('sessionConnected', serviceSessionConnected)
                  .on('streamCreated', serviceSessionStreamCreated);

    publisher = OT.initPublisher(publisherEl[0], publisherProperties);
    publisher.on('accessAllowed', servicePublisherAllowed)
             .on('accessDenied', servicePublisherDenied);

    $cancelButton.on('click', cancelService);

    servicePanel.show();
    $waitingHardwareAccess.show();

    // TODO: install a function that stops user from navigating away
  };


  $(doc).ready(function() {

    // TODO: make sure the modal cannot be opened after the service panel is already open
    serviceRequest.init('#service-request-modal', function(serviceSessionData) {
      initializeServicePanel(serviceSessionData);
    });

    servicePanel = $('#service-panel');
  });

  OT.setLogLevel(OT.DEBUG);

}(window, window.document, jQuery, _, OT));
