<?php

// Requires Composer's autoloader to initialize all classes
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Payra\PayraUtils;

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
$requiredParams = ['network', 'amount_to_convert', 'currency_to_convert', 'decimals_token', 'amount_to_wei', 'currency_to_wei', 'amount_in_wei', 'currency_from_wei_to'];
foreach ($requiredParams as $param) {
    if (!isset($data[$param])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => "Missing required parameter: '{$param}'."]);
        exit;
    }
}

try {

    $convert = PayraUtils::convertToUSD($data['amount_to_convert'], $data['currency_to_convert']);
    $tokenDecimals = PayraUtils::getTokenDecimals($data['network'], $data['decimals_token']);
    $amountWei = PayraUtils::toWei($data['amount_to_wei'], $data['network'], $data['currency_to_wei']);
    $fromWei = PayraUtils::fromWei($data['amount_in_wei'], $data['network'], $data['currency_from_wei_to']);

    echo json_encode([
        'convert' => $convert,
        'token_decimals' => $tokenDecimals,
        'amount_wei' => $amountWei,
        'from_wei' => $fromWei,
    ]);

} catch (\Exception $e) {
    error_log('Signature error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error.']);
}

?>