


var ti_bbm_playList = new Array();
var ti_bbm_autoAdvance = true;


var ti_bbm_number_of_players_loaded = 0;

// This function counts the number of players that should be loaded...
function ti_bbm_addPlayer(bbmName)	{
	ti_bbm_playList.push (bbmName);

}

// And this function counts the players that have actually loaded.
function ti_bbm_playerReady(bbmName)	{
	ti_bbm_number_of_players_loaded++;
	// If all the players have loaded, make sure all the extra players defer to the first one on the page
	if (ti_bbm_number_of_players_loaded >= ti_bbm_playList.length)	{
		ti_bbm_giveFocusTo (ti_bbm_playList[0]);
	}


}


function ti_bbm_giveFocusTo(bbmName)	{
	for (var i = 0; i < ti_bbm_playList.length; i++)	{
		try	{			
			document.getElementById(ti_bbm_playList[i]).deferToPlayer(bbmName) ;
		}	catch (e) {
			//alert ("Unable to assign focus to " + bbmName);
		}	
	}
}

// The following three functions allow Flash developers to circumvent the autoadvance features of the player.
function ti_bbm_suspend(bbmName)	{
	ti_bbm_autoAdvance = false;
	return bbmName;
}

function ti_bbm_getCurrentAdvanceState()	{
	return ti_bbm_autoAdvance;
}

function ti_bbm_resume(bbmName)	{
	ti_bbm_autoAdvance = true;
	document.getElementById(bbmName).endSWFSlide();
	return bbmName;
}



// The following function allows Flash developers to overrride standard click behavior and create interactive SWFs.
function ti_bbm_takeMouseControl(bbmName)	{
	document.getElementById(bbmName).takeMouseControl(bbmName);
	return bbmName;
}




// This function should only be called from within the player preview page, as it is used to "skin" the player on the fly.
function ti_bbm_previewNavBarColor(newBG, newGlow, titles, rollovers, repeat, overlay, delay, titlebar, titletext, thumbnails, justification, keeptitles, rollovertitles)	{
	document.getElementById('ti-billboard').previewNavigation(newBG + "|" + newGlow + "|" + String (titles) + "|" + String(rollovers) + "|" + String(repeat) + "|" + String(overlay) + "|" + delay + "|" + titlebar + "|" + titletext + "|" + thumbnails + "|" + justification + "|" + keeptitles + "|" + rollovertitles) ;
	return true;
}

