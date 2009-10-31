	<tr class="rowAlternate2">
		<form name="form_mactrack_view_ips">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="40">
						&nbsp;Site:
					</td>
					<td width="1">
						<select name="site_id" onChange="applyIPsFilterChange(document.form_mactrack_view_ips)">
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
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Records:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyIPsFilterChange(document.form_mactrack_view_ips)">
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
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='ips'>
		</form>
	</tr>