<?php
class QueueController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // 獲取當前候位狀態
    public function getStatus() {
        try {
            // 獲取當天的候位狀態
            $today = date('Y-m-d');
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
            $stmt->bindParam(':queue_date', $today);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $status = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 計算每種桌型的等待人數
                $waiting_count_small = $this->getWaitingCount(1, $today);
                $waiting_count_medium = $this->getWaitingCount(2, $today);
                $waiting_count_large = $this->getWaitingCount(3, $today);
                
                $response = [
                    'queue_date' => $status['queue_date'],
                    'current_number_small' => $status['current_number_small'],
                    'current_number_medium' => $status['current_number_medium'],
                    'current_number_large' => $status['current_number_large'],
                    'last_issued_small' => $status['last_issued_small'],
                    'last_issued_medium' => $status['last_issued_medium'],
                    'last_issued_large' => $status['last_issued_large'],
                    'waiting_count_small' => $waiting_count_small,
                    'waiting_count_medium' => $waiting_count_medium,
                    'waiting_count_large' => $waiting_count_large,
                    'updated_at' => $status['updated_at']
                ];
                
                http_response_code(200);
                echo json_encode($response);
            } else {
                // 如果沒有當天的記錄，則創建一個
                $stmt = $this->conn->prepare("INSERT INTO queue_status (queue_date) VALUES (:queue_date)");
                $stmt->bindParam(':queue_date', $today);
                $stmt->execute();
                
                $response = [
                    'queue_date' => $today,
                    'current_number_small' => 0,
                    'current_number_medium' => 0,
                    'current_number_large' => 0,
                    'last_issued_small' => 0,
                    'last_issued_medium' => 0,
                    'last_issued_large' => 0,
                    'waiting_count_small' => 0,
                    'waiting_count_medium' => 0,
                    'waiting_count_large' => 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                http_response_code(200);
                echo json_encode($response);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 獲取特定候位號碼的詳細信息
    public function getTicket($id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode($ticket);
            } else {
                http_response_code(404);
                echo json_encode(['error' => '找不到指定的候位號碼']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 創建遠端候位號碼
    public function createRemoteTicket($data) {
        // 檢查必要參數
        if (!isset($data['phone_number']) || !isset($data['party_size']) || !isset($data['verification_code'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數']);
            return;
        }

        $phone_number = $data['phone_number'];
        $party_size = $data['party_size'];
        $verification_code = $data['verification_code'];

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
            
            // 驗證驗證碼
            $stmt = $this->conn->prepare("SELECT * FROM verification_codes WHERE customer_id = :customer_id AND code = :code AND used = 0 AND expires_at > NOW()");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $verification_code);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(['error' => '驗證碼無效或已過期']);
                return;
            }
            
            // 標記驗證碼為已使用
            $stmt = $this->conn->prepare("UPDATE verification_codes SET used = 1 WHERE customer_id = :customer_id AND code = :code");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':code', $verification_code);
            $stmt->execute();
            
            // 確定桌位類型
            $table_type_id = $this->getTableTypeId($party_size);
            
            // 獲取當前候位狀態
            $today = date('Y-m-d');
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
            $stmt->bindParam(':queue_date', $today);
            $stmt->execute();
            
            $queue_status = null;
            
            if ($stmt->rowCount() > 0) {
                $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // 創建新的候位狀態記錄
                $stmt = $this->conn->prepare("INSERT INTO queue_status (queue_date) VALUES (:queue_date)");
                $stmt->bindParam(':queue_date', $today);
                $stmt->execute();
                
                $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
                $stmt->bindParam(':queue_date', $today);
                $stmt->execute();
                $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // 獲取新的票號
            $ticket_number = $this->getNextTicketNumber($table_type_id, $queue_status);
            
            // 獲取當前等待人數
            $waiting_count = $this->getWaitingCount($table_type_id, $today);
            
            // 創建候位票
            $stmt = $this->conn->prepare("INSERT INTO queue_tickets (ticket_number, customer_id, table_type_id, party_size, queue_date, queue_time, status, is_remote, waiting_count_at_creation, verification_status) VALUES (:ticket_number, :customer_id, :table_type_id, :party_size, :queue_date, NOW(), 'waiting', 1, :waiting_count, 1)");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->bindParam(':party_size', $party_size);
            $stmt->bindParam(':queue_date', $today);
            $stmt->bindParam(':waiting_count', $waiting_count);
            $stmt->execute();
            
            $ticket_id = $this->conn->lastInsertId();
            
            // 發送SMS通知
            $this->sendSMS($customer_id, $ticket_id, $ticket_number, $table_type_id, $waiting_count);
            
            // 返回創建的候位票
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = :id");
            $stmt->bindParam(':id', $ticket_id);
            $stmt->execute();
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(201);
            echo json_encode($ticket);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 創建現場候位號碼
    public function createOnsiteTicket($data) {
        // 檢查授權
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$this->verifyToken($auth_header)) {
            http_response_code(401);
            echo json_encode(['error' => '未授權']);
            return;
        }
        
        // 檢查必要參數
        if (!isset($data['phone_number']) || !isset($data['party_size'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數']);
            return;
        }

        $phone_number = $data['phone_number'];
        $party_size = $data['party_size'];

        try {
            // 檢查客戶是否存在
            $stmt = $this->conn->prepare("SELECT id FROM customers WHERE phone_number = :phone_number");
            $stmt->bindParam(':phone_number', $phone_number);
            $stmt->execute();
            
            $customer_id = null;
            
            if ($stmt->rowCount() > 0) {
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                $customer_id = $customer['id'];
            } else {
                // 創建新客戶
                $stmt = $this->conn->prepare("INSERT INTO customers (phone_number) VALUES (:phone_number)");
                $stmt->bindParam(':phone_number', $phone_number);
                $stmt->execute();
                $customer_id = $this->conn->lastInsertId();
            }
            
            // 確定桌位類型
            $table_type_id = $this->getTableTypeId($party_size);
            
            // 獲取當前候位狀態
            $today = date('Y-m-d');
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
            $stmt->bindParam(':queue_date', $today);
            $stmt->execute();
            
            $queue_status = null;
            
            if ($stmt->rowCount() > 0) {
                $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // 創建新的候位狀態記錄
                $stmt = $this->conn->prepare("INSERT INTO queue_status (queue_date) VALUES (:queue_date)");
                $stmt->bindParam(':queue_date', $today);
                $stmt->execute();
                
                $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
                $stmt->bindParam(':queue_date', $today);
                $stmt->execute();
                $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // 獲取新的票號
            $ticket_number = $this->getNextTicketNumber($table_type_id, $queue_status);
            
            // 獲取當前等待人數
            $waiting_count = $this->getWaitingCount($table_type_id, $today);
            
            // 創建候位票
            $stmt = $this->conn->prepare("INSERT INTO queue_tickets (ticket_number, customer_id, table_type_id, party_size, queue_date, queue_time, status, is_remote, waiting_count_at_creation, verification_status) VALUES (:ticket_number, :customer_id, :table_type_id, :party_size, :queue_date, NOW(), 'waiting', 0, :waiting_count, 1)");
            $stmt->bindParam(':ticket_number', $ticket_number);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->bindParam(':party_size', $party_size);
            $stmt->bindParam(':queue_date', $today);
            $stmt->bindParam(':waiting_count', $waiting_count);
            $stmt->execute();
            
            $ticket_id = $this->conn->lastInsertId();
            
            // 發送SMS通知
            $this->sendSMS($customer_id, $ticket_id, $ticket_number, $table_type_id, $waiting_count);
            
            // 返回創建的候位票
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = :id");
            $stmt->bindParam(':id', $ticket_id);
            $stmt->execute();
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(201);
            echo json_encode($ticket);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 叫號下一位客人
    public function callNextCustomer($table_type_id) {
        // 檢查授權
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$this->verifyToken($auth_header)) {
            http_response_code(401);
            echo json_encode(['error' => '未授權']);
            return;
        }

        try {
            $today = date('Y-m-d');
            
            // 獲取當前候位狀態
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
            $stmt->bindParam(':queue_date', $today);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => '找不到當天的候位狀態']);
                return;
            }
            
            $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 獲取當前叫號
            $current_number = 0;
            switch ($table_type_id) {
                case 1:
                    $current_number = $queue_status['current_number_small'];
                    break;
                case 2:
                    $current_number = $queue_status['current_number_medium'];
                    break;
                case 3:
                    $current_number = $queue_status['current_number_large'];
                    break;
            }
            
            // 查找下一個等待的客人
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE table_type_id = :table_type_id AND queue_date = :queue_date AND status = 'waiting' AND ticket_number > :current_number ORDER BY ticket_number ASC LIMIT 1");
            $stmt->bindParam(':table_type_id', $table_type_id);
            $stmt->bindParam(':queue_date', $today);
            $stmt->bindParam(':current_number', $current_number);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => '沒有更多等待的客人']);
                return;
            }
            
            $next_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 更新候位狀態
            $field = '';
            switch ($table_type_id) {
                case 1:
                    $field = 'current_number_small';
                    break;
                case 2:
                    $field = 'current_number_medium';
                    break;
                case 3:
                    $field = 'current_number_large';
                    break;
            }
            
            $stmt = $this->conn->prepare("UPDATE queue_status SET $field = :ticket_number WHERE queue_date = :queue_date");
            $stmt->bindParam(':ticket_number', $next_ticket['ticket_number']);
            $stmt->bindParam(':queue_date', $today);
            $stmt->execute();
            
            http_response_code(200);
            echo json_encode($next_ticket);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 更新候位票狀態
    public function updateTicketStatus($id, $data) {
        // 檢查授權
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$this->verifyToken($auth_header)) {
            http_response_code(401);
            echo json_encode(['error' => '未授權']);
            return;
        }
        
        // 檢查必要參數
        if (!isset($data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => '缺少必要參數']);
            return;
        }
        
        $status = $data['status'];
        
        // 檢查狀態是否有效
        if (!in_array($status, ['seated', 'no_show', 'cancelled'])) {
            http_response_code(400);
            echo json_encode(['error' => '無效的狀態值']);
            return;
        }

        try {
            // 檢查候位票是否存在
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => '找不到指定的候位票']);
                return;
            }
            
            $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 更新狀態
            $stmt = $this->conn->prepare("UPDATE queue_tickets SET status = :status, updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // 如果是入座，記錄入座時間
            if ($status === 'seated') {
                $stmt = $this->conn->prepare("UPDATE queue_tickets SET seated_time = NOW() WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
            }
            
            // 如果是失約，增加客戶的失約次數
            if ($status === 'no_show' && $ticket['is_remote']) {
                $stmt = $this->conn->prepare("UPDATE customers SET no_show_count = no_show_count + 1 WHERE id = :customer_id");
                $stmt->bindParam(':customer_id', $ticket['customer_id']);
                $stmt->execute();
                
                // 檢查是否需要加入黑名單
                $stmt = $this->conn->prepare("SELECT no_show_count FROM customers WHERE id = :customer_id");
                $stmt->bindParam(':customer_id', $ticket['customer_id']);
                $stmt->execute();
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($customer['no_show_count'] >= 3) {
                    $stmt = $this->conn->prepare("UPDATE customers SET blacklisted = 1 WHERE id = :customer_id");
                    $stmt->bindParam(':customer_id', $ticket['customer_id']);
                    $stmt->execute();
                }
            }
            
            // 返回更新後的候位票
            $stmt = $this->conn->prepare("SELECT * FROM queue_tickets WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $updated_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($updated_ticket);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => '資料庫錯誤: ' . $e->getMessage()]);
        }
    }

    // 輔助方法：獲取桌位類型ID
    private function getTableTypeId($party_size) {
        if ($party_size <= 2) {
            return 1; // 小桌 (1-2人)
        } elseif ($party_size <= 4) {
            return 2; // 中桌 (3-4人)
        } else {
            return 3; // 大桌 (5人以上)
        }
    }

    // 輔助方法：獲取下一個票號
    private function getNextTicketNumber($table_type_id, $queue_status) {
        $last_issued = 0;
        $field = '';
        
        switch ($table_type_id) {
            case 1:
                $last_issued = $queue_status['last_issued_small'];
                $field = 'last_issued_small';
                break;
            case 2:
                $last_issued = $queue_status['last_issued_medium'];
                $field = 'last_issued_medium';
                break;
            case 3:
                $last_issued = $queue_status['last_issued_large'];
                $field = 'last_issued_large';
                break;
        }
        
        $next_number = $last_issued + 1;
        
        // 更新最後發出的票號
        $stmt = $this->conn->prepare("UPDATE queue_status SET $field = :next_number WHERE id = :id");
        $stmt->bindParam(':next_number', $next_number);
        $stmt->bindParam(':id', $queue_status['id']);
        $stmt->execute();
        
        return $next_number;
    }

    // 輔助方法：獲取等待人數
    private function getWaitingCount($table_type_id, $date) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM queue_tickets WHERE table_type_id = :table_type_id AND queue_date = :queue_date AND status = 'waiting'");
        $stmt->bindParam(':table_type_id', $table_type_id);
        $stmt->bindParam(':queue_date', $date);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // 輔助方法：發送SMS
    private function sendSMS($customer_id, $ticket_id, $ticket_number, $table_type_id, $waiting_count) {
        try {
            // 獲取桌位類型名稱
            $stmt = $this->conn->prepare("SELECT name FROM table_types WHERE id = :id");
            $stmt->bindParam(':id', $table_type_id);
            $stmt->execute();
            $table_type = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 獲取當前叫號
            $today = date('Y-m-d');
            $stmt = $this->conn->prepare("SELECT * FROM queue_status WHERE queue_date = :queue_date");
            $stmt->bindParam(':queue_date', $today);
            $stmt->execute();
            $queue_status = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $current_number = 0;
            switch ($table_type_id) {
                case 1:
                    $current_number = $queue_status['current_number_small'];
                    break;
                case 2:
                    $current_number = $queue_status['current_number_medium'];
                    break;
                case 3:
                    $current_number = $queue_status['current_number_large'];
                    break;
            }
            
            // 構建消息內容
            $message = "您的候位號碼是 {$ticket_number} ({$table_type['name']}桌)。目前叫號: {$current_number}";
            if ($waiting_count > 0) {
                $message .= "。前面還有 {$waiting_count} 組客人等待。";
            }
            $message .= "\n查看最新候位狀態: http://localhost/restaurant-queue/status.php?ticket_id={$ticket_id}";
            
            // 記錄SMS發送
            $stmt = $this->conn->prepare("INSERT INTO sms_logs (customer_id, queue_ticket_id, message_content, sent_time, status) VALUES (:customer_id, :queue_ticket_id, :message_content, NOW(), 'sent')");
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':queue_ticket_id', $ticket_id);
            $stmt->bindParam(':message_content', $message);
            $stmt->execute();
            
            // 在實際環境中，這裡會調用SMS發送API
            // 這裡僅模擬發送
            return true;
        } catch (PDOException $e) {
            // 記錄錯誤但不中斷流程
            error_log('SMS發送錯誤: ' . $e->getMessage());
            return false;
        }
    }

    // 輔助方法：驗證令牌
    private function verifyToken($auth_header) {
        // 簡化的令牌驗證，實際環境應使用更安全的方法
        if (empty($auth_header) || !preg_match('/^Bearer\s+(.*)$/', $auth_header, $matches)) {
            return false;
        }
        
        $token = $matches[1];
        
        try {
            $payload = json_decode(base64_decode($token), true);
            
            // 檢查令牌是否過期
            if (!isset($payload['exp']) || $payload['exp'] < time()) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
