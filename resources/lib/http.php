<?php
/** Isolated Plenty SDK worker. Hosts and paths are fixed, no redirects or retries. */
$service = SdkRestApi::getParam('service');
$method = SdkRestApi::getParam('method');
$path = SdkRestApi::getParam('path');
$body = SdkRestApi::getParam('body');
$query = SdkRestApi::getParam('query');
$authorization = SdkRestApi::getParam('authorization');
$origins = ['plenty' => 'https://p43817.my.plentysystems.com', 'deepl' => 'https://api.deepl.com'];
$plentyRead = $method === 'GET' && preg_match('~^/rest/(?:tags(?:/\d+|/relationships)?|items/variations|items/\d+/variations/\d+/descriptions)$~D', $path);
$plentyWrite = (($method === 'POST' || $method === 'PUT') && preg_match('~^/rest/items/\d+/variations/\d+/descriptions(?:/(?:en|fr|it|es|nl))?$~D', $path)) || ($method === 'POST' && ($path === '/rest/login' || $path === '/rest/tags/relationships')) || ($method === 'DELETE' && preg_match('~^/rest/tags/relationships/\d+/variation/141$~D', $path));
$deeplAllowed = ($method === 'POST' && $path === '/v2/translate') || ($method === 'GET' && ($path === '/v2/usage' || preg_match('~^/v3/glossaries/[a-f0-9-]{36}/entries$~D', $path)));
if (!isset($origins[$service]) || ($service === 'plenty' && !$plentyRead && !$plentyWrite) || ($service === 'deepl' && !$deeplAllowed) || !is_array($body) || !is_array($query) || !is_string($authorization) || preg_match('/[\r\n]/', $authorization)) {
    return ['ok' => false, 'status' => 0];
}
try {
    $client = new \GuzzleHttp\Client(['timeout' => 40, 'connect_timeout' => 10, 'allow_redirects' => false, 'http_errors' => false, 'verify' => true]);
    $options = ['headers' => ['Accept' => 'application/json'], 'query' => $query];
    if ($authorization !== '') { $options['headers']['Authorization'] = $authorization; }
    if ($method === 'POST' || $method === 'PUT') { $options['json'] = $body; }
    $response = $client->request($method, $origins[$service] . $path, $options);
    $status = $response->getStatusCode();
    if ($status < 200 || $status >= 300) { return ['ok' => false, 'status' => $status]; }
    $text = (string)$response->getBody();
    if (strlen($text) > 8388608) { return ['ok' => false, 'status' => 0]; }
    $data = $text === '' ? null : json_decode($text, true, 512, JSON_THROW_ON_ERROR);
    return ['ok' => true, 'status' => $status, 'data' => $data];
} catch (\Throwable $e) {
    // Do not return exception messages, request objects, credentials or API bodies.
    return ['ok' => false, 'status' => 0];
}
