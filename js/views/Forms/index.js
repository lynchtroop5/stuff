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

 // Sets focus on the most appropriate field (our best guess, anyway) after the
 // form loads.
function readyFormForUse() {
    $('#ClipsForm :input').each(function(i, field) {
        var $field = $(field);
        if ($field.is(':hidden')) {
            return;
        }

        var name = $field.attr('name');
        if (typeof name == 'undefined') {
            return;
        }
        
        var value = $field.val();
        if (typeof value == 'undefined' || value != '') {
            return;
        }

        // If we hit an ORI field and it's blank, then show the ORI drop down.
        if (name == 'data[ConnectCic][ORI]' || name == 'data[ConnectCic][OriginatingAgencyORI]')
        {
            $field.get(0).focus();
            return false;
        }

        // Any other empty data field we hit, just set focus.
        if (name.indexOf('data[ConnectCic') === 0) {
            $field.get(0).focus();
            return false;
        }
    });
    
}

// Used to load form values into the displayed form.  Data is to be an object with the ConnectCIC XML tag name
// as the key.  The value of the key will be entered into the field.
function loadFormData(data) {
    $.each(data, function(i, value) {
        $('#ConnectCic' + i).val(value);
    });
}

function loadOnlyForm() {
    var children = $('#formlist').children();
    if (children.length == 1) {
        $(children[0]).click();
    }
}

function refreshForms(filter) {
    filter = filter || false;

    var url = '/forms/form_list';
    if (filter == true)
        url += '/Filter[FormList][text]:' + $('#FilterFormListText').val()
             + '/Filter[FormList][sectionId]:' + $('#FilterFormListSectionId').val();

    // Load the resulting form list into #formlist.  The completion handler will automatically load the only matching
    // form if only one form was found.
    $('#formlist').load(url, function() {
        loadOnlyForm();
    });
}

function loadForm(formId)
{
    var url = '/forms/form';
    if (!formId)
        url += '/' + formId;
    
    $('#form').load(url);
}

var hpgUpdateMouseXY = true;
var hpgMousePos = {x:0,y:0};
var hpgTimerId = 0;
var hpgNextTooltipText = '';

function hpTriggerToolTip(text, id)
{
	if (hpgTimerId || text == '')
	{
		return;
	}

	hpgNextTooltipText = text;
	
	if (typeof id == 'undefined')
		id = 'hpHelpDiv';
	
	hpgTimerId = setTimeout('hpDisplayActiveToolTip(\''+id+'\');', 500);
}

function hpSetToolTipText(text)
{
	hpgNextTooltipText = text;
}

function hpDisplayToolTip(objId, id)
{
	if (typeof id == 'undefined')
		id = 'hpHelpDiv';
	
	var d = document.getElementById(id);
	var o = document.getElementById(objId);

	if (!d || !o)
	{
		return;
	}

	d.style.zIndex = 9999;
	d.style.width = 'auto';
	d.style.visibility = 'visible';
	d.innerHTML = hpgNextTooltipText;

	var pos = getElementPosition(o);
	pos.top += o.offsetHeight + 5;

	hpPositionHelp(d, pos);
}

function hpDisplayActiveToolTip(id)
{
	if (hpgTimerId)
	{
		clearTimeout(hpgTimerId);
		hpgTimerId = 0;
	}

	if (typeof id == 'undefined')
		id = 'hpHelpDiv';
	
	var d = document.getElementById(id);

	if (!d)
	{
		return;
	}

	d.style.zIndex = 9999;
	d.style.width = 'auto';
	d.style.visibility = 'visible';
	d.innerHTML = hpgNextTooltipText;

	hpPositionHelp(d, null, id);
	
	hpgUpdateMouseXY = false;
}

function hpHideToolTip(id)
{
	if (hpgTimerId)
	{
		clearTimeout(hpgTimerId);
		hpgTimerId = 0;
		return;
	}

	if (typeof id == 'undefined')
		id = 'hpHelpDiv';
	
	var d =  document.getElementById(id); 

	if (!d)
	{
		return;
	}

	d.style.visibility = 'hidden';
	hpgUpdateMouseXY = true;
}

function hpPositionHelp(d, pos, id)
{
	if (pos)
	{
		d.style.top = pos.top + 'px';
		d.style.left = pos.left + document.body.scrollTop + 'px';
	}
	else
	{
        var o = $('#content');
        var y = hpgMousePos.y - o.offset().top + o.scrollTop();
        var x = hpgMousePos.x - o.offset().left + o.scrollLeft();

        // Add 25 pixels so the mouse cursor isn't hiding the text
		d.style.top = (y + 25) + 'px';
		d.style.left = x + 'px';
	}

	if (d.clientWidth > 400)
		d.style.width = '400px';
	
	var vp = getViewportDimensions();
	if (d.style.posLeft + d.clientWidth > vp.width)
		d.style.left = (vp.width - d.clientWidth) + 'px';
}

function getViewportDimensions()
{
	var vpWidth = 0;
	var vpHeight = 0;
	if (typeof window.innerWidth != 'undefined')
	{
		vpWidth = window.innerWidth;
		vpHeight = window.innerHeight;
	}
	
	// IE6 in standards compliant mode (i.e. with a valid doctype as the first line in the document)

	else if (typeof document.documentElement != 'undefined'
		&& typeof document.documentElement.clientWidth != 'undefined'
		&& document.documentElement.clientWidth != 0)
	{
		vpWidth = document.documentElement.clientWidth;
		vpHeight = document.documentElement.clientHeight;
	}
	// older versions of IE
	else
	{
		vpWidth = document.getElementsByTagName('body')[0].clientWidth;
		vpHeight = document.getElementsByTagName('body')[0].clientHeight;
	}
	
	return {width: vpWidth, height: vpHeight};
}

// Setup everything on document load.
$(document).ready(function() {
    // Setup auto-tabbing.
    $('#ClipsForm').autotab();

    // Any time we get a new response notification, re-load the prefill list.
    $(document).bind('client_response_end', function() {
        $('#PrefillPrefillId').load('/forms/prefill_list');
    });

    // Handle tool tips
    $('#content').live('mousemove', function(event)
    {
        if (hpgUpdateMouseXY == false)
        {
            return;
        }

        hpgMousePos.x = event.clientX;// + document.body.scrollLeft;
        hpgMousePos.y = event.clientY;// + document.body.scrollTop;
        
        //$.debug.log('(' + hpgMousePos.x + ',' + hpgMousePos.y + ')');
    });
    
    // Bind the change event on the Section List drop down to clear the text field and auto-submit the filter.
    $('#FilterFormListSectionId').bind('change', function(event) {
        $('#FilterFormListText').val('');
        $(this).parents('form:first').submit();
    });

    // Hijack the filter form to perform an AJAX style filter.
    $('#FormListFilterForm').bind('submit', function(event) {
       event.preventDefault();

        if ($('#FilterFormListText').val())
            $('#FilterFormListSectionId option:eq(0)').prop('selected', true);

        refreshForms(true);
    });

    // Bind the click event to all current formlinks and future ones.
    $('a.formlink').live('click', function(event) {
        event.preventDefault();

        // Load the HTTP response based on the link URL.
        $('#form').load($(this).attr('href'));
    });

    // Automatically upper-case each field as focus is left and close any visible drop down.
    $('#ClipsForm :input').live('blur', function() {
        if (this.nodeName.toLowerCase() == 'button')
            return;

        var item = $(this);
        if (item.attr('type') == 'hidden')
            return;

        item.val(item.val().toUpperCase());
    });

    $('#MetaTestForm').live('change', function(event) {
        if ($(this).is(':checked')) {
            $('#ClipsForm').addClass('test-mode');
        }
        else {
            $('#ClipsForm').removeClass('test-mode');
        }
    });

    // Bind the form SaveDraft button to submit a draft.
    $('#MetaSaveDraft').live('click', function(event) {
        // Build the post data from the form values
        var values = {};
        $('#ClipsForm :input').each(function(i, element) {
            var value;
            element = $(element);
            if ((value = $.trim(element.val())) != '')
                values[element.attr('name')] = value;
        });

        // Save the draft.
        $.post('/forms/save_draft', values, function() {
            // On completion, refresh the form list if we're showing draft forms.
            if ($('#FilterFormListSectionId').prop('selectedIndex') == 2)
                refreshForms();
            
            // TODO: We need to figure out how to get the delete draft button.
        });

        event.preventDefault();
    });

    // Bind the form Delete Draft button to delete a draft.
    $('#MetaDeleteDraft').live('click', function(event) {
        // Delete the draft.
        $.post('/forms/delete_draft', {'data[Meta][draftFormId]': $('#MetaDraftFormId').val()}, function() {
            
            // On completion, refresh the form list if we're showing draft forms.
            if ($('#FilterFormListSectionId').prop('selectedIndex') == 2)
                refreshForms();

            // Relaod the current form.
            $('#form').load('/forms/form');
        });

        event.preventDefault();  
    });

    // Bind the form Add Favorite button to submit a draft.
    $('#MetaSaveFavorite').live('click', function(event) {
        // Save to favorite forms
        $.post('/forms/save_favorite', function() {
            // On completion, refresh the form list if we're showing favorite forms.
            if ($('#FilterFormListSectionId').prop('selectedIndex') == 1)
                refreshForms();
            
            // TODO: We need to get the Delete Favorite button.
        });

        event.preventDefault();
    });

    // Bind the form Delete Favorite button to submit a draft.
    $('#MetaDeleteFavorite').live('click', function(event) {
        // Delete from favorite forms
        $.post('/forms/delete_favorite', function() {
            // On completion, refresh the form list if we're showing favorite forms.
            if ($('#FilterFormListSectionId').prop('selectedIndex') == 1)
                refreshForms();

            // TODO: We need to get the Add Favorite button.
        });

        event.preventDefault();
    });

    // Bind the form prefill change event to load the prefill values
    $('#PrefillPrefillId').live('change', function(event) {
        var $this = $(this);

        if (!$this.val())
            return;

        result = $.getJSON('/forms/prefill/' + $this.val() + '.json', function(data) {
            loadFormData(data);
        });
    });

    // Bind the Delete Prefill button to delete the selected prefill
    $('#DeletePrefill').live('click', function(event) {
        event.preventDefault();

        var value = $('#PrefillPrefillId').val();
        if (!value)
            return;

        $.post('/forms/delete_prefill', {'Prefill[prefillId]': value}, function(data) {
            $('#PrefillPrefillId option[value=\'' + value + '\']').remove();
        });
    });

    $('#ClipsForm').live('submit', function() {
        var $item = $(document.activeElement);
        if ($item.is(':text'))
            $item.val($item.val().toUpperCase());
        
        if (!$('#MetaKeepForm').is(':checked'))
        {
            // Detach the CLIPS client.  This should prevent a race condition where
            // the ActiveX receives the response notification event while the page
            // is still redirecting.
            if ($.clips.available())
                $.clips.unload();
            return true;
        }

        var $this = $(this);
            $.post('/forms/submit', $this.serializeArray(), function() {
        });

        
        $this[0].reset();
        $('#MetaKeepForm').prop('checked', true);

        $('#content').scrollTop(0);
        return false;
    });
    
    if (window.location.href.indexOf('Filter[FormList]') >= 0)
        loadOnlyForm();

    $.debug.log('Forms/index.js ready.');
});
