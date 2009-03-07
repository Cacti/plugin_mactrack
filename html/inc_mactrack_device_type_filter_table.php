	<script type="text/javascript">
	<!--
	function applyDeviceTypeFilterChange(objForm) {
		strURL = '?vendor=' + objForm.vendor[objForm.vendor.selectedIndex].value;
		strURL = strURL + '&type_id=' + objForm.type_id[objForm.type_id.selectedIndex].value;
		document.location = strURL;
	}

	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_device_types">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="40">
						&nbsp;Vendor:&nbsp;
					</td>
					<td width="1">
						<select name="vendor" onChange="applyDeviceTypeFilterChange(document.form_mactrack_device_types)">
							<option value='All'<?php print $_REQUEST['type_id']; if ($_REQUEST['vendor'] == 'All') print ' selected';?>'>All</option>
							<?php
							$types = db_fetch_assoc("SELECT DISTINCT vendor from mac_track_device_types ORDER BY vendor");

							if (sizeof($types) > 0) {
							foreach ($types as $type) {
								print '<option value="' . $type["vendor"] . '"';if ($_REQUEST["vendor"] == $type["vendor"]) { print " selected"; } print ">" . $type["vendor"] . "</option>\n";
							}
							}
							?>
						</select>
					</td>
					<td width="5"></td>
					<td width="40">
						&nbsp;Type:&nbsp;
					</td>
					<td width="1">
						<select name="type_id" onChange="applyDeviceTypeFilterChange(document.form_mactrack_device_types)">
							<option value="-1"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '-1') print ' selected';?>>All</option>
							<option value="1"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '1') print ' selected';?>>Hub/Switch</option>
							<option value="2"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '2') print ' selected';?>>Switch/Router</option>
							<option value="3"<?php print $_REQUEST["vendor"] . '"'; if ($_REQUEST['type_id'] == '3') print ' selected';?>>Router</option>
							?>
						</select>
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
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>