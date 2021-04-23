<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header('Access-Control-Allow-Credentials: TRUE');
header('Access-Control-Max-Age: 1');
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: *");

require_once "/web-dev/mysql.php";

$id = (int)(filter_var($_GET["id"], FILTER_VALIDATE_INT));
if (!$id) {
	die('{"error":"Filtering failed on line 17"}');
}

if ($id) {
	$threads = json_decode(file_get_contents("https://paul-s-reid.com/web-dev/Scratch%20Program%20Interface-php/forums/category.php?page=1&id=$id"));
	if (!$threads) {
		die('{"error":"File access and/or decoding failed on line 46"}');
	}

	$curl       = array();
	$result     = array();
	$curl_multi = curl_multi_init();
	if (!$curl_multi) {
		die('{"error":"cURL initialization failed on line 54"}');
	}

	$pages = $threads->category->numPages;
	for ($i = 1; $i < $pages + 1; $i++) {
		$curl[$i] = curl_init();
		if (!$curl[$i]) {
			die('{"error":"cURL initialization failed on line 61"}}');
		}

		if (!curl_setopt($curl[$i], CURLOPT_URL, "https://paul-s-reid.com/web-dev/Scratch%20Program%20Interface-php/forums/category.php?page=$i&id=$id")) {
			die('{"error":"cURL configuration failed on line 65"}');
		}

		if (!curl_setopt($curl[$i], CURLOPT_RETURNTRANSFER, 1)) {
			die('{"error":"cURL configuration failed on line 69"}');
		}

		if (curl_multi_add_handle($curl_multi, $curl[$i]) != 0) {
			die('{"error":"cURL initialization failed on line 73"}');
		}
	}

	$running = NULL;
	do {
		if (curl_multi_exec($curl_multi, $running) != CURLM_OK) {
			die('{"error":"cURL execution failed on line 80"}');
		}
	} while ($running > 0);

	$result = $mysql->query("DROP TABLE IF EXISTS scratchforums_$id");
	if (!$result) {
		die('{"error":"MySQL access failed on line 22"}');
	}

	$result = $mysql->query(
		"CREATE TABLE scratchforums_$id (
			`sticky` BOOLEAN NOT NULL DEFAULT FALSE,
			`open` BOOLEAN NOT NULL DEFAULT TRUE,
			`id` INT UNSIGNED NOT NULL,
			`subject` VARCHAR(155) NOT NULL,
			`owner` CHAR(30) NOT NULL,
			`posts` INT UNSIGNED NOT NULL,
			`views` INT UNSIGNED NOT NULL,
			INDEX(owner(15)),
			UNIQUE(id),
			FULLTEXT(subject)
		) ENGINE InnoDB"
	);
	if (!$result) {
		die('{"error":"MySQL access failed on line 27"}');
	}

	foreach ($curl as $curl_indv) {
		$threads = json_decode(curl_multi_getcontent($curl_indv));
		if ($threads === FALSE) {
			die('{"error":"Decoding failed on line 87"}');
		}

		if (curl_multi_remove_handle($curl_multi, $curl_indv) != CURLM_OK) {
			die('{"error":"cURL closure failed on line 91"}');
		}

		foreach ($threads->threads as $thread) {
			$result = $mysql->query(
				"INSERT INTO scratchforums_$id(
					`id`,
					`open`,
					`owner`,
					`posts`,
					`sticky`,
					`subject`,
					`views`
				)
				VALUES(
					'" . $mysql->real_escape_string($thread->id) . "',
					'" . $mysql->real_escape_string($thread->open) . "',
					'" . $mysql->real_escape_string($thread->username) . "',
					'" . $mysql->real_escape_string($thread->posts) . "',
					'" . $mysql->real_escape_string($thread->sticky) . "',
					'" . $mysql->real_escape_string($thread->subject) . "',
					'" . $mysql->real_escape_string($thread->views) . "'
				)"
			);
			if (!$result) {
				die('{"error":"MySQL access failed on line 96"}');
			}
		}
	}

	curl_multi_close($curl_multi);

	$result = $mysql->query("SELECT owner,count(owner) FROM scratchforums_$id GROUP BY owner ORDER BY count(owner) DESC LIMIT 50");
	if (!$result) {
		die('{"error":"Database access failed"}');
	}

	$row = $result->fetch_array(MYSQLI_NUM);


	$json[] = array(
		'username' => htmlspecialchars($row[0]),
		'threads'  => htmlspecialchars($row[1])
	);

	if (!$mysql->close()) {
		die('{"error":"MySQL closure failed on line 122"}');
	}

	echo json_encode($json);
}
