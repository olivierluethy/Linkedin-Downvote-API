<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// === DEBUG: ALLE FEHLER ANZEIGEN ===
error_reporting(E_ALL);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // oder gezielt https://www.linkedin.com
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Private-Network: true'); // WICHTIG für localhost von fremder Origin

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight für CORS
    http_response_code(200);
    exit;
}

// .env laden
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO(
        "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed', 'details' => $e->getMessage()]);
    exit;
}

// Routing anhand des Query-Parameters ?route=
$route = $_GET['route'] ?? null;

switch ($route) {

    /**
     * -------------------------------------------------------
     * POST /index.php?route=dislike
     * Body: { "post_id": "...", "client_id": "..." }
     * -------------------------------------------------------
     */
    case 'dislike':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $post_id = $input['post_id'] ?? null;
        $client_id = $input['client_id'] ?? null;

        if (!$post_id || !$client_id) {
            http_response_code(400);
            echo json_encode(['error' => 'post_id and client_id required']);
            exit;
        }

        try {
            // Prüfen, ob schon disliked
            $check = $pdo->prepare("SELECT 1 FROM dislikes WHERE post_id=? AND client_id=?");
            $check->execute([$post_id, $client_id]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Already disliked']);
                exit;
            }

            $pdo->beginTransaction();

            // Eintragen
            $pdo->prepare("INSERT INTO dislikes (post_id, client_id) VALUES (?, ?)")->execute([$post_id, $client_id]);

            // Counter hochzählen oder anlegen
            $pdo->prepare("
                INSERT INTO post_dislike_count (post_id, dislike_count)
                VALUES (?, 1)
                ON DUPLICATE KEY UPDATE dislike_count = dislike_count + 1
            ")->execute([$post_id]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Disliked']);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;


        /**
 * -------------------------------------------------------
 * POST /index.php?route=undislike
 * Body: { "post_id": "...", "client_id": "..." }
 * -------------------------------------------------------
 */
case 'undislike':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $post_id = $input['post_id'] ?? null;
    $client_id = $input['client_id'] ?? null;

    if (!$post_id || !$client_id) {
        http_response_code(400);
        echo json_encode(['error' => 'post_id and client_id required']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Prüfen, ob der Dislike existiert
        $check = $pdo->prepare("SELECT 1 FROM dislikes WHERE post_id=? AND client_id=?");
        $check->execute([$post_id, $client_id]);
        if (!$check->fetch()) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Not disliked']);
            exit;
        }

        // Dislike entfernen
        $pdo->prepare("DELETE FROM dislikes WHERE post_id=? AND client_id=?")
            ->execute([$post_id, $client_id]);

        // Zähler verringern (nur wenn > 0)
        $pdo->prepare("
            UPDATE post_dislike_count 
            SET dislike_count = GREATEST(dislike_count - 1, 0) 
            WHERE post_id = ?
        ")->execute([$post_id]);

        // Falls Zähler 0 → Zeile löschen (optional)
        $pdo->prepare("DELETE FROM post_dislike_count WHERE post_id=? AND dislike_count = 0")
            ->execute([$post_id]);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Dislike removed']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;
    /**
     * -------------------------------------------------------
     * GET /index.php?route=dislike-count&post_id=...
     * -------------------------------------------------------
     */
    case 'dislike-count':
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $post_id = $_GET['post_id'] ?? null;
        if (!$post_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing post_id']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT dislike_count FROM post_dislike_count WHERE post_id=?");
        $stmt->execute([$post_id]);
        $count = $stmt->fetchColumn() ?: 0;

        echo json_encode(['post_id' => $post_id, 'dislike_count' => (int)$count]);
        break;


    /**
     * -------------------------------------------------------
     * Default: Route nicht gefunden
     * -------------------------------------------------------
     */
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
        break;
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
