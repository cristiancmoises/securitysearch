<?php
require_once __DIR__ . '/lib/security_headers.php';
require_once __DIR__ . '/lib/luma_search.php';

header('Cache-Control: private, no-store');
header('Content-Type: text/plain; charset=UTF-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo 'Use the LUMA search shortcut with GET.';
    exit;
}
try {
    $destination = luma_search::destination($_GET);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo $error->getMessage();
    exit;
}
// Forward only the query, never filters, continuation tokens or credentials.
header('Location: ' . $destination, true, 303);
exit;
