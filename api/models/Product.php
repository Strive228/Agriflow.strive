<?php
class Product
{
    private $conn;
    private $table_name = "products";

    // Propriétés de l'objet Product
    public $id;
    public $producer_id;
    public $name;
    public $description;
    public $price;
    public $unit;
    public $stock_quantity;
    public $image_url;
    public $is_bio;
    public $is_available;
    public $created_at;
    public $updated_at;

    // Constructeur avec $db comme connexion à la base de données
    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Créer un produit
    function create()
    {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    producer_id=:producer_id, name=:name, description=:description, price=:price, unit=:unit,
                    stock_quantity=:stock_quantity, image_url=:image_url, is_bio=:is_bio, is_available=:is_available";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->producer_id = htmlspecialchars(strip_tags($this->producer_id));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description ?? ""));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->unit = htmlspecialchars(strip_tags($this->unit));
        $this->stock_quantity = htmlspecialchars(strip_tags($this->stock_quantity ?? 0));
        $this->image_url = htmlspecialchars(strip_tags($this->image_url ?? null));
        $this->is_bio = isset($this->is_bio) ? (bool)$this->is_bio : false;
        $this->is_available = isset($this->is_available) ? (bool)$this->is_available : true;

        // Binder les valeurs
        $stmt->bindParam(":producer_id", $this->producer_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":unit", $this->unit);
        $stmt->bindParam(":stock_quantity", $this->stock_quantity);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":is_bio", $this->is_bio, PDO::PARAM_BOOL);
        $stmt->bindParam(":is_available", $this->is_available, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        // printf("Erreur Create: %s.\n", implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire tous les produits (avec pagination et filtres optionnels)
    function readAll($filters = [], $page = 1, $per_page = 10)
    {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT * FROM " . $this->table_name;

        $where_clauses = [];
        if (!empty($filters['producer_id'])) {
            $where_clauses[] = "producer_id = :producer_id_filter";
        }
        if (isset($filters['is_available'])) {
            $where_clauses[] = "is_available = :is_available_filter";
        }
        if (isset($filters['is_bio'])) {
            $where_clauses[] = "is_bio = :is_bio_filter";
        }
        // TODO: Ajouter d'autres filtres si nécessaire (recherche par nom, etc.)

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        // Bind des filtres
        if (!empty($filters['producer_id'])) {
            $stmt->bindParam(":producer_id_filter", $filters['producer_id']);
        }
        if (isset($filters['is_available'])) {
             $is_available_filter = (bool)$filters['is_available'];
            $stmt->bindParam(":is_available_filter", $is_available_filter, PDO::PARAM_BOOL);
        }
        if (isset($filters['is_bio'])) {
            $is_bio_filter = (bool)$filters['is_bio'];
            $stmt->bindParam(":is_bio_filter", $is_bio_filter, PDO::PARAM_BOOL);
        }

        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // Compter tous les produits (pour la pagination)
    function countAll($filters = []) {
        $query = "SELECT COUNT(*) as total_rows FROM " . $this->table_name;
        // Appliquer les mêmes filtres que readAll pour un comptage précis
        $where_clauses = [];
        if (!empty($filters['producer_id'])) {
            $where_clauses[] = "producer_id = :producer_id_filter";
        }
        if (isset($filters['is_available'])) {
            $where_clauses[] = "is_available = :is_available_filter";
        }
        if (isset($filters['is_bio'])) {
            $where_clauses[] = "is_bio = :is_bio_filter";
        }

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $stmt = $this->conn->prepare($query);

        if (!empty($filters['producer_id'])) {
            $stmt->bindParam(":producer_id_filter", $filters['producer_id']);
        }
        if (isset($filters['is_available'])) {
            $is_available_filter = (bool)$filters['is_available'];
            $stmt->bindParam(":is_available_filter", $is_available_filter, PDO::PARAM_BOOL);
        }
        if (isset($filters['is_bio'])) {
            $is_bio_filter = (bool)$filters['is_bio'];
            $stmt->bindParam(":is_bio_filter", $is_bio_filter, PDO::PARAM_BOOL);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'];
    }


    // Lire un seul produit par ID
    function readOne()
    {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->producer_id = $row['producer_id'];
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->price = $row['price'];
            $this->unit = $row['unit'];
            $this->stock_quantity = $row['stock_quantity'];
            $this->image_url = $row['image_url'];
            $this->is_bio = (bool)$row['is_bio'];
            $this->is_available = (bool)$row['is_available'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    // Mettre à jour un produit
    function update()
    {
        $query = "UPDATE " . $this->table_name . "
                SET
                    producer_id = :producer_id,
                    name = :name,
                    description = :description,
                    price = :price,
                    unit = :unit,
                    stock_quantity = :stock_quantity,
                    image_url = :image_url,
                    is_bio = :is_bio,
                    is_available = :is_available
                WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->producer_id = htmlspecialchars(strip_tags($this->producer_id));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description ?? ""));
        $this->price = htmlspecialchars(strip_tags($this->price));
        $this->unit = htmlspecialchars(strip_tags($this->unit));
        $this->stock_quantity = htmlspecialchars(strip_tags($this->stock_quantity ?? 0));
        $this->image_url = htmlspecialchars(strip_tags($this->image_url ?? null));
        $this->is_bio = isset($this->is_bio) ? (bool)$this->is_bio : false;
        $this->is_available = isset($this->is_available) ? (bool)$this->is_available : true;

        // Binder les valeurs
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":producer_id", $this->producer_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":price", $this->price);
        $stmt->bindParam(":unit", $this->unit);
        $stmt->bindParam(":stock_quantity", $this->stock_quantity);
        $stmt->bindParam(":image_url", $this->image_url);
        $stmt->bindParam(":is_bio", $this->is_bio, PDO::PARAM_BOOL);
        $stmt->bindParam(":is_available", $this->is_available, PDO::PARAM_BOOL);

        if ($stmt->execute()) {
            return $stmt->rowCount() > 0; // Retourne true si au moins une ligne a été affectée
        }
        // printf("Erreur Update: %s.\n", implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Supprimer un produit
    function delete()
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            return $stmt->rowCount() > 0; // Retourne true si au moins une ligne a été affectée
        }
        return false;
    }

    // Vérifier si le producer_id appartient à l'utilisateur connecté (simplifié)
    // Dans une vraie application, cette logique serait plus robuste et potentiellement dans un service User/Auth.
    public function isOwner($user_id_connected, $user_role_connected) {
        if ($user_role_connected === 'admin') {
            return true; // L'admin peut tout faire
        }
        // Si l'utilisateur est un producteur, il doit être le propriétaire du produit (producer_id)
        // Cela suppose que $user_id_connected est l'ID de la table `users`
        // et que `products.producer_id` est un ID de la table `producers`.
        // Il faut donc faire le lien entre users.id et producers.id (via producers.user_id)

        // Pour simplifier ici, on va supposer que si le rôle est producteur,
        // on a déjà vérifié que le producer_id du produit correspond au producer_id de l'utilisateur connecté.
        // Cette vérification devrait idéalement se faire dans le contrôleur après avoir récupéré le profil du producteur.
        // Si $this->producer_id est déjà chargé (par readOne par exemple) :
        // return ($user_role_connected === 'producteur' && $this->producer_id === $producer_profile_id_of_connected_user);

        // Pour l'instant, cette méthode est un placeholder. La logique de propriété sera gérée dans le contrôleur.
        return false;
    }
}
?>
