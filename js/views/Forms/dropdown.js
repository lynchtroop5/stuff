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
    var _map = {};       // Lookup table maping field names to dropdown names and dropdowns to their type information
    var _cache = {};     // A cache of loaded dropdowns.
    var _proc = null;    // Cached XSLT processor for dropdown filtering
    var _current = null; // The current field, associated dropdown, and selected value

    // Given a field name, returns that field's associated dropdown.  If no dropdown is associated, then null is
    // returned.
    function dropdown(elementName) {
        if (typeof(_map['map'][elementName]) != 'undefined')
            return _map['map'][elementName]['name'];

        return null;
    }

    // Given a field name, returns that field's related field name. (i.e. in most cases VehicleModelCode will return
    // VehicleModelCode).  This is used, for example, to filter vehicle models by make.
    function relatedElement(elementName) {
        if (typeof(_map['map'][elementName]) != 'undefined')
            return _map['map'][elementName]['related'];
        return null;
    }

    // Given a dropdown name, returns that dropdown's type information.  The type information contains the following:
    //
    // * codeTypeId:     The ID used to retreive the dropdown from the server.
    // * type:           The name of the dropdown (same as the passed parameter).
    // * hasRelatedType: Whether or not the dropdown values are coded with the related field values.
    // * isLargeList:    Whether the list is considered large or not. 
    function definition(dropdownName) {
        if (typeof(_map['type'][dropdownName]) != 'undefined')
            return _map['type'][dropdownName];

        return null;
    }

    // Given a dropdown name, returns true if that dropdown has a cache entry and has at least been requested from the
    // server.  This does not mean that the dropdown is loaded.
    function cached(name) {
        return (typeof(_cache[name]) != 'undefined');
    }

    // Given a dropdown name, returns true if that dropdown has a cache entry and has been downloaded from the server.
    // If no name is present, returns true if the currently active dropdown is loaded.
    function loaded(name) {
        if (!name) {
            if (!currentType())
                return false;
            name = currentType()['type'];
        }

        return (cached(name) && _cache[name] != null);
    }

    // Caches the specified dropdown data.
    function cache(name, data) {
        if (data)
            _cache[name] = data;
        return _cache[name];
    }

    // Sets, or clears, the current dropdown
    function current($element, type) {
        if (!$element || !type)
            _current = null;
        else
            _current = {
                element: $element,
                type: type,
                selected: null
            };
    }

    // If the dropdown has been loaded, highlights the first entry in the dropdown.  If byKeyboard is true, the element
    // is scrolled into view.
    function currentSelectFirst(byKeyboard) {
        var child = $('#dropdownList').children(':first');
        if (child.length > 0) {
            if (child.prop('nodeName').toLowerCase() == 'a')
                currentSelect(child, byKeyboard);
        }
    }
    
    // Highlights the dropdown entry represented by $element.  If byKeyboard is true, the element is scrolled into view.
    function currentSelect($element, byKeyboard) {
        if (_current == null)
            return;
        
        if (_current.selected != null) {
            _current.selected.removeClass('selected');
        }
        
        _current.selected = $element;
        _current.selected.addClass('selected');

        if (byKeyboard)
            scroll();
    }

    // Returns the jQuery wrapped input box for the current dropdown.
    function currentElement() {
        if (_current == null)
            return null;
        return _current.element;
    }

    // Returns the type definition for the current dropdown.
    function currentType() {
        if (_current == null)
            return null;
        return _current.type;
    }

    // Returns the currently highlighted dropdown entry in the dropdown.
    function currentSelected() {
        if (_current == null)
            return null;
        return _current.selected;
    }

    // Ensures that the currently highlighted dropdown entry is visible in the dropdown list.
    function scroll() {
        var view = $('#dropdownList');
        var scrollTop = view.scrollTop();
        var visibleHeight = view.innerHeight();
        var maxOffset = scrollTop + visibleHeight;

        var selected = _current.selected;
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

    // Retreives the specified dropdown from the server.  When its received, it will be cached and the current dropdown
    // will be filtered.
    function fetch(dropdownName) {
        if (!cached(dropdownName)) {
            cache(dropdownName, null);

            var url = '/dropdowns/view/' + definition([dropdownName])['codeTypeId'] + '.xml';
            $.get(url, function(data) {
                cache(dropdownName, data);
                if (currentType() && currentType()['type'] == dropdownName) {
                    filter();
                }
            });

            return false;
        }

        return true;
    }

    // Returns true if the key (event.which) is one that should update the filter on the current dropdown.
    function isFilterKey(key) {
        return (key == 8 || key == 46 || key == 32 || (key > 47 && key < 91) || (key > 95 && key < 112) || key > 185);
    }

    // Updates the current dropdown based on text entered into the current field or the search text box.
    function filter(text) {
        if (!_proc || !visible() || !loaded())
            return;

        // Don't filter ORI drop downs.
        if (currentType()['type'] == '$_ORI_DD_$')
            text = '';
        else
            text = (text || currentElement().val());
        
        _proc.setParameter(null, 'filter', text);
        _proc.setParameter(null, 'relatedValue', '');
        _proc.setParameter(null, 'stripBeforeSlash', '0');

        // Handle additional drop down filtering for related fields.
        if (currentType()['hasRelatedType'] == '1') {
            var relatedName = relatedElement(currentElement().attr('name'))
            if (relatedName) {
                // Escape the brackets in the field name so jQuery can find it.
                relatedName = relatedName.replace(/(\[|\])/g, '\\$1');
                var related = $('#ClipsForm input[name=' + relatedName + ']');
                _proc.setParameter(null, 'relatedValue', related.val());
            }
        }

        // Do the update.  For large list types, we need to figure out how to load the list more quickly.
        var output = _proc.transformToFragment(cache(currentType()['type']), document);
        $('#dropdownList').html(output);
    }

    // Handles keyboard events that are considered dropdown navigation.
    function navigate(element, code) {
        switch(code)
        {            
        // Enter: Submit the form.
        // HACK:  This shouldn't be here.  It should be in index.js.
        case 13:
            if (!visible()) {
                // This should only be done if a field in the form is focused and not a textarea.
                if ($(element).parents('form:first')[0] == $('#ClipsForm')[0] && !$(element).is('textarea'))
                {
                    $('#MetaSubmit').click();
                    return true;
                }
            }
            break;
        
        // Escape: Hide the dropdown
        case 27:
            if (visible()) {
                hide();
                return true;
            }
            break;

        // Up arrow moves the current highlighted entry up.
        case 38:
            if (currentSelected() && currentSelected().prev().length > 0) {
                currentSelect(currentSelected().prev(), true);
                return true;
            }

            break;

        // Down arrow shows the dropdown if its hidden, selects the first entry if there is no selection, or moves the 
        // current highlighted entry down.
        case 40:
            if (!visible()) {
                if (element) {
                    show(element);
                    currentSelectFirst(true);
                    return true;
                }

                return false;
            }

            if (!currentSelected()) {
                currentSelectFirst(true);
                return true;
            }
            else if (currentSelected().next().length > 0) {
                currentSelect(currentSelected().next(), true);
                return true;
            }

            break;
        }

        return false;
    }

    // Shows the dropdown on the passed input box, sets the current dropdown state, and requests the dropdown from the
    // server, if necessary
    function show(element) {
        var $element = $(element);

        var dropdownName = dropdown($element.attr('name'));
        if (dropdownName == null)
            return;
        
        current($element, definition(dropdownName));

        var pos = $element.position();
        var height = $element.outerHeight();

        $('#dropdownShell').css({
            top: (pos.top + height + $('#content').scrollTop()) + 'px',
            left: pos.left
        }).show(1);

        if (!loaded()) {
            $('#dropdownList').html('<i>Loading...</i>')
            fetch(dropdownName);
        }
        else
            filter();
    }

    // Hides the current dropdown
    function hide() {
        _current = null;        
        $('#dropdownShell').hide();
        $('#dropdownSearch')
            .css('color', '#9c9c9c')
            .css('font-style', 'italic');
    }

    // Returns true if the current dropdown is visible
    function visible() {
        return (_current != null)
    }
 
    // Selects the currently highlighted entry in the dropdown list and uses its value as the input box's value.
    // The dropdown is then hidden.
    function select($element) {
        var children = $element.children('span');

        var value = $.trim($(children[0]).text());
        currentElement().val(value);
        currentElement().focus();

        //$.autotab(currentElement());
        hide();
    }

    // Initialization routine called when the document is loaded.
    $(document).ready(function() {
        // HACK to get drop dowin image icons working
        $('#ClipsForm img').live('click', function(e) {
            show($(this).prev());
            e.stopPropogation();
        });
        
        // Handle global navigation.  This is responsible for dealing with keystrokes that don't have to happen when
        // the text box has focus.
        $(document).bind('keydown', function(event) {
            $target = $(event.target);

            // If a drop down is visible, handle some special cases
            if (visible())
            {
                // Otherwise, pretend the target is the current dropdown element
                $target = currentElement();

                // If the user presses tab, they mean to select the current item in the drop down and move to the
                // next field.
                if (event.which == 9) { // Tab
                    if (currentSelected())
                        select(currentSelected());
                    $target.focus();
                    return true;
                }
            }

            // Handle standard navigation (enter, up, down, escape, etc).
            if (navigate($target[0], event.which)) {
                event.preventDefault();
                return true;
            }
        });

        // Bind the global keypress event so we can detect when the user presses the enter key.  When pressed, the user
        // wants to select the current dropdown selection.  We use keypress instead of keydown so that we can prevent
        // accidental submission of the form.
        $(document).bind('keypress', function(event) {
            if (visible() && event.keyCode == 13 && currentSelected()) {
                select(currentSelected());
                event.preventDefault();
            }
        });

        // Bind the drop down shell's search text box to filter by description
        $('#dropdownSearch')
            // Add a water-mark to the search box when it loses focus
            .live('blur', function() {
                var item = $(this);
                if (!item.val())
                {
                    item
                        .css('color', '#9c9c9c')
                        .css('font-style', 'italic');
                }
            })
            // Remove the water-mark from the search box when it gains focus
            .live('focus', function() {
                var item = $(this);
                if (item.val() == 'Search')
                {
                    item
                        .css('color', '#000000')
                        .css('font-style', 'normal');
                }
            })
            .live('keyup', function(event) {
                if (!isFilterKey(event.which))
                    return true;

                _proc.setParameter(null, 'search', 'description');
                filter($(this).val());
            });

        // If the user clicks a drop down entry, set the field's value and hide the drop down.
        $('#dropdownList a')
            .live('click', function() {
                select($(this));
            })
            // Bind the mouse over even to keep the _current.selected up to date.
            .live('mouseover', function() {
                currentSelect($(this));
            });
    
        $.debug.log('Forms/dropdown.js ready.');
    });
 
    // Allows access to dropdown helper functions from $.dropdown.
    $.dropdown = {
        // Make visible and hide methods available to the public.
        visible: visible,
        hide:    hide,

        // Add a setup function which can be used to provide drop down lookup information.  This is called when a form
        // is loaded.
        setup: function(table) {
            // Load the XSLT transformation
            if (!_proc) {
                $.get('/js/views/Forms/dropdown.xsl', function(xml) {
                    var proc = new XSLTProcessor();
                    proc.importStylesheet(xml);
                    _proc = proc;
                });
            }

            _map = {
                'map': {},              // Map of field name to drop down names
                'type': table['type']   // Table containing definitions for drop downs, keyed by name.
            };

            // Rename the drop down fields to match what the HTML is using.
            $.each(table['map'], function(field, type) {
                if (type['related'])
                    type['related'] = 'data[ConnectCic][' + type['related'] + ']';
                _map['map']['data[ConnectCic][' + field + ']'] = type;
                
            });

            // Handle hiding the drop down when the document is clicked
            $(document).click(function(e) {
                var $target = $(e.target);
                
                // Dont' hide the drop down if we click our own field
                if (currentElement() && currentElement().attr('name') == $target.attr('name')) {
                    return;
                }

                // Don't hide the drop down if this element is a descendent of our drop down shell
                if ($target.closest('#dropdownShell').length) {
                    return;
                }

                hide();
            });
        },

        show: function(field)
        {
            show(field);
        }
    };

    // Allows $('selector').dropdown() to create drop down fields
    $.fn.dropdown = function() {
        // Setup appropriate events on each of the selected fields.
        return this.each(function() {
            var $this = $(this);  // Refers to DOM Element
            var elementName = $this.attr('name');

            // Bind blur to hide the drop down if the target focus is not our drop down itself
            // or one of its children.
            $this.bind('blur', function(e) {
                setTimeout(function() {
                    var $target = $(document.activeElement);

                    // Don't hide the drop down if this element is a descendent of our drop down shell
                    if ($target.closest('#dropdownShell').length) {
                        return;
                    }

                    hide();
                }, 1);
            });

            // Nothing else to do for non-drop down fields
            if (!dropdown(elementName))
                return;

            // Bind keyup to show or filter a drop down when typing into an the field.  We have to use keyup because
            // the field hasn't been updated with the new character value.
            $this.bind('keyup', function(event) {
                // Ignore key stroke if ALT or CTRL was pressed.
                if (event.altKey || event.ctrlKey || !isFilterKey(event.which))
                    return;

                // If the dropdown isn't visible, show it.  It will automatically be filtered when its loaded.
                if (!visible() && $(this).val() != '')
                    show(this);
                else if (visible()) {
                    // Filter the drop down.
                    _proc.setParameter(null, 'search', 'code');
                    filter();
                }
            });
        });
    };
})(jQuery);
