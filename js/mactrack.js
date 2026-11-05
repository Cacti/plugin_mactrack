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

/* Show the detailed scan output in the shared response dialog. The results
   are wrapped in a scrollable container so long output gets a vertical
   scrollbar instead of overflowing the dialog. */
function mactrackShowScanResults(content) {
	var body = '<div class="mactrackScanResults">' + (content || '<em>No scan results were returned.</em>') + '</div>';

	$('#response').hide().html(body).dialog({ width: 800, maxHeight: 500 });
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
		mactrackShowScanResults(data ? data.content : '');
	}).fail(function(data) {
		// getPresentHTTPError() renders nothing when the failure carries no
		// response body (e.g. the request timed out), so show those in the
		// same results dialog instead of clearing the spinner with no feedback.
		if (data && data.responseText) {
			getPresentHTTPError(data);
		} else {
			mactrackShowScanResults('<p class="textError">The scan request timed out or did not complete.</p>');
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
