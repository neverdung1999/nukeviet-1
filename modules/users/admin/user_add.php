<?php

/**
 * @Project NUKEVIET 3.x
 * @Author VINADES (contact@vinades.vn)
 * @Copyright (C) 2012 VINADES. All rights reserved
 * @Createdate 04/05/2010
 */

if( ! defined( 'NV_IS_FILE_ADMIN' ) )
	die( 'Stop!!!' );

$page_title = $lang_module['user_add'];

$groups_list = nv_groups_list( );

$array_field_config = array( );
$result_field = $db->sql_query( "SELECT * FROM `" . NV_USERS_GLOBALTABLE . "_field` ORDER BY `weight` ASC" );
while( $row_field = $db->sql_fetch_assoc( $result_field ) )
{
	$language = unserialize( $row_field['language'] );
	$row_field['title'] = ( isset( $language[NV_LANG_DATA] )) ? $language[NV_LANG_DATA][0] : $row['field'];
	$row_field['description'] = ( isset( $language[NV_LANG_DATA] )) ? nv_htmlspecialchars( $language[NV_LANG_DATA][1] ) : '';
	$row_field['field_choices'] = ( ! empty( $row_field['field_choices'] )) ? unserialize( $row_field['field_choices'] ) : array( );
	$array_field_config[] = $row_field;
}
$custom_fields = $nv_Request->get_array( 'custom_fields', 'post' );
if( defined( 'NV_EDITOR' ) )
{
	require_once (NV_ROOTDIR . '/' . NV_EDITORSDIR . '/' . NV_EDITOR . '/nv.php');
}

$_user = array( );
$error = "";
$userid = 0;
if( $nv_Request->isset_request( 'confirm', 'post' ) )
{
	$_user['username'] = filter_text_input( 'username', 'post', '', 1, NV_UNICKMAX );
	$_user['email'] = filter_text_input( 'email', 'post', '', 1, 100 );
	$_user['password1'] = filter_text_input( 'password1', 'post', '', 0, NV_UPASSMAX );
	$_user['password2'] = filter_text_input( 'password2', 'post', '', 0, NV_UPASSMAX );
	$_user['question'] = filter_text_input( 'question', 'post', '', 1, 255 );
	$_user['answer'] = filter_text_input( 'answer', 'post', '', 1, 255 );
	$_user['full_name'] = filter_text_input( 'full_name', 'post', '', 1, 255 );
	$_user['gender'] = filter_text_input( 'gender', 'post', '', 1, 1 );
	$_user['view_mail'] = $nv_Request->get_int( 'view_mail', 'post', 0 );
	$_user['sig'] = filter_text_textarea( 'sig', '', NV_ALLOWED_HTML_TAGS );
	$_user['birthday'] = filter_text_input( 'birthday', 'post', '', 1, 10 );
	$_user['in_groups'] = $nv_Request->get_typed_array( 'group', 'post', 'int' );

	if( ($error_username = nv_check_valid_login( $_user['username'], NV_UNICKMAX, NV_UNICKMIN )) != "" )
	{
		$error = $error_username;
	}
	elseif( $_user['username'] != $db->fixdb( $_user['username'] ) )
	{
		$error = sprintf( $lang_module['account_deny_name'], '<strong>' . $_user['username'] . '</strong>' );
	}
	elseif( ($error_xemail = nv_check_valid_email( $_user['email'] )) != "" )
	{
		$error = $error_xemail;
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . NV_USERS_GLOBALTABLE . "` WHERE `md5username`=" . $db->dbescape( md5( $_user['username'] ) ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_username_exist'];
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . NV_USERS_GLOBALTABLE . "` WHERE `email`=" . $db->dbescape( $_user['email'] ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_email_exist'];
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . NV_USERS_GLOBALTABLE . "_reg` WHERE `email`=" . $db->dbescape( $_user['email'] ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_email_exist'];
	}
	elseif( $db->sql_numrows( $db->sql_query( "SELECT `userid` FROM `" . NV_USERS_GLOBALTABLE . "_openid` WHERE `email`=" . $db->dbescape( $_user['email'] ) ) ) != 0 )
	{
		$error = $lang_module['edit_error_email_exist'];
	}
	elseif( ($check_pass = nv_check_valid_pass( $_user['password1'], NV_UPASSMAX, NV_UPASSMIN )) != "" )
	{
		$error = $check_pass;
	}
	elseif( $_user['password1'] != $_user['password2'] )
	{
		$error = $lang_module['edit_error_password'];
	}
	elseif( empty( $_user['question'] ) )
	{
		$error = $lang_module['edit_error_question'];
	}
	elseif( empty( $_user['answer'] ) )
	{
		$error = $lang_module['edit_error_answer'];
	}
	else
	{
		$query_field = array( '`userid`' => 0 );
		if( ! empty( $array_field_config ) )
		{
			require (NV_ROOTDIR . "/modules/users/fields.check.php");
		}

		if( empty( $error ) )
		{
			$_user['sig'] = nv_nl2br( $_user['sig'], "<br />" );
			if( $_user['gender'] != "M" and $_user['gender'] != "F" )
			{
				$_user['gender'] = "";
			}

			if( preg_match( "/^([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{4})$/", $_user['birthday'], $m ) )
			{
				$_user['birthday'] = mktime( 0, 0, 0, $m[2], $m[1], $m[3] );
			}
			else
			{
				$_user['birthday'] = 0;
			}

			$data_in_groups = ( ! empty( $_user['in_groups'] )) ? implode( ',', $_user['in_groups'] ) : '';

			$password = $crypt->hash( $_user['password1'] );

			$sql = "INSERT INTO `" . NV_USERS_GLOBALTABLE . "` (
				        `userid`, `username`, `md5username`, `password`, `email`, `full_name`, `gender`, `birthday`, `sig`, `regdate`, 
				        `question`, `answer`, `passlostkey`, `view_mail`, 
				        `remember`, `in_groups`, `active`, `checknum`, `last_login`, `last_ip`, `last_agent`, `last_openid`) 
				        VALUES(
						NULL, 
						" . $db->dbescape( $_user['username'] ) . ",
						" . $db->dbescape( md5( $_user['username'] ) ) . ",
						" . $db->dbescape( $password ) . ",
						" . $db->dbescape( $_user['email'] ) . ",
						" . $db->dbescape( $_user['full_name'] ) . ",
						" . $db->dbescape( $_user['gender'] ) . ",
						" . $_user['birthday'] . ",
						" . $db->dbescape( $_user['sig'] ) . ",
						" . NV_CURRENTTIME . ",
						" . $db->dbescape( $_user['question'] ) . ",
						" . $db->dbescape( $_user['answer'] ) . ",
						'', 
				        " . $_user['view_mail'] . ", 
				        1, 
				        " . $db->dbescape_string( $data_in_groups ) . ", 
				        1, '', 0, '', '', '')";

			$userid = $db->sql_query_insert_id( $sql );

			if( $userid )
			{
				$query_field['`userid`'] = $userid;
				$db->sql_query( "INSERT INTO `" . NV_USERS_GLOBALTABLE . "_info` (" . implode( ', ', array_keys( $query_field ) ) . ") VALUES (" . implode( ', ', array_values( $query_field ) ) . ")" );

				nv_insert_logs( NV_LANG_DATA, $module_name, 'log_add_user', "userid " . $userid, $admin_info['userid'] );
				if( isset( $_FILES['photo'] ) and is_uploaded_file( $_FILES['photo']['tmp_name'] ) )
				{
					require_once (NV_ROOTDIR . "/includes/class/upload.class.php");

					$upload = new upload( array( 'images' ), $global_config['forbid_extensions'], $global_config['forbid_mimes'], NV_UPLOAD_MAX_FILESIZE, 80, 80 );
					$upload_info = $upload->save_file( $_FILES['photo'], NV_UPLOADS_REAL_DIR . '/' . $module_name, false );

					@unlink( $_FILES['photo']['tmp_name'] );

					if( empty( $upload_info['error'] ) )
					{
						@chmod( $upload_info['name'], 0644 );

						$file_name = str_replace( NV_ROOTDIR . "/", "", $upload_info['name'] );

						$sql = "UPDATE `" . NV_USERS_GLOBALTABLE . "` SET `photo`=" . $db->dbescape( $file_name ) . " WHERE `userid`=" . $userid;
						$db->sql_query( $sql );
					}
				}
				if( ! empty( $_user['in_groups'] ) )
				{
					foreach( $_user['in_groups'] as $group_id_i )
					{
						$query = "SELECT `users` FROM `" . NV_GROUPS_GLOBALTABLE . "` WHERE `group_id`=" . $group_id_i;
						$result = $db->sql_query( $query );
						$numrows = $db->sql_numrows( $result );
						if( $numrows )
						{
							$row_users = $db->sql_fetchrow( $result );
							$users = trim( $row_users['users'] );
							$users = ! empty( $users ) ? explode( ",", $users ) : array( );
							$users = array_merge( $users, array( $userid ) );
							$users = array_unique( $users );
							sort( $users );
							$users = array_values( $users );
							$users = ! empty( $users ) ? implode( ",", $users ) : "";

							$sql = "UPDATE `" . NV_GROUPS_GLOBALTABLE . "` SET `users`=" . $db->dbescape_string( $users ) . " WHERE `group_id`=" . $group_id_i;
							$db->sql_query( $sql );
						}
					}

				}

				Header( "Location: " . NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name );
				exit( );
			}
			$error = $lang_module['edit_add_error'];
		}
	}
}
else
{
	$_user['username'] = $_user['email'] = $_user['password1'] = $_user['password2'] = $_user['question'] = $_user['answer'] = "";
	$_user['full_name'] = $_user['gender'] = $_user['website'] = $_user['location'] = $_user['yim'] = $_user['telephone'] = "";
	$_user['fax'] = $_user['mobile'] = $_user['sig'] = $_user['birthday'] = "";
	$_user['view_mail'] = 0;
	$_user['in_groups'] = array( );
}

$genders = array(
	'N' => array(
		'key' => 'N',
		'title' => $lang_module['NA'],
		'selected' => ''
	),
	'M' => array(
		'key' => 'M',
		'title' => $lang_module['male'],
		'selected' => $_user['gender'] == "M" ? " selected=\"selected\"" : ""
	),
	'F' => array(
		'key' => 'F',
		'title' => $lang_module['female'],
		'selected' => $_user['gender'] == "F" ? " selected=\"selected\"" : ""
	)
);

$_user['view_mail'] = $_user['view_mail'] ? " checked=\"checked\"" : "";

if( ! empty( $_user['sig'] ) )
	$_user['sig'] = nv_htmlspecialchars( $_user['sig'] );

$groups = array( );
if( ! empty( $groups_list ) )
{
	foreach( $groups_list as $group_id => $grtl )
	{
		$groups[] = array(
			'id' => $group_id,
			'title' => $grtl,
			'checked' => ( ! empty( $_user['in_groups'] ) and in_array( $group_id, $_user['in_groups'] )) ? " checked=\"checked\"" : ""
		);
	}
}

$xtpl = new XTemplate( "user_add.tpl", NV_ROOTDIR . "/themes/" . $global_config['module_theme'] . "/modules/" . $module_file );
$xtpl->assign( 'LANG', $lang_module );
$xtpl->assign( 'DATA', $_user );
$xtpl->assign( 'FORM_ACTION', NV_BASE_ADMINURL . "index.php?" . NV_NAME_VARIABLE . "=" . $module_name . "&amp;" . NV_OP_VARIABLE . "=user_add" );
$xtpl->assign( 'NV_BASE_SITEURL', NV_BASE_SITEURL );

if( ! empty( $error ) )
{
	$xtpl->assign( 'ERROR', $error );
	$xtpl->parse( 'main.error' );
}

if( defined( 'NV_IS_USER_FORUM' ) )
{
	$xtpl->parse( 'main.is_forum' );
}
else
{
	foreach( $genders as $gender )
	{
		$xtpl->assign( 'GENDER', $gender );
		$xtpl->parse( 'main.edit_user.gender' );
	}

	if( ! empty( $groups ) )
	{
		foreach( $groups as $group )
		{
			$xtpl->assign( 'GROUP', $group );
			$xtpl->parse( 'main.edit_user.group.list' );
		}
		$xtpl->parse( 'main.edit_user.group' );
	}
	if( ! empty( $array_field_config ) )
	{
		$a = 0;
		foreach( $array_field_config as $row )
		{
			if( ($row['show_register'] AND $userid == 0) OR $userid > 0 )
			{
				$row['tbodyclass'] = ($a % 2) ? " class=\"second\"" : "";
				if( $userid == 0 AND ! $nv_Request->isset_request( 'confirm', 'post' ) )
				{
					if( ! empty( $row['field_choices'] ) )
					{
						$temp = array_keys( $row['field_choices'] );
						$tempkey = intval( $row['default_value'] ) - 1;
						$row['value'] = (isset( $temp[$tempkey] )) ? $temp[$tempkey] : '';
					}
					else
					{
						$row['value'] = $row['default_value'];
					}
				}
				else
				{
					$row['value'] = (isset( $custom_fields[$row['field']] )) ? $custom_fields[$row['field']] : $row['default_value'];
				}
				$row['required'] = ($row['required']) ? 'required' : '';

				$xtpl->assign( 'FIELD', $row );
				if( $row['required'] )
				{
					$xtpl->parse( 'main.edit_user.field.loop.required' );
				}
				if( $row['field_type'] == 'textbox' )
				{
					$xtpl->parse( 'main.edit_user.field.loop.textbox' );
				}
				elseif( $row['field_type'] == 'textarea' )
				{
					$row['value'] = nv_htmlspecialchars( nv_br2nl( $row['value'] ) );
					$xtpl->assign( 'FIELD', $row );
					$xtpl->parse( 'main.edit_user.field.loop.textarea' );
				}
				elseif( $row['field_type'] == 'editor' )
				{
					if( defined( 'NV_EDITOR' ) and nv_function_exists( 'nv_aleditor' ) )
					{
						$row['value'] = nv_htmlspecialchars( nv_editor_br2nl( $row['value'] ) );
						$array_tmp = explode( "@", $row['class'] );
						$edits = nv_aleditor( 'custom_fields[' . $row['field'] . ']', $array_tmp[0], $array_tmp[1], $row['value'] );
						$xtpl->assign( 'EDITOR', $edits );
						$xtpl->parse( 'main.edit_user.field.loop.editor' );
					}
					else
					{
						$row['value'] = nv_htmlspecialchars( nv_br2nl( $row['value'] ) );
						$xtpl->assign( 'FIELD', $row );
						$xtpl->parse( 'main.edit_user.field.loop.textarea' );
					}
				}
				elseif( $row['field_type'] == 'select' )
				{
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"key" => $key,
							"selected" => ($key == $row['value']) ? ' selected="selected"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.select.loop' );
					}
					$xtpl->parse( 'main.edit_user.field.loop.select' );
				}
				elseif( $row['field_type'] == 'radio' )
				{
					$number = 0;
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"id" => $row['fid'] . '_' . $number++,
							"key" => $key,
							"checked" => ($key == $row['value']) ? ' checked="checked"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.radio' );
					}
				}
				elseif( $row['field_type'] == 'checkbox' )
				{
					$number = 0;
					$valuecheckbox = explode( ',', $row['value'] );
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"id" => $row['fid'] . '_' . $number++,
							"key" => $key,
							"checked" => (in_array( $key, $valuecheckbox )) ? ' checked="checked"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.checkbox' );
					}
				}
				elseif( $row['field_type'] == 'multiselect' )
				{
					$valueselect = ( isset( $row['value'] )) ? explode( ',', $row['value'] ) : array( );
					foreach( $row['field_choices'] as $key => $value )
					{
						$xtpl->assign( 'FIELD_CHOICES', array(
							"key" => $key,
							"selected" => (in_array( $key, $valueselect )) ? ' selected="selected"' : '',
							"value" => $value
						) );
						$xtpl->parse( 'main.edit_user.field.loop.multiselect.loop' );
					}
					$xtpl->parse( 'main.edit_user.field.loop.multiselect' );
				}
				$xtpl->parse( 'main.edit_user.field.loop' );
			}
		}
		$xtpl->parse( 'main.edit_user.field' );
	}

	$xtpl->parse( 'main.edit_user' );
}
$xtpl->parse( 'main' );
$contents = $xtpl->text( 'main' );

$my_head = '';
$my_head .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/popcalendar/popcalendar.js\"></script>\n";
$my_head .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/jquery/jquery.validate.min.js\"></script>\n";
$my_head .= "<script type=\"text/javascript\" src=\"" . NV_BASE_SITEURL . "js/language/jquery.validator-" . NV_LANG_INTERFACE . ".js\"></script>\n";

include (NV_ROOTDIR . "/includes/header.php");
echo nv_admin_theme( $contents );
include (NV_ROOTDIR . "/includes/footer.php");
?>