	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_view_ips">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="40">
						&nbsp;Site:
					</td>
					<td width="1">
						<select name="i_site_id" onChange="applyIPsFilterChange(document.form_mactrack_view_ips)">
						<option value="-1"<?php if ($_REQUEST["i_site_id"] == "-1") {?> selected<?php }?>>Any</option>
						<?php
						$sites = db_fetch_assoc("SELECT * FROM mac_track_sites ORDER BY mac_track_sites.site_name");
						if (sizeof($sites) > 0) {
						foreach ($sites as $site) {
							print '<option value="' . $site["site_id"] . '"'; if ($_REQUEST["i_site_id"] == $site["site_id"]) { print " selected"; } print ">" . $site["site_name"] . "</option>";
						}
						}
						?>
					</td>
					<td>
						&nbsp<input type="image" src="<?php print $config['url_path']; ?>images/button_export.gif" name="export_ips" alt="Export" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='ips'>
		</form>
	</tr>