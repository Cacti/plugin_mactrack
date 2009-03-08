<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2009 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

global $colors, $config;

$oper_mode = api_plugin_hook_function('top_header', OPER_MODE_NATIVE);
if ($oper_mode == OPER_MODE_RESKIN) {
	return;
}

$page_title = api_plugin_hook_function('page_title', 'Cacti');

?>
<html>
<head>
	<title><?php echo $page_title; ?></title>
	<link href="<?php echo $config['url_path']; ?>include/main.css" rel="stylesheet">
	<link href="<?php echo $config['url_path']; ?>images/favicon.ico" rel="shortcut icon"/>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>include/layout.js"></script>
	<script type="text/javascript" src="<?php echo $config['url_path']; ?>plugins/mactrack/macktrack.js"></script>
	<?php if (isset($refresh)) {
	print "<meta http-equiv=refresh content=\"" . $refresh["seconds"] . "; url='" . $refresh["page"] . "'\">";
	}
	api_plugin_hook('page_head'); ?>
</style>
</head>

<?php if ($oper_mode == OPER_MODE_NATIVE) {?>
<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }else{?>
<body leftmargin="15" topmargin="15" marginwidth="15" marginheight="15" <?php print api_plugin_hook_function("body_style", "");?>>
<?php }?>

<table width="100%" cellspacing="0" cellpadding="0">
<?php if ($oper_mode == OPER_MODE_NATIVE) { ;?>
	<tr height="1" bgcolor="#a9a9a9">
		<td valign="bottom" colspan="3" nowrap>
			<table width="100%" cellspacing="0" cellpadding="0">
				<tr style="background: transparent url('<?php echo $config['url_path']; ?>images/cacti_backdrop.gif') no-repeat center right;">
					<td id="tabs" valign="bottom">
						&nbsp;<a href="<?php echo $config['url_path']; ?>index.php"><img src="<?php echo $config['url_path']; ?>images/tab_console.gif" alt="Console" align="absmiddle" border="0"></a><a href="<?php echo $config['url_path']; ?>graph_view.php"><img src="<?php echo $config['url_path']; ?>images/tab_graphs.gif" alt="Graphs" align="absmiddle" border="0"></a><?php
						api_plugin_hook('top_header_tabs');
					?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr height="2" bgcolor="#183c8f">
		<td colspan="3">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" height="2" border="0"><br>
		</td>
	</tr>
	<tr height="5" bgcolor="#e9e9e9">
		<td colspan="3">
			<table width="100%">
				<tr>
					<td>
						<?php draw_navigation_text();?>
					</td>
					<td align="right">
						<?php if (read_config_option("auth_method") != 0) { ?>
						Logged in as <strong><?php print db_fetch_cell("select username from user_auth where id=" . $_SESSION["sess_user_id"]);?></strong> (<a href="<?php echo $config['url_path']; ?>logout.php">Logout</a>)&nbsp;
						<?php } ?>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td bgcolor="#f5f5f5" colspan="1" height="8" width="135" style="background-image: url(<?php echo $config['url_path']; ?>images/shadow_gray.gif); background-repeat: repeat-x; border-right: #aaaaaa 1px solid;">
			<img src="<?php echo $config['url_path']; ?>images/transparent_line.gif" width="135" height="2" border="0"><br>
  		</td>
		<td colspan="2" height="8" style="background-image: url(<?php echo $config['url_path']; ?>images/shadow.gif); background-repeat: repeat-x;" bgcolor="#ffffff">

		</td>
	</tr>
	<tr >
		<td style="padding:5px;" width="100%" valign="top"><?php display_output_messages();?>
<?php }else{ ?>
	<tr>
		<td  style="padding:5px;" width="100%" valign="top"><?php display_output_messages();?>
<?php } ?>