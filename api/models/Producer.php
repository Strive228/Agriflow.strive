<?php
class Producer {
    private $conn;
    private $table_name = "producers";

    // Propriétés de l'objet Producer
    public $id; // producers.id
    public $user_id; // users.id (FK)
    public $farm_name;
    public $siret;
    public $experience_years;
    public $farm_type; // ENUM('cultures', 'elevage', 'mixte')
    public $surface_hectares;
    public $farm_address;
    public $certifications; // TEXT
    public $delivery_availability; // ENUM('3j', '5j', '7j')
    public $farm_description; // TEXT
    public $farm_photo_url;
    public $created_at;
    public $updated_at;

    // Pour la jointure avec users table (optionnel)
    public $user_email;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer un profil producteur complet (utilisé par un endpoint /producers POST)
    // Différent de createBasic qui est utilisé en interne lors de la création d'un User producteur.
    public function create() {
        if(empty($this->user_id) || empty($this->farm_name)) {
            return false; // user_id et farm_name sont essentiels
        }

        // Vérifier si un profil producteur existe déjà pour ce user_id
        $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bindParam(':user_id', $this->user_id);
        $stmtCheck->execute();
        if($stmtCheck->rowCount() > 0) {
            return "producer_exists_for_user";
        }
        // Vérifier si user_id existe dans la table users et a le rôle 'producteur'
        $userCheckQuery = "SELECT role FROM users WHERE id = :user_id";
        $stmtUserCheck = $this->conn->prepare($userCheckQuery);
        $stmtUserCheck->bindParam(':user_id', $this->user_id);
        $stmtUserCheck->execute();
        $userRow = $stmtUserCheck->fetch(PDO::FETCH_ASSOC);
        if(!$userRow || $userRow['role'] !== 'producteur') {
            return "invalid_user_for_producer";
        }


        $query = "INSERT INTO " . $this->table_name . "
                SET
                    user_id=:user_id, farm_name=:farm_name, siret=:siret, experience_years=:experience_years,
                    farm_type=:farm_type, surface_hectares=:surface_hectares, farm_address=:farm_address,
                    certifications=:certifications, delivery_availability=:delivery_availability,
                    farm_description=:farm_description, farm_photo_url=:farm_photo_url";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->farm_name = htmlspecialchars(strip_tags($this->farm_name));
        $this->siret = htmlspecialchars(strip_tags($this->siret ?? null));
        $this->experience_years = isset($this->experience_years) ? (int)$this->experience_years : null;
        $this->farm_type = htmlspecialchars(strip_tags($this->farm_type ?? null));
        $this->surface_hectares = isset($this->surface_hectares) ? (float)$this->surface_hectares : null;
        $this->farm_address = htmlspecialchars(strip_tags($this->farm_address ?? null));
        $this->certifications = htmlspecialchars(strip_tags($this->certifications ?? null));
        $this->delivery_availability = htmlspecialchars(strip_tags($this->delivery_availability ?? null));
        $this->farm_description = htmlspecialchars(strip_tags($this->farm_description ?? null));
        $this->farm_photo_url = htmlspecialchars(strip_tags($this->farm_photo_url ?? null));

        // Binder les valeurs
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":farm_name", $this->farm_name);
        $stmt->bindParam(":siret", $this->siret);
        $stmt->bindParam(":experience_years", $this->experience_years);
        $stmt->bindParam(":farm_type", $this->farm_type);
        $stmt->bindParam(":surface_hectares", $this->surface_hectares);
        $stmt->bindParam(":farm_address", $this->farm_address);
        $stmt->bindParam(":certifications", $this->certifications);
        $stmt->bindParam(":delivery_availability", $this->delivery_availability);
        $stmt->bindParam(":farm_description", $this->farm_description);
        $stmt->bindParam(":farm_photo_url", $this->farm_photo_url);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        error_log("Producer::create() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire tous les profils producteurs (avec pagination et filtres optionnels)
    public function readAll($filters = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        // Jointure optionnelle pour récupérer l'email de l'utilisateur
        $query = "SELECT p.*, u.email as user_email
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.user_id = u.id";

        // TODO: Ajouter des filtres si nécessaire (ex: par farm_type, delivery_availability)
        $where_clauses = [];
        if (!empty($filters['farm_type'])) {
            $where_clauses[] = "p.farm_type = :farm_type_filter";
        }

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY p.farm_name ASC LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);

        if (!empty($filters['farm_type'])) {
            $stmt->bindParam(":farm_type_filter", $filters['farm_type']);
        }

        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    // Compter tous les profils producteurs
    public function countAll($filters = []) {
        $query = "SELECT COUNT(p.id) as total_rows
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.user_id = u.id";
        // TODO: Appliquer les mêmes filtres que readAll
         $where_clauses = [];
        if (!empty($filters['farm_type'])) {
            $where_clauses[] = "p.farm_type = :farm_type_filter";
        }
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $stmt = $this->conn->prepare($query);
         if (!empty($filters['farm_type'])) {
            $stmt->bindParam(":farm_type_filter", $filters['farm_type']);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }

    // Lire un profil producteur par son ID (producers.id)
    public function readOne() {
        $query = "SELECT p.*, u.email as user_email
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            $this->user_id = (int)$row['user_id'];
            $this->farm_name = $row['farm_name'];
            $this->siret = $row['siret'];
            $this->experience_years = $row['experience_years'] !== null ? (int)$row['experience_years'] : null;
            $this->farm_type = $row['farm_type'];
            $this->surface_hectares = $row['surface_hectares'] !== null ? (float)$row['surface_hectares'] : null;
            $this->farm_address = $row['farm_address'];
            $this->certifications = $row['certifications'];
            $this->delivery_availability = $row['delivery_availability'];
            $this->farm_description = $row['farm_description'];
            $this->farm_photo_url = $row['farm_photo_url'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->user_email = $row['user_email'];
            return true;
        }
        return false;
    }

    // Lire un profil producteur par user_id (utilisé en interne et potentiellement par un endpoint /users/{user_id}/producer_profile)
    public function readByUserId() {
        $query = "SELECT p.*, u.email as user_email
                  FROM " . $this->table_name . " p
                  LEFT JOIN users u ON p.user_id = u.id
                  WHERE p.user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            $this->user_id = (int)$row['user_id']; // Déjà setté mais pour la cohérence
            $this->farm_name = $row['farm_name'];
            $this->siret = $row['siret'];
            $this->experience_years = $row['experience_years'] !== null ? (int)$row['experience_years'] : null;
            $this->farm_type = $row['farm_type'];
            $this->surface_hectares = $row['surface_hectares'] !== null ? (float)$row['surface_hectares'] : null;
            $this->farm_address = $row['farm_address'];
            $this->certifications = $row['certifications'];
            $this->delivery_availability = $row['delivery_availability'];
            $this->farm_description = $row['farm_description'];
            $this->farm_photo_url = $row['farm_photo_url'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->user_email = $row['user_email'];
            return true;
        }
        return false;
    }


    // Mettre à jour un profil producteur
    public function update() {
        if(empty($this->id)) return false;

        $query = "UPDATE " . $this->table_name . "
                SET
                    farm_name = :farm_name, siret = :siret, experience_years = :experience_years,
                    farm_type = :farm_type, surface_hectares = :surface_hectares, farm_address = :farm_address,
                    certifications = :certifications, delivery_availability = :delivery_availability,
                    farm_description = :farm_description, farm_photo_url = :farm_photo_url
                    -- user_id ne devrait pas être modifiable ici. Géré par la création/suppression de User.
                WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->farm_name = htmlspecialchars(strip_tags($this->farm_name));
        $this->siret = htmlspecialchars(strip_tags($this->siret ?? null));
        $this->experience_years = isset($this->experience_years) ? (int)$this->experience_years : null;
        $this->farm_type = htmlspecialchars(strip_tags($this->farm_type ?? null));
        $this->surface_hectares = isset($this->surface_hectares) ? (float)$this->surface_hectares : null;
        $this->farm_address = htmlspecialchars(strip_tags($this->farm_address ?? null));
        $this->certifications = htmlspecialchars(strip_tags($this->certifications ?? null));
        $this->delivery_availability = htmlspecialchars(strip_tags($this->delivery_availability ?? null));
        $this->farm_description = htmlspecialchars(strip_tags($this->farm_description ?? null));
        $this->farm_photo_url = htmlspecialchars(strip_tags($this->farm_photo_url ?? null));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':farm_name', $this->farm_name);
        $stmt->bindParam(':siret', $this->siret);
        $stmt->bindParam(':experience_years', $this->experience_years);
        $stmt->bindParam(':farm_type', $this->farm_type);
        $stmt->bindParam(':surface_hectares', $this->surface_hectares);
        $stmt->bindParam(':farm_address', $this->farm_address);
        $stmt->bindParam(':certifications', $this->certifications);
        $stmt->bindParam(':delivery_availability', $this->delivery_availability);
        $stmt->bindParam(':farm_description', $this->farm_description);
        $stmt->bindParam(':farm_photo_url', $this->farm_photo_url);
        $stmt->bindParam(':id', $this->id);

        if($stmt->execute()){
            return $stmt->rowCount() > 0; // True si au moins une ligne a été affectée
        }
        error_log("Producer::update() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Supprimer un profil producteur par son ID (producers.id)
    // Attention: Cela supprimera aussi les produits associés si ON DELETE CASCADE est sur products.producer_id
    public function delete() {
        if(empty($this->id)) return false;
         // Rappel: la FK products.producer_id a ON DELETE CASCADE dans le schéma.
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);
        if($stmt->execute()){
            return $stmt->rowCount() > 0;
        }
        error_log("Producer::delete() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }


    // --- Méthodes utilitaires déjà présentes, adaptées ---

    public function createBasic() {
        if(empty($this->user_id)) {
            error_log("Producer::createBasic() - user_id manquant.");
            return false;
        }
        $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bindParam(':user_id', $this->user_id);
        $stmtCheck->execute();
        if($stmtCheck->rowCount() > 0) return true;

        $query = "INSERT INTO " . $this->table_name . " (user_id, farm_name) VALUES (:user_id, :farm_name)";
        $stmt = $this->conn->prepare($query);
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->farm_name = htmlspecialchars(strip_tags($this->farm_name ?? "Nouvelle Ferme (ID utilisateur: {$this->user_id})"));
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':farm_name', $this->farm_name);
        if($stmt->execute()){
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function deleteByUserId() {
        if(empty($this->user_id)) return false;
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $stmt->bindParam(':user_id', $this->user_id);
        try {
            if($stmt->execute()){ return $stmt->rowCount() > 0; }
        } catch (PDOException $e) {
             error_log("Producer::deleteByUserId() - Erreur PDO: " . $e->getMessage());
        }
        return false;
    }
}
?>
