<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header('Access-Control-Allow-Credentials: TRUE');
header('Access-Control-Max-Age: 1');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: *");

$categoryPage = (int)(filter_var(
	$_GET["page"],
	FILTER_VALIDATE_INT,
	array(
		'options' => array(
			'default' => 1
		)
	)
));
$categoryID   = (int)(filter_var($_GET["id"], FILTER_VALIDATE_INT));

if ($categoryID) {
	$html = file_get_contents("https://scratch.mit.edu/discuss/" . $categoryID . "/?page=" . $categoryPage);
	if ($html) {
		if (strpos($html, '<td class="djangobbcon1" colspan="4">Forum is empty.</td>')) {
			die('{"error":"Selected page is empty"}');
		} else {
			preg_match_all('/(?<= {11}<span>).+(?=<)/', $html, $categoryName);
			preg_match_all('/(?<=<div class=")[^-]+?(?="><d)/', $html, $threadStatuses);
			preg_match_all('/(?<=c\/)[0-9]*(?=\/")/', $html, $threadIDs);
			preg_match_all('/(?<=\/">).*(?=<\/a><\/h)/', $html, $threadTitles);
			preg_match_all('/(?<=3>\n.{64}).*(?=<)/', $html, $threadPosters);
			preg_match_all('/(?<=2">)[0-9]*/', $html, $threadPosts);
			preg_match_all('/(?<=3">)[0-9]*/', $html, $threadViews);
			preg_match_all('/[ ,-.0-:ADFJM-OSTYa-eg-iln-pr-vy]{14,23}(?=<\/a> <s)/', $html, $threadDates);
			preg_match_all('/(?<=t\/)[0-9]+/', $html, $threadLastIDs);
			preg_match_all('/(?<!\n.{64})(?<=by ).[-*_0-9A-Za-z]+(?=<)/', $html, $threadLastPosters);
			preg_match_all('/(?<=page">)[0-9]+/', $html, $categoryPages);

			for ($i = 0; $i < count($threadIDs[0]); $i++) {
				if (substr($threadDates[0][$i], 0, 1) == "T") {
					$threadDates[0][$i] = str_replace("Today", date("mdY"), $threadDates[0][$i]);
					preg_match_all('/([0-9]{2})([0-9]{2})([0-9]{4}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $threadDates[0][$i], $threadDates[0][$i]);
				} else if (substr($threadDates[0][$i], 0, 1) == "Y") {
					$threadDates[0][$i] = str_replace("Yesterday", date("mdY", time() - 86400), $threadDates[0][$i]);
					preg_match_all('/([0-9]{2})([0-9]{2})([0-9]{4}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $threadDates[0][$i], $threadDates[0][$i]);
				} else {
					preg_match_all('/([A-Z][a-z]{2,4})\.? ([0-9]{1,2}), ([0-9]+) ([0-9]{2}):([0-9]{2}):([0-9]{2})/', $threadDates[0][$i], $threadDates[0][$i]);
					$threadDates[0][$i][1][0] = sprintf("%02u", array_search($threadDates[0][$i][1][0], array("Jan", "Feb", "March", "April", "May", "June", "July", "Aug", "Sept", "Oct", "Nov", "Dec")));
				}

				$threads[] = array(
					'sticky'   => (strpos($threadStatuses[0][$i], "isticky") === FALSE) ? FALSE : TRUE,
					'open'   => (strpos($threadStatuses[0][$i], "iclosed") === FALSE) ? TRUE : FALSE,
					'id'       => (int)$threadIDs[0][$i],
					'subject'  => $threadTitles[0][$i],
					'username' => $threadPosters[0][$i],
					'posts'    => (int)$threadPosts[0][$i] + 1,
					'views'    => (int)$threadViews[0][$i],
					'latest'   => array(
						'date'     => sprintf("%04u", $threadDates[0][$i][3][0]) . sprintf("%02u", $threadDates[0][$i][1][0]) . sprintf("%02u", $threadDates[0][$i][2][0]) . "T" . sprintf("%02u", $threadDates[0][$i][4][0]) . sprintf("%02u", $threadDates[0][$i][5][0]) . sprintf("%02u", $threadDates[0][$i][6][0]) . "Z",
						'id'       => (int)$threadLastIDs[0][$i],
						'username' => $threadLastPosters[0][$i]
					)
				);
			}
		}
	} else {
		die('{"error":"Selected page does not exist or is not accessible"}');
	}
} else {
	die('{"error":"Selected category ID is not a number"}');
}

echo json_encode(
	array(
		'threads'    => $threads,
		'theadCount' => count($threadIDs[0]),
		'category'   => array(
			'id'       => $categoryID,
			'title'    => $categoryName[0][0],
			'page'     => $categoryPage,
			'numPages' => (int)$categoryPages[0][count($categoryPages[0]) - 1]
		)
	)
);
