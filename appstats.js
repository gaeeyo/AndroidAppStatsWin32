/*
  > cscript.js appstats.js <email> <password>

*/

var MAIN_URL = "http://market.android.com/publish/Home";
var LOGIN_URL = "https://www.google.com/accounts/ServiceLogin";
var INSERT_SCRIPT_PATH = 'browser_script.js';

main(WScript.Arguments);

function main(args) {
	if (args.length != 2) {
		WScript.Echo('cscript ' + WScript.ScriptName + ' <email> <password>');
		return;
	}
	var email = args(0);
	var pass = args(1);
	
	getStats(email, pass);
	return;
}


function getStats(email, pass) {

	var ie = WScript.CreateObject("InternetExplorer.Application")
	ie.Navigate(MAIN_URL);
	ie.Visible = true;
	
	try {

		var loginCount = 0;
		var mainCount = 0;

		for (; true; WScript.Sleep(1000)) {
			//log('ReadyState:' + ie.ReadyState);
			if (ie.Busy) continue;
			if (ie.ReadyState != 4) continue;
			
			var url = ie.LocationURL;
			if (url.indexOf(LOGIN_URL) == 0) {
				if (loginCount++ == 0) login(ie, email, pass);
			}
			if (url.indexOf(MAIN_URL) == 0) {
				if (mainCount++ == 0) insertScript(ie);
			}

			if (ie.Document.Script.apps != undefined) {
				WScript.Sleep(2000);
				complete(ie.Document.Script.apps);
				//debug_ie(ie);
				break;
			}
		}
	}
	finally {
		ie.Quit();
	}
}

function complete(apps) {
	log('Complete');
	for (var x in apps) {
		var app = apps[x];
		var text = [];
		log('-----');
		for (var key in app) {
			log(key + " = " + app[key]);
		}
	}
}

function insertScript(ie) {
	WScript.Sleep(2000);
	log('Insert script');
	var scriptTag = ie.Document.createElement('script');
	scriptTag.text = loadTextFile(INSERT_SCRIPT_PATH);
	ie.Document.body.appendChild(scriptTag);
}

function loadTextFile(path) {
	var fso = new ActiveXObject('Scripting.FileSystemObject');
	var ts = fso.OpenTextFile(path, 1);
	return ts.ReadAll();
}

function login(ie, email, pass) {
	log('Login');
	var script = "var f=window.gaia_loginform;" +
	    		"f.Email.value='" + email + "';" +
	    		"f.Passwd.value='" + pass + "';" +
	    		"f.submit();"
	ie.Navigate('javascript:'+ script);
}

function log(msg) {
	WScript.Echo(msg);
}

function debug_ie() {
	var stdIn = WScript.StdIn;

	while (!stdIn.AtEndOfStream) {
		var str = stdIn.ReadLine();
		try {
			WScript.Echo(eval(str));
		} catch (e) {
			WScript.Echo(e.message);
		}
	}
}
