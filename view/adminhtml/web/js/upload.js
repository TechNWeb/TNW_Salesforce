/*
 * Copyright © 2022 TechNWeb, Inc. All rights reserved.
 * See TNW_LICENSE.txt for license details.
 */

define
(
    [
        'jquery'
    ],
    function($)
    {
        'use strict';
        return function (config) 
        {
            $('#uploadBtn').unbind('change');
            $('#uploadBtn').change(function()
            {  
                var formdata = new FormData(); 
                if($(this).prop('files').length > 0)
                {
                    var file = $(this).prop('files')[0];  
                    formdata.append("file", file);
                    formdata.append("form_key", window.FORM_KEY);
                    $.ajax({
                        url: config.ajaxUrl,
                        type: "POST",
                        data: formdata,
                        dataType: 'json',
                        showLoader: true,
                        contentType: false,
                        processData: false,
                        beforeSend: function () {
                            $('.tnw-message-success').hide();
                            $('.tnw-message-error').hide(); 
                        },
                        success: function (result) {
                            $('.tnw-message-success').hide();
                            $('.action.test-connection').show();                           
                        }
                    });
                }
              });
          }; 
    }
);
