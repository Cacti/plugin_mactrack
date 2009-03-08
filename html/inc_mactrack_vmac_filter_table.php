	<script type="text/javascript">
	<!--
	function applyVMACFilterChange(objForm) {
		strURL = '?filter=' + objForm.filter.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_vmacs">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="70">
						&nbsp;Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="20" value="<?php print $_REQUEST["filter"];?>">
					</td>
					<td nowrap style='white-space: nowrap;' width="50">
						&nbsp;Records:&nbsp;
					</td>
					<td width="1">
						<select name="rows" onChange="applyVMACFilterChange(document.form_mactrack_vmacs)">
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
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
					<td>
						&nbsp<input type="submit" name="export_x" value="Export">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='sites'>
		</form>
	</tr>