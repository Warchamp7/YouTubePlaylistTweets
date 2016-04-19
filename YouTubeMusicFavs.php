<?php
header('Content-Type: text/html; charset=utf-8');

/*

Tweet example

"I added <Video Title> to <Playlist Title> <Video Link>
"I added Darude - Sandstorm to My Music Favourites https://youtube.com/watch?v=AbCdEfGhIjK"

Video names are trimmed if necessary
No length is enforced on Playlist Title so keep it under ~96 characters...

Script won't tweet more than 10 videos when it runs

*/

// Database Configuration
$dbhost = '--Host--';
$dbuser = '--User--';
$dbpass = '--Pass--';
$dbname = '--Database--';

// Twitter App Credentials
// https://apps.twitter.com/
$auth = array(
	"consumer_key" => "----------",
	"consumer_secret" => "----------",
	"user_token" => "----------",
	"user_secret" => "----------"
);

// Google API Credentials
// https://console.developers.google.com/project
$baseURL = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=";
$apiKey = "----------";

// Script Configuration

$playlist[0]['id'] = "--Playlist ID--";
$playlist[0]['table'] = "--Table Name--";
$playlist[0]['title'] = "--Playlist Title--";

/* Example Playlist Config */
// $playlist[0]['id'] = "PL131ED1AF11C3B710";
// $playlist[0]['table'] = "my_music_favourites_table";
// $playlist[0]['title'] = "My Music Favourites";

/* ---------------- */	
/*    Code Below    */
/* ---------------- */
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

require_once "twitter.class.php";
$twitter = new Twitter($auth["consumer_key"], $auth["consumer_secret"], $auth["user_token"], $auth["user_secret"]);

foreach($playlist as $playlistItem) {
	$page = 1;
	$pageToken = "";
	$crawled = false;
	$tweetCount = 0;
	$tweetMax = 10;

	$sql = $mysqli->query("SELECT * FROM " . $playlistItem['table']);
	if (!$sql) {
		die('Invalid query: ' . mysql_error());
	}

	$result = $sql->fetch_all();

	$idList = array();
	foreach($result as $entry) {
		$idList[] = $entry[0];
	}

	while ($crawled == false) {
		$url = $baseURL . $playlistItem['id'] . "&key=" . $apiKey . "&pageToken=" . $pageToken;

		$feed = file_get_contents($url);

		$array = json_decode($feed,TRUE);
		
		$resultNumber = $array['pageInfo']['resultsPerPage'];
		$pageToken = isset($array['nextPageToken']) ? $array['nextPageToken'] : "";
		$totalResults = $array['pageInfo']['totalResults'];
		
		if(isset($array['items'])) {
				
			foreach($array['items'] as $entry) {
				// Song title
				$songTitle = $entry['snippet']['title'];
				
				$songID = $entry['snippet']['resourceId']['videoId'];
				
				$songLink = "https://www.youtube.com/watch?v=" . $songID . "&list=" . $playlistItem['id'];
				
				if($entry['snippet']['title'] != "Deleted video") {
					
					if(!in_array($songID, $idList)) {
						// NEW VIDEO
						
						$sql = $mysqli->query("INSERT INTO " . $playlistItem['table'] . " VALUES ('" . $songID . "');");
						if (!$sql) {
							die('Invalid query: ' . $mysqli->error);
						}
						
						if($tweetCount < $tweetMax) {
							try {
								$length = 99 - strlen($playlistItem['title']);
								$tweetTitle = (strlen($songTitle) > $length) ? substr($songTitle, 0, $length)."..." : $songTitle;
								$tweetText = "I added '" . $tweetTitle . "' to " . $playlistItem['title'] . " " . $songLink;
								
								$tweet = $twitter->send($tweetText);
								if(!$tweet) {
									echo "Tweet failed.\n";
								} else {
									$tweetCount++;
								}
								
								// Stagger tweets as a kindness to Twitter
								sleep(5);
								
							} catch(TwitterException $e) {
								echo "Error posting tweet: ".$e->getMessage()."\n";
								echo "Text: " . $tweetText;
							}
						}
						
						
					} else {
						// OLD VIDEO
					}
					
				} else {
					// DEAD VIDEO
				}
				
			}
			
		}
		
		$page += 1;
		
		if($pageToken == "" || !isset($array['items'])) {
			// End of playlist data
			$crawled = true;
		}
		
		// Stagger API requests slightly as a kindness to YouTube
		sleep(1);
		
	}

	// Finished processing playlist

}

?>
