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
    $(document).ready(function() {
        function enableRunButton(enable) {
            $('.command-submit').prop('disabled', !enable);
        }

        $('#CommandLineForm').on('submit', function(e) {
            e.preventDefault();
            var value = $('#CommandLineCommand').val();
            
            jQuery.post('/command_lines/issue.json', { "data[CommandLine][command]": value })
                .done(function(data, httpResult) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                })
                .fail(function() {
                    alert('An error occurred while processing the command line.');
                });
            
            if (history.length == 0 || value != history[history.length - 1])
            {
                history.push(value);
                historyIndex = history.length;
            }

            $('#CommandLineCommand').val('');
            $('#CommandLineCommand')[0].focus();
            return false;
        });

        $('#CommandLineCommand').on('input', function() {
            enableRunButton($(this).val() != '');
        });

        $('#CommandLineCommand').on('keyup', function(e) {
            switch(e.which)
            {
            case 27: // Escape:
                historyIndex = history.length;
                break;
                
            case 38: // Up arrow:
                if (historyIndex > 0)
                    --historyIndex;
                break;

            case 40: // Down arrow:
                if (historyIndex < history.length)
                    ++historyIndex;
                break;

            default:
                return;
            }
            if (historyIndex < history.length) {
                $('#CommandLineCommand').val(history[historyIndex]);
                enableRunButton(true);
            }
            else {
                $('#CommandLineCommand').val('');
                enableRunButton(false);
            }
        });

        var history = [];
        if (sessionStorage.getItem('command-line') != null)
            history = JSON.parse(sessionStorage.getItem('command-line'));
        var historyIndex = history.length;

        $(window).on('unload', function() {
            sessionStorage.setItem('command-line', JSON.stringify(history));
        });

        enableRunButton(false);
    });
})(jQuery);
/*
(function($) {
    var selected = null;

    function select(item) {
        if (selected) {
            selected.removeClass('selected');
        }
        selected = $(item);
        selected.addClass('selected');
        scroll();
    }

    function scroll() {
        var view = $('#commandLineList');
        var scrollTop = view.scrollTop();
        var visibleHeight = view.innerHeight();
        var maxOffset = scrollTop + visibleHeight;

        var height = selected.outerHeight(true);
        var top = selected.position().top + scrollTop;  // The position is relative to the top of the visible parent,
                                                        // not the top of the list.  So we have to add scrollTop.

        // If the item is too far up, scroll up just enough to make the entire entry visible at the top of the list.
        if (top < scrollTop)
            view.scrollTop(top);
       
        // If the item is too far down, scroll down just enough to make the entire entry visible at the bottom of the 
        // list.
        else if (top + height > maxOffset)
            view.scrollTop(scrollTop + ((top + height) - maxOffset));
    }

    $.commandLine = {
        show: function() {
            var $cmd = $('#CommandLineCommand');
            var $shell = $('#commandLineShell');
            var $list = $('#commandLineList');

            var $children = $list.children();
            if ($children.length <= 0)
                return;

            select($children[$children.length - 1]);
            $shell.show();

            var pos = $cmd.offset();
            var width = $cmd.innerWidth();

            // Display up to 7 history items simultaneously
            var visible = (($children.length <= 7) ? $children.length : 7);
            var height = visible * $($children[0]).outerHeight() + 7; // 7px for shell border/padding/margin


            $shell.css({
                top: pos.top - height - 1,
                left: pos.left,
                width: width + 2,
                height: height
            });

            if ($children.length <= 7)
                $list.css({ overflow: 'hidden' });
            else
                $list.css({ 'overflow-vertical': 'auto' });
        },

        hide: function() {
            $('#commandLineShell').hide();
            selected = null;
        },
        
        visible: function() {
            return (selected != null);
        }
    };

    $.fn.commandLine = {
    };

    $(document)        
        .ready(function() {
            alert('here');
            $('#ResponseIssueForm').on('submit', function(e) {
                e.preventDefault();
                return false;
            });
            $('#commandLineList a').live('hover', function() {
                $.commandLine.select(this);
            });

            //$.commandLine.show();
        })
        .bind('keydown', function(event) {
            if (!$.commandLine.visible())
                return false;
            
            $.debug.log('commandLine.js keydown.');
            
            switch(event.which)
            {
            // Escape: Hide the command line
            case 27:
                $.commandLine.hide();
                return true;

            // Up arrow
            case 38:
                if (selected.prev().length > 0) {
                    select(selected.prev()[0]);
                    return true;
                }
                
                break;
                
            // Down arrow
            case 40:
                if (selected.next().length > 0) {
                    select(selected.next()[0]);
                    return true;
                }

                break;
            }

            return false;
        });
        //.bind('keypress', function(event) {
        //});
})(jQuery);
*/