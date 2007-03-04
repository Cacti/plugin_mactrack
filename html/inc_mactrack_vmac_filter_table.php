	<script type="text/javascript">
	<!--
	function applyVMACFilterChange(objForm) {
		strURL = '&filter=' + objForm.filter.value;
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
					<td>
						&nbsp;<input type="image" src="<?php echo $config['url_path']; ?>images/button_go.gif" alt="Go" border="0" align="absmiddle">
					</td>
					<td>
						&nbsp;<input type="image" src="<?php echo $config['url_path']; ?>images/button_clear.gif" name="clear_vmacs" alt="Clear" border="0" align="absmiddle">
					</td>
					<td>
						&nbsp<input type="image" src="<?php echo $config['url_path']; ?>images/button_export.gif" name="export_vmacs" alt="Export" border="0" align="absmiddle">
					</td>
				</tr>
			</table>
		</td>
		<input type='hidden' name='page' value='1'>
		<input type='hidden' name='report' value='sites'>
		</form>
	</tr>