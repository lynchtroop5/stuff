/*******************************************************************************
 * Copyright 2011 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
 ******************************************************************************/

// JQuery Wrapper around console.log which can be enabled or disabled 
$.debug = {
    enable: function (value) {},

    log: function () {}
};

$.clips = {
    available: function () {
        return false;
    },

    idlePeriod: function (period) {}
};

var hasProcessed = false;
var lastScrollPosition;
var notifyTimerId = null;
var retryTimer = null;
var topRequest = null;
var totalNewResponses = 0;

function process_responses(isRefresh = null) {
    var showLoading = $('.refresh_request_mobile');
    // If refresh button was clicked then show loading gif.
    if (isRefresh) {
        showLoading.css('display', 'block');
        hasProcessed = false;
    } 
    // Read the retry counter.  It's stored in localStorage so we can keep trying across page transitions.
    var tries = window.localStorage.getItem("process_try");
    tries = ((tries != null) ? parseInt(tries) : 0);

    if (tries >= 5 || hasProcessed) {
        window.localStorage.removeItem('ran_transaction');
        window.localStorage.removeItem('process_try');
        // Turn off loading gif if refresh button was clicked.
        if (showLoading.css('display') == 'block') {
            showLoading.css('display', 'none');
        }
        return;
    }

    ++tries;
    if (!hasProcessed) {
        window.localStorage.setItem('process_try', tries);
        window.localStorage.setItem('ran_transaction', 'true');
    } else {
        // Turn off loading gif if refresh button was clicked and a response was processed.
        if (showLoading.css('display') == 'block') {
            showLoading.css('display', 'none');
        }
    }

    jQuery.ajax('/message_queues/process_queue.json', {
        dataType: 'json',

        success: function (data) {
            // Keep polling for responses for as long as we keep getting new responses.
            if (process_response_data(data) == true) {
                if (retryTimer != null) {
                    retryTimer = null;
                    clearTimeout(retryTimer);
                }

                window.localStorage.removeItem('process_try');
                process_responses();
                return;
            }

            retryTimer = setTimeout(function () {
                process_responses();
            }, 3000);
        },

        error: function (req, status, thrown) {
            if (typeof thrown == 'object' && typeof thrown.description != 'undefined')
                thrown = thrown.description;

            // TODO: Display an error
            window.localStorage.removeItem('ran_transaction');
            window.localStorage.removeItem('process_try');

            // If we get the 403 state, chances are the user logged out during a request.  Ignore it.
            if (req.readyState == 4 && req.status == 403)
                return;
        }
    });
}

function toggle_request_button() {
    var btn = $('#requests_button');
    if (btn.length == 0)
        return;

    var oldTheme = btn.attr('data-theme');
    if (typeof oldTheme == 'undefined')
        oldTheme = 'a';
    var theme = ((oldTheme != 'a') ? 'a' : 'b');

    $('#requests_button')
        .attr('data-theme', theme)
        .removeClass('ui-btn-up-' + oldTheme)
        .addClass('ui-btn-up-' + theme)
        .removeClass('ui-body-' + oldTheme)
        .addClass('ui-body-' + theme)
        .trigger('create');
}

function notify() {
    if (notifyTimerId != null)
        return;

    notifyTimerId = window.setInterval(toggle_request_button, 1000)
}

function reloadPage() {
    var node = $("div[data-role*='content']");
    // Ensure that DOM is ready
    $(function () {
        // Retrieve the DOM elements.
        $.get(window.location.href, function (data) {
            // Create page with jQuery Mobile css added
            node.html(data).trigger('pagecreate');
            // Remove loading gif.
            $('.loading_mobile').css('display', 'none');
            $(node).removeClass('loading_overlay');
        });
    });
}

function process_response_data(data) {
    var newHits = 0;
    var newRequests = 0;
    var newResponses = 0;
    var generalResponse = null;
    var requestId = null;
    var refreshThis = false;
    var thisNew = 0;
    var onRequests = false;

    if (data['requests'].length <= 0)
        return false;

    var location = window.location.href;
    var index = location.indexOf('/requests');
    if (index >= 0)
        onRequests = true;


    index = location.indexOf('/responses/request/');
    if (index >= 0) {
        index += '/responses/request/'.length;
        requestId = parseInt(location.substr(index));
    }

    for (index = 0; index < data['requests'].length; ++index) {
        var value = data['requests'][index];

        newHits += value['hitResponses'];
        newResponses += value['newResponses'];
        newRequests += ((value['requestIsNew'] > 0) ? 1 : 0);
        if (onRequests == false && value['newResponses'] > 0 && value['requestId'] == requestId) {
            thisNew = value['newResponses'];
            refreshThis = true;
        }
        // Update the response count on the request link
        else if (onRequests) {
            var node = $('#request_' + value['requestId'] + ' .response_count');
            if (node.length > 0 && value['newResponses'] > 0)
                node.attr('style', 'color: #ff0000').html(value['responses']);
            refreshThis = true;
        }

        if (value['requestId'] == 0) {
            data['requests'].splice(index, 1);
            generalResponse = value;
            --index;
        }
    }

    if (newResponses > 0) {
        var type = 'normal';

        if (newHits > 0)
            type = 'hit';
        else if (generalResponse['newResponses'] > 0)
            type = 'general';
        if (newResponses > totalNewResponses) {
            totalNewResponses = newResponses;
            if (refreshThis && !hasProcessed) {
                totalNewResponses -= thisNew;
                hasProcessed = true;
                reloadPage();
            } else
                notify();
            return true;
        }
    }
    return false;
}

$("div[data-role*='page']").live('pageshow', function () {
    // For mobile devices, require a 1 hour reauthentication timer
    if ($('#UserDoLoginForm').length <= 0) {
        setTimeout(function () {
            window.location = '/users/login';
        }, (1 * 60 * 60 + 2) * 1000);
    }

    // Will only trigger if a request was ran
    if (window.localStorage.getItem("ran_transaction") == null)
        return;

    process_responses();
});

// This function hides the header if the user is scrolling down. 
// Once the user scrolls up then the header comes back into view.
$(document).scroll(function () {
    //Get scrollPosition
    var scrollPosition = $(this).scrollTop();
    // Check that fixedtoolbar() is loaded and ready.
    if ($('[data-id="clips-header"]').fixedtoolbar()) {
        // Prevents page clicks from the user interfering with with the scroll. 
        $('[data-role="footer"], [data-id="clips-header"]').fixedtoolbar({
            tapToggle: false
        });
        if (scrollPosition > lastScrollPosition) {
            // If the header is currently showing then hide the header.
            if (!$('[data-id="clips-header"].ui-fixed-hidden').length) {
                $('[data-id="clips-header"]').fixedtoolbar('hide');
            }
        }
        else {
            // If the header is currently hidden then show the header
            if ($('[data-id="clips-header"].ui-fixed-hidden').length) {
                $('[data-id="clips-header"]').fixedtoolbar('show');
            }
        }
    }
    // Reset lastScrollPosition.
    lastScrollPosition = scrollPosition;
});

function paging(requestId, page, totalPages, perPage) {
    pageState = {
        requestId: requestId,
        page: page,
        totalpages: totalPages,
        perPage: perPage
    };
}

// Allows clicks on paging to render the new page dynamically without reloading the entire page.
$('.paging_mobile a').live('click', function (event) {
    var $this = $(this);
    var node = $("div[data-role*='content']");
    // Hides the bottom paging bar from showing. 
    $('.paging_mobile').not(':first').hide();
    // Show loading gif. 
    $('.loading_mobile').css('display', 'block');
    $(node).addClass('loading_overlay');
    // Ensure that DOM is ready
    $(function () {
        // Retrieve the initial DOM elements.
        $.get($this.href, function () {
            // Gets the url of the page the user clicked on.
            var targetUrl = $this.attr('href');
            // Retrieve the new DOM elements with the url.
            $.get(targetUrl, function (data) {
                // Create page with jQuery Mobile css added
                node.html(data).trigger('pagecreate');
                // Hide loading gif and bring back the bottom paging.
                $('.loading_mobile').css('display', 'none');
                $(node).removeClass('loading_overlay');
                $('.paging_mobile').not(':first').show();
            });

        });
    });
    event.preventDefault();
});