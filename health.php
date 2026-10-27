<?php
/**
 * Health check endpoint for Render (render.yaml → healthCheckPath).
 * Returns 200 only when the database accepts a trivial query.
 * Never exposes credentials or error details to the client.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config/database.php';

try {
    Database::getInstance()->getConnection()->query('SELECT 1');
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    error_log('Health check failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error']);
}
