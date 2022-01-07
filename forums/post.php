<?php
$debug = FALSE;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header('Access-Control-Allow-Credentials: TRUE');
header('Access-Control-Max-Age: 1');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: *");

require_once "../../mysql.php";

/**
 * The id of the post to fetch information about
 *
 * @var int
 */
if (array_key_exists("id", $_GET)) {
	$postID = (int)(filter_var($_GET["id"], FILTER_VALIDATE_INT));
} else {
	die('{"error":"No post ID provided"}');
}

if ($postID) {
	/**
	 * A cURL handle
	 *
	 * @var CurlHandle $ch
	 */
	$ch = curl_init("https://scratch.mit.edu/discuss/post/$postID/");
	if ($ch === FALSE) {
		die('{"error":"cURL initilization failed"}');
	}

	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	if ($debug) {
		curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
		$verbose = fopen('php://temp', 'w+');
		curl_setopt($ch, CURLOPT_STDERR, $verbose);
	}

	/**
	 * The post HTML to be parsed
	 *
	 * @var string $html
	 */
	$html = curl_exec($ch);
	if ($debug) {
		print_r($html);
		printf(
			"\ncUrl error (#%d): %s\n",
			curl_errno($ch),
			htmlspecialchars(curl_error($ch))
		);
		print_r(curl_getinfo($ch));


		rewind($verbose);
		$verboseLog = stream_get_contents($verbose);

		echo htmlspecialchars($verboseLog);
		exit;
	}

	/**
	 * Was the post deleted?
	 *
	 * @var bool $deleted
	 */
	$deleted    = (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 403);
	$html       = curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 ? $html : FALSE;
	$redirected = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);

	if ($html) {
		/**
		 * The thread ID
		 *
		 * @var array $thread
		 */
		preg_match_all('/c\/(\d+)\/">/m', $html, $thread);
		$html = explode(
			"<div id=\"p",
			explode(
				"<a name=\"post-$postID\">",
				$html
			)[1]
		)[0];
		/**
		 * The post HTML
		 *
		 * @var array $content
		 */
		preg_match_all('/class="post_body_html">(.*)<\/div>$/m', $html, $content);
		/**
		 * The person who made the post
		 *
		 * @var array $poster
		 */
		preg_match_all('/rs\/(.*?)\//', $html, $poster);
		/**
		 * The date the post was posted
		 *
		 * @var array|string|null $date
		 */
		preg_match_all('/\/">(.*)</', $html, $date);
		/**
		 * The index of the post in the thread
		 *
		 * @var array $position
		 */
		preg_match_all('/\d+/', $html, $position);
		/**
		 * Information about the post's edit status
		 *
		 * @var array $edit
		 */
		$edit     = preg_match_all('/class="posteditmessage">Last edited by (.*) \((.*)\)/', $html, $edit) == 0 ? NULL : $edit;
		$html     = @$content[1][0];
		$bbcode   = mb_convert_encoding(
			@file_get_contents("https://scratch.mit.edu/discuss/post/$postID/source/"),
			"HTML-ENTITIES",
			"UTF-8"
		);
		$poster   = @$poster[1][0];
		$date     = @$date[1][0] ? convertScratchDate($date[1][0]) : NULL;
		$topic    = (int)(@$thread[1][0]);
		$position = (int)(@$position[0][0]);
		$editby   = @$edit[1][0];
		$editon   = @$edit[2][0] ? convertScratchDate($edit[2][0]) : NULL;
	} else {
		$sdb = json_decode(file_get_contents("https://scratchdb.lefty.one/v2/forum/post/$postID/"), TRUE);
		if (array_key_exists("error", $sdb)) {
			if ($deleted) {
				$html     = NULL;
				$bbcode   = NULL;
				$poster   = NULL;
				$date     = NULL;
				$topic    = NULL;
				$position = NULL;
				$editby   = NULL;
				$editon   = NULL;
			} else {
				die('{"error":"Selected post does not exist or is not accessible"}');
			}
		} else {
			$html   = $sdb['content']['html'];
			$bbcode = $sdb['content']['bb'];
			$poster = $sdb['username'];
			$date   = convertSDBDate($sdb['time']['posted']);
			$topic  = $sdb['topic']['id'];
			$editby = $sdb['editor'];
			$editon = $sdb['time']['edited'] ? convertSDBDate($sdb['time']['edited']) : NULL;
		}
	}

	if (in_array((int)$topic, array(NULL, FALSE, 0, ""), TRUE)) {
		$topic = (int)explode("/", $redirected)[5];
	}

	$postID   = $postID ? (int)$postID : NULL;
	$html     = $html ? (string)$html : NULL;
	$bbcode   = $bbcode ? (string)$bbcode : NULL;
	$poster   = $poster ? (string)$poster : NULL;
	$date     = $date ? (string)$date : NULL;
	$topic    = $topic ? (int)$topic : NULL;
	$position = @$position ? (int)$position : NULL;
	$editby   = $editby ? (string)$editby : NULL;
	$editon   = $editon ? (string)$editon : NULL;
	$result   = $mysql->query(
		"INSERT INTO scratchforums_posts(
			`id`,
			`html`,
			`bbcode`,
			`poster`,
			`date`,
			`topic`,
			`position`,
			`editby`,
			`editon`,
			`deleted`
		)
		VALUES(
			'" . $mysql->real_escape_string($postID) . "',
			'" . $mysql->real_escape_string($html) . "',
			'" . $mysql->real_escape_string($bbcode) . "',
			'" . $mysql->real_escape_string($poster) . "',
			'" . $mysql->real_escape_string($date) . "',
			'" . $mysql->real_escape_string($topic) . "',
			'" . $mysql->real_escape_string($position) . "',
			'" . $mysql->real_escape_string($editby) . "',
			'" . $mysql->real_escape_string($editon) . "',
			'" . $mysql->real_escape_string($deleted) . "'
		)"
	);
	if (!$result) {
		die('{"error":"MySQL access failed on line 96"}');
	}

	die(json_encode(
		array(
			'id'         => $postID,
			'content'    => array(
				'html'   => $html,
				'bbcode' => $bbcode
			),
			'poster'   => $poster,
			'date'     => $date,
			'topic'    => $topic,
			'position' => $position,
			'edit'     => array(
				'by'   => $editby,
				'on'   => $editon
			),
			'deleted'  => $deleted
		)
	));
} else {
	die('{"error":"Selected post ID is not a number"}');
}

/**
 * Convert a date from Scratch to UTC format.
 *
 * @param string $date The Scratch-formatted date to convert.
 *
 * @return string The UTC-formatted date
 */
function convertScratchDate(string $date)
{
	/**
	 * The date split into month, day, year, hour, minute, and seccond
	 *
	 * @var array $regexp
	 */
	$regexp = NULL;

	/**
	 * The date, but with "today" and "yesterday" replaced
	 *
	 * @var string $replaced
	 */
	$replaced = str_replace("Yesterday", date("mdY", time() - 86400), str_replace("Today", date("mdY"), $date)); // replace "yesterday" and "today"
	if ($replaced == $date) {
		preg_match_all('/([A-Z][a-z]{2,4})\.? (\d{1,2}), (\d+) (\d{2}):(\d{2}):(\d{2})/', $replaced, $regexp); // split into month, day, year, hour, minute, seccond
		$regexp[1][0] = array_search($regexp[1][0], array("", "Jan", "Feb", "March", "April", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dec")); // change the month from the abbreviation to the index
	} else {
		preg_match_all('/(\d{2})(\d{2})(\d{4}) (\d{2}):(\d{2}):(\d{2})/', $replaced, $regexp); // split into month, day, year, hour, minute, seccond
	}

	return sprintf("%04u%02u%02uT%02u%02u%02uZ", $regexp[3][0], $regexp[1][0], $regexp[2][0], $regexp[4][0], $regexp[5][0], $regexp[6][0]); // use sprintf to convert it to UTC and pad each value with zeros
}
/**
 * Convert a date from ScratchDB UTC to SPI UTC format.
 *
 * @param string $date The ScratchDB-UTC-formatted date to convert.
 *
 * @return string The SPI-UTC-formatted date
 */
function convertSDBDate(string $date)
{
	/**
	 * The date split into month, day, year, hour, minute, and seccond
	 *
	 * @var array $regexp
	 */
	$regexp = NULL;
	preg_match_all('/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2}).000Z/', $date, $regexp); // split into month, day, year, hour, minute, seccond
	return sprintf("%04u%02u%02uT%02u%02u%02uZ", $regexp[1][0], $regexp[2][0], $regexp[3][0], $regexp[4][0], $regexp[5][0], $regexp[6][0]); // use sprintf to convert it to UTC and pad each value with zeros
}
