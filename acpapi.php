<?php
/**
 * PHP API class to interact with the Amazon Cloud Player
 * 
 * By Micah J. Murray
 * http://github.com/micah1701
 * 
 * heavily influenced by chipX86's Cloudplaya Python app.
 * https://github.com/chipx86/cloudplaya
 * 
 * This application is not affiliated with or endorsed by Amazon.com.
 * Please confirm that your intended use of this code is in compliance with the Amazon Cloud Player Terms of Use.
 * All code is without warranty and offered "AS IS." Use at your own risk.
 * 
 */ 

class acp_api {
	
	// path to folder to store cookies associated with the requests
	private $cookies_path = '';
	
	// path to openID log in page
	private $signin_url = 'https://www.amazon.com/ap/signin?openid.assoc_handle=usflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.return_to=https%3A%2F%2Fwww.amazon.com%2Fgp%2Fdmusic%2Fmp3%2Fplayer%3Fie%3DUTF8%26requestedView%3Dsongs';
	
	// path to Amazon's cirrus API
	private $cirrus = 'https://www.amazon.com/cirrus/';
	
	//helper function to convert an array to a "&key=value" string
	private function array2string($myArray)
	{
		$string = "";
		foreach ($myArray as $key => $value)
		{
				$string.= $key."=".$value ."&";
		}
		return rtrim($string,"&");
	}
	
	//helper function for requestId() function
	private function randHex()
	{
		return dechex(floor(rand(100,65536)));
	}
	
	//create a request id
	private function requestId()
	{
		$string = $this->randHex();
		$string.= $this->randHex();
		$string.= "-";
		$string.= $this->randHex();
		$string.= "-";
		$string.="dmcp-";
		$string.= $this->randHex();
		$string.= "-";
		$string.= $this->randHex();
		$string.= $this->randHex();
		$string.= $this->randHex();
		return $string;
	}
	
	/**
	 * make the cURL request to amazon cloud player
	 */
	private function request($url,$post=false,$callback='')
	{
		$referer = 'https://www.amazon.com/gp/dmusic/mp3/player?ie=UTF8&ref_=gno_yam_cldplyr&';
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url); 
	
		if($post && is_array($post))
		{
			curl_setopt($ch, CURLOPT_POST, true);	
			$appConfig = array(
					'ContentType'=>'JSON',
					'customerInfo.customerId'=> $_SESSION['appConfig']['customerId'],
					'customerInfo.deviceId'=> $_SESSION['appConfig']['deviceId'],
					'customerInfo.deviceType'=> $_SESSION['appConfig']['deviceType']
			);
			$merged_post_data = array_merge($post,$appConfig);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->array2string($merged_post_data));
		}
		
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		
		// if there is a CSRFToken, send the headers (otherwise, the user isn't logged in)
		if(isset($_SESSION['appConfig']['CSRFTokenConfig']['csrf_rnd']))
		{
			$headers = array(
				'Accept: application/json, text/javascript, */*; q=0.01',
				'Accept-Encoding: gzip,deflate,sdch',
				'Accept-Language: en-US,en;q=0.8',
				'Connection: keep-alive',
				'Content-Type: application/x-www-form-urlencoded',
				'csrf-rnd: '. $_SESSION['appConfig']['CSRFTokenConfig']['csrf_rnd'],
				'csrf-token: '. $_SESSION['appConfig']['CSRFTokenConfig']['csrf_token'],
				'csrf-ts: '. $_SESSION['appConfig']['CSRFTokenConfig']['csrf_ts'],
				'DNT: 1',
				'Host: www.amazon.com',
				'Origin: https://www.amazon.com',
				'x-amzn-RequestId:'. $this->requestId(),
				'X-Requested-With: XMLHttpRequest'
			);
			
			curl_setopt($ch, CURLOPT_HEADER, false); // set to true to see the headers returned by Cirrus
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
		}
		
		curl_setopt($ch,CURLOPT_ENCODING, '');	
		curl_setopt($ch, CURLOPT_REFERER, $referer); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_COOKIEJAR,  $this->cookies_path . session_id() ."_cookie.txt");
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies_path . session_id() ."_cookie.txt");
		$output = curl_exec($ch); 		
		$info = curl_getinfo($ch);
		curl_close($ch);

		if($callback != '')
		{
			return $this->$callback($output);
		}
		else
		{
			return $output;
		}
	}

	/**
	 * signIn() function is part 1 of 3 step sign in process
	 * this first function simply retreives the HTML page with the sign in form
	 * then, on callback, it triggers the 2nd step, signInSubmit()
	 */
	public function signIn($username='',$password='')
	{
		$_SESSION['username'] = $username;
		$_SESSION['password'] = $password;
		$this->request($this->signin_url,'','signInSubmit');
	}
	
	/**
	 * signInSubmit() is part 2 of 3 in sign in process
	 * this second step submits the username and password via the HTML form returned in step one
	 */
	private function signInSubmit($loginHTML)
	{
		$doc = new DOMDocument;
		if ( !@$doc->loadhtml($loginHTML) ) {
	  		echo 'something went wrong with the returned HTML: '. $loginHTML;
		}
		else
		{
		  $xpath = new DOMXpath($doc);
		  // find all the inputs fields on the form to be submitted
		  foreach($xpath->query('//form[@name="signIn"]//input') as $eInput) {	
		  	if($eInput->getAttribute('name') != "")
		  	{
		  		$fields[$eInput->getAttribute('name')] = $eInput->getAttribute('value');
		  	}
		  }
		}
		
		//overwrite the blank email and password fields with the known values
		$fields['email'] = $_SESSION['username'];
		$fields['password'] = $_SESSION['password'];
		
		$getAction = $xpath->query( '//form[@name="signIn"]/@action' );
	
		$this->request($getAction->item( 0 )->nodeValue,$fields,'signInComplete');	
	}

	/**
	 * signInComplete is step 3 of 3 in the sign in process
	 * this third step takes the response info for the previous request and saves the token and configuration data
	 */
	 private function signInComplete($output)
	 {
		foreach(preg_split("/((\r?\n)|(\r\n?))/", $output) as $line){
	    	if(strpos($line,'amznMusic.appConfig ='))
	    	{
	    		$appConfig = $line;
	    		break;	
	    	}
		} 
		
		$appConfig = trim($appConfig);
		$appConfig = ltrim($appConfig,'amznMusic.appConfig =');
		$appConfig = rtrim($appConfig,";");
	
		$_SESSION['appConfig'] = json_decode($appConfig,true);
		
		echo (isset($_SESSION['appConfig']) && $_SESSION['appConfig'] != "") ? "Successfully logged in!" : "Error: Could not log in";
	}

	/**
	 * signOut() of Amazon Cloud Player and remove local cookie and session data
	 */
	public function signOut()
	{
		unlink($this->cookies_path . session_id() ."_cookie.txt"); // delete the cookie
		unset($_SESSION['appConfig']); // kill the ACP session
		
		// "click" the logout button via request
		$this->request('http://www.amazon.com/gp/dmusic/mp3/forceSignOut?ie=UTF8&ref_=dm_cp_m_redirect');
		echo "Logged out";
	}
	
	/**
	 * Query the library for music
	 * $limit	INT	number of results to be returned (note, there is a hard limit of 500 results)
	 * $offset  INT starting result number to be returned
	 * $parameters	ARRAY	
	 * 				$keywords STRING	search using amazon's search box
	 * 				$query	ARRAY of Arrays containing query parameters ("name","comparisonType","value") for additional search results
	 * 				$columns STRING of comma seperated column names to return values for
	 * 				$sort	ARRAY of Arrays containing sort directives ("column","type")
	 */
	public function findTracks($limit=100,$offset='',$parameters=array())
	{
		$offset = (is_numeric($offset) && $offset > 0) ? $offset : '';
		
		if(!array_key_exists('keywords',$parameters))
		{
			// by default, find all tracks 
			$parameters['keywords'] = "";
		}
		
		if(!array_key_exists('columns',$parameters))
		{
			// by default, return all columns
			$parameters['columns'] = array('*'); 
		}
		elseif (!is_array($parameters['columns']))
		{
			$parameters['columns'] = explode(",",$parameters['columns']);
		}
			
		if(!array_key_exists('sort',$parameters) || !is_array($parameters['sort']))
		{
			// by default, sort tracks alphabetically by the "sortTitle" field
			$parameters['sort'] = array( array("column"=>"sortTitle","type"=>"ASC"));
		}
	
		$post = array(
			'searchReturnType'=>'TRACKS',
			'albumArtUrlsRedirects'=>'false',
			'distinctOnly'=>'false',
			'countOnly'=>'false',
			'sortCriteriaList'=>'',
			'maxResults'=> $limit, 
			'nextResultsToken'=> $offset,
			'caller'=>'getServerSongs',
			'Operation'=>'searchLibrary',
			'searchCriteria.member.1.attributeName'=>'keywords',
			'searchCriteria.member.1.comparisonType'=>'LIKE',
			'searchCriteria.member.1.attributeValue'=> $parameters['keywords'],
			'searchCriteria.member.2.attributeName'=>'assetType',
			'searchCriteria.member.2.comparisonType'=>'EQUALS',
			'searchCriteria.member.2.attributeValue'=>'AUDIO',
			'searchCriteria.member.3.attributeName'=>'status',
			'searchCriteria.member.3.comparisonType'=>'EQUALS',
			'searchCriteria.member.3.attributeValue'=>'AVAILABLE'
			);
			
			// note, other comparisonType values are "NOT_EQUALS", "IS_NOT_NULL", "IS_NULL"
			// the "LIKE" comparison seems to be case sensative and needs to be an exact match
			// so its pretty worthless outside of the "keywords" attribute.
		
		if(array_key_exists("query",$parameters))
		{
			$i=4;
			foreach($parameters['query'] as $query)
			{
				$post['searchCriteria.member.'.$i.'.attributeName'] = $query['name'];
				$post['searchCriteria.member.'.$i.'.comparisonType'] = $query['comparisonType'];
				$post['searchCriteria.member.'.$i.'.attributeValue'] = $query['value'];
				$i++;
			}
		}
		$i=1;
		foreach($parameters['columns'] as $column)
		{
			$post['selectedColumns.member.'.$i] = $column;
			$i++;
		}
		$i=1;
		foreach($parameters['sort'] as $sort)
		{
			$post['sortCriteriaList.member.'.$i.'.sortColumn'] = $sort['column'];
			$post['sortCriteriaList.member.'.$i.'.sortType'] = $sort['type'];
			$i++;
		}
		return $this->request($this->cirrus,$post,'');	
	}
	
	/**
	 * getStreamUrls() returns the URI path to download a track
	 * $ids	MIXED can be string of individual track's objectId, or a comma seperated list, or an Array
	 */
	function getStreamUrls($ids)
	{
		if(!isset($ids) || $ids == ""){ exit("Error: missing objec ID"); }
		if(!is_array($ids))
		{
			$ids = explode(",",$ids);
		}
		
		$post = array('Operation'=>'getStreamUrls');
		for($i=1; $i<=count($ids); $i++)
		{
			$post['trackIdList.member.'.$i] = $ids[$i-1];
		}
		
		return $this->request($this->cirrus,$post);
			
	}

	/**
	 * Return all play lists
	 * 
	 * $playlist_ids 		MIXED	string plays list id, or comma seperated list of ids, or array
	 * $includeTrackMetadata	BOOL	if true, shows all the tracks in the given playlist(s)
	 * $trackColumns	STRING	optional comma seperated list of track meta data to return
	 */
	public function playlists($playlist_ids='',$includeTracks=false,$trackColumns='*')
	{
		$post = array(
				'caller'=>'getServerListSongs',
				'Operation'=>'getPlaylists'
			);
		
		if($includeTracks)
		{
			$post['includeTrackMetadata'] = "true";
			$post['trackCountOnly'] = "false";
			$trackColumns = explode(",",$trackColumns);
			for($i=1; $i<=count($trackColumns); $i++)
			{
				$post['trackColumns.member.'.$i] = $trackColumns[$i-1];
			}
			
		}
		else
		{
			$post['includeTrackMetadata'] = "false";
			$post['trackCountOnly'] = "true";
		}
		
		if($playlist_ids == "")
		{
			$post['playlistIdList'] = '';
		}
		else
		{
			if(!is_array($playlist_ids))
			{
				$playlist_ids = explode(",",$playlist_ids);
			}
			for($i=1; $i<=count($playlist_ids); $i++)
			{
				$post['playlistIdList.member.'.$i] = $playlist_ids[$i-1];
			}
		}
		return $this->request($this->cirrus,$post);
	}	
	
	/**
	 * Create a new playlist.
	 * $title STRING name of new playlist
	 * Note, this function does not check if a list of the same name exists and Aamazon will let you create duplicates
	 */
	public function createPlaylist($title="New Playlist")
	{
		$post = array(
			'Operation'=>'createPlaylist',
			'caller'=>'createPlaylist',
			'trackidList'=>'',
			'title'=> $title
		);
		return $this->request($this->cirrus,$post);
	}

	public function deletePlaylist($id)
	{
		if(!$id || !isset($id) || $id == ''){ exit("error, invalid or missing object id"); }
		$post = array(
			'Operation'=>'removeFiles',
			'caller'=>'deletePlaylist',
			'adriveIds.members.1'=>$id
		);
		return $this->request($this->cirrus,$post);
	}
	
	/**
	 * addToPlaylist() adds a track or list of tracks to a given playlist.
	 * $ids	MIXED can be string of individual track's objectId, or a comma seperated list, or array
	 */
	function addToPlaylist($track_ids,$playlist_id)
	{
		if(!is_array($track_ids))
		{
			$track_ids = explode(",",$track_ids);
		}
		
		$post = array(
			'caller'=>'addToPlaylist',
			'Operation'=>'insertIntoPlaylist',
			'playlistId'=> $playlist_id
			);
		
		for($i=1; $i<=count($track_ids); $i++)
		{
			$post['trackIdList.member.'.$i] = $ids[$i-1];
		}
		
		return $this->request($this->cirrus,$post);
	}
	
	/**
	 * json_decode the result for the $request() method
	 * if there is an error. throw a fatal error message.
	 */
	public function decode($result,$subsection=false)
	{
		$decoded = json_decode($result);
		if(isset($decoded->Error))
		{
			echo "<h2>Fatal Error:</h2><pre>\n"; print_r($decoded->Error); echo "\n</pre>\n";
			exit();
		}
		return ($subsection) ? $decoded->$subsection : $decoded;
	}
	
}