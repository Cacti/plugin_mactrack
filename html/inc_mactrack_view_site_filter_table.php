	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_view_sites">
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
						&nbsp;<input type="checkbox" name="detail" <?php if (($_REQUEST["detail"] == "true") || ($_REQUEST["detail"] == "on")) print ' checked="true"';?> onClick="applySiteFilterChange(document.form_mactrack_view_sites)" alt="Device Details" border="0" align="absmiddle">Show Device Details&nbsp;
					</td>
					<td>
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_sites_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="export_sites_x" value="Export">
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
						<select name="s_site_id" onChange="applySiteFilterChange(document.form_mactrack_view_sites)">
						<option value="-1"<?php if ($_REQUEST["s_site_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$sites = db_fetch_assoc("SELECT * FROM mac_track_sites ORDER BY mac_track_sites.site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] . '"'; if ($_REQUEST["s_site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
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
						<select name="s_device_type_id" onChange="applySiteFilterChange(document.form_mactrack_view_sites)">
						<option value="-1"<?php if ($_REQUEST["s_device_type_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$device_types = db_fetch_assoc("SELECT DISTINCT mac_track_device_types.device_type_id,
								mac_track_device_types.description, mac_track_device_types.sysDescr_match
								FROM mac_track_device_types
								INNER JOIN mac_track_devices ON (mac_track_device_types.device_type_id = mac_track_devices.device_type_id)
								ORDER BY mac_track_device_types.description");

						if (sizeof($device_types) > 0) {
						foreach ($device_types as $device_type) {
							print '<option value="' . $device_type["device_type_id"] . '"'; if ($_REQUEST["s_device_type_id"] == $device_type["device_type_id"]) { print " selected"; } print ">" . $device_type["description"] . " (" . $device_type["sysDescr_match"] . ")</option>";
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
		<input type='hidden' name='hidden_site_id' value='-1'>
		<?php }?>
		</form>
	</tr>