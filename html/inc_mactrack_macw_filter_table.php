	<script type="text/javascript">
	<!--
	function applyMacWFilterChange(objForm) {
		strURL = '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_mactrack_macw">
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
						&nbsp;<input type="submit" name="go_x" value="Go">
					</td>
					<td>
						&nbsp;<input type="submit" name="clear_x" value="Clear">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		</form>
	</tr>