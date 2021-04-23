<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header('Access-Control-Allow-Credentials: TRUE');
header('Access-Control-Max-Age: 86400');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: *");


$html = file_get_contents("https://scratch.mit.edu/discuss/");
if ($html) {
	preg_match_all('/([0-9]{1,2})\/">(.+)<\/a>\n {18}\n {16}<\/h3>\n {16}(.*)/', $html, $forum);
	preg_match_all('/([0-9]+)<\/td>\n    <td class="tc3">([0-9]+)/', $html, $stat);
	preg_match_all('/([0-9]+)\/">([ ,-.0-:ADFJM-OSTYa-eg-iln-pr-vy]{14,23})<\/a>\n {16}<span class="byuser">by ([0-9a-zA-Z _*-]{3,20})/', $html, $latest);

	for ($i = 0; $i < count($forum[0]); $i++) {
		if (substr($latest[3][$i], 0, 1) == "T") {
			$latest[3][$i] = str_replace("Today", date("mdY"), $latest[3][$i]);
			preg_match_all('/([0-9]{2})([0-9]{2})([0-9]{4}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $latest[3][$i], $latest[3][$i]);
		} else if (substr($latest[3][$i], 0, 1) == "Y") {
			$latest[3][$i] = str_replace("Yesterday", date("mdY", time() - 86400), $latest[3][$i]);
			preg_match_all('/([0-9]{2})([0-9]{2})([0-9]{4}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $latest[3][$i], $latest[3][$i]);
		} else {
			preg_match_all('/([A-Z][a-z]{2,4})\.? ([0-9]{1,2}), ([0-9]+) ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $latest[3][$i], $latest[3][$i]);
			$latest[3][$i][1][0] = sprintf("%02u", array_search($latest[3][$i][1][0], array("Jan", "Feb", "March", "April", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dec")));
		}

		$forums[] = array(
			'id'       => (int)$forum[1][$i],
			'title'  => $forum[2][$i],
			'description' => $forum[3][$i],
			'threads'    => (int)$stat[1][$i] + 1,
			'posts'    => (int)$stat[2][$i],
			'latest'   => array(
				'id'       => (int)$latest[1][$i],
				'username' => $latest[2][$i],
				'date'     => sprintf("%04u", $latest[3][$i][3][0]) . sprintf("%02u", $latest[3][$i][1][0]) . sprintf("%02u", $latest[3][$i][2][0]) . "T" . sprintf("%02u", $latest[3][$i][4][0]) . sprintf("%02u", $latest[3][$i][5][0]) . sprintf("%02u", $latest[3][$i][6][0]) . "Z"
			)
		);
	}
} else {
	die(json_encode(array(
		'error' => 'An unknown error occured. Are you able to visit the site https://scratch.mit.edu/discuss/ (the Scratch forums root)? Please contact @RedGuy7 at https://scratch.mit.edu/users/RedGuy7/ for more support.'
	)));
}

echo json_encode(
	$forums
);
