<?php
/**
 * Secret — Test Suite
 *
 * Run:  php tests/run.php
 *
 * Starts a temporary PHP built-in server, runs all tests against it,
 * then shuts it down. Uses a temporary database so production data is safe.
 */

$host = '127.0.0.1';
$port = 8765 + rand(0, 200);
$baseUrl = "http://$host:$port";
$rootDir = dirname(__DIR__);
$testEnv = $rootDir . '/.env.test';
$testDb  = $rootDir . '/data/test_secrets.db';

// --- Setup ---

// Clean up any previous test DB
@unlink($testDb);

// Write a test .env
file_put_contents($testEnv, implode("\n", [
    'NEW_ITEM_PASSWORD=testpass',
    'ALLOWED_TAGS=br,a',
]));

// Override DB_PATH via environment for the test server
$env = [
    'DB_PATH' => $testDb,
    'ENV_FILE' => $testEnv,
];
$envStr = '';
foreach ($env as $k => $v) $envStr .= "$k=$v ";

// Start PHP built-in server
$cmd = "{$envStr}php -S $host:$port -t " . escapeshellarg($rootDir . '/public') . ' ' . escapeshellarg($rootDir . '/public/index.php') . ' 2>/dev/null &';
$descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
$process = proc_open($cmd, $descriptors, $pipes);
usleep(500_000); // Wait for server to start

// --- Test helpers ---

$passed = 0;
$failed = 0;
$errors = [];

function request(string $method, string $path, ?array $body = null): array {
    global $baseUrl;
    $ch = curl_init($baseUrl . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'status' => $status,
        'body'   => json_decode($response, true) ?: $response,
    ];
}

function assert_eq($expected, $actual, string $label): void {
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        $msg = "  FAIL  $label — expected " . json_encode($expected) . ", got " . json_encode($actual);
        echo $msg . "\n";
        $errors[] = $msg;
    }
}

function assert_true(bool $condition, string $label): void {
    assert_eq(true, $condition, $label);
}

function create_secret(array $overrides = []): array {
    $defaults = [
        'content'  => base64_encode('encrypted-test-content'),
        'iv'       => base64_encode('test-iv-12b'),
        'expires'  => '1D',
        'password'  => 'testpass',
        'maxViews' => 1,
    ];
    return request('POST', '/api/secret', array_merge($defaults, $overrides));
}

// --- Tests ---

echo "\nSecret — Test Suite\n";
echo str_repeat('=', 50) . "\n\n";

// ---------------------------------------------------------------
echo "1. Create secret — valid\n";
// ---------------------------------------------------------------
$r = create_secret();
assert_eq(201, $r['status'], 'returns 201');
assert_true(!empty($r['body']['id']), 'returns an id');
$textId = $r['body']['id'];

// ---------------------------------------------------------------
echo "\n2. Create secret — missing fields\n";
// ---------------------------------------------------------------
$r = request('POST', '/api/secret', ['password' => 'testpass']);
assert_eq(400, $r['status'], 'returns 400 for missing content/iv');

$r = request('POST', '/api/secret', ['content' => 'x', 'password' => 'testpass']);
assert_eq(400, $r['status'], 'returns 400 for missing iv');

// ---------------------------------------------------------------
echo "\n3. Create secret — wrong password\n";
// ---------------------------------------------------------------
$r = create_secret(['password' => 'wrong']);
assert_eq(401, $r['status'], 'returns 401');
assert_eq('Password Incorrect', $r['body']['error'], 'error message matches');

// ---------------------------------------------------------------
echo "\n4. Create secret — no password when required\n";
// ---------------------------------------------------------------
$r = create_secret(['password' => '']);
assert_eq(401, $r['status'], 'returns 401 for empty password');

// ---------------------------------------------------------------
echo "\n5. Fetch secret — valid\n";
// ---------------------------------------------------------------
$r = request('GET', "/api/secret/$textId");
assert_eq(200, $r['status'], 'returns 200');
assert_eq($textId, $r['body']['id'], 'id matches');
assert_eq(base64_encode('encrypted-test-content'), $r['body']['content'], 'content matches');
assert_eq(base64_encode('test-iv-12b'), $r['body']['iv'], 'iv matches');
assert_eq(false, $r['body']['isFile'], 'isFile is false');
assert_eq(true, $r['body']['lastView'], 'lastView is true (maxViews=1, views=1)');
assert_eq(1, $r['body']['views'], 'views is 1');
assert_eq(1, $r['body']['maxViews'], 'maxViews is 1');

// ---------------------------------------------------------------
echo "\n6. Fetch secret — deleted after single view\n";
// ---------------------------------------------------------------
$r = request('GET', "/api/secret/$textId");
assert_eq(404, $r['status'], 'returns 404 after max views reached');

// ---------------------------------------------------------------
echo "\n7. Fetch secret — not found\n";
// ---------------------------------------------------------------
$r = request('GET', '/api/secret/00000000-0000-0000-0000-000000000000');
assert_eq(404, $r['status'], 'returns 404 for nonexistent id');

// ---------------------------------------------------------------
echo "\n8. Delete secret\n";
// ---------------------------------------------------------------
$r = create_secret(['maxViews' => 5]);
$delId = $r['body']['id'];
$r = request('DELETE', "/api/secret/$delId");
assert_eq(200, $r['status'], 'returns 200');
assert_true($r['body']['deleted'] === true, 'deleted flag is true');

$r = request('GET', "/api/secret/$delId");
assert_eq(404, $r['status'], 'secret gone after DELETE');

// ---------------------------------------------------------------
echo "\n9. File secret\n";
// ---------------------------------------------------------------
$r = create_secret([
    'isFile'   => true,
    'filename' => 'test.pdf',
    'mimetype' => 'application/pdf',
    'maxViews' => 3,
]);
assert_eq(201, $r['status'], 'file secret created');
$fileId = $r['body']['id'];

$r = request('GET', "/api/secret/$fileId");
assert_eq(200, $r['status'], 'returns 200');
assert_eq(true, $r['body']['isFile'], 'isFile is true');
assert_eq('test.pdf', $r['body']['filename'], 'filename matches');
assert_eq('application/pdf', $r['body']['mimetype'], 'mimetype matches');
assert_eq(false, $r['body']['lastView'], 'not last view (1 of 3)');
assert_eq(1, $r['body']['views'], 'views is 1');
assert_eq(3, $r['body']['maxViews'], 'maxViews is 3');

// ---------------------------------------------------------------
echo "\n10. Multi-view secret — view counting\n";
// ---------------------------------------------------------------
$r = create_secret(['maxViews' => 3]);
$mvId = $r['body']['id'];

$r = request('GET', "/api/secret/$mvId");
assert_eq(1, $r['body']['views'], 'view 1 of 3');
assert_eq(false, $r['body']['lastView'], 'not last view');

$r = request('GET', "/api/secret/$mvId");
assert_eq(2, $r['body']['views'], 'view 2 of 3');
assert_eq(false, $r['body']['lastView'], 'not last view');

$r = request('GET', "/api/secret/$mvId");
assert_eq(3, $r['body']['views'], 'view 3 of 3');
assert_eq(true, $r['body']['lastView'], 'IS last view');

$r = request('GET', "/api/secret/$mvId");
assert_eq(404, $r['status'], 'deleted after max views');

// ---------------------------------------------------------------
echo "\n11. Unlimited views (maxViews=0)\n";
// ---------------------------------------------------------------
$r = create_secret(['maxViews' => 0]);
$ulId = $r['body']['id'];

for ($i = 1; $i <= 5; $i++) {
    $r = request('GET', "/api/secret/$ulId");
    assert_eq(200, $r['status'], "view $i still accessible");
    assert_eq(false, $r['body']['lastView'], "view $i is not last view");
    assert_eq($i, $r['body']['views'], "views count is $i");
    assert_eq(0, $r['body']['maxViews'], 'maxViews stays 0');
}

// Clean up
request('DELETE', "/api/secret/$ulId");

// ---------------------------------------------------------------
echo "\n12. Expiry — expired secret cleaned up\n";
// ---------------------------------------------------------------
// Insert a secret directly into the DB with an already-expired date
$db = new PDO('sqlite:' . $testDb);
$expiredId = '00000000-dead-0000-0000-000000000001';
$db->prepare('INSERT INTO secrets (id, content, iv, expires, max_views, views, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
   ->execute([$expiredId, 'old', 'old', '2000-01-01T00:00:00Z', 1, 0, '2000-01-01T00:00:00Z']);
$db = null;

$r = request('GET', "/api/secret/$expiredId");
assert_eq(404, $r['status'], 'expired secret returns 404');

// ---------------------------------------------------------------
echo "\n13. Unlimited time expiry (never)\n";
// ---------------------------------------------------------------
$r = create_secret(['expires' => 'never', 'maxViews' => 0]);
assert_eq(201, $r['status'], 'created with never expiry');
$neverId = $r['body']['id'];

$r = request('GET', "/api/secret/$neverId");
assert_eq(200, $r['status'], 'never-expiry secret accessible');
assert_eq(false, $r['body']['lastView'], 'unlimited views, not last');

request('DELETE', "/api/secret/$neverId");

// ---------------------------------------------------------------
echo "\n14. Expiry codes — each code creates a secret\n";
// ---------------------------------------------------------------
$codes = ['T5M', 'T1H', 'T12H', '1D', '3D', '7D', 'never'];
foreach ($codes as $code) {
    $r = create_secret(['expires' => $code, 'maxViews' => 0]);
    assert_eq(201, $r['status'], "expiry code '$code' accepted");
    request('DELETE', "/api/secret/" . $r['body']['id']);
}

// ---------------------------------------------------------------
echo "\n15. Invalid expiry code defaults to 1D\n";
// ---------------------------------------------------------------
$r = create_secret(['expires' => 'GARBAGE']);
assert_eq(201, $r['status'], 'invalid code still creates secret (defaults to 1D)');
request('DELETE', "/api/secret/" . $r['body']['id']);

// ---------------------------------------------------------------
echo "\n16. Content size limit\n";
// ---------------------------------------------------------------
$huge = str_repeat('A', 16 * 1024 * 1024); // 16MB > 15MB limit
$r = create_secret(['content' => $huge]);
assert_eq(413, $r['status'], 'returns 413 for oversized content');

// ---------------------------------------------------------------
echo "\n17. UUID format validation on routes\n";
// ---------------------------------------------------------------
$r = request('GET', '/api/secret/../../etc/passwd');
assert_eq(404, $r['status'], 'path traversal rejected');

$r = request('GET', '/api/secret/<script>alert(1)</script>');
assert_eq(404, $r['status'], 'XSS in path rejected');

// ---------------------------------------------------------------
echo "\n18. Static file serving\n";
// ---------------------------------------------------------------
$ch = curl_init("$baseUrl/css/app.css");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$css = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_eq(200, $status, 'CSS file returns 200');
assert_true(strpos($css, ':root') !== false, 'CSS contains :root variables');

$ch = curl_init("$baseUrl/js/app.js");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$js = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_eq(200, $status, 'JS file returns 200');
assert_true(strpos($js, 'Secret') !== false, 'JS contains Secret object');

// ---------------------------------------------------------------
echo "\n19. Home page returns HTML\n";
// ---------------------------------------------------------------
$ch = curl_init("$baseUrl/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$html = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_eq(200, $status, 'home returns 200');
assert_true(strpos($html, '<form') !== false, 'home contains a form');
assert_true(strpos($html, 'max-views') !== false, 'home contains max-views select');
assert_true(strpos($html, 'password') !== false, 'home contains password field (NEW_ITEM_PASSWORD is set)');

// ---------------------------------------------------------------
echo "\n20. Show page — valid secret\n";
// ---------------------------------------------------------------
$r = create_secret(['maxViews' => 5]);
$showId = $r['body']['id'];
$ch = curl_init("$baseUrl/s/$showId");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$html = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_eq(200, $status, 'show page returns 200');
assert_true(strpos($html, $showId) !== false, 'show page contains secret id');
request('DELETE', "/api/secret/$showId");

// ---------------------------------------------------------------
echo "\n21. Show page — nonexistent secret\n";
// ---------------------------------------------------------------
$ch = curl_init("$baseUrl/s/00000000-0000-0000-0000-999999999999");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$html = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
assert_eq(404, $status, '404 for nonexistent secret');
assert_true(strpos($html, 'does not exist') !== false, '404 page shows error message');

// ---------------------------------------------------------------
echo "\n22. 404 for unknown routes\n";
// ---------------------------------------------------------------
$r = request('GET', '/nonexistent/path');
assert_eq(404, (int)$r['status'], 'unknown path returns 404');

// --- Teardown ---

proc_terminate($process);
proc_close($process);
@unlink($testEnv);
@unlink($testDb);
@unlink($testDb . '-wal');
@unlink($testDb . '-shm');

// --- Summary ---

echo "\n" . str_repeat('=', 50) . "\n";
echo "Results: $passed passed, $failed failed\n";

if ($failed > 0) {
    echo "\nFailures:\n";
    foreach ($errors as $e) echo "$e\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
