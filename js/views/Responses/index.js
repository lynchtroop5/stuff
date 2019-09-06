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

function loading()
{
    $('#request').html('Loading...<br />' + 
                       '<img src="/img/loading.gif" height="16" width="107" title="Loading..." />');
}

var pageState = null;
var generalResponses = 0;
var newGeneralResponses = 0;
var outstandingDrilldowns = {};

function paging(requestId, page, totalPages, perPage)
{
    $.debug.log('Setting page information (', requestId, page, totalPages, perPage, ')');
    pageState = {
        requestId: requestId,
        page: page,
        totalpages: totalPages,
        perPage: perPage
    };
}

function markRead(requestId, responseCount) {
    $.debug.log('Mark read (', requestId, responseCount, ')');
    $.audio.stop();

    if (responseCount <= 0)
        return;

    var current = $.clips.newResponseCount();
    
    current -= responseCount;
    if (current <= 0)
        current = 0;

    $.clips.newResponseCount(current);

    if (requestId == 0) {
        newGeneralResponses -= responseCount;
        if (newGeneralResponses <= 0)
            newGeneralResponses = 0;

        generalResponseCount(generalResponses, newGeneralResponses);
    }
}

function generalResponseCount(request) {
    // Update the general responses link
    var $link = $('#GeneralResponseLink');

    var $count = $link.find('.count');
    $count.text(request.responses);

    if (request.newResponses > 0) {
        $count.css('color', '#ff0000');
    }
    else {
        $count.css('color', '#000000');
        if ($link.hasClass('hit_confirmation')) {
            $link.removeClass('hit_confirmation');
        }
        if ($('#response_list div').hasClass('has_hit')) {
            $link.addClass('hit_alert');
        }
    }
    if (request.hitResponses > 0) 
        $link.addClass('hit hit_alert');
    // Adding attribute to track if the response bucket has been viewed.
    $link.attr('newResponseCount', request.newResponses);

}

function updateRequestList()
{
    $.debug.log('Requesting updated request list.');
    $('#requestlist').load('/responses/request_list');
}

function updateResponseList(isArchiveResponse, notify)
{
    // This loads the entire request action again, then pulls out only the pieces we need.
    var url = '/responses/request/' + pageState['requestId'] + '/page:' + pageState['page'];
    $.debug.log('Requesting updated response list:', url);
    $.ajax(url, {
        success: function(html) {
            $.debug.log('Response list received.  Updating UI.');

            var requestTitle = $(html).filter('#request_title');
            $('#request_title').html($(requestTitle).html());

            var responses = $(html).filter('#content');
            $('#content').html($(responses).html());

            // Manually execute the script in the receive HTML block
            var script = $(html).filter('script');
            if (script) {
                $.debug.log('Executing response list scripts.');
                eval($(script).html());
            }

            // Update the alert icon
            if (notify)
                $('#alert img').attr('src', '/img/alert.gif');

            // Get numeric string from the response bucket count.
            var responseCount = $('#general_count').text();
            // Ensure that the count is greater than 0 and that this function 
            // was fired as a result of the user archiving a response.
            if (parseInt(responseCount) > 0 && isArchiveResponse) {
                // Set updated count.
                var newResponseCount = parseInt(responseCount) - 1;
                $('#general_count').text(newResponseCount);
                // Check for hit responses within the message repo. If none then remove hit alert color from bucket.
                if (!$('#response_list div').hasClass('has_hit')) {
                    if ($('#GeneralResponseLink').hasClass('hit_alert')) {
                        $('#GeneralResponseLink').removeClass('hit_alert');
                    }
                }
            }
        }
    });
}

$(document).ready(function() {
    var newRequest = false;
    var newResponse = false;
    
    // Bind a new responses event so we can update our UI.
    // TODO: This event is poorly named; it's actually called anytime there is an update to a request bucket
    $(document)
        .bind('client_response', function(event, request) {// newRequests, newResponses, generalResponse, data) {
            // request has members: requestId, requestIsNew, responses, newResponses, and hitResponses
            if (request.requestId == 0) {
                generalResponseCount(request);
            }
            else {
                // Find the request bucket
                var bucket = $('#requestLink_' + request.requestId);
                if (bucket.length == 0) {
                    newRequest = true;
                    return;
                }

                // Update the bucket count
                var bucketCount = bucket.find('.count');
                bucketCount.text(request.responses);
                if (request.newResponses > 0)
                    bucketCount.addClass('new');

                // If there's a hit, update the class
                if (request.hitResponses > 0) {
                    bucket.addClass('hit_alert');
                } 
            }
            
            // If this request bucket is the active one, and we have new responses, we need to update our
            // response list.
            if (pageState != null && pageState.requestId == request.requestId && request.newResponses > 0)
                newResponse = true;
        })
        .bind('client_response_end', function(event) {
            if (newRequest == true)
            {
                updateRequestList();
                newRequest = false;
            }
             
            if (newResponse == true)
            {
                updateResponseList(false);
                newResponse = false;
            }
        });
        
        /*
        $.debug.log('Updating page state.');

        var value;
        var index;
        var activeRequest = null;
        if (pageState != null)
        {
            if (pageState['requestId'] == 0 && generalResponse['newResponses'] > 0)
                activeRequest = generalResponse;
            else
            {
                for(index = 0; index < data.length; ++index)
                {
                    value = data[index];
                    if (value['requestId'] == pageState['requestId'] && value['newResponses'] > 0)
                    {
                        activeRequest = value;
                        break;
                    }
                }
            }
        }

        if (newRequests > 0)
            updateRequestList();
        else
        {
            for(index = 0; index < data.length; ++index)
            {
                value = data[index];
                if (value['newResponses'] <= 0)
                    continue;

                var $link = $('#requestLink_' + value['requestId']);
                if (value['hitResponses'] > 0)
                    $link.addClass('hit');

                $link.find('.count').addClass('new');
            }
        }

        if (activeRequest != null)
            updateResponseList(true);

        generalResponseCount(generalResponse);//generalResponse['responses'], generalResponse['newResponses']);
    });
        */

    // Bind to the general responses link to load the general responses
    $('#GeneralResponseLink').bind('click', function(event) { 
        var $this = $(this);
        if ($this.hasClass('selected')) {
            event.preventDefault();
            return;
        }

        $('#requestlist a.selected').removeClass('selected');
        $this.addClass('selected');

        $.debug.log('Requesting response list for request:', $this.attr('href'));
        $('#request').load($this.attr('href'));
        event.preventDefault();
    });

    // Bind to the request list links to load a particular request bucket into view.
    $('#requestlist a').live('click', function(event) {
        var $this = $(this);
        if ($this.hasClass('selected')) {
            event.preventDefault();
            return;
        }

        $('#GeneralResponseLink').removeClass('selected');
        $('#requestlist a.selected').removeClass('selected');
        $this.addClass('selected');

        $.debug.log('Requesting response list for request:', $this.attr('href'));
        $('#request').load($this.attr('href'));
        event.preventDefault();
    });

    // Bind the upper-right print button to execute some javascript.  This way we can handle the check boxes too
    $('#print_request').live('click', function(event) {
        var ids = [];
        $('.print_select:checked').each(function(i, o) {
            ids[i] = $(o).val();
        });

        if (ids.length > 0) {
            var url = $(this).attr('href') + '/' + 'ids:' + ids.join(',');
            window.open(url);

            event.preventDefault();
        }
    });

    // Bind the jump to drop down to scroll the selected response into view.
    $('#ResponseResponseId').live('change', function(event) {
        var id = '#r' + $(this).val();
        var $id = $(id);

        if ($id.length > 0) {
            $id[0].scrollIntoView();
            event.preventDefault();
        }
        else
            $('#content').prop('scrollTop', 0);
    });

    // Bind the "Select" links to check different response types for printing.
    $('a.print_all').live('click', function(event) {
        $('.print_select').prop('checked', true);
    });
    
    $('a.print_none').live('click', function(event) {
        $('.print_select').prop('checked', false);
    });
    
    $('a.print_type').live('click', function(event) {
        var type = $(this).text();

        // Find all H1 elements which contain an element of class '.response_type' whose text
        // matches the type of the clicked link.  Within those matching elements, check the print
        // selection checkbox
        $('h1')
            .has('.response_type:contains("' + type + '")')
            .find('.print_select')
            .prop('checked', true);

        event.preventDefault();
    });

    // Bind all paging links to work through ajax.
    $('.paging a').live('click', function(event) {
        var $this = $(this);

        $.debug.log('Requesting response list for request:', $this.attr('href'));
        $('#request').load($this.attr('href'));
        event.preventDefault();
    });

    // Bind the "Jump to Page" GO button to submit via AJAX
    $('.paging input[type=submit]').live('click', function(event) {
        var $form = $(this).parents('form:first');
        $.debug.log('Requesting response list for request:', $form.attr('action'), $form.serialize());

        var postData = $form.serializeArray();
        $('#request').load($form.attr('action'), postData);

        event.preventDefault();
    });
    
    // Bind drilldown links to work through an AJAX request.
    $('#content a.drilldown').live('click', function(event) {
        event.preventDefault();
        
        // Some users like to double click HTML links, so we need to prevent the request from being submitted twice.
        var $this = $(this);
        var url = $(this).attr('href');

        if (typeof outstandingDrilldowns[url] == 'undefined' || !outstandingDrilldowns[url])
        {
            outstandingDrilldowns[url] = true;

            $.debug.log('Requesting drilldown:', url);
            $.get(url, function() {
                outstandingDrilldowns[url] = false;
            });
        }
    });

    // Bind clear_response links to work through AJAX requests.
    $('#content a.clear_response').live('click', function(event) {
        event.preventDefault();
        
        if(confirm('Are you sure you wish to remove this response?') == true)
            {
                var $this = $(this);
                $.get($this.attr('href'), function() {
                    updateRequestList();
                    updateResponseList(true);
                });
            }

        return true;
    });

    $.debug.log('Responses/index.js ready.');
});
