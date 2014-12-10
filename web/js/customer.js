/* -----------------------------------------------------------------------------------------------
 * Customer Scripts
 * -----------------------------------------------------------------------------------------------*/
/* global jQuery, _, EventEmitter2, setImmediate, OT */

// Prevent leaking into global scope
!(function(exports, doc, $, _, EventEmitter, setImmediate, OT, undefined) {

  // State
  var servicePanel,
      $serviceRequestButton;

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
      $fields = $form.find('input, textarea');
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
          $modal.modal('hide');
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
  var ServicePanel = function (selector, sessionData) {
    EventEmitter.call(this);

    this.apiKey = sessionData.apiKey;
    this.sessionId = sessionData.sessionId;
    this.token = sessionData.token;

    // currently under the assumption that the DOM elements are already on the page, but it makes
    // more sense to use a template so this can be instantiated per instance.
    this.$panel = $(selector);
    this.$publisher = this.$panel.find('.publisher');
    this.$waitingHardwareAccess = this.$panel.find('.waiting .hardware-access');
    this.$waitingRepresentative = this.$panel.find('.waiting .representative');
    this.$cancelButton = this.$panel.find('.cancel-button');

    setImmediate(this.initialize.bind(this));
  };
  ServicePanel.prototype = new EventEmitter();

  ServicePanel.prototype._videoProperties = {
    insertMode: 'append',
    width: '100%',
    height: '100%',
    style: {
      buttonDisplayMode: 'off'
    }
  };

  ServicePanel.prototype._publisherAllowed = function() {
    this.$waitingHardwareAccess.hide();
    this.$waitingRepresentative.show();
    this.session.connect(this.token);
  };

  ServicePanel.prototype._publisherDenied = function() {
    // TODO: prompt user to reset their denial of the camera and try again
  };

  ServicePanel.prototype._sessionConnected = function() {
    var self = this;
    this.session.publish(this.publisher);
    $.post('/help/queue', { session_id: this.sessionId }, 'json')
      .done(function(data) {
        // TODO: install a synchronous http request to remove from queue in case the page is
        // closed
        self.queueId = data.queueId;
      })
      .fail(function() {
        // TODO: error handling
        // show a flash message that says the request failed, try again later
      });
  };

  ServicePanel.prototype._streamCreated = function() {
    // TODO: subscribe
    // TODO: change cancel button to end button
    this.$waitingRepresentative.hide();
  };

  // TODO: call disconnect and then handle the rest in the session disconnected handler
  // but its possible they never attempted to connect to the session, then what? call
  // sessionDisconnected directly?
  ServicePanel.prototype.cancel = function() {
    this.publisher.destroy();
    this.$panel.hide();
    this.$waitingHardwareAccess.hide();
    this.$waitingRepresentative.hide();
    if (this.queueId) {
      // TODO: dequeue
      console.log('dequeue');
      this.queueId = undefined;
    }

    this.emit('close');
  };

  ServicePanel.prototype.initialize = function() {
    this.session = OT.initSession(this.apiKey, this.sessionId);
    this.session.on('sessionConnected', this._sessionConnected, this)
                .on('streamCreated', this._streamCreated, this);

    this.publisher = OT.initPublisher(this.$publisher[0], this._videoProperties);
    this.publisher.on('accessAllowed', this._publisherAllowed, this)
                  .on('accessDenied', this._publisherDenied, this);

    this.$cancelButton.on('click', this.cancel.bind(this));
    this.$panel.show();
    this.$waitingHardwareAccess.show();

    this.emit('open');
    // TODO: install a function that stops user from navigating away
  };

  var disableServiceRequest = function() {
    $serviceRequestButton.prop('disabled', true);
  };

  var enableServiceRequest = function() {
    $serviceRequestButton.prop('disabled', false);
  };

  $(doc).ready(function() {
    $serviceRequestButton = $('.service-request-btn');
    serviceRequest.init('#service-request-modal', function(serviceSessionData) {
      servicePanel = new ServicePanel('#service-panel', serviceSessionData);
      servicePanel.on('open', disableServiceRequest);
      servicePanel.on('close', function() {
        enableServiceRequest();
        servicePanel.removeAllListeners();
        servicePanel = undefined;
      });
    });
  });

  OT.setLogLevel(OT.DEBUG);

}(window, window.document, jQuery, _, EventEmitter2, setImmediate, OT));
