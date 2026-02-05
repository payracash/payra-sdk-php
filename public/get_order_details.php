<?php

// Requires Composer's autoloader to initialize all classes
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Payra\PayraOrderService;

// Load environment variables from the .env file
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

header('Content-Type: application/json');
/* The headers below are only required when the backend and frontend are hosted on separate domains. */
//header('Access-Control-Allow-Origin: https://your-front.com');
//header('Access-Control-Allow-Methods: POST, OPTIONS');
//header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request (CORS preflight request)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Only POST requests are allowed.']);
    exit;
}

// Check content type
if (
    !isset($_SERVER['CONTENT_TYPE']) ||
    stripos($_SERVER['CONTENT_TYPE'], 'application/json') === false
) {
    http_response_code(415); // Unsupported Media Type
    echo json_encode(['status' => 'error', 'message' => 'Content-Type must be application/json.']);
    exit;
}

// Get JSON input data from the request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input.']);
    exit;
}

// Validate params
$requiredParams = ['network', 'orderId'];
foreach ($requiredParams as $param) {
    if (!isset($data[$param])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing required parameter: '{$param}'."]);
        exit;
    }
}

try {
    // Instance "SDK"
    $orderService = new PayraOrderService();

    // Call order verification (return array)
    $orderDetails = $orderService->getDetails(
        $data['network'],
        $data['orderId']
    );

    // Return result to frontend
    echo json_encode([
        'result'  => $orderDetails,
    ]);

} catch (\Throwable $e) {
    error_log('Signature error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal server error.',
        'reason' => $e->getMessage(),
    ]);
}

?>
