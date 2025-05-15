<?php
require_once __DIR__ . '/../config/database.php';

class RedemptionLog {
    // Database connection and table name
    private $conn;
    private $table_name = "redemption_logs";
    
    // Object properties
    public $id;
    public $coupon_id;
    public $redeemed_by;
    public $service_id;
    public $service_name;
    public $amount;
    public $remaining_balance;
    public $recipient_name;
    public $recipient_civil_id;
    public $recipient_mobile;
    public $recipient_file_number;
    public $redemption_date;
    public $redemption_time;
    public $service_description;
    public $created_at;
    
    // Constructor with database connection
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create redemption log
    public function create() {
        // Query to insert record
        $query = "INSERT INTO " . $this->table_name . "
                  SET coupon_id=:coupon_id, redeemed_by=:redeemed_by, 
                      service_id=:service_id, service_name=:service_name,
                      amount=:amount, remaining_balance=:remaining_balance,
                      recipient_name=:recipient_name, recipient_civil_id=:recipient_civil_id,
                      recipient_mobile=:recipient_mobile, recipient_file_number=:recipient_file_number,
                      redemption_date=:redemption_date, redemption_time=:redemption_time,
                      service_description=:service_description";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->coupon_id = htmlspecialchars(strip_tags($this->coupon_id));
        $this->redeemed_by = htmlspecialchars(strip_tags($this->redeemed_by));
        $this->service_id = htmlspecialchars(strip_tags($this->service_id));
        $this->service_name = htmlspecialchars(strip_tags($this->service_name));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->remaining_balance = htmlspecialchars(strip_tags($this->remaining_balance));
        $this->recipient_name = htmlspecialchars(strip_tags($this->recipient_name));
        $this->recipient_civil_id = htmlspecialchars(strip_tags($this->recipient_civil_id));
        $this->recipient_mobile = htmlspecialchars(strip_tags($this->recipient_mobile));
        $this->recipient_file_number = htmlspecialchars(strip_tags($this->recipient_file_number));
        $this->redemption_date = htmlspecialchars(strip_tags($this->redemption_date));
        $this->redemption_time = htmlspecialchars(strip_tags($this->redemption_time));
        $this->service_description = htmlspecialchars(strip_tags($this->service_description));
        
        // Bind values
        $stmt->bindParam(":coupon_id", $this->coupon_id);
        $stmt->bindParam(":redeemed_by", $this->redeemed_by);
        $stmt->bindParam(":service_id", $this->service_id);
        $stmt->bindParam(":service_name", $this->service_name);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":remaining_balance", $this->remaining_balance);
        $stmt->bindParam(":recipient_name", $this->recipient_name);
        $stmt->bindParam(":recipient_civil_id", $this->recipient_civil_id);
        $stmt->bindParam(":recipient_mobile", $this->recipient_mobile);
        $stmt->bindParam(":recipient_file_number", $this->recipient_file_number);
        $stmt->bindParam(":redemption_date", $this->redemption_date);
        $stmt->bindParam(":redemption_time", $this->redemption_time);
        $stmt->bindParam(":service_description", $this->service_description);
        
        // Execute query
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Get redemption logs by coupon
    public function getByCoupon() {
        // Query to select all records by coupon
        $query = "SELECT r.*, u.full_name as redeemer_name
                  FROM " . $this->table_name . " r
                  LEFT JOIN users u ON r.redeemed_by = u.id
                  WHERE r.coupon_id = ?
                  ORDER BY r.redemption_date DESC, r.redemption_time DESC";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind coupon id
        $stmt->bindParam(1, $this->coupon_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get total redemption amount by coupon
    public function getTotalByCoupon() {
        // Query to get total amount by coupon
        $query = "SELECT SUM(amount) as total
                  FROM " . $this->table_name . "
                  WHERE coupon_id = ?";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind coupon id
        $stmt->bindParam(1, $this->coupon_id);
        
        // Execute query
        $stmt->execute();
        
        // Get result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'] ? $row['total'] : 0;
    }
    
    // Get redemption logs with pagination
    public function readAll($page = 1, $limit = 10, $search = '') {
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Query to select all records with pagination
        $query = "SELECT r.*, c.code as coupon_code, ct.name as coupon_type, ct.value as coupon_value,
                         u.full_name as redeemer_name, b.full_name as buyer_name
                  FROM " . $this->table_name . " r
                  LEFT JOIN coupons c ON r.coupon_id = c.id
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users u ON r.redeemed_by = u.id
                  LEFT JOIN users b ON c.buyer_id = b.id
                  WHERE c.code LIKE ? OR ct.name LIKE ? OR u.full_name LIKE ? OR 
                        b.full_name LIKE ? OR r.recipient_name LIKE ? OR 
                        r.service_name LIKE ? OR r.service_description LIKE ?
                  ORDER BY r.redemption_date DESC, r.redemption_time DESC
                  LIMIT ?, ?";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize search term
        $searchTerm = "%" . htmlspecialchars(strip_tags($search)) . "%";
        
        // Bind values
        $stmt->bindParam(1, $searchTerm);
        $stmt->bindParam(2, $searchTerm);
        $stmt->bindParam(3, $searchTerm);
        $stmt->bindParam(4, $searchTerm);
        $stmt->bindParam(5, $searchTerm);
        $stmt->bindParam(6, $searchTerm);
        $stmt->bindParam(7, $searchTerm);
        $stmt->bindParam(8, $offset, PDO::PARAM_INT);
        $stmt->bindParam(9, $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Count total redemption logs
    public function countAll($search = '') {
        // Query to count all records
        $query = "SELECT COUNT(*) as total
                  FROM " . $this->table_name . " r
                  LEFT JOIN coupons c ON r.coupon_id = c.id
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users u ON r.redeemed_by = u.id
                  LEFT JOIN users b ON c.buyer_id = b.id
                  WHERE c.code LIKE ? OR ct.name LIKE ? OR u.full_name LIKE ? OR 
                        b.full_name LIKE ? OR r.recipient_name LIKE ? OR 
                        r.service_name LIKE ? OR r.service_description LIKE ?";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize search term
        $searchTerm = "%" . htmlspecialchars(strip_tags($search)) . "%";
        
        // Bind values
        $stmt->bindParam(1, $searchTerm);
        $stmt->bindParam(2, $searchTerm);
        $stmt->bindParam(3, $searchTerm);
        $stmt->bindParam(4, $searchTerm);
        $stmt->bindParam(5, $searchTerm);
        $stmt->bindParam(6, $searchTerm);
        $stmt->bindParam(7, $searchTerm);
        
        // Execute query
        $stmt->execute();
        
        // Get result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }
    
    // Get report data for dashboard
    public function getReportData() {
        // Query to get report data with current coupon type values
        $query = "SELECT 
                    ct.name as coupon_type,
                    ct.value as coupon_value,
                    COUNT(DISTINCT c.id) as total_coupons,
                    SUM(c.initial_balance) as total_value,
                    SUM(c.initial_balance - c.current_balance) as total_redeemed,
                    SUM(c.current_balance) as total_remaining
                  FROM coupons c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  GROUP BY ct.name, ct.value
                  ORDER BY ct.name";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get dashboard statistics
    public function getDashboardStats() {
        // Query to get dashboard statistics
        $query = "SELECT 
                    COUNT(DISTINCT c.id) as total_coupons,
                    COUNT(DISTINCT CASE WHEN c.buyer_id IS NOT NULL OR c.recipient_id IS NOT NULL OR EXISTS (SELECT 1 FROM redemption_logs rl WHERE rl.coupon_id = c.id) THEN c.id END) as assigned_coupons,
                    COUNT(DISTINCT CASE WHEN c.recipient_id IS NOT NULL OR EXISTS (SELECT 1 FROM redemption_logs rl WHERE rl.coupon_id = c.id) THEN c.id END) as assigned_recipients,
                    COUNT(DISTINCT CASE WHEN c.status = 'fully_redeemed' THEN c.id END) as fully_redeemed,
                    COUNT(DISTINCT CASE WHEN c.status = 'available' THEN c.id END) as available_coupons,
                    COUNT(DISTINCT CASE WHEN c.status = 'assigned' THEN c.id END) as active_coupons,
                    COUNT(DISTINCT CASE WHEN c.buyer_id IS NOT NULL THEN c.buyer_id END) as total_buyers,
                    COUNT(DISTINCT CASE WHEN c.recipient_id IS NOT NULL THEN c.recipient_id END) as total_recipients,
                    (SELECT COUNT(DISTINCT id) FROM users WHERE role = 'recipient') as all_recipients,
                    SUM(c.initial_balance) as total_value,
                    SUM(c.initial_balance - c.current_balance) as total_redeemed,
                    SUM(c.current_balance) as total_remaining
                  FROM coupons c";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get monthly sales data
    public function getMonthlySalesData($year = null) {
        // If year not provided, use current year
        if($year === null) {
            $year = date('Y');
        }
        
        // Query to get monthly sales data
        $query = "SELECT 
                    MONTH(r.redemption_date) as month,
                    ct.name as coupon_type,
                    ct.value as coupon_value,
                    COUNT(r.id) as count,
                    SUM(r.amount) as total_value
                  FROM " . $this->table_name . " r
                  LEFT JOIN coupons c ON r.coupon_id = c.id
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  WHERE YEAR(r.redemption_date) = ?
                  GROUP BY MONTH(r.redemption_date), ct.name, ct.value
                  ORDER BY MONTH(r.redemption_date), ct.name";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind year
        $stmt->bindParam(1, $year);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Delete redemption logs by recipient and reverse amounts back to coupon balance
    public function deleteByRecipientAndReverseBalance($recipientId = null, $recipientName = null, $recipientCivilId = null) {
        // Check if there's already an active transaction
        try {
            $inTransaction = $this->conn->inTransaction();
        } catch (Exception $e) {
            $inTransaction = false;
        }
        
        // Only start a transaction if one isn't already active
        $manageTransaction = !$inTransaction;
        if ($manageTransaction) {
            $this->conn->beginTransaction();
        }
        
        try {
            // Build the WHERE clause based on provided parameters
            $whereClause = [];
            $params = [];
            
            // Note: redemption_logs table doesn't have recipient_id column
            // We only use recipient_name and recipient_civil_id
            
            if ($recipientName !== null && !empty($recipientName)) {
                $whereClause[] = "recipient_name = ?";
                $params[] = $recipientName;
            }
            
            if ($recipientCivilId !== null && !empty($recipientCivilId)) {
                $whereClause[] = "recipient_civil_id = ?";
                $params[] = $recipientCivilId;
            }
            
            // If no parameters provided, return false
            if (empty($whereClause)) {
                return false;
            }
            
            // Combine WHERE clauses with OR if multiple criteria
            $whereStr = implode(" OR ", $whereClause);
            
            // First, get all redemption logs for this recipient to calculate reversal amounts
            $query = "SELECT id, coupon_id, amount 
                      FROM " . $this->table_name . " 
                      WHERE " . $whereStr;
            
            $stmt = $this->conn->prepare($query);
            
            // Bind parameters
            for ($i = 0; $i < count($params); $i++) {
                $stmt->bindParam($i + 1, $params[$i]);
            }
            
            $stmt->execute();
            
            // Process each redemption log individually to avoid foreign key issues
            $updatedCoupons = [];
            $totalReversed = 0;
            $deletedCount = 0;
            $couponAmounts = [];
            
            // First collect all redemption data
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logId = $row['id'];
                $couponId = $row['coupon_id'];
                $amount = $row['amount'];
                
                // Track total amount per coupon
                if (!isset($couponAmounts[$couponId])) {
                    $couponAmounts[$couponId] = 0;
                }
                $couponAmounts[$couponId] += $amount;
                $totalReversed += $amount;
                
                // Delete each redemption log individually
                $deleteQuery = "DELETE FROM " . $this->table_name . " WHERE id = ?";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bindParam(1, $logId);
                $deleteStmt->execute();
                $deletedCount++;
            }
            
            // Now update coupon balances
            foreach ($couponAmounts as $couponId => $amountToReverse) {
                // Update coupon balance by adding back the redeemed amount
                $updateQuery = "UPDATE coupons 
                               SET current_balance = current_balance + :amount,
                                   status = CASE 
                                       WHEN status = 'fully_redeemed' THEN 'assigned' 
                                       ELSE status 
                                   END 
                               WHERE id = :coupon_id";
                
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':amount', $amountToReverse);
                $updateStmt->bindParam(':coupon_id', $couponId);
                $updateStmt->execute();
                
                $updatedCoupons[] = $couponId;
            }
            
            // Only commit if we started the transaction
            if ($manageTransaction) {
                $this->conn->commit();
            }
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'updated_coupons' => $updatedCoupons,
                'total_reversed' => $totalReversed
            ];
            
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if ($manageTransaction) {
                $this->conn->rollBack();
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    // Delete specific redemption logs by IDs and reverse amounts back to coupon balance
    public function deleteSpecificLogsAndReverseBalance($logIds) {
        if (empty($logIds) || !is_array($logIds)) {
            return [
                'success' => false,
                'error' => 'No valid log IDs provided'
            ];
        }
        
        // Check if there's already an active transaction
        try {
            $inTransaction = $this->conn->inTransaction();
        } catch (Exception $e) {
            $inTransaction = false;
        }
        
        // Only start a transaction if one isn't already active
        $manageTransaction = !$inTransaction;
        if ($manageTransaction) {
            $this->conn->beginTransaction();
        }
        
        try {
            $updatedCoupons = [];
            $totalReversed = 0;
            $deletedCount = 0;
            $couponAmounts = [];
            
            // Prepare placeholders for the IN clause
            $placeholders = implode(',', array_fill(0, count($logIds), '?'));
            
            // Get all redemption logs to be deleted
            $query = "SELECT id, coupon_id, amount, recipient_name 
                      FROM " . $this->table_name . " 
                      WHERE id IN (" . $placeholders . ")";
                      
            $stmt = $this->conn->prepare($query);
            
            // Bind log IDs to the query
            foreach ($logIds as $index => $logId) {
                $stmt->bindValue($index + 1, $logId);
            }
            
            $stmt->execute();
            
            $recipientName = null;
            
            // Process each redemption log
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $logId = $row['id'];
                $couponId = $row['coupon_id'];
                $amount = $row['amount'];
                
                // Store recipient name for reference
                if ($recipientName === null) {
                    $recipientName = $row['recipient_name'];
                }
                
                // Track total amount per coupon
                if (!isset($couponAmounts[$couponId])) {
                    $couponAmounts[$couponId] = 0;
                }
                $couponAmounts[$couponId] += $amount;
                $totalReversed += $amount;
                
                // Delete each redemption log individually
                $deleteQuery = "DELETE FROM " . $this->table_name . " WHERE id = ?";
                $deleteStmt = $this->conn->prepare($deleteQuery);
                $deleteStmt->bindParam(1, $logId);
                $deleteStmt->execute();
                $deletedCount++;
            }
            
            // Now update coupon balances
            foreach ($couponAmounts as $couponId => $amountToReverse) {
                // Update coupon balance by adding back the redeemed amount
                $updateQuery = "UPDATE coupons 
                               SET current_balance = current_balance + :amount,
                                   status = CASE 
                                       WHEN status = 'fully_redeemed' AND (current_balance + :amount) >= initial_balance THEN 'assigned' 
                                       WHEN status = 'fully_redeemed' AND (current_balance + :amount) < initial_balance THEN 'partially_redeemed'
                                       ELSE status 
                                   END 
                               WHERE id = :coupon_id";
                
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->bindParam(':amount', $amountToReverse);
                $updateStmt->bindParam(':coupon_id', $couponId);
                $updateStmt->execute();
                
                $updatedCoupons[] = $couponId;
            }
            
            // Only commit if we started the transaction
            if ($manageTransaction) {
                $this->conn->commit();
            }
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'updated_coupons' => $updatedCoupons,
                'total_reversed' => $totalReversed,
                'recipient_name' => $recipientName
            ];
            
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if ($manageTransaction) {
                $this->conn->rollBack();
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
