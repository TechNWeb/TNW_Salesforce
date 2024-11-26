/*
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */
define([
    'Magento_Ui/js/form/components/button',
    'jquery',
    'uiRegistry',
    'underscore'
], function (Button, $, register, _) {
    'use strict';

    return Button.extend({

        /**
         * Load more content from backend to container.
         *
         * @returns {Object} Chainable.
         */
        action: function () {
            let id = this.source.data.id;
            let url = this.source.data.ajax_url;
            let page = this.source.data.current_page++;
            new Ajax.Updater('content', url, {
                parameters: {
                    key: window.FORM_KEY,
                    id: id,
                    page: page
                },
                insertion: function (receiver, responseText) {
                    let data = JSON.parse(responseText);
                    let content = data.content;
                    content.replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");

                    receiver.insert(content);
                },
                onComplete: function (response) {
                    if (response.responseText.empty()) {
                        alert('End of file');
                    }
                }
            });

            return this;
        }
    });
});
