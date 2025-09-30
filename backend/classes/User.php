<?php
/**
 * Classe User - Gerenciamento de usuários
 */

require_once '../config/database.php';

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $name;
    public $email;
    public $password_hash;
    public $created_at;
    public $updated_at;
    public $is_active;
    public $last_login;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Criar novo usuário
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  SET name = :name, 
                      email = :email, 
                      password_hash = :password_hash";

        $stmt = $this->conn->prepare($query);

        // Limpar dados
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password_hash = password_hash($this->password_hash, PASSWORD_DEFAULT);

        // Bind dos parâmetros
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Fazer login
     */
    public function login($email, $password) {
        $query = "SELECT id, name, email, password_hash, is_active 
                  FROM " . $this->table . " 
                  WHERE email = :email AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password_hash'])) {
                $this->id = $row['id'];
                $this->name = $row['name'];
                $this->email = $row['email'];
                
                // Atualizar último login
                $this->updateLastLogin();
                
                return true;
            }
        }

        return false;
    }

    /**
     * Buscar usuário por ID
     */
    public function findById($id) {
        $query = "SELECT id, name, email, created_at, updated_at, is_active, last_login 
                  FROM " . $this->table . " 
                  WHERE id = :id AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->id = $row['id'];
            $this->name = $row['name'];
            $this->email = $row['email'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->is_active = $row['is_active'];
            $this->last_login = $row['last_login'];
            
            return true;
        }

        return false;
    }

    /**
     * Verificar se email já existe
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table . " WHERE email = :email";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Atualizar último login
     */
    private function updateLastLogin() {
        $query = "UPDATE " . $this->table . " 
                  SET last_login = CURRENT_TIMESTAMP 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
    }

    /**
     * Atualizar perfil do usuário
     */
    public function updateProfile() {
        $query = "UPDATE " . $this->table . " 
                  SET name = :name, 
                      updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Alterar senha
     */
    public function changePassword($old_password, $new_password) {
        // Verificar senha atual
        $query = "SELECT password_hash FROM " . $this->table . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($old_password, $row['password_hash'])) {
                // Atualizar senha
                $query = "UPDATE " . $this->table . " 
                          SET password_hash = :password_hash, 
                              updated_at = CURRENT_TIMESTAMP 
                          WHERE id = :id";

                $stmt = $this->conn->prepare($query);
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt->bindParam(':password_hash', $new_hash);
                $stmt->bindParam(':id', $this->id);

                return $stmt->execute();
            }
        }

        return false;
    }

    /**
     * Converter para array (sem dados sensíveis)
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_login' => $this->last_login
        ];
    }
}
?>