<?php
// src/models/User.php
// Basic User model stub. Update as needed.
class User {
    public $id;
    public $username;
    public $password;

    public function __construct($id = null, $username = null, $password = null) {
        $this->id = $id;
        $this->username = $username;
        $this->password = $password;
    }

    // Add your methods here

    /**
     * Get all users from database
     */
    public static function getAll() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT id, username FROM users ORDER BY username ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
