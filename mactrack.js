var xmlHttp
var url

function applyReportFilterChange(objForm) {
	strURL = '?report=' + objForm.report.value;
	document.location = strURL;
}

function applySiteFilterChange(objForm) {
	strURL = '?report=sites';
	if (objForm.hidden_device_type_id) {
		strURL = strURL + '&device_type_id=-1';
		strURL = strURL + '&site_id=-1';
	}else{
		strURL = strURL + '&device_type_id=' + objForm.device_type_id.value;
		strURL = strURL + '&site_id=' + objForm.site_id.value;
	}
	strURL = strURL + '&detail=' + objForm.detail.checked;
	strURL = strURL + '&filter=' + objForm.filter.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	document.location = strURL;
}

function applyIPsFilterChange(objForm) {
	strURL = '?report=ips';
	strURL = strURL + '&site_id=' + objForm.site_id.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	document.location = strURL;
}

function applyDeviceFilterChange(objForm) {
	strURL = '?report=devices';
	strURL = strURL + '&site_id=' + objForm.site_id.value;
	strURL = strURL + '&status=' + objForm.status.value;
	strURL = strURL + '&type_id=' + objForm.type_id.value;
	strURL = strURL + '&device_type_id=' + objForm.device_type_id.value;
	strURL = strURL + '&filter=' + objForm.filter.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	document.location = strURL;
}

function applyMacFilterChange(objForm) {
	strURL = '?report=macs';
	strURL = strURL + '&site_id=' + objForm.site_id.value;
	strURL = strURL + '&device_id=' + objForm.device_id.value;
	strURL = strURL + '&scan_date=' + objForm.scan_date.value;
	strURL = strURL + '&rows=' + objForm.rows.value;
	strURL = strURL + '&mac_filter_type_id=' + objForm.mac_filter_type_id.value;
	strURL = strURL + '&mac_filter=' + objForm.mac_filter.value;
	strURL = strURL + '&authorized=' + objForm.authorized.value;
	strURL = strURL + '&filter=' + objForm.filter.value;
	strURL = strURL + '&vlan=' + objForm.vlan.value;
	strURL = strURL + '&ip_filter_type_id=' + objForm.ip_filter_type_id.value;
	strURL = strURL + '&ip_filter=' + objForm.ip_filter.value;
	document.location = strURL;
}

function getfromserver(baseurl) {
	xmlHttp=GetXmlHttpObject()
	if (xmlHttp==null) {
		alert ("Get Firefox!")
		return
	}

	xmlHttp.onreadystatechange=stateChanged
	xmlHttp.open("GET",baseurl,true)
	xmlHttp.send(null)
}

function scan_device(device_id) {
	url="mactrack_ajax_admin.php?action=rescan&device_id="+device_id
	document.getElementById("r_"+device_id).src="images/view_busy.gif"
	getfromserver(url)
}

function clearScanResults() {
	document.getElementById("response").innerHTML="<span/>";
}

function disable_device(device_id) {
	url="mactrack_ajax_admin.php?action=disable&device_id="+device_id
	getfromserver(url)
}

function enable_device(device_id) {
	url="mactrack_ajax_admin.php?action=enable&device_id="+device_id
	getfromserver(url)
}

function lock_device(device_id) {
	url="mactrack_ajax_admin.php?action=lock&device_id="+device_id
	getfromserver(url)
}

function unlock_device(device_id) {
	url="mactrack_ajax_admin.php?action=unlock&device_id="+device_id
	getfromserver(url)
}

function purge_device(device_id) {
	url="mactrack_ajax_admin.php?action=purge&device_id="+device_id
	getfromserver(url)
}

function stateChanged() {
	if (xmlHttp.readyState==4 || xmlHttp.readyState=="complete") {
		reply     = xmlHttp.responseText
		reply     = reply.split("!!!!")
		type      = reply[0]
		device_id = reply[1]
		color     = reply[2]
		content   = reply[3]

		if ((type == "enable") || (type == "disable") || (type == "lock") || (type == "unlock")) {
			document.getElementById("row_"+device_id).innerHTML=content
			document.getElementById("row_"+device_id).style.backgroundColor="#"+color
		} else if (type == "purge") {
			document.view_mactrack.submit();
		} else if (type == "rescan") {
			document.getElementById("r_"+device_id).src="images/rescan_device.gif"
			document.getElementById("response").innerHTML=color
		}
	}
}

function GetXmlHttpObject() {
	var objXMLHttp=null
	if (window.XMLHttpRequest) {
		objXMLHttp=new XMLHttpRequest()
	}
	else if (window.ActiveXObject) {
		objXMLHttp=new ActiveXObject("Microsoft.XMLHTTP")
	}
	return objXMLHttp
}
