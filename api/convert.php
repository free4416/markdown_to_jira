<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'MarkdownConverter.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['markdown'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No markdown provided']);
        exit;
    }

    $markdown = $input['markdown'];
    $headerStyle = $input['headerStyle'] ?? 'jira';
    
    try {
        $converter = new MarkdownConverter();
        $result = [
            'jira' => $converter->toJira($markdown, $headerStyle),
            'slack' => $converter->toSlack($markdown),
            'plaintext' => $converter->toPlainText($markdown)
        ];
        
        echo json_encode($result);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 