var url

function scan_device(device_id) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id=' + device_id
	$('#r_' + device_id).find('i').addClass('fa-spin');
	$.getJSON(url, function(data) {
		type      = data.type;
		device_id = data.device_id;
		content   = data.content;
		$('#r_' + device_id).find('i').removeClass('fa-spin');
		$('#response').hide().html(content).dialog({ 'width': 800 });
	});
}

function site_scan(site_id) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=site_scan&site_id=' + site_id;
	$('#r_' + site_id).find('i').addClass('fa-spin');
	$.getJSON(url, function(data) {
		var type      = data.type;
		var site_id   = data.site_id
		var content   = data.content;
		$('#r_' + site_id).find('i').removeClass('fa-spin');
		$('#response').hide().html(content).dialog({ 'width': 800 });
	});
}

function scan_device_interface(device_id, ifIndex) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id=' + device_id + '&ifIndex=' + ifIndex;
	$('#r_' + device_id + '_' + ifIndex).find('i').addClass('fa-spin');

	$.getJSON(url, function(data) {
		var type      = data.type;
		var device_id = data.device_id;
		var ifIndex   = data.ifIndex;
		var content   = data.content;
		$('#r_' + device_id + '_' + ifIndex).find('i').removeClass('fa-spin');
		$('#response').hide().html(content).dialog({ 'width': 800 });
	});
}

function clearScanResults() {
	$('#response').html('');
}

function disable_device(device_id) {
	url=urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=disable&device_id=' + device_id;
	$.getJSON(url, function(data) {
		var type      = data.type;
		var device_id = data.device_id;
		var content   = data.content;
		$('#line_' + device_id).html(content);
	});
}

function enable_device(device_id) {
	url=urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=enable&device_id=' + device_id;
	$.getJSON(url, function(data) {
		var type      = data.type;
		var device_id = data.device_id;
		var content   = data.content;
		$('#line_' + device_id).html(content);
	});
}
