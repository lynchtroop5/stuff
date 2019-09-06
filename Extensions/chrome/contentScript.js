if (document.getElementById('login_container')) {
	chrome.runtime.sendMessage({
		type: 'getComputerName'
	});
	chrome.runtime.onMessage.addListener(function(message,sender,sendResponse){
		document.getElementById('computer_name').textContent = message.name;
	});
}