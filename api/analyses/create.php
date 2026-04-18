<?php
/**
 * POST /api/analyses/create
 * Body: { "website_url": "https://example.com" }
 *
 * Scrapes the target website and creates an alleogen_analyses row.
 * Auth: either a logged-in subscriber session OR no auth (anonymous
 * one-timer kicking off the flow before payment). Anonymous callers
 * get an access_token; the token gates all follow-up reads.
 *
 * Response:
 *   { analysis_id, access_token, extracted_data }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/scraper.php';

requireMethod('POST');

$auth = authenticateRequest();
$userId = null;
if ($auth !== null && $auth['type'] === 'session') {
    $userId = (int) $auth['user_id'];
}

$body = readJsonBody();
$websiteUrl = trim((string) ($body['website_url'] ?? ''));

if ($websiteUrl === '') {
    jsonResponse(['error' => 'website_url is required.'], 400);
}
if (!preg_match('#^https?://#i', $websiteUrl)) {
    $websiteUrl = 'https://' . $websiteUrl;
}
$parsed = parse_url($websiteUrl);
if ($parsed === false || empty($parsed['host'])) {
    jsonResponse(['error' => 'Invalid website URL.'], 400);
}

// Create the analysis row immediately so the client has something to poll.
$pdo = getDB();
$accessToken = generateAccessToken();
$stmt = $pdo->prepare(
    "INSERT INTO alleogen_analyses (user_id, access_token, url, status)
     VALUES (:uid, :token, :url, 'analyzing')"
);
$stmt->execute([
    ':uid'   => $userId,
    ':token' => $accessToken,
    ':url'   => $websiteUrl,
]);
$analysisId = (int) $pdo->lastInsertId();

try {
    $extracted = analyzeWebsite($websiteUrl);
} catch (Throwable $e) {
    $message = $e->getMessage();
    $stmt = $pdo->prepare(
        "UPDATE alleogen_analyses
         SET status = 'failed', error_message = :msg, completed_at = NOW()
         WHERE id = :id"
    );
    $stmt->execute([':msg' => $message, ':id' => $analysisId]);

    logError('scraper', "Scrape failed for {$websiteUrl}: {$message}", $userId, ['url' => $websiteUrl]);

    $userFacing = "Could not analyze website. Please check the URL and try again.";
    if (stripos($message, 'timeout') !== false) {
        $userFacing = 'Website analysis timed out. The website may be slow or blocking requests.';
    }
    jsonResponse(['error' => $userFacing], 502);
}

$platform = (string) ($extracted['platform'] ?? 'Custom/Other');
$pageCount = (int) ($extracted['pageCount'] ?? 0);
$siteStructure = $extracted['siteStructure'] ?? null;

$stmt = $pdo->prepare(
    "UPDATE alleogen_analyses SET
        status = 'completed',
        platform = :platform,
        page_count = :pc,
        extracted_data = :ext,
        site_structure = :ss,
        completed_at = NOW()
     WHERE id = :id"
);
$stmt->execute([
    ':platform' => $platform,
    ':pc'       => $pageCount,
    ':ext'      => json_encode($extracted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ':ss'       => $siteStructure !== null ? json_encode($siteStructure) : null,
    ':id'       => $analysisId,
]);

// If the caller is a logged-in subscriber, seed a generation row so
// the questionnaire page has something to save into.
$generationId = null;
if ($userId !== null) {
    $genToken = generateAccessToken();
    $stmt = $pdo->prepare(
        "INSERT INTO alleogen_generations
            (user_id, analysis_id, access_token, website_url, business_name,
             package_tier, status, purchase_type, payment_method)
         VALUES (:uid, :aid, :token, :url, :name, 'basic',
                 'questionnaire_incomplete', 'subscription', 'credit')"
    );
    $stmt->execute([
        ':uid'   => $userId,
        ':aid'   => $analysisId,
        ':token' => $genToken,
        ':url'   => $websiteUrl,
        ':name'  => $extracted['businessName'] ?? null,
    ]);
    $generationId = (int) $pdo->lastInsertId();
}

jsonResponse([
    'success'        => true,
    'analysis_id'    => $analysisId,
    'access_token'   => $accessToken,
    'generation_id'  => $generationId,
    'extracted_data' => $extracted,
], 200);
