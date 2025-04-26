<?php
class VerificationController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // 請求驗證碼
    public function requestVerification($data) {
        // 檢查必要參數
        if (!isset($data['phone_number'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少電話號碼']);
            return;
        }

        $phone_number = $data['phone_number'];

        try {
            // 檢查電話號碼是否在黑名單中
            $stmt = $this->conn->prepare("SELECT id, blacklisted FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            $customer_id = null;
            
            if ($stmt->rowCount() > 0) {
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                $customer_id = $customer['id'];
                
                if ($customer['blacklisted']) {
                    http_response_code(400);
                    echo json_encode(['error' => '此電話號碼已被列入黑名單，請到現場取號']);
                    return;
                }
            } else {
                // 創建新客戶
                $stmt = $this->conn->prepare("INSERT INTO customers (phone_number) VALUES (:phone_number)");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                $customer_id = $this->conn->lastInsertId();
            }
            
            // 生成隨機6位數驗證碼
            $verification_code = sprintf("%06d", mt_rand(0, 999999));
            
            // 設置過期時間為10分鐘後
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // 保存驗證碼
            $stmt = $this->conn->prepare("INSERT INTO verification_codes (customer_id, code, expires_at) VALUES (:customer_id, :code, :expires_at)");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $verification_code);
            $stmt->bindParam(':expires_at', $expires_at);
            $stmt->execute();
            
            // 在實際環境中，這裡會發送SMS
            // 這裡僅模擬發送，並返回驗證碼（僅用於測試）
            http_response_code(200);
            echo json_encode([
                'message' => '驗證碼已發送',
                'test_code' => $verification_code // 僅用於測試，實際環境中不應返回
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 驗證驗證碼
    public function verifyCode($data) {
        // 檢查必要參數
        if (!isset($data['phone_number']) || !isset($data['code'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數']);
            return;
        }

        $phone_number = $data['phone_number'];
        $code = $data['code'];

        try {
            // 獲取客戶ID
            $stmt = $this->conn->prepare("SELECT id FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(['error' => '找不到該電話號碼的客戶']);
                return;
            }
            
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            $customer_id = $customer['id'];
            
            // 檢查驗證碼是否有效
            $stmt = $this->conn->prepare("SELECT * FROM verification_codes WHERE customer_id = :customer_id AND code = :code AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $code);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(['error' => '驗證碼無效或已過期']);
                return;
            }
            
            // 驗證成功
            http_response_code(200);
            echo json_encode(['verified' => true]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }
}
