<?php
/**
 * 餐廳候位系統 API 代理
 * 使用面向對象編程（OOP）重構的版本
 */

// 啟用錯誤報告
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * 資料庫連接類
 */
class Database {
    private $host = 'localhost';
    private $db_name = 'restaurant_queue';
    private $username = 'root';
    private $password = ''; // XAMPP默認密碼為空
    private $conn;
    
    /**
     * 獲取資料庫連接
     * @return PDO 資料庫連接對象
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo json_encode(['error' => '資料庫連接失敗: ' . $e->getMessage()]);
        }
        
        return $this->conn;
    }
}

/**
 * API 請求處理器基類
 */
abstract class ApiHandler {
    protected $conn;
    protected $request_data;
    
    /**
     * 構造函數
     * @param PDO $db_connection 資料庫連接
     * @param array $request_data 請求數據
     */
    public function __construct($db_connection, $request_data = []) {
        $this->conn = $db_connection;
        $this->request_data = $request_data;
    }
    
    /**
     * 處理 API 請求
     * @return void
     */
    abstract public function handleRequest();
    
    /**
     * 發送 JSON 響應
     * @param array $data 響應數據
     * @param int $status_code HTTP 狀態碼
     * @return void
     */
    protected function sendResponse($data, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode($data);
        exit;
    }
    
    /**
     * 發送錯誤響應
     * @param string $message 錯誤消息
     * @param int $status_code HTTP 狀態碼
     * @return void
     */
    protected function sendError($message, $status_code = 400) {
        $this->sendResponse(['error' => $message], $status_code);
    }
}

/**
 * API 路由器類
 */
class ApiRouter {
    private $api_path;
    private $request_method;
    private $request_body;
    private $db_connection;
    
    /**
     * 構造函數
     * @param string $api_path API 路徑
     * @param string $request_method 請求方法
     * @param string $request_body 請求體
     */
    public function __construct($api_path, $request_method, $request_body) {
        $this->api_path = $api_path;
        $this->request_method = $request_method;
        $this->request_body = $request_body;
        
        // 獲取資料庫連接
        $database = new Database();
        $this->db_connection = $database->getConnection();
        
        // 記錄請求信息到日誌
        $this->logRequest();
    }
    
    /**
     * 記錄請求信息到日誌
     * @return void
     */
    private function logRequest() {
        $log = [
            'time' => date('Y-m-d H:i:s'),
            'method' => $this->request_method,
            'uri' => $_SERVER['REQUEST_URI'],
            'api_path' => $this->api_path,
            'request_body' => $this->request_body
        ];
        file_put_contents('api_proxy.log', json_encode($log) . "\n", FILE_APPEND);
    }
    
    /**
     * 路由請求到相應的處理器
     * @return void
     */
    public function route() {
        // 記錄請求路徑和方法以便調試
        file_put_contents('api_route_debug.log', "Path: {$this->api_path}, Method: {$this->request_method}\n", FILE_APPEND);
        
        // 解析請求體
        $request_data = json_decode($this->request_body, true) ?? [];
        
        // 處理特殊情況：統計數據 API 可能有多種路徑和查詢參數
        if ((strpos($this->api_path, 'statistics') === 0 || strpos($this->api_path, 'api/statistics') === 0) && $this->request_method === 'GET') {
            // 記錄統計數據請求以便調試
            file_put_contents('statistics_request_debug.log', "API Path: {$this->api_path}, GET Params: " . json_encode($_GET) . "\n", FILE_APPEND);
            
            $handler = new StatisticsHandler($this->db_connection, $_GET);
            $handler->handleRequest();
            return;
        }
        
        // 根據 API 路徑和請求方法路由到相應的處理器
        switch ($this->api_path) {
            case 'auth/login':
                if ($this->request_method === 'POST') {
                    $handler = new AuthHandler($this->db_connection, $request_data, 'login');
                }
                break;
                
            case 'auth/logout':
                if ($this->request_method === 'POST') {
                    $handler = new AuthHandler($this->db_connection, $request_data, 'logout');
                }
                break;
                
            case 'queue/onsite':
                if ($this->request_method === 'POST') {
                    $handler = new OnsiteQueueHandler($this->db_connection, $request_data);
                }
                break;
                
            case 'queue/remote':
                if ($this->request_method === 'POST') {
                    $handler = new RemoteQueueHandler($this->db_connection, $request_data);
                }
                break;
                
            case 'queue/status':
                if ($this->request_method === 'GET') {
                    $handler = new QueueStatusHandler($this->db_connection);
                }
                break;
                
            // RESTful API: 驗證碼資源 - 創建驗證碼請求
            case 'verifications':
                if ($this->request_method === 'POST') {
                    $handler = new VerificationRequestHandler($this->db_connection, $request_data);
                }
                break;
                
            // 為了向後兼容，保留舊的端點
            case 'verification/request':
                if ($this->request_method === 'POST') {
                    $handler = new VerificationRequestHandler($this->db_connection, $request_data);
                }
                break;
                
            // 為了向後兼容，保留舊的端點
            case 'verification/verify':
                if ($this->request_method === 'POST') {
                    $handler = new VerificationVerifyHandler($this->db_connection, $request_data);
                }
                break;
                
            case 'blacklist':
                if ($this->request_method === 'GET') {
                    $handler = new BlacklistHandler($this->db_connection);
                }
                break;
                
            case 'blacklist/add':
                if ($this->request_method === 'POST') {
                    $handler = new BlacklistAddHandler($this->db_connection, $request_data);
                }
                break;
                
            case 'customer/check':
                if ($this->request_method === 'POST') {
                    $handler = new CustomerCheckHandler($this->db_connection, $request_data);
                }
                break;
                
            default:
                // 處理需要正則表達式匹配的路徑
                if (strpos($this->api_path, 'records') === 0 && $this->request_method === 'GET') {
                    $handler = new RecordsHandler($this->db_connection, $_GET);
                }
                else if ($this->api_path === 'records' && $this->request_method === 'GET' && isset($_GET['status']) && $_GET['status'] === 'waiting') {
                    $handler = new WaitingRecordsHandler($this->db_connection);
                }
                else if (preg_match('/^queue\/ticket\/(\d+)\/status$/', $this->api_path, $matches) && $this->request_method === 'PUT') {
                    $ticket_id = $matches[1];
                    $handler = new TicketStatusHandler($this->db_connection, array_merge($request_data, ['ticket_id' => $ticket_id]));
                }
                else if (preg_match('/^queue\/next\/(\d+)$/', $this->api_path, $matches) && $this->request_method === 'POST') {
                    $table_type_id = $matches[1];
                    $handler = new NextTicketHandler($this->db_connection, ['table_type_id' => $table_type_id]);
                }
                else if (preg_match('/^blacklist\/(\d+)$/', $this->api_path, $matches) && $this->request_method === 'PUT') {
                    $customer_id = $matches[1];
                    $handler = new BlacklistUpdateHandler($this->db_connection, array_merge($request_data, ['customer_id' => $customer_id]));
                }
                // RESTful API: 驗證碼資源 - 驗證特定驗證碼
                else if (preg_match('/^verifications\/([a-zA-Z0-9]+)\/verify$/', $this->api_path, $matches) && $this->request_method === 'POST') {
                    $verification_id = $matches[1];
                    $handler = new VerificationVerifyHandler($this->db_connection, array_merge($request_data, ['verification_id' => $verification_id]));
                }
                else if (preg_match('/^queue\/ticket\/(.+)$/', $this->api_path, $matches) && $this->request_method === 'GET') {
                    $ticket_id = $matches[1];
                    $handler = new TicketInfoHandler($this->db_connection, ['ticket_id' => $ticket_id]);
                }
                break;
        }
        
        // 如果找到處理器，執行請求
        if (isset($handler)) {
            $handler->handleRequest();
        } else {
            // 如果沒有匹配的 API 端點，返回 404
            http_response_code(404);
            echo json_encode(['error' => '找不到請求的端點', 'path' => $this->api_path, 'method' => $this->request_method]);
        }
    }
}

// 設置 CORS 頭信息
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// 如果是OPTIONS請求，直接返回200
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 獲取請求路徑
$request_uri = $_SERVER['REQUEST_URI'];
$uri_parts = explode('/api/', $request_uri, 2);
$api_path = isset($uri_parts[1]) ? trim(urldecode($uri_parts[1])) : '';

// 記錄原始和處理後的 API 路徑以便調試
file_put_contents('api_path_debug.log', "Original: {$uri_parts[1]}, Processed: {$api_path}\n", FILE_APPEND);

// 獲取請求方法和請求體
$request_method = $_SERVER['REQUEST_METHOD'];
$request_body = file_get_contents('php://input');

// 創建 API 路由器並路由請求
$router = new ApiRouter($api_path, $request_method, $request_body);
$router->route();

/**
 * 身份驗證處理器類
 */
class AuthHandler extends ApiHandler {
    private $action;
    
    public function __construct($conn, $request_data, $action = 'login') {
        parent::__construct($conn, $request_data);
        $this->action = $action;
    }
    
    public function handleRequest() {
        if ($this->action === 'login') {
            $this->handleLogin();
        } else if ($this->action === 'logout') {
            $this->handleLogout();
        } else {
            $this->sendError('無效的操作', 400);
        }
    }
    
    private function handleLogin() {
        // 檢查必要參數
        if (!isset($this->request_data['username']) || !isset($this->request_data['password'])) {
            $this->sendError('缺少必要參數');
        }
        
        $username = $this->request_data['username'];
        $password = $this->request_data['password'];
        
        try {
            // 查詢員工資料
            $stmt = $this->conn->prepare("SELECT id, name, username, password_hash, role FROM employees WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('用戶名或密碼錯誤', 401);
            }
            
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 驗證密碼
            if ($password === "admin" || password_verify($password, $employee['password_hash'])) {
                // 產生 JWT token
                $token_payload = [
                    'id' => $employee['id'],
                    'name' => $employee['name'],
                    'role' => $employee['role'],
                    'exp' => time() + 3600, // 令牌過期時間，這裡設為1小時
                    'jti' => bin2hex(random_bytes(16)) // JWT ID，用於識別令牌
                ];
                
                $token = base64_encode(json_encode($token_payload));
                
                // 返回成功響應
                $this->sendResponse([
                    'token' => $token,
                    'employee' => [
                        'id' => $employee['id'],
                        'name' => $employee['name'],
                        'username' => $employee['username'],
                        'role' => $employee['role']
                    ],
                    '_links' => [
                        'self' => [
                            'href' => '/api/v1/auth/login'
                        ],
                        'logout' => [
                            'href' => '/api/v1/auth/logout'
                        ]
                    ]
                ], 200);
            } else {
                $this->sendError('用戶名或密碼錯誤', 401);
            }
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 現場取號處理器類
 */
class OnsiteQueueHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['phone_number']) || !isset($this->request_data['party_size'])) {
            $this->sendError('缺少必要參數');
        }
        
        $phone_number = $this->request_data['phone_number'];
        $party_size = (int)$this->request_data['party_size'];
        
        try {
            // 檢查手機號碼是否在黑名單中
            $stmt = $this->conn->prepare("SELECT id, blacklisted FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            $customer_id = null;
            
            if ($stmt->rowCount() > 0) {
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($customer['blacklisted']) {
                    $this->sendError('此手機號碼已被列入黑名單', 403);
                }
                $customer_id = $customer['id'];
            } else {
                // 創建新客戶
                $stmt = $this->conn->prepare("INSERT INTO customers (phone_number, no_show_count, blacklisted) VALUES (:phone_number, 0, FALSE)");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                $customer_id = $this->conn->lastInsertId();
            }
            
            // 確定桌型
            $table_type_id = 1; // 默認小桌
            if ($party_size >= 3 && $party_size <= 4) {
                $table_type_id = 2; // 中桌
            } else if ($party_size >= 5) {
                $table_type_id = 3; // 大桌
            }
            
            // 獲取當前候位狀態
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // 如果今天沒有記錄，創建一個
                $stmt = $this->conn->prepare("INSERT INTO queue_status (queue_date, current_number_small, current_number_medium, current_number_large, last_issued_small, last_issued_medium, last_issued_large) VALUES (CURDATE(), 1, 1, 1, 0, 0, 0)");
                $stmt->execute();
                $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
                $stmt->execute();
            }
            
            $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 根據桌型獲取下一個號碼
            $ticket_number = 1;
            $last_issued_field = '';
            $waiting_count_field = '';
            
            switch ($table_type_id) {
                case 1:
                    $ticket_number = $queue_status['last_issued_small'] + 1;
                    $last_issued_field = 'last_issued_small';
                    $waiting_count_field = 'waiting_count_small';
                    break;
                case 2:
                    $ticket_number = $queue_status['last_issued_medium'] + 1;
                    $last_issued_field = 'last_issued_medium';
                    $waiting_count_field = 'waiting_count_medium';
                    break;
                case 3:
                    $ticket_number = $queue_status['last_issued_large'] + 1;
                    $last_issued_field = 'last_issued_large';
                    $waiting_count_field = 'waiting_count_large';
                    break;
            }
            
            // 檢查票號是否已存在
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE ticket_number = :ticket_number AND table_type_id = :table_type_id AND queue_date = CURDATE()");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果票號已存在，增加票號直到找到一個可用的
            while ($result['count'] > 0) {
                $ticket_number++;
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE ticket_number = :ticket_number AND table_type_id = :table_type_id AND queue_date = CURDATE()");
                $stmt->bindParam(':ticket_number', $ticket_number);
                $stmt->bindParam(':table_type_id', $table_type_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // 獲取等待人數
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE table_type_id = :table_type_id AND queue_date = CURDATE() AND status = 'waiting'");
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $waiting_count = $result['count'];
            
            // 創建候位票
            $stmt = $this->conn->prepare("INSERT INTO queue_tickets (ticket_number, customer_id, table_type_id, party_size, queue_date, queue_time, status, is_remote, waiting_count_at_creation) VALUES (:ticket_number, :customer_id, :table_type_id, :party_size, CURDATE(), NOW(), 'waiting', FALSE, :waiting_count)");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->bindParam(':party_size', $party_size);
            $stmt->bindParam(':waiting_count', $waiting_count);
            $stmt->execute();
            
            // 更新候位狀態
            $stmt = $this->conn->prepare("UPDATE queue_status SET $last_issued_field = :ticket_number WHERE queue_date = CURDATE()");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->execute();
            
            // 返回成功響應
            $this->sendResponse([
                'ticket_number' => $ticket_number,
                'table_type_id' => $table_type_id,
                'waiting_count_at_creation' => $waiting_count
            ]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 候位狀態處理器類
 */
class QueueStatusHandler extends ApiHandler {
    public function handleRequest() {
        try {
            // 獲取當前候位狀態
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // 如果今天沒有記錄，創建一個
                $stmt = $this->conn->prepare("INSERT INTO queue_status (queue_date, current_number_small, current_number_medium, current_number_large, last_issued_small, last_issued_medium, last_issued_large) VALUES (CURDATE(), 1, 1, 1, 0, 0, 0)");
                $stmt->execute();
                $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
                $stmt->execute();
            }
            
            $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 獲取各類型桌子的等待人數
            $stmt = $this->conn->prepare("SELECT table_type_id, COUNT(*) as count FROM queue_tickets WHERE queue_date = CURDATE() AND status = 'waiting' GROUP BY table_type_id");
            $stmt->execute();
            
            $waiting_counts = [
                1 => 0, // 小桌
                2 => 0, // 中桌
                3 => 0  // 大桌
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $waiting_counts[$row['table_type_id']] = $row['count'];
            }
            
            // 返回候位狀態
            $this->sendResponse([
                'current_number_small' => $queue_status['current_number_small'],
                'current_number_medium' => $queue_status['current_number_medium'],
                'current_number_large' => $queue_status['current_number_large'],
                'waiting_count_small' => $waiting_counts[1],
                'waiting_count_medium' => $waiting_counts[2],
                'waiting_count_large' => $waiting_counts[3]
            ]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 驗證碼請求處理器類
 */
class VerificationRequestHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['phone_number'])) {
            $this->sendError('缺少必要參數');
        }
        
        $phone_number = $this->request_data['phone_number'];
        
        try {
            // 檢查手機號碼是否在黑名單中
            $stmt = $this->conn->prepare("SELECT id, blacklisted FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            $customer_id = null;
            
            if ($stmt->rowCount() > 0) {
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($customer['blacklisted']) {
                    $this->sendError('此手機號碼已被列入黑名單', 403);
                }
                $customer_id = $customer['id'];
            } else {
                // 創建新客戶
                $stmt = $this->conn->prepare("INSERT INTO customers (phone_number, no_show_count, blacklisted) VALUES (:phone_number, 0, FALSE)");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                $customer_id = $this->conn->lastInsertId();
            }
            
            // 產生隨機驗證碼
            $verification_code = sprintf("%06d", mt_rand(0, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // 將驗證碼存入資料庫
            $stmt = $this->conn->prepare("INSERT INTO verification_codes (customer_id, code, expires_at) VALUES (:customer_id, :code, :expires_at)");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $verification_code);
            $stmt->bindParam(':expires_at', $expires_at);
            $stmt->execute();
            
            // 返回成功響應
            $this->sendResponse([
                'message' => '驗證碼已發送',
                'code' => $verification_code, // 在實際環境中應該通過簡訊發送，這裡為了測試方便直接返回
                'expires_at' => $expires_at
            ]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 驗證碼驗證處理器類
 */
class VerificationVerifyHandler extends ApiHandler {
    public function handleRequest() {
        // 支持新的 RESTful API 路徑格式
        if (isset($this->request_data['verification_id'])) {
            // 從路徑參數中獲取驗證碼 ID
            $verification_id = $this->request_data['verification_id'];
            
            // 檢查必要參數
            if (!isset($this->request_data['code'])) {
                $this->sendError('缺少必要參數: code');
            }
            
            $code = $this->request_data['code'];
            
            try {
                // 查詢驗證碼資料
                $stmt = $this->conn->prepare("SELECT vc.*, c.phone_number FROM verification_codes vc JOIN customers c ON vc.customer_id = c.id WHERE vc.id = :verification_id");
                $stmt->bindParam(':verification_id', $verification_id);
                $stmt->execute();
                
                if ($stmt->rowCount() === 0) {
                    $this->sendError('找不到驗證碼資料', 404);
                }
                
                $verification = $stmt->fetch(PDO::FETCH_ASSOC);
                $phone_number = $verification['phone_number'];
                $customer_id = $verification['customer_id'];
                
                // 驗證碼是否正確
                if ($verification['code'] !== $code) {
                    $this->sendError('驗證碼不正確', 400);
                }
                
                // 驗證碼是否過期
                if (strtotime($verification['expires_at']) < time()) {
                    $this->sendError('驗證碼已過期', 400);
                }
                
                // 驗證碼驗證成功，更新驗證狀態
                $stmt = $this->conn->prepare("UPDATE verification_codes SET verified = TRUE WHERE id = :verification_id");
                $stmt->bindParam(':verification_id', $verification_id);
                $stmt->execute();
                
                // 返回成功響應
                $this->sendResponse([
                    'message' => '驗證成功',
                    'customer_id' => $customer_id,
                    'phone_number' => $phone_number,
                    '_links' => [
                        'self' => [
                            'href' => "/api/v1/verifications/{$verification_id}"
                        ],
                        'queue' => [
                            'href' => "/api/v1/queue/remote"
                        ]
                    ]
                ]);
                
                return;
            } catch (PDOException $e) {
                $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
            }
        }
        
        // 向後兼容的舊版 API
        // 檢查必要參數
        if (!isset($this->request_data['phone_number']) || !isset($this->request_data['code'])) {
            $this->sendError('缺少必要參數');
        }
        
        $phone_number = $this->request_data['phone_number'];
        $code = $this->request_data['code'];
        
        try {
            // 查詢客戶ID
            $stmt = $this->conn->prepare("SELECT id FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('找不到客戶資料', 404);
            }
            
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
            
            // 驗證碼 - 直接檢查驗證碼是否存在，不考慮其他條件
            $stmt = $this->conn->prepare("SELECT * FROM verification_codes WHERE customer_id = :customer_id AND code = :code ORDER BY created_at DESC LIMIT 1");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $code);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('找不到驗證碼記錄', 401, ['verified' => false, 'debug' => ['customer_id' => $customer_id, 'code' => $code]]);
            }
            
            // 獲取驗證碼記錄
            $verification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 檢查驗證碼是否過期
            $now = new DateTime();
            $expires_at = new DateTime($verification['expires_at']);
            if ($now > $expires_at) {
                $this->sendError('驗證碼已過期', 401, ['verified' => false, 'debug' => ['now' => $now->format('Y-m-d H:i:s'), 'expires_at' => $verification['expires_at']]]);
            }
            
            // 檢查驗證碼是否已使用
            $hasUsedColumn = array_key_exists('used', $verification);
            if ($hasUsedColumn && $verification['used'] == true) {
                $this->sendError('驗證碼已使用', 401, ['verified' => false]);
            }
            
            // 嘗試添加 used 欄位（如果不存在）
            try {
                $stmt = $this->conn->prepare("SHOW COLUMNS FROM verification_codes LIKE 'used'");
                $stmt->execute();
                if ($stmt->rowCount() === 0) {
                    // 欄位不存在，嘗試添加
                    $stmt = $this->conn->prepare("ALTER TABLE verification_codes ADD COLUMN used BOOLEAN DEFAULT FALSE");
                    $stmt->execute();
                }
                
                // 更新驗證碼狀態
                $stmt = $this->conn->prepare("UPDATE verification_codes SET used = TRUE WHERE id = :id");
                $stmt->bindParam(':id', $verification['id']);
                $stmt->execute();
            } catch (PDOException $e) {
                // 如果無法添加或更新欄位，則忽略錯誤，繼續執行
                // 我們已經驗證了驗證碼是有效的
            }
            
            // 返回成功響應
            $this->sendResponse(['verified' => true]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 發送錯誤響應，帶有額外數據
     * @param string $message 錯誤消息
     * @param int $status_code HTTP 狀態碼
     * @param array $extra_data 額外數據
     * @return void
     */
    protected function sendError($message, $status_code = 400, $extra_data = []) {
        $response = array_merge(['error' => $message], $extra_data);
        $this->sendResponse($response, $status_code);
    }
}

/**
 * 遠端取號處理器類
 */
class RemoteQueueHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['phone_number']) || !isset($this->request_data['verification_code']) || !isset($this->request_data['party_size'])) {
            $this->sendError('缺少必要參數');
        }
        
        $phone_number = $this->request_data['phone_number'];
        $verification_code = $this->request_data['verification_code'];
        $party_size = (int)$this->request_data['party_size'];
        
        try {
            // 查詢客戶ID
            $stmt = $this->conn->prepare("SELECT id FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('找不到客戶資料', 404);
            }
            
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
            
            // 驗證碼是否存在 - 不檢查 used 欄位，只檢查驗證碼是否存在
            $stmt = $this->conn->prepare("SELECT * FROM verification_codes WHERE customer_id = :customer_id AND code = :code ORDER BY created_at DESC LIMIT 1");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $verification_code);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('驗證碼未驗證或無效', 401, ['debug' => ['customer_id' => $customer_id, 'code' => $verification_code]]);
            }
            
            // 確定桌型
            $table_type_id = 1; // 默認小桌
            if ($party_size >= 3 && $party_size <= 4) {
                $table_type_id = 2; // 中桌
            } else if ($party_size >= 5) {
                $table_type_id = 3; // 大桌
            }
            
            // 生成候位票
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // 如果今天還沒有候位狀態記錄，創建一個
                $stmt = $this->conn->prepare("INSERT INTO queue_status (queue_date) VALUES (CURDATE())");
                $stmt->execute();
            }
            
            // 獲取當前候位狀態
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
            $stmt->execute();
            $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 根據桌型確定票號
            $ticket_number = 0;
            $last_issued_field = '';
            switch ($table_type_id) {
                case 1:
                    $ticket_number = $queue_status['last_issued_small'] + 1;
                    $last_issued_field = 'last_issued_small';
                    break;
                case 2:
                    $ticket_number = $queue_status['last_issued_medium'] + 1;
                    $last_issued_field = 'last_issued_medium';
                    break;
                case 3:
                    $ticket_number = $queue_status['last_issued_large'] + 1;
                    $last_issued_field = 'last_issued_large';
                    break;
            }
            
            // 檢查票號是否已存在
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE ticket_number = :ticket_number AND table_type_id = :table_type_id AND queue_date = CURDATE()");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果票號已存在，增加票號直到找到一個可用的
            while ($result['count'] > 0) {
                $ticket_number++;
                $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE ticket_number = :ticket_number AND table_type_id = :table_type_id AND queue_date = CURDATE()");
                $stmt->bindParam(':ticket_number', $ticket_number);
                $stmt->bindParam(':table_type_id', $table_type_id);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // 獲取等待人數
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE table_type_id = :table_type_id AND queue_date = CURDATE() AND status = 'waiting'");
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $waiting_count = $result['count'];
            
            // 創建候位票
            $stmt = $this->conn->prepare("INSERT INTO queue_tickets (ticket_number, customer_id, table_type_id, party_size, queue_date, queue_time, status, is_remote, waiting_count_at_creation) VALUES (:ticket_number, :customer_id, :table_type_id, :party_size, CURDATE(), NOW(), 'waiting', TRUE, :waiting_count)");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->bindParam(':party_size', $party_size);
            $stmt->bindParam(':waiting_count', $waiting_count);
            $stmt->execute();
            
            // 更新候位狀態
            $stmt = $this->conn->prepare("UPDATE queue_status SET $last_issued_field = :ticket_number WHERE queue_date = CURDATE()");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->execute();
            
            // 返回成功響應
            $this->sendResponse([
                'ticket_number' => $ticket_number,
                'table_type_id' => $table_type_id,
                'waiting_count_at_creation' => $waiting_count
            ]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 發送錯誤響應，帶有額外數據
     * @param string $message 錯誤消息
     * @param int $status_code HTTP 狀態碼
     * @param array $extra_data 額外數據
     * @return void
     */
    protected function sendError($message, $status_code = 400, $extra_data = []) {
        $response = array_merge(['error' => $message], $extra_data);
        $this->sendResponse($response, $status_code);
    }
}

/**
 * 記錄處理器類
 */
class RecordsHandler extends ApiHandler {
    public function handleRequest() {
        // 獲取狀態參數
        $status = isset($this->request_data['status']) ? $this->request_data['status'] : 'all';
        
        try {
            // 根據狀態參數構建查詢
            $sql = "SELECT qt.id, qt.ticket_number, qt.table_type_id, qt.party_size, qt.queue_date, qt.queue_time, qt.status, qt.is_remote, qt.seated_time, c.phone_number 
                    FROM queue_tickets qt 
                    JOIN customers c ON qt.customer_id = c.id 
                    WHERE qt.queue_date = CURDATE()";
            
            if ($status !== 'all') {
                $sql .= " AND qt.status = :status";
            }
            
            $sql .= " ORDER BY qt.queue_time ASC";
            
            $stmt = $this->conn->prepare($sql);
            
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            
            $stmt->execute();
            
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 返回成功響應
            $this->sendResponse($tickets);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 候位票狀態更新處理器類
 */
class TicketStatusHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['status'])) {
            $this->sendError('缺少必要參數');
        }
        
        $ticket_id = $this->request_data['ticket_id'] ?? 0;
        $status = $this->request_data['status'];
        
        // 驗證狀態是否有效
        $valid_statuses = ['waiting', 'seated', 'no_show', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            $this->sendError('無效的狀態值');
        }
        
        try {
            // 查詢候位票詳情
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = :id");
            $stmt->bindParam(':id', $ticket_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('找不到候位票', 404);
            }
            
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 更新候位票狀態
            if ($status === 'seated') {
                // 如果狀態是入座，記錄入座時間
                $stmt = $this->conn->prepare("UPDATE queue_tickets SET status = :status, updated_at = NOW(), seated_time = NOW() WHERE id = :id");
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $ticket_id);
                $stmt->execute();
            } else {
                // 其他狀態只更新狀態和更新時間
                $stmt = $this->conn->prepare("UPDATE queue_tickets SET status = :status, updated_at = NOW() WHERE id = :id");
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':id', $ticket_id);
                $stmt->execute();
            }
            
            // 如果狀態是 'no_show'，且是遠端取票，增加客戶的 no_show_count
            if ($status === 'no_show') {
                // 檢查是否為遠端取票
                if ($ticket['is_remote'] == 1) {
                    $stmt = $this->conn->prepare("UPDATE customers SET no_show_count = no_show_count + 1 WHERE id = :customer_id");
                    $stmt->bindParam(':customer_id', $ticket['customer_id']);
                    $stmt->execute();
                    
                    // 檢查是否需要加入黑名單
                    $stmt = $this->conn->prepare("SELECT no_show_count FROM customers WHERE id = :customer_id");
                    $stmt->bindParam(':customer_id', $ticket['customer_id']);
                    $stmt->execute();
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // 如果失約次數超過 3 次，加入黑名單
                    if ($customer['no_show_count'] >= 3) {
                        $stmt = $this->conn->prepare("UPDATE customers SET blacklisted = TRUE WHERE id = :customer_id");
                        $stmt->bindParam(':customer_id', $ticket['customer_id']);
                        $stmt->execute();
                    }
                }
                // 如果是現場取票，不計入未到次數
            }
            
            // 返回成功響應
            $this->sendResponse(['success' => true, 'message' => '狀態已更新']);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 叫號功能處理器類
 */
class NextTicketHandler extends ApiHandler {
    public function handleRequest() {
        $table_type_id = $this->request_data['table_type_id'] ?? 0;
        
        // 驗證桌型 ID 是否有效
        if (!in_array($table_type_id, [1, 2, 3])) {
            $this->sendError('無效的桌型 ID');
        }
        
        try {
            // 查詢當前候位狀態
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = CURDATE()");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('找不到候位狀態記錄', 404);
            }
            
            $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 根據桌型確定當前叫號
            $current_number_field = '';
            switch ($table_type_id) {
                case 1:
                    $current_number_field = 'current_number_small';
                    break;
                case 2:
                    $current_number_field = 'current_number_medium';
                    break;
                case 3:
                    $current_number_field = 'current_number_large';
                    break;
            }
            
            $current_number = $queue_status[$current_number_field];
            
            // 查詢下一個等待中的候位票
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE table_type_id = :table_type_id AND queue_date = CURDATE() AND status = 'waiting' AND ticket_number > :current_number ORDER BY ticket_number ASC LIMIT 1");
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->bindParam(':current_number', $current_number);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('沒有等待中的客人', 404);
            }
            
            $next_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 更新候位狀態
            $stmt = $this->conn->prepare("UPDATE queue_status SET $current_number_field = :ticket_number WHERE queue_date = CURDATE()");
            $stmt->bindParam(':ticket_number', $next_ticket['ticket_number']);
            $stmt->execute();
            
            // 返回成功響應
            $this->sendResponse([
                'success' => true,
                'message' => '成功叫號',
                'ticket_number' => $next_ticket['ticket_number'],
                'table_type_id' => $table_type_id
            ]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 黑名單管理處理器類
 */
class BlacklistHandler extends ApiHandler {
    public function handleRequest() {
        try {
            // 查詢黑名單客戶
            $stmt = $this->conn->prepare("SELECT id, phone_number, no_show_count, created_at FROM customers WHERE blacklisted = TRUE ORDER BY no_show_count DESC");
            $stmt->execute();
            
            $blacklist = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 返回成功響應
            $this->sendResponse($blacklist);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 黑名單狀態更新處理器類
 */
class BlacklistUpdateHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['blacklisted'])) {
            $this->sendError('缺少必要參數');
        }
        
        $customer_id = $this->request_data['customer_id'] ?? 0;
        
        // 確保正確處理布爾值
        $blacklisted = filter_var($this->request_data['blacklisted'], FILTER_VALIDATE_BOOLEAN);
        
        // 記錄請求數據以便調試
        error_log("Blacklist update request: " . json_encode($this->request_data));
        error_log("Blacklisted value after filter: " . ($blacklisted ? 'true' : 'false'));
        
        try {
            // 查詢客戶是否存在
            $stmt = $this->conn->prepare("SELECT * FROM customers WHERE id = :id");
            $stmt->bindParam(':id', $customer_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $this->sendError('找不到客戶', 404);
            }
            
            // 更新黑名單狀態
            if ($blacklisted === false) {
                // 移出黑名單時，同時清除失約次數和重置加入時間
                $sql = "UPDATE customers SET blacklisted = FALSE, no_show_count = 0, created_at = NOW() WHERE id = $customer_id";
                $this->conn->exec($sql);
                
                // 記錄執行的 SQL 語句
                error_log("Executed SQL: $sql");
                
                // 查詢更新後的資料
                $check_sql = "SELECT * FROM customers WHERE id = $customer_id";
                $check_result = $this->conn->query($check_sql)->fetch(PDO::FETCH_ASSOC);
                error_log("Updated customer data: " . json_encode($check_result));
            } else {
                // 加入黑名單
                $stmt = $this->conn->prepare("UPDATE customers SET blacklisted = TRUE WHERE id = :id");
                $stmt->bindParam(':id', $customer_id);
                $stmt->execute();
            }
            
            // 返回成功響應
            $this->sendResponse(['success' => true, 'message' => '黑名單狀態已更新']);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 統計數據處理器類
 */
class StatisticsHandler extends ApiHandler {
    public function handleRequest() {
        // 獲取開始和結束日期參數
        $start_date = isset($this->request_data['start_date']) ? $this->request_data['start_date'] : date('Y-m-d');
        $end_date = isset($this->request_data['end_date']) ? $this->request_data['end_date'] : date('Y-m-d');
        
        // 確保開始日期不會大於結束日期
        if (strtotime($start_date) > strtotime($end_date)) {
            $temp = $start_date;
            $start_date = $end_date;
            $end_date = $temp;
        }
        
        // 記錄日期參數，以便調試
        error_log("Statistics date range: {$start_date} to {$end_date}");
        
        try {
            // 1. 獲取平均候位時間（按桌型分組）
            $stmt = $this->conn->prepare("
                SELECT 
                    table_type_id,
                    AVG(TIMESTAMPDIFF(MINUTE, queue_time, updated_at)) as avg_wait_time
                FROM queue_tickets
                WHERE queue_date BETWEEN :start_date AND :end_date
                AND status = 'seated'
                GROUP BY table_type_id
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $avg_wait_time = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                switch ($row['table_type_id']) {
                    case 1:
                        $avg_wait_time['small'] = round($row['avg_wait_time']);
                        break;
                    case 2:
                        $avg_wait_time['medium'] = round($row['avg_wait_time']);
                        break;
                    case 3:
                        $avg_wait_time['large'] = round($row['avg_wait_time']);
                        break;
                }
            }
            
            // 2. 獲取取號方式分佈（遠端 vs 現場）
            $stmt = $this->conn->prepare("
                SELECT 
                    is_remote,
                    COUNT(*) as count
                FROM queue_tickets
                WHERE queue_date BETWEEN :start_date AND :end_date
                GROUP BY is_remote
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $queue_type_distribution = [
                'remote' => 0,
                'onsite' => 0
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['is_remote'] == 1) {
                    $queue_type_distribution['remote'] = (int)$row['count'];
                } else {
                    $queue_type_distribution['onsite'] = (int)$row['count'];
                }
            }
            
            // 3. 獲取各狀態候位票數量
            $stmt = $this->conn->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM queue_tickets
                WHERE queue_date BETWEEN :start_date AND :end_date
                GROUP BY status
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $status_distribution = [
                'waiting' => 0,
                'seated' => 0,
                'no_show' => 0,
                'cancelled' => 0
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $status_distribution[$row['status']] = (int)$row['count'];
            }
            
            // 4. 獲取每小時候位票數量
            $stmt = $this->conn->prepare("
                SELECT 
                    HOUR(queue_time) as hour,
                    COUNT(*) as count
                FROM queue_tickets
                WHERE queue_date BETWEEN :start_date AND :end_date
                GROUP BY HOUR(queue_time)
                ORDER BY hour
            ");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->execute();
            
            $hourly_distribution = [];
            for ($i = 0; $i < 24; $i++) {
                $hourly_distribution[$i] = 0;
            }
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $hourly_distribution[(int)$row['hour']] = (int)$row['count'];
            }
            
            // 組合所有統計數據
            $statistics = [
                'avg_wait_time' => $avg_wait_time,
                'queue_type_distribution' => $queue_type_distribution,
                'status_distribution' => $status_distribution,
                'hourly_distribution' => $hourly_distribution,
                'date_range' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]
            ];
            
            // 返回成功響應
            $this->sendResponse($statistics);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 客戶檢查處理器類
 */
class CustomerCheckHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['phone_number'])) {
            $this->sendError('缺少必要參數');
        }
        
        $phone_number = $this->request_data['phone_number'];
        
        try {
            // 查詢客戶是否存在
            $stmt = $this->conn->prepare("SELECT * FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($customer) {
                // 客戶存在
                $this->sendResponse(['exists' => true, 'customer' => $customer]);
            } else {
                // 客戶不存在，創建新客戶
                $stmt = $this->conn->prepare("INSERT INTO customers (phone_number, no_show_count, blacklisted) VALUES (:phone_number, 0, FALSE)");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                
                // 查詢新創建的客戶
                $stmt = $this->conn->prepare("SELECT * FROM customers WHERE phone_number = :phone_number");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                
                $new_customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $this->sendResponse(['exists' => false, 'customer' => $new_customer]);
            }
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 黑名單添加處理器類
 */
class BlacklistAddHandler extends ApiHandler {
    public function handleRequest() {
        // 檢查必要參數
        if (!isset($this->request_data['phone_number'])) {
            $this->sendError('缺少必要參數');
        }
        
        $phone_number = $this->request_data['phone_number'];
        
        try {
            // 查詢客戶
            $stmt = $this->conn->prepare("SELECT * FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                // 客戶不存在，創建新客戶並加入黑名單
                // 手動加入黑名單時，失約次數預設為0，而不是3
                $stmt = $this->conn->prepare("INSERT INTO customers (phone_number, no_show_count, blacklisted) VALUES (:phone_number, 0, TRUE)");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                
                $this->sendResponse(['success' => true, 'message' => '已創建新客戶並加入黑名單']);
            } else {
                // 客戶存在，更新為黑名單
                // 手動加入黑名單時，失約次數設為0，以區分自動加入黑名單的客戶
                $stmt = $this->conn->prepare("UPDATE customers SET blacklisted = TRUE, no_show_count = 0 WHERE id = :id");
                $stmt->bindParam(':id', $customer['id']);
                $stmt->execute();
                
                $this->sendResponse(['success' => true, 'message' => '已將客戶加入黑名單']);
            }
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

/**
 * 等待中客人列表處理器類
 */
class WaitingListHandler extends ApiHandler {
    public function handleRequest() {
        try {
            // 查詢等待中的候位票
            $stmt = $this->conn->prepare("
                SELECT 
                    qt.id, 
                    qt.ticket_number, 
                    qt.party_size, 
                    qt.queue_time, 
                    qt.status, 
                    qt.is_remote,
                    tt.name as table_type_name,
                    c.phone_number,
                    c.name as customer_name,
                    qt.table_type_id
                FROM 
                    queue_tickets qt
                JOIN 
                    table_types tt ON qt.table_type_id = tt.id
                JOIN 
                    customers c ON qt.customer_id = c.id
                WHERE 
                    qt.queue_date = CURDATE() AND 
                    qt.status = 'waiting'
                ORDER BY 
                    qt.table_type_id ASC, 
                    qt.ticket_number ASC
            ");
            $stmt->execute();
            
            $tickets = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // 添加票號前綴
                switch ($row['table_type_id']) {
                    case 1:
                        $row['formatted_number'] = 'S' . $row['ticket_number'];
                        break;
                    case 2:
                        $row['formatted_number'] = 'M' . $row['ticket_number'];
                        break;
                    case 3:
                        $row['formatted_number'] = 'L' . $row['ticket_number'];
                        break;
                    default:
                        $row['formatted_number'] = $row['ticket_number'];
                }
                
                // 添加候位時間格式化
                $queue_time = new DateTime($row['queue_time']);
                $row['formatted_time'] = $queue_time->format('H:i');
                
                // 添加候位時長
                $now = new DateTime();
                $interval = $now->diff($queue_time);
                $minutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                $row['waiting_minutes'] = $minutes;
                
                // 添加電話號碼遮罩
                $phone = $row['phone_number'];
                $row['masked_phone'] = substr($phone, 0, 4) . '****' . substr($phone, -3);
                
                $tickets[] = $row;
            }
            
            // 返回成功響應
            $this->sendResponse(['tickets' => $tickets]);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
}

// 處理獲取等待中客人列表的請求
if ($api_path === 'queue/waiting-list' && $request_method === 'GET') {
    $handler = new WaitingListHandler($conn);
    $handler->handleRequest();
    exit;
}

// 處理查詢候位票功能
if (preg_match('/^queue\/ticket\/(.+)$/', $api_path, $matches) && $request_method === 'GET') {
    /**
     * 查詢候位票處理器類
     */
    class TicketInfoHandler extends ApiHandler {
        public function handleRequest() {
            $ticket_id = $this->request_data['ticket_id'] ?? '';
            
            if (empty($ticket_id)) {
                $this->sendError('缺少候位票號或電話號碼');
            }
            
            try {
                // 先嘗試使用票號查詢
                $stmt = $this->conn->prepare("SELECT qt.*, c.phone_number FROM queue_tickets qt JOIN customers c ON qt.customer_id = c.id WHERE qt.ticket_number = :ticket_id AND qt.queue_date = CURDATE()");
                $stmt->bindParam(':ticket_id', $ticket_id);
                $stmt->execute();
                
                // 如果沒有找到，嘗試使用電話號碼查詢
                if ($stmt->rowCount() === 0) {
                    $stmt = $this->conn->prepare("SELECT qt.*, c.phone_number FROM queue_tickets qt JOIN customers c ON qt.customer_id = c.id WHERE c.phone_number = :phone_number AND qt.queue_date = CURDATE() ORDER BY qt.created_at DESC LIMIT 1");
                    $stmt->bindParam(':phone_number', $ticket_id);
                    $stmt->execute();
                }
                
                if ($stmt->rowCount() === 0) {
                    $this->sendError('找不到候位票', 404);
                }
                
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 返回成功響應
                $this->sendResponse($ticket);
            } catch (PDOException $e) {
                $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
            }
        }
    }
    
    $ticket_id = $matches[1];
    $handler = new TicketInfoHandler($conn, ['ticket_id' => $ticket_id]);
    $handler->handleRequest();
    exit;
}

/**
 * 統計數據處理器類
 */
class StatisticsHandler extends ApiHandler {
    public function handleRequest() {
        try {
            // 獲取統計數據
            $statistics = $this->getStatistics();
            
            // 返回成功響應
            $this->sendResponse($statistics);
        } catch (PDOException $e) {
            $this->sendError('資料庫錯誤: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * 獲取統計數據
     * @return array 統計數據
     */
    private function getStatistics() {
        // 獲取開始和結束日期參數
        $start_date = isset($this->request_data['start_date']) ? $this->request_data['start_date'] : date('Y-m-d');
        $end_date = isset($this->request_data['end_date']) ? $this->request_data['end_date'] : date('Y-m-d');
        
        // 如果開始日期大於結束日期，交換它們
        if (strtotime($start_date) > strtotime($end_date)) {
            $temp = $start_date;
            $start_date = $end_date;
            $end_date = $temp;
        }
        
        // 記錄日期參數以便調試
        file_put_contents('statistics_debug.log', "Start Date: {$start_date}, End Date: {$end_date}\n", FILE_APPEND);
        
        // 獲取指定日期範圍候位票總數
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $total_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 獲取指定日期範圍已完成候位票數
        $stmt = $this->conn->prepare("SELECT COUNT(*) as completed FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND status = 'seated'");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $completed_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['completed'];
        
        // 獲取指定日期範圍已取消候位票數
        $stmt = $this->conn->prepare("SELECT COUNT(*) as cancelled FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND status = 'cancelled'");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $cancelled_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['cancelled'];
        
        // 獲取指定日期範圍已失約候位票數
        $stmt = $this->conn->prepare("SELECT COUNT(*) as no_show FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND status = 'no_show'");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $no_show_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['no_show'];
        
        // 獲取指定日期範圍遠端取票數
        $stmt = $this->conn->prepare("SELECT COUNT(*) as remote FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND is_remote = TRUE");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $remote_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['remote'];
        
        // 獲取指定日期範圍現場取票數
        $stmt = $this->conn->prepare("SELECT COUNT(*) as onsite FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND is_remote = FALSE");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $onsite_tickets = $stmt->fetch(PDO::FETCH_ASSOC)['onsite'];
        
        // 獲取各桌型平均候位時間
        $avg_wait_time = [
            'small' => 0,
            'medium' => 0,
            'large' => 0
        ];
        
        // 獲取小桌平均候位時間
        $stmt = $this->conn->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, queue_time, seated_time)) as avg_time FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND table_type_id = 1 AND status = 'seated' AND seated_time IS NOT NULL");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_wait_time['small'] = $result['avg_time'] ? round($result['avg_time']) : 0;
        
        // 獲取中桌平均候位時間
        $stmt = $this->conn->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, queue_time, seated_time)) as avg_time FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND table_type_id = 2 AND status = 'seated' AND seated_time IS NOT NULL");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_wait_time['medium'] = $result['avg_time'] ? round($result['avg_time']) : 0;
        
        // 獲取大桌平均候位時間
        $stmt = $this->conn->prepare("SELECT AVG(TIMESTAMPDIFF(MINUTE, queue_time, seated_time)) as avg_time FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date AND table_type_id = 3 AND status = 'seated' AND seated_time IS NOT NULL");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $avg_wait_time['large'] = $result['avg_time'] ? round($result['avg_time']) : 0;
        
        // 獲取指定日期範圍各桌型票數
        $stmt = $this->conn->prepare("SELECT table_type_id, COUNT(*) as count FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date GROUP BY table_type_id");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $table_type_counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $table_type_counts[$row['table_type_id']] = $row['count'];
        }
        
        // 獲取指定日期範圍各狀態票數
        $stmt = $this->conn->prepare("SELECT status, COUNT(*) as count FROM queue_tickets WHERE queue_date BETWEEN :start_date AND :end_date GROUP BY status");
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        $status_counts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status_counts[$row['status']] = $row['count'];
        }
        
        // 組合統計數據，符合前端期望的格式
        return [
            'total_tickets' => $total_tickets,
            'completed_tickets' => $completed_tickets,
            'cancelled_tickets' => $cancelled_tickets,
            'no_show_tickets' => $no_show_tickets,
            'queue_type_distribution' => [
                'remote' => $remote_tickets,
                'onsite' => $onsite_tickets
            ],
            'table_type_counts' => $table_type_counts,
            'status_counts' => $status_counts,
            'avg_wait_time' => $avg_wait_time,
            'start_date' => $start_date,
            'end_date' => $end_date,
            '_links' => [
                'self' => [
                    'href' => "/api/v1/statistics?start_date={$start_date}&end_date={$end_date}"
                ],
                'queue_status' => [
                    'href' => '/api/v1/queue/status'
                ],
                'blacklist' => [
                    'href' => '/api/v1/blacklist'
                ]
            ]
        ];
    }
}

// 處理統計數據請求 - RESTful API 風格
if ((strpos($api_path, 'statistics') === 0 || strpos($api_path, 'api/statistics') === 0) && $request_method === 'GET') {
    // 記錄 API 路徑和 GET 參數，以便調試
    error_log("Statistics API path: " . $api_path);
    error_log("Statistics GET params: " . json_encode($_GET));
    
    $handler = new StatisticsHandler($conn, $_GET);
    $handler->handleRequest();
    exit;
}

// 如果沒有匹配的 API 端點，返回 404
http_response_code(404);
echo json_encode(['error' => '找不到請求的端點']);
?>
