/* -----------------------------------------------------------------------------------------------
 * Customer Scripts
 * -----------------------------------------------------------------------------------------------*/
/* global jQuery, _, OT */
// TODO: how to i explicitly declare dependency on jsPanel?

// Prevent leaking into global scope
!(function(exports, doc, $, _, OT, undefined) {

  // State
  var serviceRequestModal,
      serviceRequestForm,
      servicePanel,
      serviceSession,
      serviceQueueId,
      publisher;

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
        $fields = $form.find('input, textarea'),
        requestData = $fields.serialize();
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

    $.post('/help/session', requestData, 'json')
      .done(function(data) {
        initializeServicePanel(data.apiKey, data.sessionId, data.token);
        serviceRequestModal.hide();
      })
      .fail(function() {
        // TODO: error handling
        // show a flash message that says the request failed, try again later
      })
      .always(function() {
        enableFields();
      });
  };
  var requestModalHidden = function() {
    serviceRequestForm[0].reset();
  };

  // Service Panel
  var initializeServicePanel = function (apiKey, sessionId, token) {
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
        $cancelButton = $('.cancel-button');

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
    serviceRequestModal = $('#service-request-modal');
    serviceRequestForm = serviceRequestModal.find('.request-form');
    serviceRequestForm.submit(requestSubmit);
    serviceRequestModal.find('.request-submit').click(function() {
      serviceRequestForm.submit();
    });
    serviceRequestModal.on('hidden.bs.modal', requestModalHidden);
    servicePanel = $('#service-panel');
  });

  OT.setLogLevel(OT.DEBUG);

}(window, window.document, jQuery, _, OT));
