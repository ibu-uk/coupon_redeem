<?php
require_once __DIR__ . '/../config/database.php';

class Coupon {
    // Database connection and table name
    private $conn;
    private $table_name = "coupons";
    
    // Object properties
    public $id;
    public $code;
    public $coupon_type_id;
    public $buyer_id;
    public $recipient_id;
    public $initial_balance;
    public $current_balance;
    public $issue_date;
    public $status;
    public $created_at;
    public $updated_at;
    public $buyer_name;
    public $buyer_email;
    public $buyer_civil_id;
    public $buyer_mobile;
    public $buyer_file_number;
    public $recipient_name;
    public $recipient_email;
    public $recipient_civil_id;
    public $recipient_mobile;
    public $recipient_file_number;
    public $coupon_type_name;
    public $coupon_type_value;
    
    // Constructor with database connection
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Assign coupon to buyer
    public function assignToBuyer() {
        // Check if coupon is already assigned
        if($this->status !== 'available') {
            return false;
        }
        
        // Set issue date to current date
        $this->issue_date = date('Y-m-d');
        
        // Set status to assigned
        $this->status = 'assigned';
        
        // Query to update record
        $query = "UPDATE " . $this->table_name . "
                  SET buyer_id=:buyer_id, issue_date=:issue_date, status=:status
                  WHERE id=:id";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->buyer_id = htmlspecialchars(strip_tags($this->buyer_id));
        $this->issue_date = htmlspecialchars(strip_tags($this->issue_date));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind values
        $stmt->bindParam(":buyer_id", $this->buyer_id);
        $stmt->bindParam(":issue_date", $this->issue_date);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Read single coupon
    public function readOne() {
        // Query to read single record
        $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                         b.full_name as buyer_name, b.email as buyer_email, b.civil_id as buyer_civil_id, 
                         b.mobile_number as buyer_mobile, b.file_number as buyer_file_number,
                         r.full_name as recipient_name, r.email as recipient_email, r.civil_id as recipient_civil_id,
                         r.mobile_number as recipient_mobile, r.file_number as recipient_file_number
                  FROM " . $this->table_name . " c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users b ON c.buyer_id = b.id
                  LEFT JOIN users r ON c.recipient_id = r.id
                  WHERE c.id = ?
                  LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind id of coupon to be read
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->code = $row['code'];
            $this->coupon_type_id = $row['coupon_type_id'];
            $this->buyer_id = $row['buyer_id'];
            $this->recipient_id = $row['recipient_id'];
            $this->initial_balance = $row['initial_balance'];
            $this->current_balance = $row['current_balance'];
            $this->issue_date = $row['issue_date'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->buyer_name = $row['buyer_name'];
            $this->buyer_email = $row['buyer_email'];
            $this->buyer_civil_id = $row['buyer_civil_id'];
            $this->buyer_mobile = $row['buyer_mobile'];
            $this->buyer_file_number = $row['buyer_file_number'];
            $this->recipient_name = $row['recipient_name'];
            $this->recipient_email = $row['recipient_email'];
            $this->recipient_civil_id = $row['recipient_civil_id'];
            $this->recipient_mobile = $row['recipient_mobile'];
            $this->recipient_file_number = $row['recipient_file_number'];
            $this->coupon_type_name = $row['coupon_type_name'];
            $this->coupon_type_value = $row['coupon_type_value'];
            
            // Return the row data
            return $row;
        }
        
        return false;
    }
    
    // Get coupon by code
    public function getByCode() {
        // Query to read single record by code
        $query = "SELECT c.*, ct.name as coupon_type_name, ct.value as coupon_type_value,
                         b.full_name as buyer_name, b.email as buyer_email, b.civil_id as buyer_civil_id, 
                         b.mobile_number as buyer_mobile, b.file_number as buyer_file_number,
                         r.full_name as recipient_name, r.email as recipient_email, r.civil_id as recipient_civil_id,
                         r.mobile_number as recipient_mobile, r.file_number as recipient_file_number
                  FROM " . $this->table_name . " c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users b ON c.buyer_id = b.id
                  LEFT JOIN users r ON c.recipient_id = r.id
                  WHERE c.code = ?
                  LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize code
        $this->code = htmlspecialchars(strip_tags($this->code));
        
        // Bind code of coupon to be read
        $stmt->bindParam(1, $this->code);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->id = $row['id'];
            $this->coupon_type_id = $row['coupon_type_id'];
            $this->buyer_id = $row['buyer_id'];
            $this->recipient_id = $row['recipient_id'];
            $this->initial_balance = $row['initial_balance'];
            $this->current_balance = $row['current_balance'];
            $this->issue_date = $row['issue_date'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->buyer_name = $row['buyer_name'];
            $this->buyer_email = $row['buyer_email'];
            $this->buyer_civil_id = $row['buyer_civil_id'];
            $this->buyer_mobile = $row['buyer_mobile'];
            $this->buyer_file_number = $row['buyer_file_number'];
            $this->recipient_name = $row['recipient_name'];
            $this->recipient_email = $row['recipient_email'];
            $this->recipient_civil_id = $row['recipient_civil_id'];
            $this->recipient_mobile = $row['recipient_mobile'];
            $this->recipient_file_number = $row['recipient_file_number'];
            $this->coupon_type_name = $row['coupon_type_name'];
            $this->coupon_type_value = $row['coupon_type_value'];
            
            return true;
        }
        
        return false;
    }
    
    // Assign recipient to coupon
    public function assignRecipient() {
        // Query to update record
        $query = "UPDATE " . $this->table_name . "
                  SET recipient_id=:recipient_id
                  WHERE id=:id";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->recipient_id = htmlspecialchars(strip_tags($this->recipient_id));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind values
        $stmt->bindParam(":recipient_id", $this->recipient_id);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Update coupon status
    public function updateStatus() {
        // Query to update record
        $query = "UPDATE " . $this->table_name . "
                  SET status=:status
                  WHERE id=:id";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind values
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Redeem amount from coupon
    public function redeem($amount, $service_id, $service_name, $service_description, $redeemed_by, $recipient_data) {
        // Check if coupon is valid for redemption
        if($this->status !== 'assigned' && $this->status !== 'available') {
            return false;
        }
        
        // Check if coupon has enough balance
        if($this->current_balance < $amount) {
            return false;
        }
        
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // Create redemption log
            $query = "INSERT INTO redemption_logs 
                      (coupon_id, service_id, service_name, amount, 
                       recipient_name, recipient_civil_id, recipient_mobile, recipient_file_number, 
                       redeemed_by, redemption_date, redemption_time, remaining_balance, service_description) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->bindParam(2, $service_id);
            $stmt->bindParam(3, $service_name);
            $stmt->bindParam(4, $amount);
            $stmt->bindParam(5, $recipient_data['recipient_name']);
            $stmt->bindParam(6, $recipient_data['recipient_civil_id']);
            $stmt->bindParam(7, $recipient_data['recipient_mobile']);
            $stmt->bindParam(8, $recipient_data['recipient_file_number']);
            $stmt->bindParam(9, $redeemed_by);
            
            // Calculate remaining balance
            $remaining_balance = $this->current_balance - $amount;
            $stmt->bindParam(10, $remaining_balance);
            $stmt->bindParam(11, $service_description);
            
            $stmt->execute();
            
            // Update coupon balance
            $query = "UPDATE " . $this->table_name . " 
                      SET current_balance = current_balance - ?, 
                          status = CASE WHEN (current_balance - ?) <= 0 THEN 'fully_redeemed' ELSE status END 
                      WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $amount);
            $stmt->bindParam(2, $amount);
            $stmt->bindParam(3, $this->id);
            
            $stmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            
            // Update object properties
            $this->current_balance -= $amount;
            if($this->current_balance <= 0) {
                $this->status = 'fully_redeemed';
            }
            
            return true;
            
        } catch(Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return false;
        }
    }
    
    // Get available coupons by type
    public function getAvailableCouponsByType($coupon_type_id) {
        // Query to select available coupons by type
        $query = "SELECT c.*, ct.name as coupon_type_name
                  FROM " . $this->table_name . " c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  WHERE c.coupon_type_id = ? AND c.status = 'available'
                  ORDER BY SUBSTRING(c.code, 1, 1) ASC, CAST(SUBSTRING(c.code, 2) AS UNSIGNED) ASC";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind coupon type id
        $stmt->bindParam(1, $coupon_type_id);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Count total coupons
    public function countAll($search = '') {
        // Query to count all records with search
        $query = "SELECT COUNT(*) as total
                  FROM " . $this->table_name . " c
                  LEFT JOIN users b ON c.buyer_id = b.id
                  LEFT JOIN users r ON c.recipient_id = r.id
                  WHERE c.code LIKE ? OR b.full_name LIKE ? OR r.full_name LIKE ?";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Sanitize search term
        $searchTerm = "%" . htmlspecialchars(strip_tags($search)) . "%";
        
        // Bind values
        $stmt->bindParam(1, $searchTerm);
        $stmt->bindParam(2, $searchTerm);
        $stmt->bindParam(3, $searchTerm);
        
        // Execute query
        $stmt->execute();
        
        // Get result
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row['total'];
    }
    
    // Get coupons by buyer
    public function getByBuyer($page = 1, $limit = 10) {
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Query to select all records by buyer
        $query = "SELECT c.*, ct.name as coupon_type_name,
                         r.full_name as recipient_name, r.email as recipient_email
                  FROM " . $this->table_name . " c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users r ON c.recipient_id = r.id
                  WHERE c.buyer_id = ?
                  ORDER BY c.created_at DESC
                  LIMIT ?, ?";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(1, $this->buyer_id);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
        $stmt->bindParam(3, $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get coupons by recipient
    public function getByRecipient($page = 1, $limit = 10) {
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Query to select all records by recipient
        $query = "SELECT c.*, ct.name as coupon_type_name,
                         b.full_name as buyer_name, b.email as buyer_email
                  FROM " . $this->table_name . " c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users b ON c.buyer_id = b.id
                  WHERE c.recipient_id = ?
                  ORDER BY c.created_at DESC
                  LIMIT ?, ?";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(1, $this->recipient_id);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
        $stmt->bindParam(3, $limit, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Get all coupons with pagination
    public function readAll($page = 1, $limit = 10, $search = '') {
        // Calculate offset
        $offset = ($page - 1) * $limit;
        
        // Base query for both search and non-search scenarios
        $baseQuery = "SELECT c.*, ct.name as coupon_type_name,
                         b.full_name as buyer_name, 
                         COALESCE(r.full_name, 
                            (SELECT rl.recipient_name FROM redemption_logs rl 
                             WHERE rl.coupon_id = c.id 
                             ORDER BY rl.redemption_date DESC, rl.redemption_time DESC 
                             LIMIT 1)
                         ) as recipient_name,
                         (SELECT COUNT(*) FROM redemption_logs WHERE coupon_id = c.id) as redemption_count,
                         (SELECT COUNT(DISTINCT recipient_name) FROM redemption_logs WHERE coupon_id = c.id) as unique_recipients,
                         CASE 
                             WHEN c.recipient_id IS NOT NULL THEN 1
                             WHEN (SELECT COUNT(*) FROM redemption_logs WHERE coupon_id = c.id) > 0 THEN 1
                             ELSE 0
                         END as has_recipients
                  FROM " . $this->table_name . " c
                  LEFT JOIN coupon_types ct ON c.coupon_type_id = ct.id
                  LEFT JOIN users b ON c.buyer_id = b.id
                  LEFT JOIN users r ON c.recipient_id = r.id
                  LEFT JOIN (
                      SELECT coupon_id, COUNT(*) as log_count 
                      FROM redemption_logs 
                      GROUP BY coupon_id
                  ) rl ON c.id = rl.coupon_id";
        
        // Prepare statement based on whether search is empty or not
        if(empty($search)) {
            // No search term - show all coupons
            $query = $baseQuery . " 
                  ORDER BY ct.name ASC, SUBSTRING(c.code, 1, 1) ASC, CAST(SUBSTRING(c.code, 2) AS UNSIGNED) ASC
                  LIMIT ?, ?";
                  
            // Prepare query statement
            $stmt = $this->conn->prepare($query);
            
            // Bind only pagination parameters
            $stmt->bindParam(1, $offset, PDO::PARAM_INT);
            $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        } else {
            // Search term provided - filter results
            $query = $baseQuery . " 
                  WHERE c.code LIKE ? OR b.full_name LIKE ? OR 
                        c.id IN (SELECT coupon_id FROM redemption_logs WHERE recipient_name LIKE ?)
                  ORDER BY ct.name ASC, SUBSTRING(c.code, 1, 1) ASC, CAST(SUBSTRING(c.code, 2) AS UNSIGNED) ASC
                  LIMIT ?, ?";
                  
            // Prepare query statement
            $stmt = $this->conn->prepare($query);
            
            // Sanitize search term
            $searchTerm = "%" . htmlspecialchars(strip_tags($search)) . "%";
            
            // Bind search and pagination parameters
            $stmt->bindParam(1, $searchTerm);
            $stmt->bindParam(2, $searchTerm);
            $stmt->bindParam(3, $searchTerm);
            $stmt->bindParam(4, $offset, PDO::PARAM_INT);
            $stmt->bindParam(5, $limit, PDO::PARAM_INT);
        }
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
}
?>
