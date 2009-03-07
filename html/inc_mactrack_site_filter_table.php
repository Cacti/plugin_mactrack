	<script type="text/javascript">
	<!--
	function applySiteFilterChange(objForm) {
		strURL = '?report=sites';
		if (objForm.hidden_device_type_id) {
			strURL = strURL + '&device_type_id=-1';
			strURL = strURL + '&site_id=-1';
		}else{
			strURL = strURL + '&device_type_id=' + objForm.device_type_id[objForm.device_type_id.selectedIndex].value;
			strURL = strURL + '&site_id=' + objForm.site_id[objForm.site_id.selectedIndex].value;
		}
		strURL = strURL + '&detail=' + objForm.detail.checked;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_sites">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="70">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td>
						&nbsp;<input type="checkbox" name="detail" <?php if (($_REQUEST["detail"] == "true") || ($_REQUEST["detail"] == "on")) print ' checked="true"';?> onClick="applySiteFilterChange(document.form_mactrack_sites)" alt="Device Details" border="0" align="absmiddle">Show Device Details&nbsp;
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			<?php
			if (!($_REQUEST["detail"] == "false")) { ?>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="70">
						&nbsp;Site:
					</td>
					<td width="1">
						<select name="site_id" onChange="applySiteFilterChange(document.form_mactrack_sites)">
						<option value="-1"<?php if ($_REQUEST["site_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$sites = db_fetch_assoc("SELECT * FROM mac_track_sites ORDER BY mac_track_sites.site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] . '"'; if ($_REQUEST["site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
					</td>
				</tr>
			</table>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="70">
						&nbsp;Sub Type:
					</td>
					<td width="1">
						<select name="device_type_id" onChange="applySiteFilterChange(document.form_mactrack_sites)">
						<option value="-1"<?php if ($_REQUEST["device_type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$device_types = db_fetch_assoc("SELECT DISTINCT mac_track_device_types.device_type_id,
							mac_track_device_types.description, mac_track_device_types.sysDescr_match
							FROM mac_track_device_types
							INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
							ORDER BY mac_track_device_types.description");
						if (sizeof($device_types) > 0) {
						foreach ($device_types as $device_type) {
							print '<option value="' . $device_type["device_type_id"] . '"'; if ($_REQUEST["device_type_id"] == $device_type["device_type_id"]) { print " selected"; } print ">" . $device_type["description"] . " (" . $device_type["sysDescr_match"] . ")</option>";
						}
						}
						?>
					</td>
				</tr>
			<?php }?>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='sites'>
		<?php
		if ($_REQUEST["detail"] == "false") { ?>
		<input type='hidden' name='hidden_device_type_id' value='-1'>
		<?php }?>
		</form>
	</tr>