/*
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

/*jshint browser:true jquery:true*/
define([
    "jquery",
    "mage/translate",
    "domReady!"
], function($){
    "use strict";

    $.widget('tnw.testConnection', {
        options: {
            statusSelector: '.connection-test-status',
            statusSelectorLabel: '.connection-test-status-label',
            testActionSelector: '.action.test-connection',
            errorMessageClass: 'tnw-message-error',
            successMessageClass: 'tnw-message-success',
            loaderClass: 'tnw-message-spinner',
            usernameElem: '#tnwsforce_general_salesforce_username',
            passwordElem: '#tnwsforce_general_salesforce_password',
            tokenElem: '#tnwsforce_general_salesforce_token',
            authTypeElem: '#tnwsforce_general_salesforce_auth_type',
            oauthDomainElem: '#tnwsforce_general_salesforce_oauth_domain',
            oauthClientIdElem: '#tnwsforce_general_salesforce_oauth_client_id',
            oauthClientSecretElem: '#tnwsforce_general_salesforce_oauth_client_secret',
            wsdlElem: '#tnwsforce_general_salesforce_wsdl'
        },

        /**
         * Constructor method that prepares connection tester activities
         * @private
         */
        _create: function() {

            this.element.on('click', this.options.testActionSelector, $.proxy(this.connect, this));
            this._messageBox = this.element.find(this.options.statusSelector);

            // Enable 'Test Connection' button
            this.element.find(this.options.testActionSelector).prop('disabled', false);

            // Reset all text and styles
            this._resetAll();
        },

        /**
         * Resets UI for the status
         * @private
         */
        _resetAll: function() {
            // Reset content of the message box and hide it
            this._messageBox.hide();
            this._messageBox.find(this.options.statusSelectorLabel).text('');

            this._resetAllStyling();
        },

        /**
         * Reset all styling: success and error
         * @private
         */
        _resetAllStyling: function() {
            this._resetSuccessStyling();

            this._resetErrorStyling();

            this.element.find(this.options.statusSelectorLabel).addClass(this.options.loaderClass);
        },

        /**
         * Resets message box styling for error message
         * @private
         */
        _resetSuccessStyling: function() {
            if (this._messageBox.hasClass(this.options.successMessageClass)) {
                this._messageBox.removeClass(this.options.successMessageClass);
            }

            this._removeLoaderAnimation();
        },

        /**
         * Resets message box styling for success message
         * @private
         */
        _resetErrorStyling: function() {
            if (this._messageBox.hasClass(this.options.errorMessageClass)) {
                this._messageBox.removeClass(this.options.errorMessageClass);
            }
            this._removeLoaderAnimation();
        },

        /**
         * Hide spinner
         * @private
         */
        _removeLoaderAnimation: function() {
            this.element.find(this.options.statusSelectorLabel).removeClass(this.options.loaderClass);
        },

        /**
         * Method triggeres an AJAX request to test Salesforce connection
         * @param e - Event
         */
        connect: function(e) {
            $.ajax({
                url: this.options.url,
                // POST keeps the password, token and secret out of URLs and web server logs
                type: 'post',
                async: true,
                dataType: 'json',
                context: this,
                data: {
                    'formId': this.options.type,
                    username: $(this.options.usernameElem).val(),
                    password: $(this.options.passwordElem).val(),
                    token: $(this.options.tokenElem).val(),
                    auth_type: $(this.options.authTypeElem).val(),
                    oauth_domain: $(this.options.oauthDomainElem).val(),
                    oauth_client_id: $(this.options.oauthClientIdElem).val(),
                    oauth_client_secret: $(this.options.oauthClientSecretElem).val(),
                    wsdl: $(this.options.wsdlElem).val()
                },
                beforeSend: function () {
                    this._resetAllStyling();

                    // Hide the 'Test' button to prevent double click
                    this.element.find(this.options.testActionSelector).hide();

                    this._messageBox.show();
                    this._messageBox.find(this.options.statusSelectorLabel).text(this.options.inProgressMessage);
                },
                error:  function (response) {
                    // Show button to allow re-test
                    this.element.find(this.options.testActionSelector).show();

                    this._messageBox.find(this.options.statusSelectorLabel).text(response.responseText);
                    this._resetSuccessStyling();
                    this._messageBox.addClass(this.options.errorMessageClass);
                },
                success: function (response) {
                    this._messageBox.find(this.options.statusSelectorLabel).text(this.options.successMessage);

                    this._resetErrorStyling();

                    this._messageBox.addClass(this.options.successMessageClass);
                }
            });
        }
    });

    return $.tnw.testConnection;
});
