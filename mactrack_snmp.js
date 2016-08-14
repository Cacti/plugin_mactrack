function changeSNMPVersion() {
	snmp_version = document.getElementById('snmp_version').value;
	switch (snmp_version) {
	case "0":
		setSNMP("None");
		break;
	case "1":
	case "2":
		setSNMP("v1v2");
		break;
	case "3":
		setSNMP("v3");
		break;
	}
}

function setSNMP(snmp_type) {
	switch (snmp_type) {
	case "None":
		document.getElementById('row_snmp_username').style.display = "none";
		document.getElementById('row_snmp_password').style.display = "none";
		document.getElementById('row_snmp_readstring').style.display = "none";
		document.getElementById('row_snmp_auth_protocol').style.display = "none";
		document.getElementById('row_snmp_priv_passphrase').style.display = "none";
		document.getElementById('row_snmp_priv_protocol').style.display = "none";
		document.getElementById('row_snmp_context').style.display = "none";
		document.getElementById('row_snmp_port').style.display = "none";
		document.getElementById('row_snmp_timeout').style.display = "none";
		document.getElementById('row_max_oids').style.display = "none";

		break;
	case "v1v2":
		document.getElementById('row_snmp_username').style.display = "none";
		document.getElementById('row_snmp_password').style.display = "none";
		document.getElementById('row_snmp_readstring').style.display = "";
		document.getElementById('row_snmp_auth_protocol').style.display = "none";
		document.getElementById('row_snmp_priv_passphrase').style.display = "none";
		document.getElementById('row_snmp_priv_protocol').style.display = "none";
		document.getElementById('row_snmp_context').style.display = "none";
		document.getElementById('row_snmp_port').style.display = "";
		document.getElementById('row_snmp_timeout').style.display = "";
		document.getElementById('row_max_oids').style.display = "";

		break;
	case "v3":
		document.getElementById('row_snmp_username').style.display = "";
		document.getElementById('row_snmp_password').style.display = "";
		document.getElementById('row_snmp_readstring').style.display = "none";
		document.getElementById('row_snmp_auth_protocol').style.display = "";
		document.getElementById('row_snmp_priv_passphrase').style.display = "";
		document.getElementById('row_snmp_priv_protocol').style.display = "";
		document.getElementById('row_snmp_context').style.display = "";
		document.getElementById('row_snmp_port').style.display = "";
		document.getElementById('row_snmp_timeout').style.display = "";
		document.getElementById('row_max_oids').style.display = "";

		break;
	}
}

function addLoadEvent(func) {
	var oldonload = window.onload;
	if (typeof window.onload != 'function') {
		window.onload = func;
	} else {
		window.onload = function() {
			if (oldonload) {
				oldonload();
			}
			func();
		}
	}
}

addLoadEvent(changeSNMPVersion);
