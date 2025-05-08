<?php
require_once __DIR__ . '/../config/database.php';

class CouponType {
    // Database connection and table name
    private $conn;
    private $table_name = "coupon_types";
    
    // Object properties
    public $id;
    public $name;
    public $description;
    public $value;
    public $created_at;
    public $updated_at;
    
    // Constructor with database connection
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Read all coupon types
    public function readAll() {
        // Query to select all records
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY value DESC";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
    
    // Read single coupon type
    public function readOne() {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind id of coupon type to be read
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->value = $row['value'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Get coupon type by name
    public function getByName() {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE name = ? LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind name of coupon type to be read
        $stmt->bindParam(1, $this->name);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->id = $row['id'];
            $this->description = $row['description'];
            $this->value = $row['value'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Update coupon type
    public function update() {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // Get the current coupon type data before updating
            $query = "SELECT name, value FROM " . $this->table_name . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->id);
            $stmt->execute();
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            $oldName = $oldData['name'];
            $oldValue = $oldData['value'];
            
            // Query to update coupon type record
            $query = "UPDATE " . $this->table_name . "
                      SET name=:name, description=:description, value=:value
                      WHERE id=:id";
            
            // Prepare query statement
            $stmt = $this->conn->prepare($query);
            
            // Sanitize inputs
            $this->name = htmlspecialchars(strip_tags($this->name));
            $this->description = htmlspecialchars(strip_tags($this->description));
            $this->value = htmlspecialchars(strip_tags($this->value));
            $this->id = htmlspecialchars(strip_tags($this->id));
            
            // Bind new values
            $stmt->bindParam(":name", $this->name);
            $stmt->bindParam(":description", $this->description);
            $stmt->bindParam(":value", $this->value);
            $stmt->bindParam(":id", $this->id);
            
            // Execute query to update coupon type
            if(!$stmt->execute()) {
                throw new Exception("Failed to update coupon type");
            }
            
            // If value has changed, update ALL coupons with this type (not just available ones)
            if($oldValue != $this->value) {
                // Update initial_balance for ALL coupons of this type
                $query = "UPDATE coupons 
                          SET initial_balance = :new_value
                          WHERE coupon_type_id = :type_id";
                          
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":new_value", $this->value);
                $stmt->bindParam(":type_id", $this->id);
                
                if(!$stmt->execute()) {
                    throw new Exception("Failed to update coupon initial balances");
                }
                
                // Update current_balance for available coupons
                $query = "UPDATE coupons 
                          SET current_balance = :new_value 
                          WHERE coupon_type_id = :type_id 
                          AND status = 'available'";
                          
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":new_value", $this->value);
                $stmt->bindParam(":type_id", $this->id);
                
                if(!$stmt->execute()) {
                    throw new Exception("Failed to update available coupon balances");
                }
                
                // Update current_balance for assigned coupons (proportionally)
                $query = "UPDATE coupons 
                          SET current_balance = (current_balance / :old_value) * :new_value 
                          WHERE coupon_type_id = :type_id 
                          AND status = 'assigned'";
                          
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":old_value", $oldValue);
                $stmt->bindParam(":new_value", $this->value);
                $stmt->bindParam(":type_id", $this->id);
                
                if(!$stmt->execute()) {
                    throw new Exception("Failed to update assigned coupon balances");
                }
                
                // For partially redeemed coupons, adjust current_balance proportionally
                $query = "UPDATE coupons 
                          SET current_balance = (current_balance / :old_value) * :new_value 
                          WHERE coupon_type_id = :type_id 
                          AND status = 'partially_redeemed'";
                          
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":old_value", $oldValue);
                $stmt->bindParam(":new_value", $this->value);
                $stmt->bindParam(":type_id", $this->id);
                
                if(!$stmt->execute()) {
                    throw new Exception("Failed to update partially redeemed coupon balances");
                }
            }
            
            // If name has changed, update coupon codes
            if($oldName != $this->name) {
                // Get the uppercase version of the new name for coupon codes
                $newNameUpper = strtoupper($this->name);
                $oldNameUpper = strtoupper($oldName);
                
                // Update coupon codes
                $query = "UPDATE coupons 
                          SET code = REPLACE(code, :old_name_prefix, :new_name_prefix) 
                          WHERE coupon_type_id = :type_id";
                          
                $stmt = $this->conn->prepare($query);
                $oldNamePrefix = $oldNameUpper . '-';
                $newNamePrefix = $newNameUpper . '-';
                $stmt->bindParam(":old_name_prefix", $oldNamePrefix);
                $stmt->bindParam(":new_name_prefix", $newNamePrefix);
                $stmt->bindParam(":type_id", $this->id);
                
                if(!$stmt->execute()) {
                    throw new Exception("Failed to update coupon codes");
                }
            }
            
            // Commit transaction
            $this->conn->commit();
            return true;
            
        } catch(Exception $e) {
            // Rollback transaction on error
            $this->conn->rollback();
            return false;
        }
    }
    
    // Get coupon type usage statistics
    public function getUsageStatistics() {
        // Query to get usage statistics
        $query = "SELECT 
                    ct.id, 
                    ct.name, 
                    ct.description,
                    ct.value,
                    COUNT(c.id) AS total_coupons,
                    SUM(CASE WHEN c.status = 'available' THEN 1 ELSE 0 END) AS available_coupons,
                    SUM(CASE WHEN c.status = 'assigned' THEN 1 ELSE 0 END) AS assigned_coupons,
                    SUM(CASE WHEN c.status = 'fully_redeemed' THEN 1 ELSE 0 END) AS redeemed_coupons
                  FROM " . $this->table_name . " ct
                  LEFT JOIN coupons c ON ct.id = c.coupon_type_id
                  GROUP BY ct.id, ct.name, ct.description, ct.value
                  ORDER BY ct.value DESC";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
}
?>
