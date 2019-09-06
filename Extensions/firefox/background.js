// connects to the firefox native messaging app
browser.runtime.onMessage.addListener(
    function (request) {
        if (request.type == 'getComputerName') {
            // connect to the native app //
            var port = browser.runtime.connectNative("clips.native.messaging.host");
            // send a message to the native app //
            port.postMessage(request.type);
            // listen for incoming messages from the native app //
            port.onMessage.addListener(function (request) {
                browser.tabs.query({active: true, currentWindow: true}, function(tabs){
                    browser.tabs.sendMessage(tabs[0].id, {name: request.data}, function(response) {}); 
                });
            })
            // when the port disconnects  //
            port.onDisconnect.addListener(function (error) {
                console.log("last error:" + browser.runtime.lastError);
            })
        }
    }
)