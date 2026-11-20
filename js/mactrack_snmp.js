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

/* Reorder and remove actions change data, so they are sent as POST with the CSRF token */
$(function() {
	$(document).on('click', '.mactrackSnmpItemAction', function(event) {
		event.preventDefault();

		var action = $(this).attr('data-action');
		var itemId = $(this).attr('data-item-id');
		var snmpId = $(this).attr('data-snmp-id');
		var base   = urlPath + 'plugins/mactrack/mactrack_snmp.php';

		$.post(base + '?action=' + encodeURIComponent(action) + '&item_id=' + encodeURIComponent(itemId) + '&id=' + encodeURIComponent(snmpId), { __csrf_magic: csrfMagicToken }).done(function() {
			loadPageNoHeader(base + '?header=false&action=edit&id=' + encodeURIComponent(snmpId));
		}).fail(function(data) {
			getPresentHTTPError(data);
		});
	});
});
