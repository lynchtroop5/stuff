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

var twoFactor = {};

function status(type, text) {
    $target = $('#client_status');

    $target.text(type + ': ' + text);
    if (type == 'Error')
        $target.addClass('error');
    else
        $target.removeClass('error');
}

function setTwoFactor(table) {
    // Make all the computer names lower case.
    $.each(table, function(agencyId, computers) {
        $.each(computers, function(index, computer) {
            table[agencyId][index] = computer.toLowerCase();
        });
    });

    twoFactor = table;
}

function toggleTwoFactorField()
{
    var visible = false;
    agencyId = $('#AgencyAgencyId').val();
    if (typeof twoFactor[agencyId] != 'undefined' && twoFactor[agencyId]) {
        var computers = twoFactor[agencyId];

        var ipAddress = $('#computer_ip').text();
        var computerName = $.clips.computerName().toLowerCase();

        visible = (computers.indexOf(computerName) >= 0 
                    || computers.indexOf(ipAddress) >= 0
                    || computers.indexOf('*') >= 0);
    }
    
    if (visible)
        $('#two_factor').show();
    else
        $('#two_factor').hide();
}

// Login disabled if websocket is not connected
function disableLogin() {
    $('#UserDoLoginForm input[type="submit"]').attr('disabled', 'disabled');
    $('#UserDoLoginForm input').attr('readonly', 'readonly');
}

// Enables login once websocket connection is established
function enableLogin() {
    $('#UserDoLoginForm input[type="submit"]').removeAttr('disabled');
    $('#UserDoLoginForm input').removeAttr('readonly');
}


// Function to check if browser is IE
// TODO Need to wire this up once a route for getting computer name in IE is established
function isIE() {
    ua = navigator.userAgent;
    var is_ie = ua.indexOf("MSIE ") > -1 || ua.indexOf("Trident/") > -1;
    return is_ie;
}

$(document).ready(function() {
    // Establishe connection to the websocket
    const socket = $.clips.socketConnect();
    disableLogin();
    // Event listener for once the socket connection is opened. Sets the status of the login process
    socket.addEventListener('open', function (event) {
        status('Status', 'Connecting to CLIPS Server.');
    });
    // Event listener for initial connection message. Enables login for user and sets status to ready
    socket.addEventListener('message', function (event) {
        if (event.data == 'Connection Established'); {
            enableLogin();
            $.clips.available(true);
            status('Status', 'Ready.');
        }
    });
    // Event listener for socket connection error. Will alert the user and disable the login
    socket.addEventListener('error', function (event) {
        disableLogin()
        $.clips.available(false);
        $('#computer_name').text('Unknown');
        status('Error', 'Connection to CLIPS Server failed.');
    });

    // Force a logout if it's logged in.
    if ($.clips.inSession() == true) {
        $.debug.log('Forcing clips client logoff');
        $.clips.logout();
    }

    // Display the computer name and set the endpoint hidden field.
    setTimeout(function() {
        computerName = $.clips.computerName();
        $('#DeviceEndpoint').val(computerName);
    }, 500); 

    // Bind some listeners for events.
    $('#client_status')
        .bind('client_error', function(event, code, message) {
            // If we're not in session, it's because we terminated it.  No need to display an error in this case.
            if ($.clips.inSession() == false) {
                $.debug.log('Error ignored because we\'re not in session.');
                return;
            }

            // Make the login button and fields writable again.
            // $('#UserDoLoginForm input[type="submit"]').removeAttr('disabled');
            // $('#UserDoLoginForm input').removeAttr('readonly');

            status('Error', message + ' (' + code + ')');
        })
        .bind('client_login', function(event, agencyId, userId, deviceId, flags) {
            // TODO: userId of 0 means disabled user-checks server side.  Just keep it in mind.
            $.debug.log('Clips client login response received.  Logging in to website.');

            status('Status', 'Logging in...');
            $("#UserDoLoginForm")[0].submit();
        });

    // Handle agency drop down changes to show/hide the two-factor token field.
    $('#AgencyAgencyId').bind('change', function(event) {
        toggleTwoFactorField();
    });

    // Hijack the login so we can perform the ActiveX authentication before posting to the server.
    $('#UserDoLoginForm input[type="submit"]').bind('click', function(event) {
        event.preventDefault();

        $.debug.log('Pausing website login to authenticate clips client.');
        status('Status', 'Authenticating user...');
        
        var ipAddress = $('#computer_ip').text();
        var agencyId = $('#AgencyAgencyId').val();
        var userName = $('#UserUserName').val();
        var computerName = $('#DeviceEndpoint').val();

        if (!agencyId)
            agencyId = 0;

        // Disable the login button and make the fields readonly.  This prevents users from double-submitting or making
        // changes while authentication is in progress.
        $('#UserDoLoginForm input').attr('readonly', 'readonly');
        $(this).attr('disabled', 'disabled');
        $.clips.startSession(agencyId, userName, ipAddress, computerName);
    });

    // Indicate we're ready to go.
    // status('Status', 'Ready.');
    // $('#UserDoLoginForm input[type="submit"]').removeAttr('disabled');

    $.debug.log('Users/login.js ready.');
});
