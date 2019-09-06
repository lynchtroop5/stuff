if (document.getElementById('login_container')) {
	browser.runtime.sendMessage({
		type: 'getComputerName'
	});
	browser.runtime.onMessage.addListener(function(message,sender,sendResponse){
		document.getElementById('computer_name').innerHTML = message.name;
	});
}