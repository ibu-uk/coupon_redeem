<?php
require_once __DIR__ . '/../config/database.php';

class User {
    // Database connection and table name
    private $conn;
    private $table_name = "users";
    
    // Object properties
    public $id;
    public $username;
    public $password;
    public $email;
    public $full_name;
    public $civil_id;
    public $mobile_number;
    public $file_number;
    public $purchase_date;
    public $entry_date;
    public $role;
    public $created_at;
    public $updated_at;
    public $created_by_admin_id;
    
    // Constructor with database connection
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Create user
    public function create() {
        // For staff users, we only need username and password
        if ($this->role === 'staff') {
            // Generate placeholder values for required fields
            if (empty($this->email)) {
                $this->email = 'staff_' . $this->username . '@placeholder.com';
            }
            if (empty($this->full_name)) {
                $this->full_name = 'Staff - ' . $this->username;
            }
            // Set empty values for optional fields
            $this->civil_id = $this->civil_id ?: '';
            $this->mobile_number = $this->mobile_number ?: '';
            $this->file_number = $this->file_number ?: '';
        }
        
        // Set default dates if not provided
        if (empty($this->purchase_date)) {
            $this->purchase_date = date('Y-m-d');
        }
        if (empty($this->entry_date)) {
            $this->entry_date = date('Y-m-d');
        }
        
        // Query to insert record
        $query = "INSERT INTO " . $this->table_name . "
                  SET username=:username, password=:password, email=:email, 
                      full_name=:full_name, civil_id=:civil_id, mobile_number=:mobile_number, 
                      file_number=:file_number, purchase_date=:purchase_date, entry_date=:entry_date, role=:role";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->civil_id = htmlspecialchars(strip_tags($this->civil_id));
        $this->mobile_number = htmlspecialchars(strip_tags($this->mobile_number));
        $this->file_number = htmlspecialchars(strip_tags($this->file_number));
        $this->purchase_date = htmlspecialchars(strip_tags($this->purchase_date));
        $this->entry_date = htmlspecialchars(strip_tags($this->entry_date));
        $this->role = htmlspecialchars(strip_tags($this->role));
        
        // Bind values
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":civil_id", $this->civil_id);
        $stmt->bindParam(":mobile_number", $this->mobile_number);
        $stmt->bindParam(":file_number", $this->file_number);
        $stmt->bindParam(":purchase_date", $this->purchase_date);
        $stmt->bindParam(":entry_date", $this->entry_date);
        $stmt->bindParam(":role", $this->role);
        
        // Execute query
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    // Read single user
    public function readOne() {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind id of user to be read
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->full_name = $row['full_name'];
            $this->civil_id = $row['civil_id'];
            $this->mobile_number = $row['mobile_number'];
            $this->file_number = $row['file_number'];
            $this->purchase_date = $row['purchase_date'];
            $this->entry_date = $row['entry_date'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Get user by username
    public function getByUsername() {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = ? LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind username of user to be read
        $stmt->bindParam(1, $this->username);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->id = $row['id'];
            $this->password = $row['password'];
            $this->email = $row['email'];
            $this->full_name = $row['full_name'];
            $this->civil_id = $row['civil_id'];
            $this->mobile_number = $row['mobile_number'];
            $this->file_number = $row['file_number'];
            $this->purchase_date = $row['purchase_date'];
            $this->entry_date = $row['entry_date'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Get user by email
    public function getByEmail() {
        // Query to read single record
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Bind email of user to be read
        $stmt->bindParam(1, $this->email);
        
        // Execute query
        $stmt->execute();
        
        // Get retrieved row
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            // Set values to object properties
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->password = $row['password'];
            $this->full_name = $row['full_name'];
            $this->civil_id = $row['civil_id'];
            $this->mobile_number = $row['mobile_number'];
            $this->file_number = $row['file_number'];
            $this->purchase_date = $row['purchase_date'];
            $this->entry_date = $row['entry_date'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        
        return false;
    }
    
    // Check if buyer exists, if not create a new one
    public function findOrCreateBuyer() {
        // First check if user exists by civil ID or mobile number since email is optional
        $query = "SELECT id FROM " . $this->table_name . " WHERE civil_id = ? OR mobile_number = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->civil_id);
        $stmt->bindParam(2, $this->mobile_number);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id = $row['id'];
            return $this->id;
        }
        
        // If email is provided, also check by email
        if(!empty($this->email)) {
            $query = "SELECT id FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $this->email);
            $stmt->execute();
            
            if($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $this->id = $row['id'];
                return $this->id;
            }
        }
        
        // If not exists, create new buyer
        $this->role = 'buyer';
        
        // Set entry date to today if not provided
        if(empty($this->entry_date)) {
            $this->entry_date = date('Y-m-d');
        }
        
        // Set purchase date to today if not provided
        if(empty($this->purchase_date)) {
            $this->purchase_date = date('Y-m-d');
        }
        
        // If email is empty, generate a placeholder
        if(empty($this->email)) {
            $this->email = 'buyer_' . $this->civil_id . '@placeholder.com';
        }
        
        // Generate a username from civil ID if email is not provided
        if(empty($this->username)) {
            $this->username = 'user_' . substr($this->civil_id, -6) . rand(100, 999);
        }
        
        // Generate a default password for new buyers
        if(empty($this->password)) {
            $this->password = 'buyer' . substr($this->civil_id, -4) . rand(10, 99);
        }
        
        if($this->create()) {
            return $this->id;
        }
        
        return false;
    }
    
    // Update user
    public function update() {
        // Query to update record
        $query = "UPDATE " . $this->table_name . "
                  SET username=:username, email=:email, full_name=:full_name, 
                      civil_id=:civil_id, mobile_number=:mobile_number, file_number=:file_number,
                      purchase_date=:purchase_date, entry_date=:entry_date, role=:role
                  WHERE id = :id";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize inputs
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->full_name = htmlspecialchars(strip_tags($this->full_name));
        $this->civil_id = htmlspecialchars(strip_tags($this->civil_id));
        $this->mobile_number = htmlspecialchars(strip_tags($this->mobile_number));
        $this->file_number = htmlspecialchars(strip_tags($this->file_number));
        $this->purchase_date = htmlspecialchars(strip_tags($this->purchase_date));
        $this->entry_date = htmlspecialchars(strip_tags($this->entry_date));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind values
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":full_name", $this->full_name);
        $stmt->bindParam(":civil_id", $this->civil_id);
        $stmt->bindParam(":mobile_number", $this->mobile_number);
        $stmt->bindParam(":file_number", $this->file_number);
        $stmt->bindParam(":purchase_date", $this->purchase_date);
        $stmt->bindParam(":entry_date", $this->entry_date);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":id", $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Verify password
    public function verifyPassword($password) {
        return password_verify($password, $this->password);
    }
    
    // Delete user
    public function delete() {
        // Query to delete record
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        // Prepare query
        $stmt = $this->conn->prepare($query);
        
        // Sanitize input
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        // Bind id of record to delete
        $stmt->bindParam(1, $this->id);
        
        // Execute query
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
    
    // Read all users
    public function readAll() {
        // Query to read all users
        $query = "SELECT *, 
                 'Auto-generated' as creation_type 
                 FROM " . $this->table_name . " 
                 ORDER BY role, full_name";
        
        // Prepare query statement
        $stmt = $this->conn->prepare($query);
        
        // Execute query
        $stmt->execute();
        
        return $stmt;
    }
}
?>
