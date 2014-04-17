<?php
session_start();  // make sure you initiate a session so your browser stays sync'd with the server's log in status
require_once 'acpapi.php';

$acp = new acp_api;

switch($_GET['action']){
	
	case ('signin') :
		$result = $acp->signIn($_REQUEST['username'],$_REQUEST['password']);
		$html = $result;		
	break;

	case ('signout') :
		$result = $acp->signOut();
		$html = $result;
	break;
	
	case ('playlists') :
		$result = $acp->playlists();
		$json = $acp->decode($result);
		$html = "<h1>Playlists:</h1>";
		$html.= "<ul>\n";
		foreach( $json->getPlaylistsResponse->getPlaylistsResult->playlistInfoList as $index => $data)
		{
			$html.= "<li>".$data->title ." (". $data->trackCount ." tracks) ";
			$html.= "<a href=\"?action=playlist&playlist_id=".$data->adriveId ."\">view</a> ";
			$html.= "<a href=\"?action=deletePlaylist&playlist_id=".$data->adriveId ."\">delete</a></li>\n";		
		}	
	break;
	
	case ('playlist') :
		$result = $acp->playlists($_GET['playlist_id'],true,'title,objectId');
		$json = $acp->decode($result);
		$html = "<h1>Playlist ". $json->getPlaylistsResponse->getPlaylistsResult->playlistInfoList[0]->title .":</h1>";
		$html.= "<ul>\n";
		foreach($json->getPlaylistsResponse->getPlaylistsResult->playlistInfoList[0]->playlistEntryList as $index => $data)
		{
			$html.= "<li>".$data->metadata->title ."  <a href=\"?action=playTrack&track_id=".$data->metadata->objectId ."\">listen</a></li>\n";		
		}
	break;
	
	case ('createPlaylist') :
		$result = $acp->createPlaylist($_GET['name']);
		$json = $acp->decode($result);
		$html = "New Playlist (". htmlspecialchars($_GET['name']) .") created!<br>\n";
		$html.= "ID: ". $json->createPlaylistResponse->createPlaylistResult->adriveId;		
	break;
	
	case ('deletePlaylist') :
		if(!isset($_GET['confirm']) || $_GET['confirm'] != "delete")
		{
			$html= "ARE YOU SURE YOU WANT TO DELETE THIS PLAYLIST?!! ";
			$html.= '<a href="?action=deletePlaylist&playlist_id='.$_GET['playlist_id'].'&confirm=delete">Yes, Delete it</a>';
		}
		elseif(isset($_GET['confirm']) && $_GET['confirm'] == "delete")
		{
			$result = $acp->deletePlaylist($_GET['playlist_id']);
			$json = $acp->decode($result);
			header('location: ?action=playlists');
			exit();
		}
	break;
	case ('playTrack') :
		$result = $acp->getStreamUrls($_GET['track_id']);
		$json = $acp->decode($result);
		$url = $json->getStreamUrlsResponse->getStreamUrlsResult->trackStreamUrlList[0]->url;
		header('location: '.$url); 
		exit();
	break;
	
	case ('tracks') :
		$result = $acp->findTracks(100,'',array('columns'=>'title,objectId'));
		$json = $acp->decode($result);
		$html = "<h1>100 Tracks:</h1>";
		$html.= "<ul>\n";
		foreach($json->searchLibraryResponse->searchLibraryResult->searchReturnItemList as $index => $data)
		{
			$html.= "<li>".$data->metadata->title ."  <a href=\"?action=playTrack&track_id=".$data->metadata->objectId ."\">listen</a></li>\n";		
		}
	break;
	
	case ('search') :
		$result = $acp->findTracks(100,'',array('keywords'=>$_GET['keywords'],'columns'=>'title,objectId'));
		$json = $acp->decode($result);
		$html = "<h1>Search Results for: <em>". htmlspecialchars($_GET['keywords']). "</em></h1>";
		$html.= "<ul>\n";
		foreach($json->searchLibraryResponse->searchLibraryResult->searchReturnItemList as $index => $data)
		{
			$html.= "<li>".$data->metadata->title ."  <a href=\"?action=playTrack&track_id=".$data->metadata->objectId ."\">listen</a></li>\n";		
		}
	break;
	
	default:
		$html = "";
}


if( isset($_SESSION['appConfig']) && $_SESSION['appConfig'] != "")
{
?>
<p>Here's some stuff you can do:</p>
<ul>
	<li><a href="?action=playlists">Manage your playlists</a></li>
	<li><a href="?action=tracks">See your first 100 tracks</a></li>
	<li><form method="get" action=""><input type="hidden" name="action" value="search"><input type="text" name="keywords" placeholder="Enter Keywords"><input type="submit" value="Search"></form></li>
	<li><form method="get" action=""><input type="hidden" name="action" value="createPlaylist"><input type="text" name="name" placeholder="Enter name for new playlist"><input type="submit" value="Create Playlist"></form></li>
	<li><a href="?action=signout">Sign Out</a></li>
</ul>
<?
} 
else
{
?>	
<form action="?action=signin" method="post">
	Username: <input name="username" placeholder="Username (e-mail address)" type="email"><br>
	Password: <input name="password" placeholder="Password" type="password"><br>
	<input type="submit" value="Sign In">
</form>
<?
}

echo "<hr>\n";
echo $html;