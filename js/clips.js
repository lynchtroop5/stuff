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


(function ($) {
    // Disable AJAX caching.  Otherwise, certain session variables on the server won't be set by AJAX requests.
    $.ajaxSetup({ cache: false });

    // JQuery Wrapper around console.log which can be enabled or disabled 
    $.debug = {
        _enabled: true,

        enable: function (value) {
            this._enabled = value;
        },

        log: function () {
            if (!this._enabled)
                return;

            // Although arguments acts like an array, it's not.  So we can't use 'join' on it directly.
            var args = [];
            for (var a = 0; a < arguments.length; ++a)
                args[a] = arguments[a];

            console.log(args.join(' '));
        }
    };

    // Setup a dummy function for console.log.  Prevents debug statements from blowing up scripts.
    if (typeof console == 'undefined' || !console || typeof console.log == 'undefined' || !console.log) {
        window.console = { log: function () { } };
    }

    // Internet explorer refuses to implement indexOf.
    if (typeof Array.indexOf == 'undefined' || !Array.indexOf) {
        Array.prototype.indexOf = function (obj, start) {
            start = start || 0;
            for (var i = start; i < this.length; i++) {
                if (this[i] == obj)
                    return i;
            }

            return -1;
        }
    }
    
    var hostUrl = window.location;
    var hostSocketAddress = "ws://" + hostUrl.hostname + ":8383";
    var webSocket = null;
    var disconnects = 0;
    var retryMax = 60;
    var _connectStatus = false;
    var idleId = null;
    var reauthId = null;
    var killId = null;
    var idlePeriod = 0;
    var processId = null;
    var isActive = false;
    var flashId = null;

    var clientState = {
        session: null,
        authentication: null,
        notifications: 0,
        enableRestart: false,
        newResponses: 0
    };

    function reset_state(enableRestart) {
        $.debug.log('Resetting client state');

        enableRestart = enableRestart || false;

        clientState.notifications = 0;
        clientState.enableRestart = clientState.enableRestart && enableRestart;
        if (clientState.enableRestart == true)
            $.debug.log('Client restart enabled.');

        clientState.newResponses = 0;
        clientState.session = clientState.authentication = null;
    }

    function process_responses() {
        $.debug.log('Issuing process_queue request.');

        $.event.trigger('client_poll');

        jQuery.ajax('/message_queues/process_queue.json', {
            dataType: 'json',
            success: function (data) {
                $.debug.log('process_queue response received.');

                if ($.clips.inSession() == false) {
                    $.debug.log('process_queue response aborted due to no session.');
                    return;
                }
                // Process the server response
                process_response_data(data);

                // Decrement our notification count. If we have more than 0, then we received additional notifications
                // while this request was outstanding. Trigger another poll.
                if (--clientState.notifications >= 1) {
                    $.debug.log('Triggering process_queue request due to multiple notifications.');
                    clientState.notifications = 1;

                    process_responses();
                }
                else
                    $.event.trigger('client_poll_complete');
            },
            error: function (req, status, thrown) {
                $.debug.log('process_queue error received.');

                // TODO: Indicate error?
                $.event.trigger('client_poll_complete');

                if (typeof thrown == 'object' && typeof thrown.description != 'undefined')
                    thrown = thrown.description;

                // Reset the notification count to prevent a hang-up on message processing.
                clientState.notifications = 0;

                // Display the error to the user
                var msg = 'Error (' + status + '): ' + thrown + '\n\n'
                    + 'Status: ' + status + '\n'
                    + 'State: ' + req.readyState + '\n'
                    + 'Status: ' + req.status + '\n'
                    + 'Data: ' + req.responseText;
                $.debug.log('process_queue request failed: ' + msg);

                // If we get the 403 state, chances are the user logged out during a request.  Ignore it.
                if (req.readyState == 4 && req.status == 403) {
                    $.debug.log('process_queue error ignored due to potential logout.');
                    return;
                }

                alert('An error occurred during response processing.\n\n' + msg);
            }
        });
    }

    function process_response_data(queueSummary) {
        var total = 0; // Integer to tally all requests.
        var hasHit = false; // Boolean value for a Solicited Hit message.
        var hasGeneral = false; // Boolean value for Solicited message.
        var hasConfirmation = false; // Boolean value for Hit Confirmation Request/Response.
        // Boolean value to ensure responses were processed. Failsafe for 'normal' tone being played with every processed queue.
        var hasProcessed = false;

        // Iterate through each queueSummaryKey in the queueSummary.
        for (var queueSummaryKey in queueSummary) {
            if (queueSummaryKey == 'requests' && queueSummary[queueSummaryKey] != null) {
                for (var index = 0; index < queueSummary[queueSummaryKey].length; ++index) {
                    var request = queueSummary[queueSummaryKey][index];
                    // Addition assignment to total.
                    total += request.newResponses;
                    // TODO: This event is poorly named; it's actually called anytime there is an update to a request bucket
                    $.event.trigger('client_response', request);//[newRequests, newResponses, generalResponse, requests]);
                }
            }
            if (queueSummaryKey == "response" && queueSummary[queueSummaryKey] != null) {
                // Set queueSummary
                var responseData = queueSummary[queueSummaryKey];
                // Map through returned array(s)
                responseData.map(function (val, index) {
                    if (val === null || typeof val !== 'object') {
                        return;
                    }
                    // Nested map to iterate through all responseData values.
                    Object.keys(val).map(function (responseVal) {
                        if (responseVal == 'class' && !hasConfirmation) {
                            // Set hasConfirmation if specific condititions are met.
                            hasConfirmation = responseData[index][responseVal] == 'HitConfirmationRequest' ||
                                responseData[index][responseVal] == 'HitConfirmationResponse'
                                ? true : false;
                        }
                        else if (responseVal == 'hit' && !hasHit) {
                            // Set hasHit if specific condititions are met.
                            hasHit = responseData[index][responseVal]['Detected'] == '1' ? true : false;
                        }
                        else if (responseVal == 'request' && !hasGeneral) {
                            // Set hasGeneral if specific condititions are met.
                            if (responseData[index][responseVal]['Id']) {
                                hasGeneral = parseInt(responseData[index][responseVal]['Id']) > 0 ? true : false;
                            } else {
                                hasGeneral = false;
                            }
                        }
                    });
                });
                hasProcessed = true;
            }
        }

        $.event.trigger('client_response_end');

        if (queueSummary.prefill.length > 0) {
            var prefill = queueSummary.prefill[0];
            if (prefill.formId)
                $.event.trigger('prefill_request', [queueSummary.prefill[0].prefillId, queueSummary.prefill[0].formId]);
        }

        if (total > 0) {
            // Check local storage for type. 
            var type = window.localStorage.getItem('type');
            // Switch statement in priority order to set proper type if a new message or messages has been processed.
            if (hasProcessed) {
                switch (true) {
                    case hasConfirmation:
                        type = 'confirmation';
                        break;
                    case hasHit:
                        type = 'hit';
                        break;
                    case hasGeneral:
                        type = 'general';
                        break;
                    default:
                        type = 'normal';
                }
                // Set local storage type.
                window.localStorage.setItem('type', type);
            }
            // Check if type is available; if not then set type to normal.
            type = window.localStorage.getItem('type') || 'normal';

            // If we're not on the responses tab, update the responses tab background color to indicate the type of
            // response received.
            var $responseTab = $('#new_alert').parents('li:first');
            if (!$responseTab.hasClass('current')) {
                if (type !== 'general')
                    $('#new_alert').parents('li:first').addClass(type);
            }

            $.debug.log('Playing notification tone: ', type);
            loop = !$.clips.focused(); // only loop if the window isn't active.
            $.audio.play(type, loop);
        }

        $.clips.newResponseCount(total, type);
    }

    $.clips = {
        available: function(connected) {
            // Initial connected argument comes once the websocket connection is established
            // Global _connectStatus is set.
            _connectStatus = connected;
            return _connectStatus;
        },

        // function to connect to the websocket
        socketConnect: function () {
            function connect(address) { 
                // Create webSocket connection
                webSocket = new WebSocket(address);
                // Connection opened
                webSocket.onopen = function() {
                    // Send message to socket with verification credentials
                    webSocket.send(
                        JSON.stringify({
                            "clientIp": hostUrl.host,
                            "location": hostUrl.pathname,
                        })
                    );
                }
                // Listening for messages from socket
                // TODO needs to be wired up to receive messages from connectCIC
                // Currently works with test message
                webSocket.onmessage = function(msg) {
                    if (msg.data == "New Transaction Received") {
                        $.clips._onResponse();
                    }
                }
                // Socket closed
                // Socket attempts to reconnect if disconnected
                webSocket.onclose = function() {
                    disconnects++;
                    var retry = Math.pow(2, disconnects);
                    if (retry > retryMax) {
                        disconnects--;
                        retry = retryMax;
                    }
                    setTimeout(function() {
                        connect(hostSocketAddress);
                    }, retry * 100);
                }
            }
            connect(hostSocketAddress);
            return webSocket;
        },

        focused: function () {
            return isActive;
        },

        // version: function () {
        //     if (!this.available())
        //         return null;
        //     return ClipsClient.GetDllVersion();
        // },

        computerName: function () {
            // Getting computer name from login and stored into the clientState
            clientState.computerName = $("#computer_name").text();
            return clientState.computerName;
        },

        idlePeriod: function (period) {
            idlePeriod = period;
            $.debug.log('Setting idle period:', period, 'minutes');
            this.resetIdle();
        },

        resetIdle: function () {
            $.debug.log('Resetting user idle timer.');

            if (idleId != null) {
                clearTimeout(idleId);
                idleId = null;
            }

            if (idlePeriod > 0)
                idleId = setTimeout(function () { $.clips._onIdle(); }, idlePeriod * 60 * 1000);
        },

        start: function () {
            if (!this.available(_connectStatus))
                return false;

            // Attach to the ClipsClient
            $.debug.log('Starting clips client.');
            //ClipsClient.Ready();

            // Start a timer to process queued events in the event handler.  Calls the global processQueue
            $.debug.log('Starting event processing timer.');
            processId = setInterval(function () {
                $.clips.processQueue();
            }, 1000);

            $.event.trigger('client_attached');

            return true;
        },

        stop: function () {
            $.debug.log('Stopping event processing timer.');
            if (processId != null) {
                clearInterval(processId);
                processId = null;
            }

            if (this.available(_connectStatus)) {
                $.debug.log('Detaching from clips client.');
                //ClipsClient.Detach();
            }

            $.event.trigger('client_detached');
        },

        unload: function () {
            this.cache('client_state', clientState);
            this.stop();
        },

        processQueue: function () {
            //$.debug.log('Processing queued clips client events.');

            if (!this.available(_connectStatus)) {
                $.debug.log('Aborting event processing.');
                this.stop();
                return;
            }

            $.event.trigger('client_process_queue');
            // ClipsClient.ProcessEvents();
            $.event.trigger('client_process_queue_done');
        },

        logout: function () {
            if (!this.available(_connectStatus))
                return;

            $.debug.log('Issuing clips client logout.');

            reset_state();
            //ClipsClient.Logout();
        },

        startSession: function (agencyId, userName, ipAddress, computerName) {
            if (!this.available(_connectStatus))
                return;

            // Read the computer name from our wrapper function.  We'll pass the
            // result to the ActiveX control's beginAuthenticate call.
            //var computerName = $.clips.computerName();

            reset_state(true);
            clientState.authentication = {
                agencyId: agencyId,
                userName: userName,
                ipAddress: ipAddress,
                computerName: computerName
            };

            $.debug.log('Starting clips client session (', agencyId, userName, computerName, ipAddress, ')');

            // Backwards compatability fix for old versions of the ActiveX control where
            // computerName could not be passed.
            // if (typeof ClipsClient.beginAuthenticate2 != 'undefined')
            //     ClipsClient.beginAuthenticate2(agencyId, userName, computerName, ipAddress);
            // else
            //     ClipsClient.beginAuthenticate(agencyId, userName, ipAddress);
            //$.clips._onLoginResponse(agencyId, deviceId, userId, flags);
            $.clips._onLoginResponse();
        },

        inSession: function () {
            return clientState.authentication != null;
        },

        cache: function (key, value) {
            var cache = localStorage.getItem(key);
            if (typeof value == 'undefined') {
                if (cache != null) {
                    return JSON.parse(cache);
                }
                return null
            }
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        },

        newResponseCount: function (count, type) {
            if (typeof count != 'undefined') {
                $newCount = $('#new_count');
                $newCount.text(count);
                var $responseLink = $('#GeneralResponseLink');
                var $responseTab = $('#navmenu ul:first li:first');

                if (count > 0) {
                    // Checks if the first element of the <ul> does not have the 'current' class, if the 'confirmation' 
                    // class exists on the first element of the <ul>.  
                    if (!$responseTab.hasClass('current') && $responseTab.hasClass('confirmation')) {
                        // Add the alert to the href tag if the user is not on the Responses tab.
                        $('.confirmation a:first').addClass('confirmationAlert');
                        $('.confirmation').addClass('confirmationAlert');
                    }
                    // Check the message type and check if the new response count is at zero.
                    if (type == 'confirmation' && $responseLink.attr('newresponsecount') != 0) {
                        // Add the alert to the Unsolicited Messages tab.
                        $responseLink.addClass('hit_confirmation');
                        if ($responseLink.hasClass('hit_alert')) {
                            $responseLink.removeClass('hit_alert');
                        }
                    }
                    $newCount.css('color', '#f00');
                    $('#new_alert img').prop('src', '/img/rotate.gif');
                }
                else {
                    $newCount.css('color', '#000000');
                    $('#new_alert img').prop('src', '/img/no_rotate.gif');
                }

                clientState.newResponses = count;
                this._flash(0);
            }

            return clientState.newResponses;
        },

        _flash: function (flash) {
            document.title = 'CLIPS';
            if (this.focused() || clientState.newResponses <= 0)
                return;

            if ((flash % 2) == 0)
                document.title = 'You have new responses - CLIPS';

            setTimeout(function () { $.clips._flash(flash + 1); }, 2500);
        },

        _onIdle: function () {
            $.debug.log('Logging off idle user');

            this.logout();
            window.location = '/users/logout/reason:You have been logged out due to inactivity.';
        },

        _onError: function (errorCode, message) {
            $.debug.log('Triggering client_error event (', errorCode, message, ')');
            $.event.trigger('client_error', [errorCode, message]);

            var auth = clientState.authentication;
            var enableRestart = clientState.enableRestart;

            $.debug.log('Restarting clips client session.');
            this.logout();
            clientState.enableRestart = enableRestart;
            if (enableRestart)
                this.startSession(auth.agencyId, auth.userName, auth.computerName, auth.ipAddress);
        },

        _onLoginResponse: function (agencyId, deviceId, userId, flags) {
        //_onLoginResponse: function () {
            //$.debug.log('Received session start response (', agencyId, deviceId, userId, flags, ')');

            // Now that we've successfully logged in, allow automatic restarting.
            clientState.enableRestart = true;

            //Setup our current session data.
            clientState.session = {
                agencyId: agencyId,
                deviceId: deviceId,
                userId: userId
            };

            // Notify anyone listening for a logon response.
            $.debug.log('Triggering client_login event ( <session_data> )');
            $.event.trigger('client_login', [clientState.session]);
            //$.event.trigger('client_login');
        },

        _onResponse: function () {
            $.debug.log('Received response notification');

            $.event.trigger('client_response_notification');

            // If we're already processing notifications, don't process more.
            if (++clientState.notifications > 1)
                return;

            // Processing of responses is handled from process_responses. It will decrement clientState.notifications
            // when it receives a processing response from the server.
            process_responses();
        },

        _onStatus: function (status, newResponses) {
            $.debug.log('Triggering client_status event (', status, newResponses, ')');
            $.event.trigger('client_status', [status, newResponses]);
        }
    };

    $(document)
        .bind('keyup', function (event) {
            $.clips.resetIdle();
            return false;
        })
        .bind('mouseup', function (event) {
            $.clips.resetIdle();
            return false;
        })
        .bind('focusin', function (event) {
            $.debug.log('Document focusin.');
            isActive = true;
            $.clips.resetIdle();
            return false;
        })
        .bind('focusout', function (event) {
            $.debug.log('Document focusout.');
            isActive = false;
            return false;
        })
        /*
        .bind('client_error', function(event, code, message) {
        })
        .bind('client_response', function() {
        })
        .bind('client_status', function(status, newResponses) {
        });
        */
        .bind('prefill_request', function (event, prefillId, prefillForm) {
            // Navigate to the requested form and load the prefill request
            var url = '/forms/index/' + prefillForm + '/prefillId:' + prefillId;
            $.debug.log('Redirecting to load form: ' + url);
            window.location = url;
        })
        .ready(function () {
            // Establish websocket
            const socket = $.clips.socketConnect();
            // Event listener to update availability of CLIPS once connection is established
            socket.addEventListener('message', function (event) {
                if (event.data == 'Connection Established'); {
                    $.clips.available(true);
                }
            });
            // Fix browser limitations not supporting :after or content CSS
            $('.required label').append(function () {
                if (!$(this).parent().hasClass('checkbox'))
                    $(this).append('<span style="color: #f00;">*</span>');
            });
            //if ($.clips.available(_connectStatus)) {
                // Bind an event to the logout link to issue a logout request to the ClipsClient.  We don't prevent the
                // default action because we still want to redirect to the logout page.
                $('#logout_link').bind('click', function (event) {
                    if ($.clips.available(_connectStatus))
                        $.clips.logout();
                });

                // Populate the CLIPS ActiveX Control version number in the copyright block.
                // $('#clips_client_version').text($.clips.version());

                // Bind the ActiveX callbacks
                $.debug.log('Binding clips client callbacks.');
                //ClipsClient.AddErrorHandler(document, '__onClientError');
                //ClipsClient.AddLoginResponseHandler(document, '__onClientLoginResponse');
                //ClipsClient.AddResponseNotificationHandler(document, '__onClientResponse');
                //ClipsClient.AddStatusHandler(document, '__onClientStatus');

                // Load the current client state
                $.debug.log('Loading cached client state.');
                var state = $.clips.cache('client_state');
                if (state != null)
                    clientState = state;

                // This will force the Responses tab to show the correct new response count
                if (clientState.newResponses > 0)
                    $.clips.newResponseCount(clientState.newResponses);

                // HACK: Prime queued responses received during page transition
                clientState.notifications = 0;
                $.clips._onResponse();
                // Start the CLIPS Client
                $.clips.start();
            // }
            // else
            //     $('#clips_client_version').text('Unknown');

            $.debug.log('clips.js ready');
        });

    // Handle some ClipsClient things when the browser is unloaded.
    $(window)
        .unload(function () {
            if (!$.clips.available(_connectStatus))
                return;
            $.debug.log('Handling page unload.');
            $.clips.unload();
        });
})(jQuery);

// ActiveX Callbacks which simply trigger jQuery events.  These methods must be global or the reference count to them
// drops to zero (the ActiveX control remembers the method name) and the ActiveX control will fail to call them.  Do not
// call these directly.
// function __onClientError(errorCode, message) {
//     $.debug.log('__onClientError(', errorCode, message, ')');
//     $.clips._onError(errorCode, message);
// }

// function __onClientLoginResponse(agencyId, deviceId, userId, flags) {
//     $.debug.log('__onClientLoginResponse(', agencyId, deviceId, userId, flags, ')');
//     $.clips._onLoginResponse(agencyId, deviceId, userId, flags);
// }

// function __onClientResponse() {
//     $.debug.log('__onClientResponse()');
//     $.clips._onResponse();
// }

// function __onClientStatus(status, newResponses) {
//     $.debug.log('__onClientStatus(', status, newResponses, ')');
//     $.clips._onStatus(status, newResponses);
// }
