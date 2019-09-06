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
    var player = null;
    var audio = null;
    var loopId = null;

    $.audio = {
        initialize: function(config) {
            // Initialize javascript audio method
            player = new Audio();
            audio = config;
        },

        playing: function() {
            return (loopId != null);
        },

        play: function(type, loop) {
            if (!player)
                return;

            if (typeof loop == 'undefined')
                loop = true;
    
            // Now check to make sure that '< None >' was not selected
            // If it is just return. Note: the config.ctp file creates the
            // path with the slash and the selected file name is appended
            if(audio['file'][type].charAt(audio['file'][type].length - 1) == '/') {
                $.debug.log('No file specified');
                return;
			}

            // Make sure the configuration is valid for this type
            if ('file' == "" || typeof audio['file'] == 'undefined' || !audio['file'] ||
                    typeof audio['file'][type] == 'undefined' || !audio['file'][type]) {
                $.debug.log('Unknown audio type not played: ', type);
                return;
            }
            // Set the source of the audio file to the player
            player.src = audio['file'][type];
            // Check the audio player current time. If true then reset to 0 to start over
            if (player.currentTime != 0) {
                player.currentTime = 0;
            }
            player.play();
            // Play the audio and loop it every 2 seconds.
            if (loopId != null)
                clearInterval(loopId);
            if (loop == true) {
                loopId = setInterval(function() { player.play(); }, 2000);
            }
        },

        stop: function() {
            $.debug.log('Stopping currently playing audio.');

            if (loopId != null)
                clearInterval(loopId);

            player.pause();
            loopId = null;
        }
    };
})(jQuery);