<?php
class Customer {
    private $conn;
    private $table_name = "customers";

    // Propriétés de l'objet Customer
    public $id; // customers.id
    public $user_id; // users.id (FK)
    public $delivery_address; // TEXT
    public $food_preferences; // ENUM('bio', 'local', 'aucune')
    public $created_at;
    public $updated_at;

    // Pour la jointure avec users table (optionnel)
    public $user_email;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer un profil client complet (utilisé par un endpoint /customers POST)
    // Différent de createBasic qui est utilisé en interne lors de la création d'un User client.
    public function create() {
        if(empty($this->user_id)) { // delivery_address et food_preferences sont optionnels à la création directe
            return false;
        }

        // Vérifier si un profil client existe déjà pour ce user_id
        $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bindParam(':user_id', $this->user_id);
        $stmtCheck->execute();
        if($stmtCheck->rowCount() > 0) {
            return "customer_exists_for_user";
        }
        // Vérifier si user_id existe dans la table users et a le rôle 'client'
        $userCheckQuery = "SELECT role FROM users WHERE id = :user_id";
        $stmtUserCheck = $this->conn->prepare($userCheckQuery);
        $stmtUserCheck->bindParam(':user_id', $this->user_id);
        $stmtUserCheck->execute();
        $userRow = $stmtUserCheck->fetch(PDO::FETCH_ASSOC);
        if(!$userRow || $userRow['role'] !== 'client') {
            return "invalid_user_for_customer";
        }

        $query = "INSERT INTO " . $this->table_name . "
                SET
                    user_id=:user_id, delivery_address=:delivery_address,
                    food_preferences=:food_preferences";

        $stmt = $this->conn->prepare($query);

        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->delivery_address = htmlspecialchars(strip_tags($this->delivery_address ?? null));
        $this->food_preferences = htmlspecialchars(strip_tags($this->food_preferences ?? 'aucune'));

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":delivery_address", $this->delivery_address);
        $stmt->bindParam(":food_preferences", $this->food_preferences);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        error_log("Customer::create() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire tous les profils clients (pour Admin)
    public function readAll($filters = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT c.*, u.email as user_email
                  FROM " . $this->table_name . " c
                  LEFT JOIN users u ON c.user_id = u.id";

        $where_clauses = [];
        if (!empty($filters['food_preferences'])) {
            $where_clauses[] = "c.food_preferences = :food_preferences_filter";
        }
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);

        if (!empty($filters['food_preferences'])) {
            $stmt->bindParam(":food_preferences_filter", $filters['food_preferences']);
        }

        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Compter tous les profils clients (pour Admin)
    public function countAll($filters = []) {
        $query = "SELECT COUNT(c.id) as total_rows
                  FROM " . $this->table_name . " c
                  LEFT JOIN users u ON c.user_id = u.id";
        $where_clauses = [];
        if (!empty($filters['food_preferences'])) {
            $where_clauses[] = "c.food_preferences = :food_preferences_filter";
        }
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $stmt = $this->conn->prepare($query);
         if (!empty($filters['food_preferences'])) {
            $stmt->bindParam(":food_preferences_filter", $filters['food_preferences']);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }

    // Lire un profil client par son ID (customers.id)
    public function readOne() {
        $query = "SELECT c.*, u.email as user_email
                  FROM " . $this->table_name . " c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            $this->user_id = (int)$row['user_id'];
            $this->delivery_address = $row['delivery_address'];
            $this->food_preferences = $row['food_preferences'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->user_email = $row['user_email'];
            return true;
        }
        return false;
    }

    // Lire un profil client par user_id
    public function readByUserId() {
        $query = "SELECT c.*, u.email as user_email
                  FROM " . $this->table_name . " c
                  LEFT JOIN users u ON c.user_id = u.id
                  WHERE c.user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            // $this->user_id est déjà setté
            $this->delivery_address = $row['delivery_address'];
            $this->food_preferences = $row['food_preferences'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->user_email = $row['user_email'];
            return true;
        }
        return false;
    }

    // Mettre à jour un profil client
    public function update() {
        if(empty($this->id)) return false;

        $query = "UPDATE " . $this->table_name . "
                SET
                    delivery_address = :delivery_address,
                    food_preferences = :food_preferences
                    -- user_id ne devrait pas être modifiable ici.
                WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->delivery_address = htmlspecialchars(strip_tags($this->delivery_address ?? null));
        $this->food_preferences = htmlspecialchars(strip_tags($this->food_preferences ?? 'aucune'));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':delivery_address', $this->delivery_address);
        $stmt->bindParam(':food_preferences', $this->food_preferences);
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()){
            return $stmt->rowCount() > 0;
        }
        error_log("Customer::update() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Supprimer un profil client par son ID (customers.id)
    // Normalement géré via la suppression du User associé (soft delete User -> deleteByUserId Customer)
    public function delete() {
        if(empty($this->id)) return false;
        // Attention aux contraintes FK (orders.customer_id, favorites.customer_id)
        // Le schéma SQL a ON DELETE CASCADE pour ces tables, donc la suppression du customer supprimera ses commandes/favoris.
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);
        if($stmt->execute()){
            return $stmt->rowCount() > 0;
        }
        error_log("Customer::delete() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // --- Méthodes utilitaires déjà présentes ---
    public function createBasic() {
        if(empty($this->user_id)) return false;
        $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bindParam(':user_id', $this->user_id);
        $stmtCheck->execute();
        if($stmtCheck->rowCount() > 0) return true;

        $query = "INSERT INTO " . $this->table_name . " (user_id, food_preferences) VALUES (:user_id, :food_preferences)";
        $stmt = $this->conn->prepare($query);
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $default_food_pref = 'aucune'; // Valeur par défaut pour food_preferences
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':food_preferences', $default_food_pref);
        if($stmt->execute()){
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function deleteByUserId() {
        if(empty($this->user_id)) return false;
        // La suppression des commandes et favoris est gérée par ON DELETE CASCADE dans la DB.
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $stmt->bindParam(':user_id', $this->user_id);
        try {
            if($stmt->execute()){ return $stmt->rowCount() > 0; }
        } catch (PDOException $e) {
             error_log("Customer::deleteByUserId() - Erreur PDO: " . $e->getMessage());
        }
        return false;
    }
}
?>
