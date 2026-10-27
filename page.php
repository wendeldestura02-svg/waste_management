<?php

$views = [
	'schedules' => 'schedules',
	'violators' => 'entity-violators',
];
$view = $_GET['view'] ?? '';
$anchor = $views[$view] ?? '';
$parameters = array_intersect_key(
	$_GET,
	array_flip(['success', 'error', 'zone_id'])
);
$destination = 'index.php';

if ($parameters) {
	$destination .= '?' . http_build_query($parameters);
}

if ($anchor !== '') {
	$destination .= '#' . $anchor;
}

header('Location: ' . $destination);
exit;
