<?php

require_once('lib/Env.php');
require_once('lib/WIW.php');


if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
}

try {

	$dbh = new mysqli('localhost', $dbname, $dbpass, $dbname);
	if (!$dbh) { die('Could not connect: ' . mysql_error()); }

    $API = new WIW($_REQUEST['request'], $_SERVER['HTTP_ORIGIN'], $dbh);
    $json = $API->processAPI();
    print $json;

} catch (Exception $e) {
    echo json_encode(Array('error' => $e->getMessage()));
}

?>
