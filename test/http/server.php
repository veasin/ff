<?php
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$uriPath = parse_url($uri, PHP_URL_PATH);
$query = parse_url($uri, PHP_URL_QUERY);

// CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');

// Route
if (preg_match('#^/status/(\d+)$#', $uriPath, $m)) {
    http_response_code((int)$m[1]);
    echo json_encode(['code' => (int)$m[1]]);
    return;
}

if ($uriPath === '/redirect') {
    http_response_code(302);
    header('Location: /json');
    return;
}

if ($uriPath === '/slow') {
    sleep(2);
    echo json_encode(['slow' => true]);
    return;
}

if ($uriPath === '/json') {
    header('Content-Type: application/json');
    echo json_encode(['method' => $method, 'path' => '/json', 'ok' => true]);
    return;
}

if ($uriPath === '/query') {
    parse_str($query ?? '', $params);
    header('Content-Type: application/json');
    echo json_encode(['query' => $params]);
    return;
}

if ($uriPath === '/echo') {
    $body = file_get_contents('php://input');
    header('Content-Type: application/json');
    echo json_encode([
        'method' => $method,
        'body' => $body,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
    ]);
    return;
}

if ($uriPath === '/headers') {
    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($k, 5)));
            $headers[$name] = $v;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE'])) $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
    header('Content-Type: application/json');
    echo json_encode(['headers' => $headers]);
    return;
}

if ($uriPath === '/multi-header') {
    header('X-Custom: val1');
    header('X-Custom: val2', false);
    echo json_encode(['ok' => true]);
    return;
}

if ($uriPath === '/encoding') {
    $body = file_get_contents('php://input');
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    echo json_encode([
        'content_type' => $ct,
        'body_raw' => $body,
        'body_json' => json_decode($body, true),
    ]);
    return;
}

if ($uriPath === '/html') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<html><body><h1>Hello</h1></body></html>';
    return;
}

if ($uriPath === '/form') {
    header('Content-Type: application/x-www-form-urlencoded');
    echo 'foo=bar&num=42';
    return;
}

http_response_code(404);
echo json_encode(['error' => 'not found']);
