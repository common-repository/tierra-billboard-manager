<?php
header("Content-Type: text/html");
/*
if (isset($_GET['preview']) && $_GET['preview'] == 'true' )	{
	header("Content-Type: application/xml;charset=utf-8");
}	else	{
	header("Content-Type: application/xspf+xml;charset=utf-8");

}
*/


require_once('../../../wp-config.php');
require_once('../../../wp-settings.php');

global $wpdb, $_billboard_manager_db_version, $_billboard_manager, $baseurl, $pluginurl;

$_billboard_manager = $wpdb->prefix . "ti_billboard_manager";

$playlist_id = intval($_GET['id']);

$media_id = isset($_GET['media_id']) ? intval($_GET['media_id']) : -1;

$baseurl =  $_SERVER["QUERY_STRING"];

$pluginURL = WP_PLUGIN_URL;

if ($media_id <= 0)	{

	$sql = 'select title, image, tracks, creation_date, license from  ' . $_billboard_manager . ' where id = ' . $wpdb->escape($playlist_id);

}	else	{
	
	$sql = 'select id, post_title as title, "' . $media_id . '" as tracks, post_date as creation_date  from ' . $wpdb->posts . ' where id = ' . $media_id; 
	
}

$row = $wpdb->get_row($sql);

$license = $row->license ? htmlentities($row->license) : '';

$title = htmlentities(stripslashes($row->title));
$tracks = split (',' , $row->tracks);
$i = 0;


echo<<<__END_OF_HEADER__
<?xml version="1.0" encoding="UTF-8"?>
<playlist version="1" xmlns = "http://xspf.org/ns/0/">
	<title>$title</title>
	<creator>Tierra Billboard Manager</creator>
	<annotation>Playlist generated via Tierra Billboard Manager, part of the Tierra WordPress CMS Toolkit</annotation>
	<info>http://tierra-innovation.com/wordpress-cms/</info>
	<image>$pluginURL/tierra-billboard-manager/skin/brand.png</image>
	<license>$license</license>
	<date>$row->creation_date</date>
	<trackList>
__END_OF_HEADER__
;

$wpuploads = wp_upload_dir();

if ($row->tracks)	{
	foreach ($tracks as $track)	{ 
		$sql = 'select id, post_title as track, guid, post_date, post_excerpt, post_modified from ' . $wpdb->posts . ' where id = ' . $track;

		$row = $wpdb->get_row($sql);
		
		if ($row) {
			$metadata = get_post_meta($row->id, '_wp_attachment_metadata', true);
	
	
			if ( ( $row->id = intval($row->id) ) && $thumb_url = get_attachment_icon_src( $row->id ) )
				$thumb_url =  htmlspecialchars($thumb_url[0]);
			else {
				$wpuploads = wp_upload_dir();
				if ($metadata['file'])	{
					$path_parts = pathinfo($metadata['file']);
					$datepath = $wpuploads['baseurl'] . "/" .$path_parts['dirname'];
				}
				$thumb_url =  htmlspecialchars($metadata['sizes']['thumbnail']['file']
					?	($datepath  . '/' . stripslashes($metadata['sizes']['thumbnail']['file']) )
					:	"/wp-includes/images/crystal/interactive.png");
			}
		
	
			
			print "
		<track>
			<location>" . ( $row->guid ? htmlspecialchars($row->guid) : ( $wpuploads['baseurl'] . '/' . $metadata['file']  ))."</location>
			
			<creator>" .( $metadata['_ti_bbm_artist'] ? htmlspecialchars($metadata['_ti_bbm_artist']) : "" )."</creator>
			<album>" . ( $metadata['_ti_bbm_album'] ? htmlspecialchars($metadata['_ti_bbm_album']) : "" ). "</album>
			<image>$thumb_url</image>
			<title>" . ( $row->track ? htmlspecialchars($row->track) : "No title" ) . "</title>
			<annotation>Type:" .$wpdb->escape($row->post_mime_type) .";</annotation>
			<info>" . $wpdb->escape($metadata['_ti_bbm_linkTo']) ."</info>
			<trackNum>" .  $wpdb->escape($metadata['_ti_bbm_tracknum']) ."</trackNum>
			<duration>" . $wpdb->escape($metadata['_ti_bbm_duration'])  ."</duration>
			<meta><description>" . htmlspecialchars(stripslashes($row->post_excerpt)) . "</description></meta>
			
		</track>
				";			
		}
	}
}



print<<<__END_OF_XML__

	</trackList>
</playlist>	


__END_OF_XML__
;

?>

