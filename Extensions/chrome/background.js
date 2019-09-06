// connects to the chrome native messaging app
chrome.runtime.onMessage.addListener(
    function (request) {
        if (request.type == 'getComputerName') {
            var computerName;
            // connect to the native app //
            var port = chrome.runtime.connectNative("clips.native.messaging.host");
            // send a message to the native app //
            port.postMessage(request.type);
            // listen for incoming messages from the native app //
            port.onMessage.addListener(function (request) {
                chrome.tabs.query({active: true, currentWindow: true}, function(tabs){
                    chrome.tabs.sendMessage(tabs[0].id, {name: request.data}, function(response) {}); 
                });
            });
            // when the port disconnects  //
            port.onDisconnect.addListener(function (error) {
                console.log("last error:" + chrome.runtime.lastError.message);
            })
        }
    }
)