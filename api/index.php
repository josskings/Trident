<?php
// 啟用錯誤報告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 允許跨域請求
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// 如果是OPTIONS請求，直接返回200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 資料庫連接設置
$host = 'localhost';
$db_name = 'restaurant_queue';
$username = 'root';
$password = ''; // XAMPP默認密碼為空

// 路由處理
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

// 移除前面的路徑部分，只保留API路徑
$api_index = array_search('api', $uri_parts);
if ($api_index !== false) {
    $uri_parts = array_slice($uri_parts, $api_index + 1);
}

// 獲取請求方法和路徑
$request_method = $_SERVER['REQUEST_METHOD'];
$endpoint = $uri_parts[0] ?? '';
$resource = $uri_parts[1] ?? '';
$id = $uri_parts[2] ?? null;

// 獲取請求體
$request_body = file_get_contents('php://input');
$data = json_decode($request_body, true);

// 創建資料庫連接
try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("set names utf8");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => '資料庫連接失敗: ' . $e->getMessage()]);
    exit;
}

// 路由到對應的控制器
switch ($endpoint) {
    case 'auth':
        require_once 'controllers/AuthController.php';
        $controller = new AuthController($conn);
        handleAuthRoutes($controller, $resource, $request_method, $data, $id);
        break;
    case 'verification':
        require_once 'controllers/VerificationController.php';
        $controller = new VerificationController($conn);
        handleVerificationRoutes($controller, $resource, $request_method, $data, $id);
        break;
    case 'queue':
        require_once 'controllers/QueueController.php';
        $controller = new QueueController($conn);
        handleQueueRoutes($controller, $resource, $request_method, $data, $id);
        break;
    case 'blacklist':
        require_once 'controllers/BlacklistController.php';
        $controller = new BlacklistController($conn);
        handleBlacklistRoutes($controller, $resource, $request_method, $data, $id);
        break;
    case 'statistics':
        require_once 'controllers/StatisticsController.php';
        $controller = new StatisticsController($conn);
        handleStatisticsRoutes($controller, $resource, $request_method, $data, $id);
        break;
    case 'table-types':
        require_once 'controllers/TableTypeController.php';
        $controller = new TableTypeController($conn);
        handleTableTypeRoutes($controller, $resource, $request_method, $data, $id);
        break;
    case 'records':
        require_once 'controllers/RecordsController.php';
        $controller = new RecordsController($conn);
        handleRecordsRoutes($controller, $resource, $request_method, $data, $id);
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => '找不到請求的端點']);
        break;
}

// 處理認證路由
function handleAuthRoutes($controller, $resource, $method, $data, $id) {
    switch ($resource) {
        case 'login':
            if ($method === 'POST') {
                $controller->login($data);
            } else {
                methodNotAllowed();
            }
            break;
        default:
            resourceNotFound();
            break;
    }
}

// 處理驗證路由
function handleVerificationRoutes($controller, $resource, $method, $data, $id) {
    switch ($resource) {
        case 'request':
            if ($method === 'POST') {
                $controller->requestVerification($data);
            } else {
                methodNotAllowed();
            }
            break;
        case 'verify':
            if ($method === 'POST') {
                $controller->verifyCode($data);
            } else {
                methodNotAllowed();
            }
            break;
        default:
            resourceNotFound();
            break;
    }
}

// 處理候位路由
function handleQueueRoutes($controller, $resource, $method, $data, $id) {
    switch ($resource) {
        case 'status':
            if ($method === 'GET') {
                $controller->getStatus();
            } else {
                methodNotAllowed();
            }
            break;
        case 'ticket':
            if ($id && $method === 'GET') {
                $controller->getTicket($id);
            } elseif ($id && $method === 'PUT' && isset($uri_parts[3]) && $uri_parts[3] === 'status') {
                $controller->updateTicketStatus($id, $data);
            } else {
                resourceNotFound();
            }
            break;
        case 'remote':
            if ($method === 'POST') {
                $controller->createRemoteTicket($data);
            } else {
                methodNotAllowed();
            }
            break;
        case 'onsite':
            if ($method === 'POST') {
                $controller->createOnsiteTicket($data);
            } else {
                methodNotAllowed();
            }
            break;
        case 'next':
            if ($id && $method === 'POST') {
                $controller->callNextCustomer($id);
            } else {
                resourceNotFound();
            }
            break;
        default:
            resourceNotFound();
            break;
    }
}

// 處理黑名單路由
function handleBlacklistRoutes($controller, $resource, $method, $data, $id) {
    if ($resource === '' && $method === 'GET') {
        $controller->getBlacklist();
    } elseif ($id && $method === 'PUT') {
        $controller->updateBlacklistStatus($id, $data);
    } else {
        resourceNotFound();
    }
}

// 處理統計路由
function handleStatisticsRoutes($controller, $resource, $method, $data, $id) {
    switch ($resource) {
        case 'daily':
            if ($method === 'GET') {
                $date = $_GET['date'] ?? date('Y-m-d');
                $controller->getDailyStatistics($date);
            } else {
                methodNotAllowed();
            }
            break;
        case 'waiting-time':
            if ($method === 'GET') {
                $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
                $endDate = $_GET['end_date'] ?? date('Y-m-d');
                $isPeakHour = isset($_GET['is_peak_hour']) ? filter_var($_GET['is_peak_hour'], FILTER_VALIDATE_BOOLEAN) : null;
                $controller->getWaitingTimeStatistics($startDate, $endDate, $isPeakHour);
            } else {
                methodNotAllowed();
            }
            break;
        default:
            resourceNotFound();
            break;
    }
}

// 處理桌位類型路由
function handleTableTypeRoutes($controller, $resource, $method, $data, $id) {
    if ($resource === '' && $method === 'GET') {
        $controller->getAllTableTypes();
    } else {
        resourceNotFound();
    }
}

// 處理記錄路由
function handleRecordsRoutes($controller, $resource, $method, $data, $id) {
    if ($resource === '' && $method === 'GET') {
        $date = $_GET['date'] ?? date('Y-m-d');
        $status = $_GET['status'] ?? null;
        $controller->getRecords($date, $status);
    } else {
        resourceNotFound();
    }
}

// 方法不允許
function methodNotAllowed() {
    http_response_code(405);
    echo json_encode(['error' => '方法不允許']);
    exit;
}

// 資源不存在
function resourceNotFound() {
    http_response_code(404);
    echo json_encode(['error' => '資源不存在']);
    exit;
}
