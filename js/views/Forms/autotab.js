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

(function($) {
    // Returns true if the key (event.which) is one that should update the filter on the current dropdown.
    function isFilterKey(key) {
        return (key == 8 || key == 46 || key == 32 || (key > 47 && key < 91) || (key > 95 && key < 112) || key > 185);
    }

    function autotab($element) {
        var value = $element.val();
        var length = value.length;
        var maxLength = $element.attr('maxlength');

        if (length < maxLength)
            return;

        if (length > maxLength)
            $element.val(value.subString(0, maxLength));

        // Find the next element on the form and focus it.  If it can't be found, move back to the first field on
        // the form.
        var form = $element.parents('form:first');
        var nextElement = form.find(':input[tabindex=' + ($element.attr('tabindex') + 1) + ']');
        if (nextElement.length <= 0)
            nextElement = form.find(':input[tabindex=1]');

        nextElement.focus().select();
    }

    $.autotab = autotab;

    $.fn.autotab = function() {
        return this.find(':input')
            .live('keyup', function(event) {
                if (this.nodeName.toLowerCase() == 'button')
                    return;

                // CTRL key is okay if it's V (CTRL + V = Paste)
                if (event.altKey || (event.ctrlKey && event.which != 86))
                    return true;

                if (!isFilterKey(event.which))
                    return true;

                autotab($(this));
                return true;
            })
            .live('keydown', function(event) {
                if (event.which != 9)
                    return;
                
                $this = $(this);
                var form = $this.parents('form:first');
                var nextElement = form.find(':input[tabindex=' + ($this.attr('tabindex') + 1) + ']');
                if (nextElement.length <= 0)
                {
                    nextElement = form.find(':input[tabindex=1]');
                    nextElement.focus().select();
                    event.preventDefault();
                }
            });
    }

    $(document).ready(function() {
        $.debug.log('Forms/autotab.js ready.');
    })
})(jQuery);
