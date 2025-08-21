<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $email;
    public $password_hash; // Pour la création/mise à jour de mot de passe
    public $phone;
    public $role;
    public $created_at;
    public $updated_at;
    public $last_login;
    public $is_active;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer un utilisateur (par un admin, ou lors de l'inscription)
    function create() {
        // Le hashage du mot de passe doit être fait ici ou avant d'appeler cette méthode.
        // Pour l'instant, on suppose que password_hash est déjà hashé.
        if (empty($this->email) || empty($this->password_hash) || empty($this->role)) {
            return false; // Champs essentiels manquants
        }

        // Vérifier si l'email existe déjà
        if ($this->emailExists($this->email)) {
            // error_log("Tentative de création d'utilisateur avec un email existant: " . $this->email);
            return "email_exists";
        }

        $query = "INSERT INTO " . $this->table_name . "
                SET
                    email = :email,
                    password_hash = :password_hash,
                    phone = :phone,
                    role = :role,
                    is_active = :is_active";

        $stmt = $this->conn->prepare($query);

        $this->email = htmlspecialchars(strip_tags($this->email));
        // password_hash est déjà hashé, pas de strip_tags
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? null));
        $this->role = htmlspecialchars(strip_tags($this->role));
        $this->is_active = isset($this->is_active) ? (bool)$this->is_active : true;

        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':is_active', $this->is_active, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            // Potentiellement créer un profil client/producteur associé ici si nécessaire
            return true;
        }
        // error_log("Erreur PDO User Create: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire tous les utilisateurs (pour admin)
    function readAll($filters = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT id, email, phone, role, created_at, updated_at, last_login, is_active
                  FROM " . $this->table_name;

        $where_clauses = [];
        if(!empty($filters['role'])) {
            $where_clauses[] = "role = :role_filter";
        }
        if(isset($filters['is_active'])) {
            $where_clauses[] = "is_active = :is_active_filter";
        }

        if(count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        if(!empty($filters['role'])) {
            $stmt->bindParam(":role_filter", $filters['role']);
        }
        if(isset($filters['is_active'])) {
            $is_active_filter = (bool)$filters['is_active'];
            $stmt->bindParam(":is_active_filter", $is_active_filter, PDO::PARAM_BOOL);
        }

        $stmt->bindParam(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // Compter tous les utilisateurs (pour admin, avec filtres)
    function countAll($filters = []) {
        $query = "SELECT COUNT(*) as total_rows FROM " . $this->table_name;
        $where_clauses = [];
        if(!empty($filters['role'])) {
            $where_clauses[] = "role = :role_filter";
        }
        if(isset($filters['is_active'])) {
            $where_clauses[] = "is_active = :is_active_filter";
        }
         if(count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $stmt = $this->conn->prepare($query);
        if(!empty($filters['role'])) {
            $stmt->bindParam(":role_filter", $filters['role']);
        }
        if(isset($filters['is_active'])) {
             $is_active_filter = (bool)$filters['is_active'];
            $stmt->bindParam(":is_active_filter", $is_active_filter, PDO::PARAM_BOOL);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }


    // Lire un utilisateur par ID (pour admin ou utilisateur lui-même)
    // Ne retourne pas le hash du mot de passe
    function readOne($fetch_password_hash = false) {
        $fields = "id, email, phone, role, created_at, updated_at, last_login, is_active";
        if ($fetch_password_hash) { // Uniquement pour usage interne (ex: login)
            $fields .= ", password_hash";
        }
        $query = "SELECT " . $fields . " FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->role = $row['role'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->last_login = $row['last_login'];
            $this->is_active = (bool)$row['is_active'];
            if ($fetch_password_hash && isset($row['password_hash'])) {
                $this->password_hash = $row['password_hash'];
            } else {
                $this->password_hash = null; // S'assurer qu'il n'est pas exposé par défaut
            }
            return true;
        }
        return false;
    }

    // Mettre à jour un utilisateur (par admin ou utilisateur lui-même)
    // Ne permet pas de changer le mot de passe directement ici (utiliser une méthode dédiée)
    function update() {
        $query = "UPDATE " . $this->table_name . "
                SET
                    email = :email,
                    phone = :phone,
                    role = :role,
                    is_active = :is_active
                    -- Ne pas mettre à jour password_hash ici
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? null));
        $this->role = htmlspecialchars(strip_tags($this->role)); // Seul un admin devrait pouvoir changer le rôle
        $this->is_active = isset($this->is_active) ? (bool)$this->is_active : true;

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':is_active', $this->is_active, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        // error_log("Erreur PDO User Update: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Supprimer un utilisateur (par admin)
    // Pourrait être une désactivation (is_active = false) ou une suppression réelle.
    // La suppression réelle peut avoir des problèmes de contraintes FK.
    function delete() {
        // Option 1: Désactivation (Soft Delete)
        // $query = "UPDATE " . $this->table_name . " SET is_active = false WHERE id = :id";

        // Option 2: Suppression réelle (Hard Delete) - Risqué avec FK
        // Avant de supprimer, il faudrait supprimer les enregistrements liés dans customers, producers, etc.
        // ou s'assurer que les FK ont ON DELETE CASCADE / SET NULL.
        // Pour l'instant, on fait un soft delete et on anonymise l'email.
         $query = "UPDATE " . $this->table_name . " SET is_active = false, email = CONCAT('deleted_user_', id, '@example.com') WHERE id = :id";
        // On modifie l'email pour permettre une réinscription avec le même email plus tard si besoin,
        // et pour éviter les conflits d'unicité si on ne supprime pas vraiment la ligne.

        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    // Vérifier si un email existe
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $email_clean = htmlspecialchars(strip_tags($email));
        $stmt->bindParam(':email', $email_clean);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }

    // Mettre à jour le mot de passe
    public function updatePassword($new_password_hash) {
        if (empty($this->id) || empty($new_password_hash)) {
            return false;
        }
        $query = "UPDATE " . $this->table_name . " SET password_hash = :password_hash WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password_hash', $new_password_hash);
        $stmt->bindParam(':id', $this->id);
        if ($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    // Pour la connexion (utilisé par auth.php probablement)
    public function login($email) {
        $query = "SELECT id, email, password_hash, role, is_active FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $email_clean = htmlspecialchars(strip_tags($email));
        $stmt->bindParam(':email', $email_clean);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = (int)$row['id'];
            $this->email = $row['email'];
            $this->password_hash = $row['password_hash'];
            $this->role = $row['role'];
            $this->is_active = (bool)$row['is_active'];
            return true; // Utilisateur trouvé, le mot de passe sera vérifié dans le contrôleur/service d'auth
        }
        return false; // Utilisateur non trouvé
    }

    // Mettre à jour last_login
    public function updateLastLogin() {
        if(empty($this->id)) return false;
        $query = "UPDATE " . $this->table_name . " SET last_login = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }
}
?>
