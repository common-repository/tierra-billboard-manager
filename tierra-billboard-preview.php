<?php
require_once('../../../wp-config.php');
require_once('../../../wp-settings.php');


echo'<html><head><title>Tierra Billboard Preview</title>';

wp_enqueue_style( 'jqueryui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/ui-lightness/jquery-ui.css' );

wp_enqueue_style( 'previewcss', get_option('siteurl') . '/wp-admin/load-styles.php?c=1&dir=ltr&load=global,wp-admin' );

wp_deregister_script('jquery-ui-core'); //deregister current jquery
wp_register_script('jquery-ui-core', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/jquery-ui.min.js', true, '1.7.2', false); //load jquery from google api, and place in header
wp_enqueue_script('jquery-ui-core');
wp_enqueue_script( 'jqueryaccordion', WP_PLUGIN_URL . '/tierra-billboard-manager/js/ui/ui.accordion.js' );


wp_enqueue_script( 'swfobject' );

wp_enqueue_script( 'jquery' );
wp_enqueue_script ("jscolor", WP_PLUGIN_URL . "/tierra-billboard-manager/js/jscolor/jscolor.js");
wp_enqueue_script ("tierra_bbm", WP_PLUGIN_URL . "/tierra-billboard-manager/js/tierra-billboard-manager.js");
wp_head();

global  $wpdb, $_billboard_manager_db_version, $_billboard_manager_db, $ti_bbm_base_query, $userdata,  $ti_bbm_prev_width, $ti_bbm_prev_height;

$_billboard_manager_db = $wpdb->prefix . "ti_billboard_manager";

$media_id =  htmlspecialchars($_REQUEST['media_id']);
$playlist_id = htmlspecialchars($_REQUEST['ti_bbm_playlist_id']);
$playlist_url =  urlencode( WP_PLUGIN_URL . "/tierra-billboard-manager/tierra-billboard-playlist.php?media_id=$media_id&id=$playlist_id");

$embedcode = '[ti_billboard ';

if ($media_id > 0)	{
	$embedcode .= "media=\"" . $media_id ."\"";
}	elseif ($_REQUEST['name'] !="") {
	$embedcode .= "name=\"" . htmlspecialchars(stripslashes($_REQUEST['name'])) . "\"";
} else{
	$embedcode .= "name=\"" . $playlist_id . "\"";
}


/* **** LET'S GET THE SAVED DATA FOR THIS PIECE **** */

if (isset($playlist_id))	{
		$sql = 'select id, autoPlay, backgroundColor, navBarColor, glowColor, showTitles, showRollovers, repeating, useOverlay, delay, titleBGColor, titleFGColor, justifyTitle, width, height, showThumbnail from  ' . $_billboard_manager_db . ' where title = "___DEFAULT___" limit 1';
		$defaultPlaylist = $wpdb->get_row($sql);
		$sql = 'select id, autoPlay, backgroundColor, navBarColor, glowColor, showTitles, showRollovers, repeating, useOverlay, delay, titleBGColor, titleFGColor, justifyTitle, width, height, showThumbnail, modification_date from  ' . $_billboard_manager_db . ' where id = "' . $playlist_id .'" limit 1';
		$specificPlaylist = $wpdb->get_row($sql);
		$playlist = isset ($defaultPlaylist) ?  array_merge((array) $defaultPlaylist, (array) $specificPlaylist): (array)$specificPlaylist;

		$autoplay = $playlist['autoPlay'];
		$navigation =$playlist['navBarColor'];
		$glow = $playlist['glowColor'];
		$repeat = $playlist['repeating'];
		$title = $playlist['showTitles'];
		$rollovers =  $playlist['showRollovers'];
		

		$overlay = $playlist['useOverlay'];
		
		$delay = $playlist['delay'];
		$titlebarcolor =  $playlist['titleBGColor'];
		$titletextcolor = $playlist['titleFGColor'];
		$backgroundColor = $playlist['backgroundColor'];
		$justify =  $playlist['justifyTitle'];
		$width = $playlist['width'];
		$height = $playlist['height'];
		$thumbnails =  $playlist['showThumbnail'];
		$keeptitles = $playlist['keepTitles'];
		$titlesonrollover = $playlist['titlesOnRollover'];
		
		$cb_thumbnails =  checkBoxValueOfBoolean($thumbnails);
		$cb_title =  checkBoxValueOfBoolean($title);
		$cb_rollovers = checkBoxValueOfBoolean($rollovers);
		$cb_repeat = checkBoxValueOfBoolean($repeat);
		$cb_autoplay = checkBoxValueOfBoolean($autoplay);
		$cb_overlay =  checkBoxValueOfBoolean($overlay);
		$cb_keeptitles =  checkBoxValueOfBoolean($keeptitles);
		$cb_titlesonrollover =  checkBoxValueOfBoolean($titlesonrollover);

		
}

/* ************************ */


$just = array(	'left' => '',
				'center' => '',
				'right' =>''
);
$just[strtolower($playlist['justifyTitle'])] = 'selected';

$selL = $just['left'];
$selR = $just['right'];
$selC = $just['center'];
		
$pluginURL = WP_PLUGIN_URL;

// If there's a better way to get this, I'd love to know it.
$adminURL = get_option('siteurl') . '/wp-admin/tools.php?page=tierra-billboard-manager.php';

echo<<<__END_OF_PREVIEW___
	<style>
	body {
		margin:0;
		padding:0;
		height:95%;
	}
	
	.tierraPlayer {
		width:100%;
		height:280px;
		margin:10px auto;
		padding:0;
		text-align:center;
		background-color:$backgroundColor;
	}
	.centerLine	{
		width:600px;
		margin:10px auto;
		text-align:center;
		line-height:2.1em;
		
	}
	.embedCode {
		width:550px;
		height:75px;
		text-align:center;
	}
	
	</style>
	
	<script language="javascript">AC_FL_RunContent = 0;</script>

	
	<script src="$pluginURL/tierra-billboard-manager/js/AC_RunActiveContent.js" language="javascript"></script>
	</head>
	<body>

	<div class="tierraPlayer" id="tierraPlayerBG"><div id="tierraPlayer">Flash not loaded.</div></div>
	
	<script language="javascript">

		// These are the strings that make up the shortcode...
		var autoplayStatus = '';
		var repeatStatus = '';
		var backgroundColor = '';
		var navigationColor = '';
		var titleBarColor = '';
		var volume = '';
		var delay = '';
		var rolloverStatus = '';
		var titleStatus = '';
		var overlayStatus = '';
		var glowColor = '';
		var titleTextColor = '';
		var thumbnailStatus = '';
		var textJustification = '';
		var widthVal = '';
		var heightVal = '';
		var keepTitleStatus = '';
		var titleRolloverStatus = '';
		
		// End Strings
		
		var flashvars = {
			delay: delay,
			autoplay: '$autoplay',
			repeat_playlist: '$repeat',
			playlist_url: "$playlist_url",
			overlay: '$overlay',
			title: '$title',
			delay: '$delay',
			titleBarColor: '0x$titlebarcolor',
			titleTextColor: '0x$titletextcolor',
			textAlign: '$justify',
			theme:  "$pluginURL/tierra-billboard-manager/skin",
			thumbnails: '$thumbnails',
			navBarColor:'0x$navigation',
			rollovers: '$rollovers',
			glowColor: '0x$glow',
			keepTitleStatus: '$keeptitles',
			titleRolloverStatus: '$titlesonrollover'
			
		}
		
		var params = {
			salign: '',
			allowscriptaccess: 'sameDomain',
			allowFullScreen: true,
			menu: true,
			bgcolor: '#ffffff',
			devicefont: false,
			wmode: 'transparent',
			quality: 'high',
			play: true,
			loop: true,
			scale: 'showall'
		}
		var attributes = {
			id: 'ti-billboard',
			name: 'ti-billboard',
			align: 'middle'
		}
		swfobject.embedSWF("$pluginURL/tierra-billboard-manager/swf/ti-billboard.swf", "tierraPlayer", "440", "280", "10.0.0","expressInstall.swf", flashvars, params, attributes);

			
			function updateSnippet()	{
				
				jQuery(".embedCode").html('$embedcode'  +  widthVal + heightVal + delay +  autoplayStatus + repeatStatus +  volume +   backgroundColor + navigationColor + titleBarColor +  titleTextColor +   glowColor  +overlayStatus  +titleStatus  + rolloverStatus +   thumbnailStatus +   textJustification + keepTitleStatus + titleRolloverStatus + "]" );
				//alert ('halfway');
				
				try {
					ti_bbm_previewNavBarColor(
						'0x' + jQuery('input#navBarColorSelector').val(),
						'0x' + jQuery('input#glowColorSelector').val(),
						jQuery("input#chk_titles").attr('checked'),
						jQuery("input#chk_rollovers").attr('checked'),
						jQuery("input#chk_repeat").attr('checked'),
						jQuery("input#chk_overlay").attr('checked'),
						Math.min(3600, Math.max(jQuery("input#delay_seconds").val(), 0)),
						'0x' + jQuery('input#titleBarColorSelector').val() ,
						'0x' + jQuery('input#titleTextColorSelector').val(),
						jQuery("input#chk_thumbnail").attr('checked'),
						jQuery("select#textJustification").val(),
						jQuery("input#chk_keepTitles").attr('checked'),
						jQuery("input#chk_titlesOnRollover").attr('checked')
					);
				}	 catch(e)	{
					
				}

			}
		
		function confirmDefaultsSaved (data)	{
			// If this has been saved as the default, there's no reason to make the shortcode overly complicated.
			if (data.substr( 0,5) != 'ERROR')	{
				jQuery(".embedCode").html('$embedcode' + ']');
			}
			alert ("Options saved: " + data);
			
		}
		
		function savePlaylist(id)	{
						
			var url = "$adminURL";
			
			var parameters = {
				action:				'ti_bbm_update_playlist',
				ti_bbm_playlist_id:	id,
				autoPlay:			jQuery('input#chk_auto').attr('checked'),
				backgroundColor:	jQuery('input#backgroundColorSelector').val(),
				navBarColor:		jQuery('input#navBarColorSelector').val(),
				glowColor:			jQuery('input#glowColorSelector').val(),
				showTitles:			jQuery("input#chk_titles").attr('checked'),
				showRollOvers:		jQuery("input#chk_rollovers").attr('checked'),
				repeat:				jQuery("input#chk_repeat").attr('checked'),
				useOverlay:			jQuery("input#chk_overlay").attr('checked'),
				delay:				Math.min(3600, Math.max(jQuery("input#delay_seconds").val(), 0)),
				titleBGColor:		jQuery('input#titleBarColorSelector').val() ,
				titleFGColor:		jQuery('input#titleTextColorSelector').val(),
				showThumbnail:		jQuery("input#chk_thumbnail").attr('checked'),
				justifyTitle:		jQuery("select#textJustification").val(),
				keepTitles:			jQuery("input#chk_keepTitles").attr('checked'),
				titlesOnRollover:	jQuery("input#chk_titlesOnRollover").attr('checked'),
					
				width:				jQuery("input#playerWidth").val(),
				height:				jQuery("input#playerHeight").val()
			};
			
			jQuery.post(url, parameters, confirmDefaultsSaved);

		}


		jQuery(function() {		
		
			jQuery("input#chk_repeat").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					repeatStatus = ' repeat="1"';
				 } else	{
					repeatStatus = ' repeat="0"';
				 }
				 
				updateSnippet();			
			});
			
			jQuery("input#chk_auto").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					autoplayStatus = ' autoplay="1"';
				 } else	{
					autoplayStatus = ' autoplay="0"';
				 }
				 
				updateSnippet();			
			});

			jQuery("input#chk_keepTitles").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					jQuery("input#chk_titles").attr('checked', true);
					jQuery("input#chk_titlesOnRollover").attr('checked', true);
					
					
					keepTitleStatus = ' keeptitles="1"';
				 } else	{
					keepTitleStatus = ' keeptitles="0"';
				 }
				 
				updateSnippet();			
			});


			jQuery("input#chk_thumbnail").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					thumbnailStatus = ' thumbnails="1"';
				 } else	{
					thumbnailStatus = ' thumbnails="0"';
				 }
				 
				updateSnippet();			
			});



			
			jQuery("input#chk_titles").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					titleStatus = ' titles="1"';
				 } else	{
				 	jQuery("input#chk_keepTitles").attr('checked', false);
					titleStatus = ' titles="0"';
				 }
				 
				updateSnippet();			
			});
			
			jQuery("input#chk_rollovers").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					rolloverStatus = ' rollovers="1"';
				 } else	{
					rolloverStatus = ' rollovers="0"';
				 }
				 
				updateSnippet();			
			});

	
			jQuery("input#chk_titlesOnRollover").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					titleRolloverStatus = ' titlesOnRollover="1"';
				 } else	{
				 	jQuery("input#chk_keepTitles").attr('checked', false);
					titleRolloverStatus = ' titlesOnRollover="0"';
				 }
				 
				updateSnippet();			
			});

	
			jQuery("input#chk_overlay").change(function()	{
				 if(jQuery(this).attr('checked'))	{
					overlayStatus = ' overlay="1"';
				 } else	{
					overlayStatus = '';
				 }
				 
				updateSnippet();			
			});
	
	
			//delay_seconds
			jQuery("input#delay_seconds").change(function()	{
				// Delay must be between 0 and 3600
				var v = Math.min(3600, Math.max(jQuery(this).val(), 0));
				delay = ' delay="' + v + '"';				 
				updateSnippet();			
			});
			
			jQuery("input#volume_input").change(function()	{
				// Volume must be between 0 and 100
				var v = Math.min(100, Math.max(jQuery(this).val(), 0));
				 if( jQuery(this).val() != 50)	{
					volume = ' volume="' + v + '"';
				 }
				 
				updateSnippet();			
			});
		
			jQuery('input#playerWidth').change(function() {
					widthVal = ' width="' + jQuery(this).val()   + '" ';
					updateSnippet();
				}
			);

			jQuery('input#playerHeight').change(function() {
					heightVal = ' height="' + jQuery(this).val()   + '" ';
					updateSnippet();
				}
			);
			jQuery('input#titleBarColorSelector').change(function() {
					titleBarColor = ' titlebarcolor="' + '0x' + jQuery(this).val()   + '" ';
					updateSnippet();
				}
			);
			jQuery('input#titleTextColorSelector').change(function() {
					titleTextColor = ' titleTextColor="' + '0x' + jQuery(this).val()   + '" ';
					updateSnippet();
				}
			);

			jQuery('input#glowColorSelector').change(function() {
					glowColor = ' glow="' + '0x' + jQuery(this).val()   + '" ';
					updateSnippet();
				}
			);
			
			
			jQuery('input#backgroundColorSelector').change(function() {
					backgroundColor = ' background="' + '0x' + jQuery(this).val()   + '" ';
					updateSnippet();
					jQuery('#tierraPlayerBG').css({ backgroundColor: '#' + jQuery(this).val() });
				}
			);
			jQuery('input#navBarColorSelector').change(	function()	{
					navigationColor = ' navigation="' + '0x' + jQuery(this).val()  + '" ';
					updateSnippet();
				}
			);	
				
			jQuery('select#textJustification').change (function()	{
					textJustification = ' justify="'+ jQuery(this).val()  + '" ';
					updateSnippet();
				}
			);	


	

			jQuery("#accordion").accordion({
				fillSpace: true,
				collapsible: true,
				clearStyle: (document.all) ? false : true // Thanks to IE for this!
			});
			
		});
		
	</script>

	

	<form id="option_form">
	<div id="accordion">

		<h3><a href="#" >Get short code</a></h3>
		<div style="height: 150px; min-height: 150px;">

			<ul>
				<li>The following short code can be used to embed this billboard using the settings selected below. If you choose to save these settings, the billboard can be embedded as-is with no need to specify custom options.</li>
				<li><textarea class="embedCode" >$embedcode]</textarea></li>
			</ul>

			<div style="clear: both;"></div>

		</div>
	
		<h3><a href="#">General settings</a></h3>
		<div>	
			<ul>
				<li>AutoStart? <input id="chk_auto" type="checkbox" $cb_autoplay/> &nbsp; &nbsp; 
				Repeat on end? <input id="chk_repeat" type="checkbox" $cb_repeat/> &nbsp; &nbsp; 
				</li>
				<li>Background: <input  id="backgroundColorSelector" class="color" size="6" value="$backgroundColor" /> </li>
				<li>Show each item for (seconds):<input id="delay_seconds" type="text" size="3" value="$delay"/></li>
				
				<li>Player dimensions ( W x H in pixels): <input id="playerWidth" type="text" size="3" value="$width"/>x<input id="playerHeight" type="text" size="3" value="$height"/> (Not reflected in preview)</li>
			</ul>
	
		</div>
		
		<h3><a href="#">Navigation settings</a></h3>
		<div>
			<ul>
				<li>
					Background color:<input id="navBarColorSelector"  class="color" type="text" size="7" value="$navigation"/> &nbsp; &nbsp; Highlight (glow):<input id="glowColorSelector"  class="color" type="text" size="6" value="$glow"/>
				<li>
				<li>
				Use overlay (Navigation is invisible until mouse enters billboard)?: <input id="chk_overlay" type="checkbox" $cb_overlay/>
				</li>
			</ul>
		</div>
		
		<h3><a href="#">Titles/overlay settings</a></h3>
		<div>
			<ul>
				<li>Title background:<input  class="color" id="titleBarColorSelector" type="text" size="7" value="$titlebarcolor"/> &nbsp; &nbsp; Title text:<input  class="color" id="titleTextColorSelector" type="text" size="7" value="$titletextcolor"/> &nbsp; &nbsp; Title justification:<select id="textJustification">
					<option $selL val="left">left</option>
					<option $selC val="center">center</option>
					<option $selR val="right">right</option>
					</select>
				</li>
			
				<li>Keep titles on screen?: <input id="chk_keepTitles" type="checkbox" $cb_keeptitles /></li>
				
				<li>&nbsp; &nbsp; Show title card when slides start? <input id="chk_titles" type="checkbox" $cb_title/> </li>
				<li>&nbsp; &nbsp; Show title card when mouse is over billboard? <input id="chk_titlesOnRollover" type="checkbox" $cb_titlesonrollover/> </li>
				<li>
				Show titles of other items when cursor rolls over navigation items? <input id="chk_rollovers" type="checkbox" $cb_rollovers/>
				</li>
				<li>
					Show thumbnails?: <input id="chk_thumbnail" type="checkbox" $cb_thumbnails /> &nbsp; &nbsp;
				</li>
			</ul>
		</div>
__END_OF_PREVIEW___
;

// We don't want the save routines included if this is not an actual playlist...

	if ($playlist_id)	{
		echo<<<___END_OF_SAVING___
				<h3><a href="#">Save playlist settings</a></h3>
				<div>
					<a  name='save_billboard' class='button-primary' value='Save for this billboard' onClick='javascript:savePlaylist($playlist_id);'>Save settings for this billboard</a>
					<p>or</p>
					<a  name='save_defaults' class='button-primary' value='Save these settings as sitewide defaults' onClick='javascript:savePlaylist(-1);'>Save these settings as sitewide defaults</a>
					<p>Please note that changing the default settings will not affect existing players already assigned custom properties.</p>
				</div>
___END_OF_SAVING___
	;
	}
	
print "</div></form></body></html>";


function checkBoxValueOfBoolean($val, $defaultValue = '') {
		
	if (!isset($val))	{
		$val = $defaultValue;
	}
	$isBoolean = '';
	switch ( strtolower($val) )	{
		case "1":
		case "true":
		case "yes":
		case "ok":
		case "y":
		case "on":
		case "enabled":
			$isBoolean = ' checked ';
		break;
	}
	return $isBoolean;  
}
