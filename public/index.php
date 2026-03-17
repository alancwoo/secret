<?php
/**
 * Secret — End-to-end encrypted secret sharing
 * Single-file PHP application. No framework required.
 * 
 * Requirements: PHP 8.0+, PDO SQLite extension, mod_rewrite
 */

// ---------------------------------------------------------------------------
// Static file handling (for PHP built-in server / Valet / Herd)
// ---------------------------------------------------------------------------

$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($requestPath !== '/' && $requestPath !== '') {
    $filePath = __DIR__ . $requestPath;
    if (is_file($filePath)) {
        // Let PHP's built-in server serve the file directly
        if (php_sapi_name() === 'cli-server') {
            return false;
        }
        // For other SAPI (e.g. Valet/Herd), serve manually with correct MIME type
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'json' => 'application/json',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'webmanifest' => 'application/manifest+json',
            'xml'  => 'application/xml',
        ];
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = $mimeTypes[$ext] ?? mime_content_type($filePath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

define('ROOT_DIR', dirname(__DIR__));
define('VIEWS_DIR', ROOT_DIR . '/views');
define('DB_PATH', getenv('DB_PATH') ?: ROOT_DIR . '/data/secrets.db');
define('MAX_CONTENT_SIZE', 15 * 1024 * 1024); // 15MB (base64 overhead for ~10MB file)

// Load .env file
$envFile = getenv('ENV_FILE') ?: ROOT_DIR . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Remove surrounding quotes
        if (preg_match('/^"(.*)"$/', $value, $m)) $value = $m[1];
        if (preg_match("/^'(.*)'$/", $value, $m)) $value = $m[1];
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

function env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// ---------------------------------------------------------------------------
// Database
// ---------------------------------------------------------------------------

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Enable WAL mode for better concurrent read/write
    $pdo->exec('PRAGMA journal_mode=WAL');

    // Create table if not exists
    $pdo->exec('CREATE TABLE IF NOT EXISTS secrets (
        id         TEXT PRIMARY KEY,
        content    TEXT NOT NULL,
        iv         TEXT NOT NULL,
        is_file    INTEGER NOT NULL DEFAULT 0,
        filename   TEXT,
        mimetype   TEXT,
        expires    TEXT NOT NULL,
        max_views  INTEGER NOT NULL DEFAULT 1,
        views      INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )');

    // Migrate: add max_views and views columns if missing
    $cols = array_column($pdo->query('PRAGMA table_info(secrets)')->fetchAll(), 'name');
    if (!in_array('max_views', $cols)) {
        $pdo->exec('ALTER TABLE secrets ADD COLUMN max_views INTEGER NOT NULL DEFAULT 1');
    }
    if (!in_array('views', $cols)) {
        $pdo->exec('ALTER TABLE secrets ADD COLUMN views INTEGER NOT NULL DEFAULT 0');
    }

    return $pdo;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variant 1
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function render(string $view, array $data = []): void {
    extract($data);
    $__view = VIEWS_DIR . '/' . $view . '.php';
    if (!file_exists($__view)) {
        http_response_code(500);
        echo "View not found: $view";
        exit;
    }
    // Start output buffering for the content section
    ob_start();
    require $__view;
    $__content = ob_get_clean();
    // If the view set $layout, wrap it; otherwise output directly
    if (isset($layout)) {
        $content = $__content;
        require VIEWS_DIR . '/layout.php';
    } else {
        echo $__content;
    }
    exit;
}

/**
 * Calculate expiry datetime from an ISO 8601 duration-style code.
 * Accepts: T5M, T1H, T12H, 1D, 3D, 7D, never
 * "never" returns a date 100 years from now (effectively unlimited).
 */
function calculate_expiry(string $code): string {
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $map = [
        'T5M'   => 'PT5M',
        'T1H'   => 'PT1H',
        'T12H'  => 'PT12H',
        '1D'    => 'P1D',
        '3D'    => 'P3D',
        '7D'    => 'P7D',
        'never' => 'P100Y',
    ];
    $interval = $map[$code] ?? 'P1D';
    $now->add(new DateInterval($interval));
    return $now->format('Y-m-d\TH:i:s\Z');
}

/**
 * Clean up expired secrets (runs on each request, very cheap query).
 */
function cleanup_expired(): void {
    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    db()->prepare('DELETE FROM secrets WHERE expires < ?')->execute([$now]);
}

// ---------------------------------------------------------------------------
// Request parsing
// ---------------------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip trailing slash (except root)
if ($uri !== '/' && str_ends_with($uri, '/')) {
    $uri = rtrim($uri, '/');
}

// ---------------------------------------------------------------------------
// Routes
// ---------------------------------------------------------------------------

// Cleanup expired secrets on every request (fast indexed DELETE)
cleanup_expired();

// GET / — Create secret form
if ($method === 'GET' && $uri === '/') {
    render('create', [
        'title'           => 'New Secret',
        'requirePassword' => env('NEW_ITEM_PASSWORD') !== '',
        'allowedTags'     => env('ALLOWED_TAGS', 'br,a'),
    ]);
}

// POST /api/secret — Create a new secret (JSON API)
if ($method === 'POST' && $uri === '/api/secret') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || empty($input['content']) || empty($input['iv'])) {
        json_response(['error' => 'Missing required fields'], 400);
    }

    // Check content size
    if (strlen($input['content']) > MAX_CONTENT_SIZE) {
        json_response(['error' => 'Content exceeds maximum size'], 413);
    }

    // Password check
    $requiredPass = env('NEW_ITEM_PASSWORD');
    if ($requiredPass !== '') {
        $givenPass = $input['password'] ?? '';
        if ($givenPass !== $requiredPass) {
            json_response(['error' => 'Password Incorrect'], 401);
        }
    }

    $id       = uuid();
    $expires  = calculate_expiry($input['expires'] ?? '1D');
    $isFile   = !empty($input['isFile']) ? 1 : 0;
    $maxViews = intval($input['maxViews'] ?? 1);
    if ($maxViews < 0) $maxViews = 0; // 0 = unlimited

    $now = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    $stmt = db()->prepare('INSERT INTO secrets (id, content, iv, is_file, filename, mimetype, expires, max_views, views, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?)');
    $stmt->execute([
        $id,
        $input['content'],
        $input['iv'],
        $isFile,
        $isFile ? ($input['filename'] ?? null) : null,
        $isFile ? ($input['mimetype'] ?? null) : null,
        $expires,
        $maxViews,
        $now,
    ]);

    json_response(['id' => $id], 201);
}

// GET /api/secret/{id} — Fetch secret data (JSON API, used by show page)
if ($method === 'GET' && preg_match('#^/api/secret/([0-9a-f-]+)$#i', $uri, $m)) {
    $id   = $m[1];
    $stmt = db()->prepare('SELECT * FROM secrets WHERE id = ?');
    $stmt->execute([$id]);
    $secret = $stmt->fetch();

    if (!$secret) {
        json_response(['error' => 'Secret not found or expired'], 404);
    }

    // Check expiry
    $expires = new DateTime($secret['expires'], new DateTimeZone('UTC'));
    if (new DateTime('now', new DateTimeZone('UTC')) > $expires) {
        db()->prepare('DELETE FROM secrets WHERE id = ?')->execute([$id]);
        json_response(['error' => 'Secret not found or expired'], 404);
    }

    // Increment view count
    $newViews = (int)$secret['views'] + 1;
    $maxViews = (int)$secret['max_views'];
    db()->prepare('UPDATE secrets SET views = ? WHERE id = ?')->execute([$newViews, $id]);

    // If this view exhausts the limit, delete the secret
    // (max_views=0 means unlimited — only time expiry applies)
    $lastView = ($maxViews > 0 && $newViews >= $maxViews);
    if ($lastView) {
        db()->prepare('DELETE FROM secrets WHERE id = ?')->execute([$id]);
    }

    json_response([
        'id'        => $secret['id'],
        'content'   => $secret['content'],
        'iv'        => $secret['iv'],
        'isFile'    => (bool)$secret['is_file'],
        'filename'  => $secret['filename'],
        'mimetype'  => $secret['mimetype'],
        'lastView'  => $lastView,
        'views'     => $newViews,
        'maxViews'  => $maxViews,
    ]);
}

// DELETE /api/secret/{id} — Delete a secret
if ($method === 'DELETE' && preg_match('#^/api/secret/([0-9a-f-]+)$#i', $uri, $m)) {
    $id = $m[1];
    db()->prepare('DELETE FROM secrets WHERE id = ?')->execute([$id]);
    json_response(['deleted' => true]);
}

// GET /s/{id} — Show/decrypt secret page
if ($method === 'GET' && preg_match('#^/s/([0-9a-f-]+)$#i', $uri, $m)) {
    $id = $m[1];

    // Check if secret exists (don't leak content — the JS will fetch via API)
    $stmt = db()->prepare('SELECT id FROM secrets WHERE id = ?');
    $stmt->execute([$id]);
    $exists = $stmt->fetch();

    if (!$exists) {
        http_response_code(404);
        render('404', ['title' => 'Not Found']);
    }

    render('show', [
        'title'       => 'Secret',
        'id'          => $id,
        'allowedTags' => env('ALLOWED_TAGS', 'br,a'),
    ]);
}

// ---------------------------------------------------------------------------
// Fallback: 404
// ---------------------------------------------------------------------------
http_response_code(404);
render('404', ['title' => 'Not Found']);
