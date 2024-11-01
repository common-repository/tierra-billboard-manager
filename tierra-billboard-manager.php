<?php
/*
 * Plugin Name: Tierra Billboard Manager
 * Plugin URI: http://www.tierra-innovation.com/wordpress-cms/plugins/billboard-manager/
 * Description: Create, manage and embed MP3 playlists within the WordPress admin panel. Playlists can be embedded using the included swf player or played via third-party <a target="_blank" href="http://xspf.xiph.org/applications/">XSPF-compatible music players</a>.
 * Version: 1.14
 * Author: Tierra Innovation
 * Author URI: http://www.tierra-innovation.com/


Changes:
 1.14   - Fixes SWF/HTML overlay issue by setting wmode to opaque
 1.13	- Fixes issues with showtitles not persisting and a bug with wordpress 3.0 upon new activation.
 1.12	- Fixed bug introduced in 1.11 that could affect existing playlists
 1.11 	- Fixed bug in player that could lead to simultaneous sound playing
		- Added logo to admin page.
		- Added full-screen capabilities
		- Removed audio icon on still image presentations
		- Major revisions to asset management and player to accommodate inclusion of remote files within billboard.
		- Various bug fixes
1.01	- Fixed admin issues reported by users of Internet Explorer
1.0 	- Initial version		

*/


/*
 * This is a modified version (under the MIT License) of a plugin
 * originally developed by Tierra Innovation for WNET.org.
 * 
 * This plugin is currently available for use in all personal
 * or commercial projects under both MIT and GPL licenses. This
 * means that you can choose the license that best suits your
 * project, and use it accordingly.
 *
 * MIT License: http://www.tierra-innovation.com/license/MIT-LICENSE.txt
 * GPL2 License: http://www.tierra-innovation.com/license/GPL-LICENSE.txt
 */



// This is the minimum level required to perform many of the functions within this plugin. Uploading still requires level 7
define( 'TI_BBM_LEVEL_REQUIRED', 4);

	
@ini_set('upload_max_size','100M');
@ini_set('post_max_size','105M');
@ini_set('max_execution_time','300');

require_once (ABSPATH . WPINC . '/pluggable.php');
global $userdata;
get_currentuserinfo();

wp_enqueue_script ("jquery");	
wp_enqueue_script ('thickbox');
wp_enqueue_script ('ac_run_active_content', WP_PLUGIN_URL . "/tierra-billboard-manager/js/AC_RunActiveContent.js");
wp_enqueue_script ("tierra_bbm", WP_PLUGIN_URL . "/tierra-billboard-manager/js/tierra-billboard-manager.js");
wp_enqueue_style ('thickbox');


// Pre-2.6 compatibility
if ( ! defined( 'WP_CONTENT_URL' ) )
      define( 'WP_CONTENT_URL', get_option( 'siteurl' ) . '/wp-content' );
if ( ! defined( 'WP_CONTENT_DIR' ) )
      define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );

if ( ! defined( 'WP_PLUGIN_DIR' ) )
      define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

if ( ! defined( 'WP_PLUGIN_URL' ) )
      define( 'WP_PLUGIN_URL', get_option('siteurl') . '/wp-content/plugins' );


// Height and width of the preview Thickbox
$ti_bbm_prev_height = 655;
$ti_bbm_prev_width = 610;



// module globals
$_billboard_manager_db_version = 1.13;

// these need to be declared global so they are in scope for the activation hook
global  $wpdb, $_billboard_manager_db_version, $_billboard_manager_db, $ti_bbm_base_query, $userdata,  $ti_bbm_prev_width, $ti_bbm_prev_height;



$_billboard_manager_db = $wpdb->prefix . "ti_billboard_manager";



$ti_bbm_base_query =  $_SERVER["QUERY_STRING"];

// installer
register_activation_hook(__FILE__, '_ti_bbm_install');

if (isset($_POST['action']))	{
	if (isset ($_POST['ti_bbm_playlist_id']) && $_POST['action'] == 'ti_bbm_update_playlist')	{
		ti_bbm_update_playlist ();
		
		exit;
	}
}

if (isset($_GET['action']))	{
	
	if (isset ($_GET['ti_bbm_playlist_id']) && $_GET['action'] == 'ti_bbm_view_playlist')	{
		
		ti_bbm_return_playlist_html(intval($_GET['ti_bbm_playlist_id']));
		exit;
	}

	//ti_bbm_return_playlist_xml
	
	if (isset ($_GET['ti_bbm_playlist_id']) && $_GET['action'] == 'xml')	{
		ti_bbm_return_playlist_xml(intval($_GET['ti_bbm_playlist_id']));
		exit;
	}	
	
	if (isset ($_GET['ti_bbm_playlist_id']) && $_GET['action'] == 'ti_bbm_view_player')	{
		ti_bbm_print_player(intval($_GET['ti_bbm_playlist_id']));
		exit;
	}	
	
	
	
	if ($_GET['action'] == 'ti_bbm_get_playlist_options')	{
		ti_bbm_return_playlist_options();
		exit;
	}
	if ($_GET['action'] == 'ti_bbm_add_playlist' && isset($_GET['playlist_title']))	{
		ti_bbm_create_new_playlist($_GET['playlist_title']);
		exit;
	}
	
	//ti_bbm_delete_playlist
	if ($_GET['action'] == 'ti_bbm_delete_playlist' && isset($_GET['ti_bbm_playlist_id']))	{
		ti_bbm_delete_current_playlist($_GET['ti_bbm_playlist_id']);
		exit;
	}

	if ($_GET['action'] == 'ti_bbm_add_tracks_to_playlist' && isset($_GET['tracks']) && isset($_GET['ti_bbm_playlist_id']))	{
		ti_bbm_add_tracks_to_playlist($_GET['tracks'], $_GET['ti_bbm_playlist_id']);
		exit;
	}

	// Both ti_bbm_reorder_playlist and ti_bbm_remove_from_playlist simply replace the existing playlist with a new one provided within
	// the tracks property. Thus, they function identically.
	if ($_GET['action'] == 'ti_bbm_remove_from_playlist' && isset($_GET['tracks']) && isset($_GET['ti_bbm_playlist_id']))	{
		ti_bbm_return_tracks_in_playlist($_GET['tracks'], $_GET['ti_bbm_playlist_id']);
		exit;
	}

	// ti_bbm_reorder_playlist
	if ($_GET['action'] == 'ti_bbm_reorder_playlist' && isset($_GET['tracks']) && isset($_GET['ti_bbm_playlist_id']))	{
		ti_bbm_return_tracks_in_playlist($_GET['tracks'], $_GET['ti_bbm_playlist_id']);
		exit;
	}

}
				

function _ti_bbm_install() {

	global $wpdb, $_billboard_manager_db_version, $_billboard_manager_db;
	$pluginURL = WP_PLUGIN_URL;
	if ($wpdb->get_var("SHOW TABLES LIKE '$_billboard_manager_db'") != $_billboard_manager_db) {
			
			$sql = "CREATE TABLE $_billboard_manager_db (
						`id` int(11) NOT NULL AUTO_INCREMENT,
						`title` varchar(64) DEFAULT 'Tierra Billboard Manager Playlist',
						`description` varchar(255) DEFAULT NULL,
						`image` varchar(255) DEFAULT NULL,
						`random` int(1) DEFAULT NULL,
						`autoPlay` int(1) DEFAULT '1',
						`license` text,
						`tracks` text,
						`modification_date` datetime DEFAULT NULL,
						`creation_date` datetime DEFAULT NULL,
						`last_play_date` datetime DEFAULT NULL,
						`views` int(11) DEFAULT NULL,
						`backgroundColor` varchar(8) DEFAULT 'ffffff',
						`navBarColor` varchar(8) DEFAULT '000000',
						`glowColor` varchar(8) DEFAULT '9999ff',
						`showTitles` int(1) DEFAULT '1',
						`showRollovers` int(1) DEFAULT '1',
						`repeating` int(1) DEFAULT '1',
						`useOverlay` int(1) DEFAULT '0',
						`keepTitles` int(1) DEFAULT '0',
						`delay` int(11) DEFAULT '8',
						`titleBGColor` varchar(8) DEFAULT '000000',
						`titleFGColor` varchar(8) DEFAULT 'FFFFFF',
						`themeURL` varchar(255) DEFAULT  '$pluginURL/tierra-billboard-manager/skin/',
						`showThumbnail` int(1) DEFAULT '1',
						`titlesOnRollover` int(1) DEFAULT '1',
						`justifyTitle` varchar(8) DEFAULT 'left',
						`width` int(11) DEFAULT '440',
						`height` int(11) DEFAULT '280',
						`initialVolume` int(6) DEFAULT '1',
						
					PRIMARY KEY `id` (`id`),
					UNIQUE KEY `title` (`title`)
			)";
			
			


		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		$wpdb->query ("INSERT INTO $_billboard_manager_db set title='___DEFAULT___'");
		
		add_option("billboard_manager_db_version", $_billboard_manager_db_version);
		
		
	}
}




function ti_bbm_test_for_activation() {
	global $wpdb, $_billboard_manager_db;
	
	$tables = array($_billboard_manager_db);
	
	$ok = true;
	foreach ($tables as $table) {
		if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
			$ok = false;
			break;
		}
	}
	
	if (!$ok && (intval(get_bloginfo( 'version' ), 10)) < 3.0)
		echo "<p><strong>Plugin Check:</strong> <span style='color: red;'>It looks like this plugin did not fully activate.  Please <a href='plugins.php'>click here</a> and toggle the plugin off and on to redo the activation.</span></p>";
}

ti_bbm_test_for_activation();

// set admin screen
function ti_bbm_modify_billboard_menu() {
		
	add_options_page(
		'Tierra Billboard Manager', // page title
		'Tierra Billboards', // sub-menu title
		'manage_options', // access/capa
		'tierra-billboard-manager.php', // file
		'ti_bbm_admin_billboard_manager' // function
	);

	add_management_page(
		'Tierra Billboard Manager', // page title
		'Tierra Billboards', // sub-menu title
		'edit_others_posts', // access/capa
		'tierra-billboard-manager.php', // file
		'ti_bbm_admin_billboard_manager' // function
	);
	
}

add_shortcode('ti_billboard', 'ti_bbm_print_player');
add_action('admin_menu', 'ti_bbm_modify_billboard_menu');



function add_upload_ext($mimes='')
{
        $mimes['flv']='video/x-flv';
        return $mimes;
}
add_filter("upload_mimes","add_upload_ext");


function ti_bbm_admin_billboard_manager() {
	
	
	
	if ( isset($_FILES['file']))	{
		ti_bbm_upload_files();
		ti_bbm_print_billboard_admin();
	}	else {
		// This allows us to perform the basic functions...
		if (isset($_GET['action']))	{
			$action = strtolower($_GET['action']);
			switch ($action)	{
				case 'edit'	:
					if (isset($_GET['asset_id']))	{
						ti_bbm_edit_existing_asset(intval($_GET['asset_id']));
					}
					break;
				
				case 'update'	:
					if (isset($_GET['asset_id']))	{
						ti_bbm_update_existing_asset(intval($_GET['asset_id']));
						ti_bbm_print_billboard_admin();
					}
					break;
	
	
				default:
					ti_bbm_print_billboard_admin();
					break;
				
				
			}
		}	else {
			ti_bbm_print_billboard_admin();
		}
		
	} 
		
}


function ti_bbm_return_tracks_in_playlist($tracks, $ti_bbm_playlist_id)	{
	
	ti_bbm_check_permissions();

	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;

	$sql = "UPDATE $_billboard_manager_db set tracks =\"" . $wpdb->escape($tracks) . "\" where id = \"" . $wpdb->escape($ti_bbm_playlist_id) ."\"";
	
	$result = $wpdb->query($sql);

	if ($result >= 0)	{
		ti_bbm_return_playlist_html($wpdb->escape($ti_bbm_playlist_id));
		return;
	}	else	{
		echo "!!!ERROR!!!: Cannot add/modify tracks for selected playlist.";
		return;
	}
	return;

}

function ti_bbm_check_permissions ($levelRequired =  TI_BBM_LEVEL_REQUIRED , $str = 'You do not have permission to access this functionality.')	{
	global $userdata;
	if (!current_user_can('edit_others_posts'))	{
		echo("ACCESS ERROR: " . $str );
		exit;
	}
		
}

function ti_bbm_add_tracks_to_playlist($tracks, $ti_bbm_playlist_id)	{

	ti_bbm_check_permissions();
	
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;
	
	// First we want to get the existing tracks... 		
	$sql = 'select tracks from  ' . $_billboard_manager_db . ' where id = ' . $wpdb->escape($ti_bbm_playlist_id) . ' limit 1';
	$tmp = $wpdb->get_var($sql);

	// Split 'em up so we eliminate any empty tracks
	$tmpArray = split (',', $tmp);
	
	// merge 'em with the new tracks
	$tmpTracks = array_merge($tmpArray, split (',', $tracks));
	$tmpArray = array();
	for ($i = 0; $i < count($tmpTracks); $i++)	{
		if (intval($tmpTracks[$i]) > 0)	{
			array_push($tmpArray, $tmpTracks[$i]);
		}
	}
	// And finally, smash all that back into a string that holds both track numbers and their order
	$trackStr = join(',', $tmpArray);

	$sql = "UPDATE $_billboard_manager_db set tracks =\"" . $wpdb->escape($trackStr) . "\" where id = \"" . $wpdb->escape($ti_bbm_playlist_id) ."\"";
	
	$result = $wpdb->query($sql);
	if ($result >= 0)	{
		ti_bbm_return_playlist_html($wpdb->escape($ti_bbm_playlist_id));
		return;
	}	else	{
		echo "!!!ERROR!!!: Cannot add tracks to selected playlist.";
		return;
	}
	return;
}


function ti_bbm_delete_current_playlist ($ti_bbm_playlist_id)	{
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;
	
	ti_bbm_check_permissions(TI_BBM_LEVEL_REQUIRED, 'You do not have permission delete playlists.');
	
	$sql = "DELETE from $_billboard_manager_db where id = (\"" . $wpdb->escape($ti_bbm_playlist_id) . "\")";
	
	$result = $wpdb->query($sql);
	if ($result >=0)	{

		ti_bbm_return_playlist_options();
		
		return;
	}	else	{
		echo "!!!ERROR!!!: Could not delete playlist.";
		return;
	}
	
}

function ti_bbm_create_new_playlist($title)	{
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;

	ti_bbm_check_permissions(TI_BBM_LEVEL_REQUIRED, 'You do not have permission to create a new playlist.');

	// Insert using the default values...
	$sql = "INSERT into $_billboard_manager_db  (title, modification_date, creation_date, views, autoPlay, backgroundColor, navBarColor, glowColor, showTitles, showRollovers, repeating, useOverlay, delay, titleBGColor, titleFGColor, justifyTitle, width, height, showThumbnail, keepTitles, titlesOnRollover) SELECT \"" . $wpdb->escape($title) . "\", now(), now(), '0', autoPlay, backgroundColor, navBarColor, glowColor, showTitles, showRollovers, repeating, useOverlay, delay, titleBGColor, titleFGColor, justifyTitle, width, height, showThumbnail, keepTitles, titlesOnRollover from  $_billboard_manager_db where title = \"___DEFAULT___\" limit 1";
	
	
	
	
	$result = $wpdb->query($sql);
	
	if ($result && $result >=0)	{
		ti_bbm_return_playlist_options($wpdb->escape($title));
		return;
	}	else	{
		
		echo "!!!ERROR!!!: Playlist already exists.";
		return;
	}
}

function ti_bbm_return_playlist_options($selectedTitle = null)	{
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;
	$sql = "select id, title, description, tracks from $_billboard_manager_db order by title";	
	$rows = $wpdb->get_results($sql);
	
	$options="";
	
	$i = 0;
	if ($rows) {	
		foreach ($rows as $row)	{
			// We don't want the DEFAULT SETTINGS SHOWN HERE.
			if ($row->title == '___DEFAULT___') continue;
			
			$i++;
			// Either be the first or the  entry...
			$selectStatus = (($selectedTitle == null && $i == 1) || ($selectedTitle != null && $row->title == $selectedTitle)) ? "SELECTED" : "";
			

			$options .= "\n<option $selectStatus value=\"" . $wpdb->escape($row->id) . "\">" . htmlspecialchars(stripslashes($row->title)) ."  </option>"; 
		}
	}
	echo $options;
	
}

function ti_bbm_list_playlists()	{
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;
	$options = ti_bbm_print_javascript();
	$options .= "<input type='text' name='new_playlist_name' id='new_playlist_name'>";
	$options .= "<input type='submit' name='create_playlist' class='button-primary' value='Create new playlist' onClick='javascript:createPlaylist();'/>";
	$options .= " or select an existing playlist: ";
 	$options .= '<select id="playlist_selection" name="playlist_selection" onChange="javascript:swapPlaylist()">';
	
	$options .= "<input type='submit' name='ti_bbm_delete_playlist' class='button-primary alignright' value='Delete this playlist' onClick='javascript:deletePlaylist();'/>";


	$options .= "\n</select>";


	
	$options .=<<<__END_OF_HEADER__

	<table id='playlist_preview' name='playlist_preview' class='widefat'>
			<thead>
				<tr>
					<th scope='col'>#</th>
					<th scope='col'>Title</th>
					<th scope='col'>Creator</th>
					<th scope='col'>Compilation</th>
					<th scope='col'>Type</th>
					<th scope='col'>Preview/Embed code</th>
					<th scope='col'>Info</th>
					<th scope='col' class='delete'>Delete</th>
				</tr>
			</thead>
			<tbody id ="playlist">
			<tr><td></td></tr></tbody>
			</table>
			<span id="ie_workaround" name="ie_workaround" ><table><tbody><tr></tr></tbody></table></span>
__END_OF_HEADER__
;
$options .= "<br/><div><div class='alignleft'>
			<input type='button' value='Save Ordering' name='saveordering' class='button-secondary' onclick='changePlaylistOrder(); return false' />
        	</div>
			<div class='alignright'><input type='button' value='Remove' name='removeit' class='button-secondary remove' onclick='removeFromExistingPlaylist(); return false' /></div>
		  <div><br/>";
	
	return $options;
	
}


function ti_bbm_print_javascript()	{

//wp_enqueue_script ("tierra_bbm", WP_PLUGIN_URL . "/tierra-billboard-manager/js/tierra-billboard-manager.js");

	global $ti_bbm_base_query;
	

	
	$scripts =<<<__END_OF_SCRIPTS__


	<script>
	
	// Run when create_playlist is pressed
	//
	function createPlaylist()	{
		
		var nonspace = /\S/;
		
		if (jQuery('#new_playlist_name').val().search(nonspace)) {
			alert ('Please enter a name for the new playlist.');
			return;
		}
		
		jQuery.get(
					'?$ti_bbm_base_query',
					{ 	action:	'ti_bbm_add_playlist', playlist_title: jQuery('#new_playlist_name').val()},
					function(data)	{
				
						var error = "!!!ERROR!!!:";
						
						if (data.toString().match (error) )	{
							alert ("Unable to create a playlist with the given name. Does this playlist already exist?");
							return;
						}
						alert ("Created new playlist!");
						jQuery('#playlist_selection').children().remove();
						jQuery('#playlist_selection').append (data);
						swapPlaylist();
					}
		);
		return false;
		
	}
	
	function deletePlaylist()	{
		if (confirm("Are you sure you want to delete the current playlist?\\n\\n" + jQuery("#playlist_selection option:selected").text() ))	{
				jQuery.get(
					'?$ti_bbm_base_query',
					{ 	action:	'ti_bbm_delete_playlist', ti_bbm_playlist_id: jQuery('#playlist_selection').val() },
					function(data)	{
						jQuery('#playlist_selection').children().remove();
						jQuery('#playlist_selection').append (data);
						
						swapPlaylist();
						
					}
			);
			
		}	else	{
			alert ("Playlist remains active.");
		}
		return false;
	}
	
	
	
	// Run when playlist_selection is changed.
	function swapPlaylist()	{
		
		jQuery.get(
					'?$ti_bbm_base_query',
					{ 	action:	'ti_bbm_view_playlist', ti_bbm_playlist_id: jQuery('#playlist_selection').val() },
					function(data)	{	
						spoonFeedIE(data);
					}
		);
		return false;
	}


	// The following function is to accommodate Microsoft Internet Explorer's absurd implementation of
	// innerHTML, which does not work on tbody. Otherwise, a single jQuery line would suffice:
	//
	//		jQuery('tbody#playlist')[0].innerHTML = data;
	//
	// Instead, we end up with this extra function and a placeholder <span/> in the page. Nice, IE.
	//
	function spoonFeedIE (data)	{
		var temp =jQuery('#ie_workaround')[0];
		temp.innerHTML =  '<table><tbody id="playlist">' + data + '</tbody></table>';
		var tb = jQuery('tbody#playlist')[0];
		tb.parentNode.replaceChild (temp.firstChild.firstChild, tb);
		tb_init('a.dynamicthickbox');
	}

	// Used as a sort parameter to allow us to sort the tracks by value (allowing us to resort the tracks)
	function compareSortProperty(a, b) {
		return a.value - b.value;
	}


	function changePlaylistOrder()	{
		var orderCodes = jQuery("input:text.sortOrder");
		orderCodes.sort(compareSortProperty);

		var str = '';
		for (var i = 0 ; i < orderCodes.length; i++)	{
			str += (orderCodes[i].id.split('_')[1] + ','); 
		}
		
		jQuery.get(
			'?$ti_bbm_base_query',
			{ 	action:	'ti_bbm_reorder_playlist' , tracks: str, ti_bbm_playlist_id: jQuery('#playlist_selection').val() },
			function(data)	{
				spoonFeedIE(data);
			}
		);	
			
	}



	function addToExistingPlaylist()	{
		//var checks = $("#billboardForm").toggleCheckboxes(".top5", true);
		var checked = jQuery("input:checkbox:checked.addMedia");
		var str = '';
		for (var i = 0 ; i < checked.length; i++)	{
			str += (checked[i].id.split('_')[1] + ','); 
		}
		
		jQuery.get(
			'?$ti_bbm_base_query',
			{ 	action:	'ti_bbm_add_tracks_to_playlist' , tracks: str, ti_bbm_playlist_id: jQuery('#playlist_selection').val() },
			function(data)	{
				spoonFeedIE(data);
				
			}
		);
		
	}
	

	function removeFromExistingPlaylist()	{
		
		var checked = jQuery("input:checkbox:not(:checked).removeMedia");
		var str = '';
		for (var i = 0 ; i < checked.length; i++)	{
			str += (checked[i].id.split('_')[1] + ','); 
		}
		
		jQuery.get(
			'?$ti_bbm_base_query',
			{ 	action:	'ti_bbm_remove_from_playlist' , tracks: str, ti_bbm_playlist_id: jQuery('#playlist_selection').val() },
			function(data)	{
				spoonFeedIE(data);
			}
		);
		
	}
		


	
	// Run on load to populate dropdowns
	jQuery(function() {
		
					
		jQuery('.waiting').hide();
		
		jQuery.get(
					'?$ti_bbm_base_query',
					{ 	action:	'ti_bbm_get_playlist_options' },
					function(data)	{
						jQuery('#playlist_selection').append (data);
						swapPlaylist();
					}
		);
		
		
		// Q: Why not use 'change' instead of 'click' ? A: IE.
		jQuery("input[name='submissionType']").click(function()	{
			 
				// Hide all the submission type form fields...
				jQuery("input[name='submissionType']").each(function()	{
					jQuery(".ti_bbm_" + jQuery(this).val() + "FileField").hide();
				});
				// But show the relevant one
				jQuery(".ti_bbm_" + jQuery("input[@name='submissionType']:checked").val() + "FileField").show();
				
			}
		
		);
		
		// Hide the URL field to start
		jQuery('.ti_bbm_externalFileField').hide();
		
		
		
		
	});
	


	
	</script>
	
__END_OF_SCRIPTS__
;

	return $scripts;
}



function ti_bbm_print_billboard_admin() {
	ti_bbm_check_permissions(TI_BBM_LEVEL_REQUIRED, 'You do not have permission to access this page.');

	
	global $_billboard_manager_db,  $_billboard_manager_db_version, $wpdb, $ti_bbm_base_query, $ti_bbm_prev_width, $ti_bbm_prev_height;



	$playlist_dropdown = ti_bbm_list_playlists();

	// execute the form
	print "
	<div class='wrap'>

<div id='icon-options-general' class='icon32'><img src='http://tierra-innovation.com/wordpress-cms/logos/src/billboard-manager/$_billboard_manager_db_version/default.gif' alt='' title='' /><br /></div>

		<h2 style='height:64px;'>Tierra Billboard Manager $_billboard_manager_db_version</h2>

		<div>
		<p>
		$playlist_dropdown
		
		</p>	
</div>
<br/>
<br/>




	<h3>Available Billboard-compatible Media</h3>
		<p>To create a new file, add it to the section below.  Once added, you can edit it or add it to one of the playlists above.</p>

			<form id='billboardForm' method='post' enctype='multipart/form-data'>

			<style type='text/css'>
			.widefat th.delete { text-align: right !important; }
			</style>

			<table id='available_media' class='widefat'>
			<thead>
				<tr>
					
					<th scope='col'>Title</th>
					<th scope='col'>Creator</th>
					<th scope='col'>Compilation</th>
					<th scope='col'>Type</th>
					<th scope='col'>Preview/Embed code</th>
					<th scope='col'>Info</th>
					<th scope='col' class='delete'>Add</th>
				</tr>
			</thead>
			<tbody>
	";
	
		
		$sql = 'select id, post_title as track, guid, post_date, post_mime_type, post_modified from ' . $wpdb->posts . ' where post_type = "attachment" and post_mime_type rlike "flv|mp3|mp4|m4v|ogg|mpg|mpeg|image/jpeg|image/gif|image/png|application/x-shockwave-flash" order by post_title';


		$rows = $wpdb->get_results($sql);
		
	
		if ($rows) {	
			foreach ($rows as $row)	{
				$metadata = get_post_meta($row->id, '_wp_attachment_metadata', true);
				
				print "<tr class='alternate author-self status-publish' valign='top'>
						<td scope='col'>" .  htmlspecialchars(stripslashes($row->track)) . "</td>
						<td scope='col'>" .  htmlspecialchars(stripslashes($metadata['_ti_bbm_artist'])) . "</td>
						<td scope='col'>" .  htmlspecialchars(stripslashes($metadata['_ti_bbm_album'])) . "</td>
						<td scope='col'>" . $wpdb->escape($row->post_mime_type). "</td>
						<!--<td scope='col'><a href='" . $wpdb->escape($row->guid) . "' target='_blank'>" . $wpdb->escape($row->guid) ."</a></td>-->
						<td><a name=\"Billboard: " .  htmlspecialchars(stripslashes($row->track)) . "\" id=\"Billboard: " . htmlspecialchars(stripslashes( $row->track)). "\"
						class='thickbox' href='". WP_PLUGIN_URL .	"/tierra-billboard-manager/tierra-billboard-preview.php?media_id="
						. 	$wpdb->escape($row->id) . "&keepThis=true&TB_iframe=true&height=" .$ti_bbm_prev_height . "&width=" . $ti_bbm_prev_width .
						"'>Preview/Embed</a></td>
						<td scope='col'><a href='?" . $ti_bbm_base_query. "&asset_id=". $wpdb->escape($row->id) . "&action=edit' title=''>Edit</a></td>
						<td scope='col' align='right'><input  class='addMedia' id='cb_" . $wpdb->escape($row->id) . "' type='checkbox' /></td>
					</tr>
				";
			}
		}	else	{
			print "<tr>
					<th colspan='7'>No media results</th>
					</tr>";
		}
		
		
		
		print "</tbody>
			</table>

			<br class='clear'>

			<div class='tablenav'>

				<div class='alignright'>

					<input type='button' value='Add' name='addit' class='button-secondary add' onclick='addToExistingPlaylist(); return false' />
		 
				</div>

			</div>

		<!-- adding the form to add items.  The form should also serve as the edit function as well. -->

		<style type='text/css'>
			select.smore { width: 120px; }
		</style>

		<h3>Add New Media File</h3>

		<ul>
			<li>Title: <input type='text' name='track' value='' /></li>
			<li>Creator/Artist: <input type='text' name='artist' value='' /></li>
			<li>Compilation: <input type='text' name='album' value='' /></li>
			<li><strong>Now select the media you wish to add.</strong>
				<ul>
				<li><input type='radio' name='submissionType' value='upload' checked='checked' />Upload file <input class='ti_bbm_uploadFileField' type='file' name='file' value='' /></li>
			<li><input type='radio' name='submissionType' value='external' />Use existing URL* <input type='text' class='ti_bbm_externalFileField' name='externalURL' id='externalURL' size='50' value='' /></li>
			</ul></li>
		</ul>
		<br />
				<input type='submit' id ='mainSubmitButton' name='submit' class='button-primary' value='Add Media File' />
				<img src='images/wpspin_light.gif' class='waiting' />
			</form>
<p><em>*Note: Remote content must be accessible from this page, and you must have permission to use it.</em></p>
		</div></div></div>

	";

}


function ti_bbm_return_playlist_xml($ti_bbm_playlist_id)	{
	
	header('Location: ' . WP_PLUGIN_URL . '/tierra-billboard-manager/tierra-billboard-playlist.php?id=' . $ti_bbm_playlist_id);
	exit;

}



function ti_bbm_update_existing_asset ()	{
	
	ti_bbm_check_permissions( TI_BBM_LEVEL_REQUIRED, 'You do not have permission to edit existing assets.');
	
		
	global $_billboard_manager_db, $wpdb;
	
	
	
	$post = array(
		'ID' =>  $wpdb->escape($_POST['post_id']),
		'post_title' => $wpdb->escape($_POST['track']), 
		'post_name' => $wpdb->escape($_POST['track']),
		'post_author' => $user_ID,
		'ping_status' => get_option('default_ping_status'),
		'post_excerpt' => $wpdb->escape($_POST['description']),
		'post_content' => $wpdb->escape($_POST['caption']),
		'guid' =>  $wpdb->escape($_POST['guid']),

	  );

	$pID = wp_update_post($post);
	
	$metadata =  array (
		'_ti_bbm_album' => $wpdb->escape($_POST['album'] ),
		'_ti_bbm_artist' => $wpdb->escape($_POST['artist'] ),
		'_ti_bbm_linkTo' => $wpdb->escape($_POST['linkTo'] )
		
	);
	
		  
	update_post_meta(  $pID, '_wp_attachment_metadata',$metadata) or add_post_meta( $pID, '_wp_attachment_metadata', $metadata);
	
}


function ti_bbm_edit_existing_asset ($asset_id)	{
	ti_bbm_check_permissions(TI_BBM_LEVEL_REQUIRED, 'You do not have permission to edit existing assets.');

	
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query;
	
	$sql = 'select id, post_title as track, guid, post_mime_type, post_content, post_excerpt, post_date, post_modified from ' . $wpdb->posts . ' where id = "' . intval($asset_id) . '"';

	$row = $wpdb->get_row($sql);
	
	if ($row) {	
		$metadata = get_post_meta($row->id, '_wp_attachment_metadata', true);
	}

	
	
	if ( ( $row->id = intval($row->id) ) && $thumb_url = get_attachment_icon_src( $row->id ) )
		$thumb_url = $thumb_url[0];
	else {
		$wpuploads = wp_upload_dir();
		if ($metadata['file'])	{
			$path_parts = pathinfo($metadata['file']);
			$datepath = $wpuploads['baseurl'] . "/" .$path_parts['dirname'];
		}
		$thumb_url = $metadata['sizes']['thumbnail']['file']
			?	($datepath  . '/' . stripslashes($metadata['sizes']['thumbnail']['file']) )
			:	"/wp-includes/images/crystal/interactive.png";
	}
	
	$track = htmlspecialchars(stripslashes($row->track));
	$album = htmlspecialchars(stripslashes($metadata[_ti_bbm_album]));
	$artist = htmlspecialchars(stripslashes($metadata[_ti_bbm_artist]));
	$linkTo = stripslashes($metadata[_ti_bbm_linkTo]);
	$description = htmlspecialchars(stripslashes($row->post_excerpt));
	
	print<<<_END_OF_FORM
	<div class="wrap">
	<?php screen_icon(); ?>
<h2>Edit Media</h2>

	<form method="post" action="?$ti_bbm_base_query&action=update&asset_id=$row->id" class="media-upload-form" id="media-single-form">
<div class="media-single">
<div id='media-item' class='media-item'>

	
	
	<input type="hidden" name="attachments" value="0" />
	
	<table class='slidetoggle describe form-table'>

		<thead class='media-item-info'>
		<tr>
			<td class='A1B1' rowspan='4'><img class='thumbnail' src='$thumb_url' alt='' /></td>
			<td>$row->guid</td>
		</tr>
		<tr><td>$row->post_mime_type</td></tr>
		<tr><td>$row->post_modified</td></tr>

		<tr><td></td></tr>
		</thead>
		<tbody>
		<tr class='post_title'>
			<th valign='top' scope='row' class='label'><label for='track'><span class='alignleft'>Title</span><span class='alignright'></span><br class='clear' /></label></th>
			<td class='field'><input type='text' id='track' name='track' value="$track"/></td>
		</tr>
		
		<tr class='post_title'>
			<th valign='top' scope='row' class='label'><label for='artist'><span class='alignleft'>Artist/Creator</span><span class='alignright'></span><br class='clear' /></label></th>
			<td class='field'><input type='text' id='artist' name='artist' value="$artist"/></td>
		</tr>	

		<tr class='post_title'>
			<th valign='top' scope='row' class='label'><label for='album'><span class='alignleft'>Compilation</span><span class='alignright'></span><br class='clear' /></label></th>
			<td class='field'><input type='text' id='album' name='album' value="$album"/></td>
		</tr>
		
		<tr class='post_excerpt'>

			<th valign='top' scope='row' class='label'><label for='post_content'><span class='alignleft'>Link billboard item to:</span><span class='alignright'></span><br class='clear' /></label></th>
			<td class='field'><input type='text' id='linkTo'  name='linkTo' value="$linkTo"/></td>
		</tr>
		<tr class='post_content'>
			<th valign='top' scope='row' class='label'><label for='description'><span class='alignleft'>Description/Caption</span><span class='alignright'></span><br class='clear' /></label></th>
			<td class='field'><textarea type='text' id='description' name='description'>$description</textarea></td>
		</tr>

		<tr class='image_url'>
			<th valign='top' scope='row' class='label'><label for='guid'><span class='alignleft'>File URL</span><span class='alignright'></span><br class='clear' /></label></th>
			<td class='field'><input type='text' class='urlfield' readonly="readonly" name='guid' value="$row->guid" /><br /><p class='help'>Location of the uploaded file.</p></td>
		</tr>
	</tbody>
	</table>
</div>
</div>

<p class="submit">
<input type="submit" class="button-primary" name="save" value="Update Media" />
<input type="hidden" name="post_id" id="post_id" value="$row->id" />
</form>
</div>


_END_OF_FORM
;
	
	
}


function ti_bbm_return_playlist_html ($ti_bbm_playlist_id)	{
	global $_billboard_manager_db, $wpdb, $ti_bbm_base_query,  $ti_bbm_prev_width, $ti_bbm_prev_height;

	$sql = 'select tracks, title from  ' . $_billboard_manager_db . ' where id = ' . $wpdb->escape($ti_bbm_playlist_id);
	
	$tracklist = $wpdb->get_row($sql);
	
	$tracks = split (',' , $tracklist->tracks);
	$i = 0;

	if ($tracklist->tracks)	{
		foreach ($tracks as $track)	{ 
			$sql = 'select id, post_title as track, guid, post_mime_type, post_date, post_modified from ' . $wpdb->posts . ' where id = ' . $track;
	
			$row = $wpdb->get_row($sql);
			
			if ($row) {
				$metadata = get_post_meta($row->id, '_wp_attachment_metadata', true);
					print "<tr class='alternate author-self status-publish' valign='top'>
						<td class='manage-column column-cb check-column' scope='col'><input type='text' id='sort_" . $wpdb->escape($row->id) . "' class='sortOrder'  value='" . ++$i ."' size='2'></td>
						<td scope='col'>" . htmlspecialchars(stripslashes($row->track)) . "</td>
						<td scope='col'>" . htmlspecialchars(stripslashes($metadata['_ti_bbm_artist'])) . "</td>
					
						<td scope='col'>" . htmlspecialchars(stripslashes($metadata['_ti_bbm_album'])) . "</td>
						<td scope='col'>" . htmlspecialchars($wpdb->escape($row->post_mime_type)). "</td>
<td><a name=\"Billboard: " . htmlspecialchars(stripslashes( $row->track)). "\" id=\"Billboard: " . htmlspecialchars(stripslashes($row->track)) . "\"
						class='dynamicthickbox thickbox' href='". WP_PLUGIN_URL .	"/tierra-billboard-manager/tierra-billboard-preview.php?media_id="
						. 	$wpdb->escape($row->id) . "&keepThis=true&TB_iframe=true&height=" .$ti_bbm_prev_height . "&width=" . $ti_bbm_prev_width .
						"'>Preview/Embed</a></td>

<!--						<td scope='col'><a href='" . $wpdb->escape($row->guid) ."' target='_blank'>" . $wpdb->escape($row->guid) ."</a></td> -->
						<td scope='col'><a href='?" . $ti_bbm_base_query. "&asset_id=". $wpdb->escape($row->id) . "&action=edit' title=''>Edit</a></td>
						<td scope='col' align='right'><input type='checkbox' id='cb_" . $wpdb->escape($row->id) . "' class='removeMedia'  /></td>
					</tr>
				";

			}
		}
	}	else	{
		print "<tr>
				<th colspan='8'>No playlist results</th>
				</tr>";
	}
	print "<tr><th colspan='2'><a target='_blank' href='" . WP_PLUGIN_URL ."/tierra-billboard-manager/tierra-billboard-playlist.php?preview=true&id=$ti_bbm_playlist_id' title=''>Download XML File</a> (Right-click and copy URL to use elsewhere)";
	print "<th colspan='6'>";
	
	print '<a class="dynamicthickbox thickbox alignright" name="Tracklist: ' . htmlspecialchars(stripslashes($tracklist->title)) . '" id="Tracklist: ' . htmlspecialchars(stripslashes($tracklist->title)) . '" href="'
			.  	WP_PLUGIN_URL 
			.	'/tierra-billboard-manager/tierra-billboard-preview.php?name='
			.  urlencode($tracklist->title)
			. '&ti_bbm_playlist_id='
			. 	$ti_bbm_playlist_id
			. '&keepThis=true&TB_iframe=true&height=' . $ti_bbm_prev_height . '&width=' . $ti_bbm_prev_width . '">Preview / Adjust Player</a>';
	
	
	print "</th></tr>";

}



function ti_bbm_upload_files()	{
	global $_billboard_manager_db, $wpdb;

	ti_bbm_check_permissions(7, 'You do not have permission to upload files.');
	
	$wpdir = wp_upload_dir();
	$submissionType = strtolower($_POST['submissionType']);

	if ($_FILES['file'])	{
		
		$uploaded_file = $_FILES['file']['tmp_name'];
		
		if ($submissionType == 'upload' && isset ($_FILES['file']['error']) && $_FILES['file']['error'] > 0)	{
			echo '<div id="message" class="error fade"><p>UPLOAD ERROR: ' . ti_bbm_file_upload_error_message($_FILES['file']['error']) . ' </p></div>';
			return;
		};
		
	
		$pContent = '';

		// Move the file to the correct location within the WP install
		if ($submissionType == 'upload' && isset($_FILES['file']['name']) && $_FILES['file']['name'] > '' )	{
			
			$newfile = wp_upload_bits( $_FILES['file']['name'], null,  file_get_contents($_FILES["file"]["tmp_name"] ));
			
		}	else {

			if ($submissionType == 'external')	{
					$info = get_headers(esc_url($_POST['externalURL']), 1);
					echo '<div id="message" class="updated fade"><p>URL entered. Please ensure <a target="_blank" href="' . esc_url($_POST['externalURL']) .'">'. esc_url($_POST['externalURL'])  . '</a> is the correct URL for your external media.</p><p>The remote server says the current mime type of this file is ' . $info['Content-Type'] .'. Please note that if this is not correct, the URL may not show up within the Billboard Player.</p><p>Also, please note that remote files may only be used if the remote server includes this server within its crossdomain.xml file.</div>';
					
						
					$newfile = array ( 'url' => esc_url($_POST['externalURL']) , 'file' => array('name' =>esc_url($_POST['externalURL']), type => $info['Content-Type'], basedir=>esc_url($_POST['externalURL']), baseurl=>esc_url($_POST['externalURL']))  );
					
					
					// Otherwise, let's note the error
			}
		}
		
		if ($newfile->error)	{

			echo '<div id="message" class="error fade"><p>ERROR! Unable to create new file.</p></div>';
			
		}	else {
			echo '<div id="message" class="updated fade"><p>Media successfully added to collection.</p></div>';
		}
		
	}

	$attachment= array(
		'post_content' => '',
		'post_title' => $wpdb->escape($_POST['track']) ? $wpdb->escape($_POST['track']) : 'Unknown', 
		'post_name' => $wpdb->escape($_POST['track']) ? $wpdb->escape($_POST['track']) : 'Unknown',
		'post_mime_type' => $wpdb->escape($_FILES['file']['type']) ? $wpdb->escape($_FILES['file']['type']) : $wpdb->escape($info ? $info['Content-Type'] : 'unknown'),
		'post_author' => $user_ID,
		'post_type' => 'attachment',
		'post_status' => 'inherit',
		'post_parent' => 0,
		'menu_order' => 0,
		
		'guid' =>  $newfile['url']
		
	  );

	$ti_metadata =  array (
		'_ti_bbm_album' => $wpdb->escape($_POST['album']) ? $wpdb->escape($_POST['album'] ) : 'Unknown',
		'_ti_bbm_artist' => $wpdb->escape($_POST['artist'])? $wpdb->escape($_POST['artist'] ) : 'Unknown',
		'_ti_bbm_linkTo' => $wpdb->escape($_POST['linkTo'])? $wpdb->escape($_POST['linkTo'] ) : ''
	);

	
		
	
	if ($submissionType == 'upload')	{
		$attach_id = wp_insert_attachment( $attachment, $newfile['file'] );
		$attach_data = wp_generate_attachment_metadata( $attach_id, $newfile['file']  );
		$combined_metadata = array_merge($attach_data, $ti_metadata);
	}	else {
		$attach_id = wp_insert_post( $attachment );
		$combined_metadata = $ti_metadata;
	}
	
	//var_dump ($combined_metadata);
	wp_update_attachment_metadata( $attach_id,  $combined_metadata );


}


	
// This is for shortcode use..	
// Skin is the path to the swf MINUS the '.swf' extension! (although if present, it's removed automatically)
function ti_bbm_print_player ($atts)	{
	global $_billboard_manager_db, $wpdb;
	
	
	wp_enqueue_script ("tierra_bbm", WP_PLUGIN_URL . "/tierra-billboard-manager/js/tierra-billboard-manager.js");
	
	// Get the default colors...
	$sql = 'select id, autoPlay, themeURL, backgroundColor, navBarColor, glowColor, showTitles, showRollovers, repeating, useOverlay, delay, titleBGColor, titleFGColor, justifyTitle, width, height, showThumbnail, keepTitles, titlesOnRollover, modification_date from  ' . $_billboard_manager_db . ' where title = "___DEFAULT___" limit 1';
	$defaultPlaylist = $wpdb->get_row($sql);
	

	
	extract(shortcode_atts(array(  
        "name" => "",
		"media" => "",
		"id" => 1,
		"background" => null,
		"navigation" =>  null,
		"titlebarcolor" => null,
		"titletextcolor" => null,
		"glow" => null,
		"skin" => WP_PLUGIN_URL . "/tierra-billboard-manager/swf/ti-billboard.swf",
		"theme" => WP_PLUGIN_URL . "/tierra-billboard-manager/skin",
		"autoplay" => null,
		"rollovers" => null,
		"thumbnails" => null,
		"keeptitles" => null,
		"titlesonrollover" => null,
		"justify" => null,
		"titles" => null,
		"volume" => null,
		"repeat" => null,
		"delay" => null,
		"width" =>  null,
		"height" =>  null,
		"overlay" => null,
		"title"	=> "Playlist managed by Tierra Billboard Manager"
		
    ), $atts));
		

	// IF WE'VE BEEN PROVIDED A PLAYLIST NAME, LET'S USE THIS TO GLEAN THE CORRECT PLAYLIST ID
	if ($name != "")	{
		$sql = 'select id, autoPlay, themeURL, backgroundColor, navBarColor, glowColor, showTitles, showRollovers, repeating, useOverlay, delay, titleBGColor, titleFGColor, justifyTitle, width, height, showThumbnail, keepTitles, titlesOnRollover, modification_date from  ' . $_billboard_manager_db . ' where title = "' . addslashes($wpdb->escape($name)) . '" limit 1';
		
		$specificPlaylist = $wpdb->get_row($sql);
		
		$playlist = (object)( isset ($defaultPlaylist) ?  array_merge((array)$defaultPlaylist, (array)$specificPlaylist) : $specificPlaylist );
		
		$ti_bbm_playlist_id= $playlist->id;
		$autoplay = isset($autoplay) ? $autoplay : $playlist->autoPlay;
		$theme = isset($theme) ? $theme : $playlist->themeURL ;
		$navigation = isset($navigation) ? $navigation : $playlist->navBarColor ;
		$glow = isset($glow) ? $glow :$playlist->glowColor ;
		$background = isset($background )? $background : $playlist->backgroundColor;
		$repeat = isset($repeat) ? $repeat : $playlist->repeating ;
		//$title = isset($title) ? $title : $playlist->showTitles ;
		$titles = isset($titles) ? $titles : 0 ;
		$rollovers =  isset($rollovers) ?  $rollovers : $playlist->showRollovers ;
		$repeat = isset($repeat) ? $repeat : $playlist->repeating;
		$overlay = isset($overlay) ? $overlay : $playlist->useOverlay ;
		$delay =  isset($delay) ?  $delay : $playlist->delay ;
		$titlebarcolor =  isset($titlebarcolor) ?  $titlebarcolor : $playlist->titleBGColor ;
		$titletextcolor = isset($titletextcolor) ? $titletextcolor : $playlist->titleFGColor;
		$justify = isset($justify) ? $justify : $playlist->justifyTitle;
		$width = $width > 0 ? $width : $playlist->width;
		$height =  $height > 0 ?  $height : $playlist->height;
		$thumbnails = isset($thumbnails) ?  $thumbnails : $playlist->showThumbnail;
		$keeptitles = isset ($keeptitles) ? $keeptitles : $playlist->keepTitles;
		$titlesonrollover = isset ($titlesonrollover) ? $titlesonrollover : $playlist->titlesOnRollover;
		
	}	else {
		$ti_bbm_playlist_id= intval($id);
	}
	
	$media_id = "" ? "" : intval($media);
	
	$playlistURL = WP_PLUGIN_URL . "/tierra-billboard-manager/tierra-billboard-playlist.php?id=$ti_bbm_playlist_id&media_id=$media_id";
	
	$player =  preg_replace('/\.swf$/', '', $skin);
	$playerURL = $player . '.swf';
	
	$acURL =  WP_PLUGIN_URL ."/tierra-billboard-manager/js/AC_RunActiveContent.js";
	$flashvars =  (isset($autoplay) ? "autoplay=" . urlencode($autoplay) : "")
				. (isset($autoload) ? "&autoload=" . urlencode($autoload) : "")
				. (isset($repeat) ? "&repeat_playlist=" . urlencode($repeat) : "")
				. (isset($title) ? "&player_title=" . urlencode($title) : "")
				. (isset($volume) ? "&volume=" . urlencode($volume) : "")
				. (isset($delay) ? "&delay=" .urlencode($delay) : "")
				. (isset($overlay) ? "&overlay=" .urlencode($overlay) : "")
				. (isset($theme) ? "&theme_url=" .urlencode($theme) : "")
				. (isset($justify) ? "&textAlign=" .urlencode($justify) : "")
				. (isset($glow) ? "&glowColor=0x" .urlencode($glow) : "")
				. (isset($titlebarcolor) ? "&titleBarColor=0x" . urlencode($titlebarcolor) : "")
				. (isset($titletextcolor) ? "&titleTextColor=0x" . urlencode($titletextcolor) : "")
				. (isset($navigation) ? "&navBarColor=0x" . urlencode($navigation) : "")
				. (isset($ti_bbm_playlist_id) ? "&id=" . ("player_" . $ti_bbm_playlist_id) : "")
				. (isset($playlistURL) ? "&playlist_url=" . urlencode($playlistURL) : "")
				. (isset($rollovers) ? "&rollovers=" .urlencode($rollovers) : "")
				. (isset($titles) ? "&titles=" .urlencode($titles) : "")
				. (isset($thumbnails) ? "&thumbnails=" .urlencode($thumbnails) : "")
				. (isset($keeptitles) ? "&keepTitles=" . urlencode($keeptitles) : "")
				. (isset($titlesonrollover) ? "&titlesOnRollover=" . urlencode($titlesonrollover) : "")
	;
				
	$response=<<<__END_PLAYER_CODE__
	
	<script language="javascript">
	
	

	
	AC_FL_RunContent(
		'id', 'player_$ti_bbm_playlist_id',
		'class',  'ti_billboard_player',
		'codebase', 'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0',
		'width', '$width',
		'height', '$height',
		'src', '$player',
		'quality', 'high',
		'pluginspage', 'http://www.macromedia.com/go/getflashplayer',
		'align', 'middle',
		'play', 'true',
		'loop', 'true',
		'scale', 'showall',
		'wmode', 'opaque',
		'devicefont', 'false',
		
		'bgcolor', '$background',
		'name', 'player_$ti_bbm_playlist_id',
		'menu', 'true',
		'allowFullScreen', 'true',
		'allowScriptAccess','always',
		'flashvars', '$flashvars',
		'movie', '$player',
		'salign', ''
	); //end AC code
	
	// Add to the queue of controlled players, so we can turn it down when another player takes focus...
	ti_bbm_addPlayer('player_$ti_bbm_playlist_id');
	</script>
	
		
		<noscript>
				<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0" width="$width" height="$height" id="player_$ti_bbm_playlist_id" name="player_$ti_bbm_playlist_id"  align="middle">
				<param name="allowScriptAccess" value="always" />
				<param name="allowFullScreen" value="true" />
				<param name="movie" value="$playerURL?$flashvars" /><param name="quality" value="high" /><param name="bgcolor" value="$background" />
				<embed src="$playerURL?$flashvars" quality="high" bgcolor="$background" width="$width" height="$height" id="player_$ti_bbm_playlist_id"  name="player_$ti_bbm_playlist_id" align="middle" allowScriptAccess="always" allowFullScreen="true" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
				</object>
			</noscript>


__END_PLAYER_CODE__
;

return $response;

}



// USe post data to update the existing playlist
function ti_bbm_update_playlist ()	{
	
	global $_billboard_manager_db, $wpdb;
	
	$id = $wpdb->escape($_POST['ti_bbm_playlist_id']);
	$autoPlay =  ti_bbm_booleanValue(($_POST['autoPlay']));
	$backgroundColor = $wpdb->escape($_POST['backgroundColor']);
	$navBarColor = $wpdb->escape($_POST['navBarColor']);
	$glowColor =  $wpdb->escape($_POST['glowColor']);
	$showTitles = ti_bbm_booleanValue($_POST['showTitles']);
	$showRollOvers = ti_bbm_booleanValue($_POST['showRollOvers']);
	$repeat = ti_bbm_booleanValue($_POST['repeat']);
	$useOverlay = ti_bbm_booleanValue($_POST['useOverlay']);
	$delay = $wpdb->escape($_POST['delay']);
	$titleBGColor = $wpdb->escape($_POST['titleBGColor']);
	$titleFGColor = $wpdb->escape($_POST['titleFGColor']);
	$justifyTitle = $wpdb->escape($_POST['justifyTitle']);
	$width = $wpdb->escape($_POST['width']);
	$height = $wpdb->escape($_POST['height']);
	$showThumbnail = ti_bbm_booleanValue($_POST['showThumbnail']);
	$keepTitles = ti_bbm_booleanValue($_POST['keepTitles']);
	$titlesOnRollover = ti_bbm_booleanValue($_POST['titlesOnRollover']);
	
	if ($id == '-1')	{
		$sql = 'select id from  ' . $_billboard_manager_db . ' where title = "___DEFAULT___" limit 1';
		$id = $wpdb->get_var($sql);
	}
	
	$sql=<<<__END_OF_SQL__
			UPDATE $_billboard_manager_db
			SET	autoPlay	=	"$autoPlay",
				backgroundColor = "$backgroundColor",
				navBarColor =	"$navBarColor",
				glowColor 	=	"$glowColor",
				showTitles 	=	"$showTitles",
				showRollOvers = "$showRollOvers",
				repeating 	=		"$repeat",
				useOverlay 	=	"$useOverlay",
				delay 		=	"$delay",
				titleBGColor =	"$titleBGColor",
				titleFGColor = "$titleFGColor",
				justifyTitle = "$justifyTitle",
				keepTitles =  "$keepTitles",
				width	= 	"$width",
				height	=	"$height",
				showThumbnail = "$showThumbnail",
				titlesOnRollover = "$titlesOnRollover",
				modification_date = now()
			WHERE
				id = "$id"
__END_OF_SQL__
;

	$result = $wpdb->query($sql);


	if ($result > 0)	{
		echo "Settings successfully saved.";
		return;
	}	else	{
		echo "ERROR! Cannot add/modify attributes for selected playlist.";
		return;
	}
					
}


function ti_bbm_booleanValue($val, $defaultValue = false) {
	if (!isset($val))	{
		$val = $defaultValue;
	}
	$isBoolean = 0;
	switch ( strtolower($val) )	{
		case "1":
		case "true":
		case "yes":
		case "ok":
		case "y":
		case "on":
		case "enabled":
			$isBoolean = 1;
		break;
	}
	return $isBoolean;  
}

function ti_bbm_file_upload_error_message($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}



if (!function_exists('get_headers')) {
function get_headers($url, $format=0) {
    $headers = array();
    $url = parse_url($url);
    $host = isset($url['host']) ? $url['host'] : '';
    $port = isset($url['port']) ? $url['port'] : 80;
    $path = (isset($url['path']) ? $url['path'] : '/') . (isset($url['query']) ? '?' . $url['query'] : '');
    $fp = fsockopen($host, $port, $errno, $errstr, 3);
    if ($fp)
    {
        $hdr = "GET $path HTTP/1.1\r\n";
        $hdr .= "Host: $host \r\n";
        $hdr .= "Connection: Close\r\n\r\n";
        fwrite($fp, $hdr);
        while (!feof($fp) && $line = trim(fgets($fp, 1024)))
        {
            if ($line == "\r\n") break;
            list($key, $val) = explode(': ', $line, 2);
            if ($format)
                if ($val) $headers[$key] = $val;
                else $headers[] = $key;
            else $headers[] = $line;
        }
        fclose($fp);
        return $headers;
    }
    return false;
}
}

?>