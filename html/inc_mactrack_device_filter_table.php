	<script type="text/javascript">
	<!--
	function applyDeviceFilterChange(objForm) {
		strURL = '?site_id=' + objForm.site_id.value;
		strURL = strURL + '&status=' + objForm.status.value;
		strURL = strURL + '&type_id=' + objForm.type_id.value;
		strURL = strURL + '&device_type_id=' + objForm.device_type_id.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		document.location = strURL;
	}

	-->
	</script>
	<tr class="rowAlternate2">
		<form name="form_mactrack_devices">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="site_id" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>All</option>
						<option value="-2"<?php if ($_REQUEST["site_id"] == "-2") {?> selected<?php }?>>None</option>
						<?php
						$sites = db_fetch_assoc("select site_id,site_name from mac_track_sites order by site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="'. $site["site_id"] . '"';if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td width="5"></td>
					<td width="20">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="import_x" value="Import">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="50">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="type_id" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
						<option value="-1"<?php if ($_REQUEST["type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<option value="1"<?php if ($_REQUEST["type_id"] == "1") {?> selected<?php }?>>Switch/Hub</option>
						<option value="2"<?php if ($_REQUEST["type_id"] == "2") {?> selected<?php }?>>Switch/Router</option>
						<option value="3"<?php if ($_REQUEST["type_id"] == "3") {?> selected<?php }?>>Router</option>
						</select>
					</td>
					<td width="5"></td>
					<td width="70">
						&nbsp;Sub Type:
					</td>
					<td width="1">
						<select name="device_type_id" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
						<option value="-1"<?php if ($_REQUEST["type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						if ($_REQUEST["type_id"] != -1) {
							$device_types = db_fetch_assoc("SELECT DISTINCT
								mac_track_devices.device_type_id,
								mac_track_device_types.description,
								mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								RIGHT JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
								WHERE device_type='" . $_REQUEST["type_id"] . "'
								ORDER BY mac_track_device_types.description");
						}else{
							$device_types = db_fetch_assoc("SELECT DISTINCT
								mac_track_devices.device_type_id,
								mac_track_device_types.description,
								mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								RIGHT JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
								ORDER BY mac_track_device_types.description;");
						}
						if (sizeof($device_types) > 0) {
						foreach ($device_types as $device_type) {
							if ($device_type["device_type_id"] == 0) {
								$display_text = "Unknown Device Type";
							}else{
								$display_text = $device_type["description"] . " (" . $device_type["sysDescr_match"] . ")";
							}
							print '<option value="' . $device_type["device_type_id"] . '"'; if ($_REQUEST["device_type_id"] == $device_type["device_type_id"]) { print " selected"; } print ">" . $display_text . "</option>";
						}
						}
						?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
    				<td width="50">
						&nbsp;Status:&nbsp;
					</td>
					<td width="1">
						<select name="status" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
							<option value="-1"<?php if ($_REQUEST["status"] == "-1") {?> selected<?php }?>>Any</option>
							<option value="3"<?php if ($_REQUEST["status"] == "3") {?> selected<?php }?>>Up</option>
							<option value="-2"<?php if ($_REQUEST["status"] == "-2") {?> selected<?php }?>>Disabled</option>
							<option value="1"<?php if ($_REQUEST["status"] == "1") {?> selected<?php }?>>Down</option>
							<option value="0"<?php if ($_REQUEST["status"] == "0") {?> selected<?php }?>>Unknown</option>
							<option value="4"<?php if ($_REQUEST["status"] == "4") {?> selected<?php }?>>Error</option>
						</select>
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Records:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyDeviceFilterChange(document.form_mactrack_devices)">
							<option value="-1"<?php if (get_request_var_request("rows") == "-1") {?> selected<?php }?>>Default</option>
							<?php
							if (sizeof($item_rows) > 0) {
							foreach ($item_rows as $key => $value) {
								print "<option value='" . $key . "'"; if (get_request_var_request("rows") == $key) { print " selected"; } print ">" . $value . "</option>\n";
							}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>