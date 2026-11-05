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

/* Upper bound (ms) for a rescan/site scan request. If the subprocess stalls
   the request fails with a timeout instead of leaving the action icon
   spinning forever. */
var mactrackScanTimeout = 300000;

/* Render the JSON scan results in the shared response dialog. */
function mactrackShowScanResults(data) {
	var content = (data && data.content) ? data.content : '<em>No scan results were returned.</em>';
	$('#response').hide().html(content).dialog({ width: 800, maxHeight: 500 });
}

/* Surface a short Cacti toast using the 1.2.x session-message mechanism
   (what raise_message_javascript() emits). Pass isError to show it at the
   error level so a failure is never reported as a success. */
function mactrackScanToast(message, isError) {
	if (!message || typeof displayMessages !== 'function') {
		return;
	}

	var level = isError
		? ((typeof MESSAGE_LEVEL_ERROR !== 'undefined') ? MESSAGE_LEVEL_ERROR : 3)
		: ((typeof MESSAGE_LEVEL_INFO !== 'undefined') ? MESSAGE_LEVEL_INFO : 1);

	window.sessionMessage = {
		message: message,
		level:   level
	};

	displayMessages();
}

/* Run an admin scan action, spinning the passed icon until the request
   settles (success, failure or timeout) so it can never spin indefinitely. */
function mactrackRunScan(url, icon) {
	icon.addClass('fa-spin');

	return $.ajax({
		type:     'POST',
		url:      url,
		data:     { __csrf_magic: csrfMagicToken },
		dataType: 'json',
		timeout:  mactrackScanTimeout
	}).done(function(data) {
		mactrackShowScanResults(data);

		// A nonzero subprocess exit comes back as HTTP 200 with an error flag
		// and its detail already in the results dialog, so only toast real
		// successes rather than reporting a failure as completed.
		if (data && !data.error) {
			mactrackScanToast(data.message, false);
		}
	}).fail(function(data) {
		// getPresentHTTPError() renders nothing when the failure carries no
		// response body (e.g. the request timed out), which would otherwise
		// clear the spinner with no feedback, so report those explicitly.
		if (data && data.responseText) {
			getPresentHTTPError(data);
		} else {
			mactrackScanToast('The scan request timed out or did not complete.', true);
		}
	}).always(function() {
		icon.removeClass('fa-spin');
	});
}

function scan_device(device_id) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id=' + device_id;
	mactrackRunScan(url, $('#r_' + device_id).find('i'));
}

function site_scan(site_id) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=site_scan&site_id=' + site_id;
	mactrackRunScan(url, $('#r_' + site_id).find('i'));
}

function scan_device_interface(device_id, ifIndex) {
	url = urlPath + 'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id=' + device_id + '&ifIndex=' + ifIndex;
	mactrackRunScan(url, $('#r_' + device_id + '_' + ifIndex).find('i'));
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
	$(document).on('click', '.mactrackRescanDevice', function(event) {
		event.preventDefault();
		scan_device($(this).attr('data-device-id'));
	});

	$(document).on('click', '.mactrackReturn', function() {
		cactiReturnTo();
	});
});
