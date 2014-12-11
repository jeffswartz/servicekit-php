/* -----------------------------------------------------------------------------------------------
 * Representative Scripts
 * -----------------------------------------------------------------------------------------------*/
/* global jQuery, _, setImmediate, OT */

// Prevent leaking into global scope
!(function(exports, doc, $, _, setImmediate, OT, undefined) {

  // Service Provider Login
  var serviceProviderLogin = (function() {
    var $modal, $form, $fields, $submit, $accessInfo, $accessSuccess, $accessError,
        loginCompleteCallback, publisher;

    var init = function(modalSelector, publisherConfig, done) {
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

      loginCompleteCallback = done;
    };

    var submit = function(event) {
      event.preventDefault();

      disableFields();

      if (!publisher.accessAllowed || validateForm() === false) {
        enableFields();
        return;
      }

      // NOTE: There is no authentication implemented for representatives. For that reason, there is
      // no request sent to the server. The structure of the code is designed such that
      // authentication can be added at a later time.

      setImmediate(function() {
        loginCompleteCallback(publisher);
        $modal.modal('hide');
        enableFields();
      });

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

  // Service Provider
  var serviceProvider = (function() {
    var $el, $publisher, $subscriber, $getCustomer, $endCall, $customerName, $problemText,
      session, publisher, subscriber, connected, waitingForCustomer;

    var init = function(selector) {
      $el = $(selector);
      $publisher = $el.find('.publisher');
      $subscriber = $el.find('.subscriber');
      $getCustomer = $el.find('.get-customer');
      $endCall = $el.find('.end-call');
      $customerName = $el.find('.customer-name');
      $problemText = $el.find('.problem-text');

      $getCustomer.on('click', getCustomer);
      $endCall.on('click', endCall);
    };

    var start = function(pub) {
      $getCustomer.show();
      publisher = pub;
      publisher.on('streamDestroyed', function(event) {
        event.preventDefault();
      });
    };

    var getCustomer = function() {

      $getCustomer.prop('disabled', true);

      $.post('/help/queue', dequeueData, 'json')
        .done(function(customerData, textStatus, jqXHR) {

          // When there is a customer available, begin chat
          if (jqXHR.status === 200) {
            beginCall(customerData);

          // When there isn't a customer available, poll
          } else if (jqXHR.status === 204) {
            setTimeout(getCustomer, pollingInterval);
          }
        })
        .fail(function() {
          // TODO: error handling
          // prompt user, re enable the get customer button
        });
    };

    var renderCustomer = function(customerData) {
      // templating
      $customerName.text(customerData.customerName);
      $problemText.text(customerData.problemText);

      $getCustomer.hide();
      $endCall.show();
    };

    var clearCustomer = function() {
      // cleanup templated data
      $customerName.text('');
      $problemText.text('');

      $getCustomer.show().prop('disabled', false);
      $endCall.hide();
    };

    var beginCall = function(customerData) {
      renderCustomer(customerData);

      session = OT.initSession(customerData.apiKey, customerData.sessionId);
      session.on('sessionConnected', sessionConnected);
      session.on('sessionDisconnected', sessionDisconnected);
      session.on('streamCreated', streamCreated);
      session.on('streamDestroyed', streamDestroyed);
      session.connect(customerData.token);
    };

    var endCall = function() {
      if (connected) {
        session.unpublish(publisher);
        session.disconnect();
      } else {
        clearCustomer();
      }
    };

    var waitForCustomerExpired = function() {
      if (waitingForCustomer) {
        waitingForCustomer = false;
        // TODO: let the user know the reason this customer is being skipped is because they left
        endCall();
      }
    };

    var sessionConnected = function() {
      //start a timer within which to wait for the customer's stream to be created
      waitingForCustomer = true;
      setTimeout(waitForCustomerExpired, customerWaitExpirationInterval);

      connected = true;

      session.publish(publisher);
    };

    var sessionDisconnected = function() {
      connected = false;
      subscriber = undefined;
      session.off();
      session = undefined;
      clearCustomer();
    };

    var streamCreated = function(event) {
      if (!subscriber) {
        waitingForCustomer = false;
        subscriber = session.subscribe(event.stream, $subscriber[0], videoProperties);
      }
    };

    var streamDestroyed = function(event) {
      if (subscriber && event.stream === subscriber.stream) {
        endCall();
      }
    };

    var publisherConfig = function() {
      return {
        el: $publisher[0],
        props: videoProperties
      };
    };

    var videoProperties = {
      insertMode: 'append',
      width: '100%',
      height: '100%',
      style: {
        buttonDisplayMode: 'off'
      }
    };

    var pollingInterval = 5000;
    var customerWaitExpirationInterval = 5000;

    var dequeueData = '_METHOD=DELETE';

    return {
      init: init,
      publisherConfig: publisherConfig,
      start: start
    };
  }());


  $(doc).ready(function() {
    serviceProvider.init('#service-provider');
    serviceProviderLogin.init(
      '#service-provider-login-modal',
      serviceProvider.publisherConfig(),
      serviceProvider.start
    );
  });

}(window, window.document, jQuery, _, setImmediate, OT));
