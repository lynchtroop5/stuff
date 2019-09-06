/*******************************************************************************
 * Copyright 2012 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/

var forms = new Array('vehicle', 'person-oln', 'person-name', 'gun', 'article');

function hide() {
    $.each(forms, function(i, v) {
        $('#FieldContain' + v).hide();
        $('#Link' + v).removeClass('ui-btn-active');
    });
}

function show(form) {
    hide();

    $('#Link' + form).addClass('ui-btn-active');
    $('#FieldContain' + form).show();
}

$("div[data-role*='page']").live('pageshow', function() {
    $('form').submit(function() {
        window.localStorage.setItem("ran_transaction", "true");
        return true;
    });

    // Automatically upper-case each field as focus is left.
    $('form :input').live('blur', function() {
        if (this.nodeName.toLowerCase() == 'button')
            return;

        var item = $(this);
        if (item.attr('type') == 'hidden')
            return;

        item.val(item.val().toUpperCase());
    });
    
    
    $('form').live('submit', function() {
        var $item = $(document.activeElement);
        if ($item.is(':text'))
            $item.val($item.val().toUpperCase());
    });
    
    show(forms[0]);
});