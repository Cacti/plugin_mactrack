	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_view_macs">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;Site:&nbsp;
					</td>
					<td width="1">
						<select name="m_site_id" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["m_site_id"] == "-1") {?> selected<?php }?>>N/A</option>
						<?php
						$sites = db_fetch_assoc("select site_id,site_name from mac_track_sites order by site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] .'"'; if ($_REQUEST["m_site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
						</select>
					</td>
					<td width="1">
						&nbsp;Device:&nbsp;
					</td>
					<td width="1">
						<select name="m_device_id" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["m_device_id"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						if ($_REQUEST["m_site_id"] == -1) {
							$filter_devices = db_fetch_assoc("SELECT device_id, device_name, hostname FROM mac_track_devices ORDER BY device_name");
						}else{
							$filter_devices = db_fetch_assoc("SELECT device_id, device_name, hostname FROM mac_track_devices WHERE site_id='" . $_REQUEST["m_site_id"] . "' ORDER BY device_name");
						}
						if (sizeof($filter_devices) > 0) {
						foreach ($filter_devices as $filter_device) {
							print '<option value=" ' . $filter_device["device_id"] . '"'; if ($_REQUEST["m_device_id"] == $filter_device["device_id"]) { print " selected"; } print ">" . $filter_device["device_name"] . "(" . $filter_device["hostname"] . ")" .  "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_macs_x" value="Clear">
					</td>
					<td>
						&nbsp;<input type="submit" name="export_macs_x" value="Export">
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;IP Address:
					</td>
					<td width="1">
						<select name="m_ip_filter_type_id">
						<?php
						for($i=1;$i<=sizeof($mactrack_search_types);$i++) {
							print "<option value='" . $i . "'"; if ($_REQUEST["m_ip_filter_type_id"] == $i) { print " selected"; } print ">" . $mactrack_search_types[$i] . "</option>\n";
						}
						?>
						</select>
					</td>
					<td width="1">
						<input type="text" name="m_ip_filter" size="20" value="<?php print $_REQUEST["m_ip_filter"];?>">
					</td>
					<td width="80">
						&nbsp;VLAN Name:&nbsp;
					</td>
					<td width="1">
						<select name="m_vlan_id" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["m_vlan_id"] == "-1") {?> selected<?php }?>>All</option>
						<?php
						$sql_where = "";
						if ($_REQUEST["m_device_id"] != "-1") {							$sql_where = "WHERE device_id='" . $_REQUEST["m_device_id"] . "'";
						}

						if ($_REQUEST["m_site_id"] != "-1") {							if (strlen($sql_where)) {								$sql_where .= " AND site_id='" . $_REQUEST["m_site_id"] . "'";
							}else{								$sql_where = "WHERE site_id='" . $_REQUEST["m_site_id"] . "'";
							}
						}

						$vlans = db_fetch_assoc("SELECT DISTINCT vlan_id, vlan_name FROM mac_track_vlans $sql_where ORDER BY vlan_name ASC");
						if (sizeof($vlans) > 0) {
						foreach ($vlans as $vlan) {
							print '<option value="' . $vlan["vlan_id"] . '"'; if ($_REQUEST["m_vlan_id"] == $vlan["vlan_id"]) { print " selected"; } print ">" . $vlan["vlan_name"] . "</option>\n";
						}
						}
						?>
						</select>
					</td>
					<td width="60">
						&nbsp;Show:&nbsp;
					</td>
					<td width="1">
						<select name="m_rowstoshow" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="1"<?php if ($_REQUEST["m_rowstoshow"] == "1") {?> selected<?php }?>>All</option>
						<option value="2"<?php if ($_REQUEST["m_rowstoshow"] == "2") {?> selected<?php }?>>Most Recent</option>
						<?php
						$scan_dates = db_fetch_assoc("select scan_date from mac_track_scan_dates order by scan_date desc");
						if (sizeof($scan_dates) > 0) {
						foreach ($scan_dates as $scan_date) {
							print '<option value="' . $scan_date["scan_date"] . '"'; if ($_REQUEST["m_rowstoshow"] == $scan_date["scan_date"]) { print " selected"; } print ">" . $scan_date["scan_date"] . "</option>\n";
						}
						}
						?>
						</select>
					</td>
				</tr>
				<tr>
					<td width="80">
						&nbsp;Mac Address:
					</td>
					<td width="1">
						<select name="m_mac_filter_type_id">
						<?php
						for($i=1;$i<=sizeof($mactrack_search_types)-2;$i++) {
							print "<option value='" . $i . "'"; if ($_REQUEST["m_mac_filter_type_id"] == $i) { print " selected"; } print ">" . $mactrack_search_types[$i] . "</option>\n";
						}
						?>
						</select>
					</td>
					<td width="1">
						<input type="text" name="m_mac_filter" size="20" value="<?php print $_REQUEST["m_mac_filter"];?>">
					</td>
					<td width="80">
						&nbsp;Authorized:&nbsp;
					</td>
					<td width="1">
						<select name="m_authorized" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<option value="-1"<?php if ($_REQUEST["m_authorized"] == "-1") {?> selected<?php }?>>All</option>
						<option value="1"<?php if ($_REQUEST["m_authorized"] == "1") {?> selected<?php }?>>Yes</option>
						<option value="0"<?php if ($_REQUEST["m_authorized"] == "0") {?> selected<?php }?>>No</option>
						</select>
					</td>
					<td width="60">
						&nbsp;Records:&nbsp;
					</td>
					<td width="1">
						<select name="m_rows_selector" onChange="applyMacFilterChange(document.form_mactrack_view_macs)">
						<?php
						if (sizeof($rows_selector) > 0) {
						foreach ($rows_selector as $key => $value) {
							print '<option value="' . $key . '"'; if ($_REQUEST["m_rows_selector"] == $key) { print " selected"; } print ">" . $value . "</option>\n";
						}
						}
						?>
						</select>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="80">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="m_filter" size="45" value="<?php print $_REQUEST["m_filter"];?>">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='m_page' value='1'>
		<input type='hidden' name='report' value='macs'>
		</form>
	</tr>