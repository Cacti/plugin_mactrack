/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

var url

function scan_device(device_id) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id=' + device_id
	$('#r_' + device_id).find('i').addClass('fa-spin');
	$.post(url, { __csrf_magic: csrfMagicToken }, function(data) {
		type      = data.type;
		device_id = data.device_id;
		content   = data.content;
		$('#r_' + device_id).find('i').removeClass('fa-spin');
		$('#response').hide().html(content).dialog({ width: 800, maxHeight: 500 });
	}, 'json').fail(function(data) {
		$('#r_' + device_id).find('i').removeClass('fa-spin');
		getPresentHTTPError(data);
	});
}

function site_scan(site_id) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=site_scan&site_id=' + site_id;
	$('#r_' + site_id).find('i').addClass('fa-spin');
	$.post(url, { __csrf_magic: csrfMagicToken }, function(data) {
		var type      = data.type;
		var site_id   = data.site_id
		var content   = data.content;
		$('#r_' + site_id).find('i').removeClass('fa-spin');
		$('#response').hide().html(content).dialog({ width: 800, maxHeight: 500 });
	}, 'json').fail(function(data) {
		$('#r_' + site_id).find('i').removeClass('fa-spin');
		getPresentHTTPError(data);
	});
}

function scan_device_interface(device_id, ifIndex) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id=' + device_id + '&ifIndex=' + ifIndex;
	$('#r_' + device_id + '_' + ifIndex).find('i').addClass('fa-spin');

	$.post(url, { __csrf_magic: csrfMagicToken }, function(data) {
		var type      = data.type;
		var device_id = data.device_id;
		var ifIndex   = data.ifIndex;
		var content   = data.content;
		$('#r_' + device_id + '_' + ifIndex).find('i').removeClass('fa-spin');
		$('#response').hide().html(content).dialog({ width: 800, maxHeight: 500 });
	}, 'json').fail(function(data) {
		$('#r_' + device_id + '_' + ifIndex).find('i').removeClass('fa-spin');
		getPresentHTTPError(data);
	});
}

function clearScanResults() {
	$('#response').html('');
}

function disable_device(device_id) {
	url=urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=disable&device_id=' + device_id;
	$.post(url, { __csrf_magic: csrfMagicToken }, function(data) {
		var type      = data.type;
		var device_id = data.device_id;
		var content   = data.content;
		$('#line_' + device_id).html(content);
	}, 'json').fail(function(data) {
		getPresentHTTPError(data);
	});
}

function enable_device(device_id) {
	url=urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=enable&device_id=' + device_id;
	$.post(url, { __csrf_magic: csrfMagicToken }, function(data) {
		var type      = data.type;
		var device_id = data.device_id;
		var content   = data.content;
		$('#line_' + device_id).html(content);
	}, 'json').fail(function(data) {
		getPresentHTTPError(data);
	});
}

/* Delegated handlers survive AJAX page loads and rows re-rendered by enable/disable */
$(function() {
	$(document).on('click', '.mactrackRescanDevice', function() {
		scan_device($(this).attr('data-device-id'));
	});

	$(document).on('click', '.mactrackReturn', function() {
		cactiReturnTo();
	});
});
