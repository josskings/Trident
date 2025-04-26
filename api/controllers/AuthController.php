<?php
class AuthController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function login($data) {
        // 檢查必要參數
        if (!isset($data['username']) || !isset($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數']);
            return;
        }

        $username = $data['username'];
        $password = $data['password'];

        try {
            // 查詢員工資料
            $stmt = $this->conn->prepare("SELECT id, name, username, password_hash, role FROM employees WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $employee = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 使用 password_verify 函數來驗證密碼
                if (password_verify($password, $employee['password_hash'])) {
                    // 創建JWT令牌（簡化版）
                    $payload = [
                        'id' => $employee['id'],
                        'name' => $employee['name'],
                        'role' => $employee['role'],
                        'exp' => time() + 3600 // 1小時過期
                    ];
                    
                    $token = base64_encode(json_encode($payload));
                    
                    // 返回登入成功響應
                    http_response_code(200);
                    echo json_encode([
                        'token' => $token,
                        'employee' => [
                            'id' => $employee['id'],
                            'name' => $employee['name'],
                            'username' => $employee['username'],
                            'role' => $employee['role']
                        ]
                    ]);
                } else {
                    // 密碼錯誤
                    http_response_code(401);
                    echo json_encode(['error' => '用戶名或密碼錯誤']);
                }
            } else {
                // 用戶不存在
                http_response_code(401);
                echo json_encode(['error' => '用戶名或密碼錯誤']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }
}
