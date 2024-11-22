<?php
$BASE_URL = 'https://server.domain';
$API_TOKEN = 'Hv2FxEMoa3moTVuRahMsMK3VUCwdmjmt';
$API_SECRET = 'zihea5hTIpIgxsPFboby4hctopxWQSKd';
$SERVER_ID = '57e9e364fd632c233e86f827';

function auth_request($method, $path, $headers = [], $data = null)
{
	global $BASE_URL, $API_TOKEN, $API_SECRET;

	$auth_timestamp = time();
	$auth_nonce = bin2hex(random_bytes(16));
	$auth_string = implode('&', [$API_TOKEN, $auth_timestamp, $auth_nonce, strtoupper($method), $path]);
	$auth_signature = base64_encode(hash_hmac('sha256', $auth_string, $API_SECRET, true));

	$auth_headers = [
		'Auth-Token: ' . $API_TOKEN,
		'Auth-Timestamp: ' . $auth_timestamp,
		'Auth-Nonce: ' . $auth_nonce,
		'Auth-Signature: ' . $auth_signature,
	];

	$headers = array_merge($auth_headers, $headers);

	$options = [
		'http' => [
			'header' => implode("\r\n", $headers),
			'method' => $method,
			'content' => $data,
		],
	];

	$context = stream_context_create($options);
	return file_get_contents($BASE_URL . $path, false, $context);
}

$ORGANIZATION_ID = 1;

$response = auth_request(
	'GET',
	'/user/' . $ORGANIZATION_ID . '?page=0',
	['Content-Type: application/json']
);


if ($http_response_header[0] !== 'HTTP/1.1 200 OK') {
	throw new Exception('Request failed: ' . $http_response_header[0]);
}