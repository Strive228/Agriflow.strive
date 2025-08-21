<?php
class Delivery
{
    private $conn;
    private $table_name = "deliveries";

    // Propriétés
    public $id;
    public $order_id;
    public $customer_id; // Utilisé pour la vérification des droits, pas une colonne directe de deliveries pour la création.
    public $status;
    public $tracking_number;
    public $estimated_delivery_date;
    public $actual_delivery_date;
    public $delivery_person_name;
    public $delivery_person_phone;
    public $delivery_notes;
    // La propriété delivery_address est récupérée via une jointure dans readOne et getCustomerDeliveries
    // Elle n'est pas directement dans la table 'deliveries' selon le schéma SQL.
    // Elle appartient à la commande (orders.delivery_address)
    public $created_at;
    public $updated_at;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function setCustomerId($customer_id)
    {
        $this->customer_id = htmlspecialchars(strip_tags($customer_id));
    }

    // Créer une nouvelle livraison (POST /deliveries)
    // Cette méthode est modifiée pour être plus complète.
    public function create()
    {
        // Vérifier si order_id est fourni et existe
        if (empty($this->order_id)) {
            return false; // Ou lancer une exception
        }

        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    order_id = :order_id,
                    status = :status,
                    tracking_number = :tracking_number,
                    estimated_delivery_date = :estimated_delivery_date,
                    actual_delivery_date = :actual_delivery_date,
                    delivery_person_name = :delivery_person_name,
                    delivery_person_phone = :delivery_person_phone,
                    delivery_notes = :delivery_notes";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->order_id = htmlspecialchars(strip_tags($this->order_id));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'en_attente')); // Valeur par défaut si non fournie
        $this->tracking_number = htmlspecialchars(strip_tags($this->tracking_number ?? null));
        $this->estimated_delivery_date = htmlspecialchars(strip_tags($this->estimated_delivery_date ?? null));
        $this->actual_delivery_date = htmlspecialchars(strip_tags($this->actual_delivery_date ?? null));
        $this->delivery_person_name = htmlspecialchars(strip_tags($this->delivery_person_name ?? null));
        $this->delivery_person_phone = htmlspecialchars(strip_tags($this->delivery_person_phone ?? null));
        $this->delivery_notes = htmlspecialchars(strip_tags($this->delivery_notes ?? null));

        // Bind des valeurs
        $stmt->bindParam(":order_id", $this->order_id);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":tracking_number", $this->tracking_number);
        $stmt->bindParam(":estimated_delivery_date", $this->estimated_delivery_date);
        $stmt->bindParam(":actual_delivery_date", $this->actual_delivery_date);
        $stmt->bindParam(":delivery_person_name", $this->delivery_person_name);
        $stmt->bindParam(":delivery_person_phone", $this->delivery_person_phone);
        $stmt->bindParam(":delivery_notes", $this->delivery_notes);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        // Afficher l'erreur si l'exécution échoue
        // printf("Erreur d'exécution : %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Lire les détails d'une livraison (GET /deliveries/{id})
    // Modifiée pour ne pas dépendre de customer_id pour une lecture générale (ex: admin)
    // La vérification des droits se fera dans le contrôleur.
    public function readOne()
    {
        $query = "SELECT d.*, o.customer_id, o.delivery_address
                FROM " . $this->table_name . " d
                LEFT JOIN orders o ON d.order_id = o.id
                WHERE d.id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->order_id = $row['order_id'];
            $this->customer_id = $row['customer_id']; // Pour information, peut être utilisé par le contrôleur
            $this->status = $row['status'];
            $this->tracking_number = $row['tracking_number'];
            $this->estimated_delivery_date = $row['estimated_delivery_date'];
            $this->actual_delivery_date = $row['actual_delivery_date'];
            $this->delivery_person_name = $row['delivery_person_name'];
            $this->delivery_person_phone = $row['delivery_person_phone'];
            $this->delivery_notes = $row['delivery_notes'];
            // $this->delivery_address = $row['delivery_address']; // Adresse de livraison de la commande associée
            // Pour éviter confusion, la propriété delivery_address sur l'objet Delivery est retirée car elle n'est pas une colonne de la table deliveries.
            // Elle sera retournée dans le tableau de données par le contrôleur si nécessaire.
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return $row; // Retourner toutes les données pour le contrôleur
        }
        return false;
    }

    // Nouvelle méthode pour lire toutes les livraisons (GET /deliveries)
    // Pourrait être paginée et filtrée
    public function readAll($filters = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;

        $select_part = "SELECT DISTINCT d.*, o.customer_id, o.delivery_address as order_delivery_address, u.email as customer_email";
        $from_part = " FROM " . $this->table_name . " d
                       LEFT JOIN orders o ON d.order_id = o.id
                       LEFT JOIN customers c ON o.customer_id = c.id
                       LEFT JOIN users u ON c.user_id = u.id";

        $where_clauses = [];
        $params = [];

        if (!empty($filters['customer_id'])) { // customers.id
            $where_clauses[] = "o.customer_id = :customer_id_filter";
            $params[':customer_id_filter'] = $filters['customer_id'];
        }
        if (!empty($filters['producer_id'])) { // producers.id
            $from_part .= " LEFT JOIN order_items oi ON o.id = oi.order_id
                            LEFT JOIN products p ON oi.product_id = p.id";
            $where_clauses[] = "p.producer_id = :producer_id_filter";
            $params[':producer_id_filter'] = $filters['producer_id'];
        }
        if (!empty($filters['status'])) { // deliveries.status
            $where_clauses[] = "d.status = :status_filter";
            $params[':status_filter'] = $filters['status'];
        }

        $query = $select_part . $from_part;
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // Compter toutes les livraisons avec filtres
    public function countAll($filters = []) {
        $select_part = "SELECT COUNT(DISTINCT d.id) as total_rows";
        $from_part = " FROM " . $this->table_name . " d
                       LEFT JOIN orders o ON d.order_id = o.id";
        // Ne pas joindre users ici, non nécessaire pour le count

        $where_clauses = [];
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where_clauses[] = "o.customer_id = :customer_id_filter";
            $params[':customer_id_filter'] = $filters['customer_id'];
        }
        if (!empty($filters['producer_id'])) {
            $from_part .= " LEFT JOIN order_items oi_count ON o.id = oi_count.order_id
                            LEFT JOIN products p_count ON oi_count.product_id = p_count.id";
            $where_clauses[] = "p_count.producer_id = :producer_id_filter";
            $params[':producer_id_filter'] = $filters['producer_id'];
        }
        if (!empty($filters['status'])) {
            $where_clauses[] = "d.status = :status_filter";
            $params[':status_filter'] = $filters['status'];
        }

        $query = $select_part . $from_part;
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'] ?? 0;
    }


    // Mettre à jour une livraison (PUT /deliveries/{id})
    // La méthode updateStatus est spécifique, celle-ci est plus générale.
    public function update()
    {
        $query = "UPDATE " . $this->table_name . "
                SET
                    order_id = :order_id,
                    status = :status,
                    tracking_number = :tracking_number,
                    estimated_delivery_date = :estimated_delivery_date,
                    actual_delivery_date = :actual_delivery_date,
                    delivery_person_name = :delivery_person_name,
                    delivery_person_phone = :delivery_person_phone,
                    delivery_notes = :delivery_notes,
                    updated_at = CURRENT_TIMESTAMP
                WHERE
                    id = :id";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->order_id = htmlspecialchars(strip_tags($this->order_id));
        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->tracking_number = htmlspecialchars(strip_tags($this->tracking_number));
        $this->estimated_delivery_date = htmlspecialchars(strip_tags($this->estimated_delivery_date));
        $this->actual_delivery_date = htmlspecialchars(strip_tags($this->actual_delivery_date)); // Peut être null si la livraison n'est pas encore effectuée
        $this->delivery_person_name = htmlspecialchars(strip_tags($this->delivery_person_name));
        $this->delivery_person_phone = htmlspecialchars(strip_tags($this->delivery_person_phone));
        $this->delivery_notes = htmlspecialchars(strip_tags($this->delivery_notes));

        // Bind des valeurs
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":order_id", $this->order_id);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":tracking_number", $this->tracking_number);
        $stmt->bindParam(":estimated_delivery_date", $this->estimated_delivery_date);
        $stmt->bindParam(":actual_delivery_date", $this->actual_delivery_date);
        $stmt->bindParam(":delivery_person_name", $this->delivery_person_name);
        $stmt->bindParam(":delivery_person_phone", $this->delivery_person_phone);
        $stmt->bindParam(":delivery_notes", $this->delivery_notes);

        if ($stmt->execute()) {
            return true;
        }
        // printf("Erreur d'exécution : %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Mettre à jour le statut d'une livraison (utilisé par /deliveries/{id}/status)
    // Cette méthode est conservée pour sa spécificité si elle est toujours utilisée ailleurs.
    public function updateStatus()
    {
        $query = "UPDATE " . $this->table_name . "
                SET status = :status,
                    actual_delivery_date = CASE 
                        WHEN :status = 'livree' AND actual_delivery_date IS NULL THEN CURRENT_TIMESTAMP
                        ELSE actual_delivery_date
                    END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
                // Ajout de "AND actual_delivery_date IS NULL" pour ne pas écraser une date de livraison réelle déjà définie.

        $stmt = $this->conn->prepare($query);

        $this->status = htmlspecialchars(strip_tags($this->status));
        $this->id = htmlspecialchars(strip_tags($this->id));


        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            // Vérifier si la mise à jour a affecté une ligne, sinon la livraison n'existe peut-être pas
            // ou les droits ne correspondent pas (si on ajoutait une clause WHERE sur user_id)
            if($stmt->rowCount() > 0) {
                 // Si le statut est 'livree', récupérer la date de livraison réelle mise à jour
                if ($this->status == 'livree') {
                    $query_select = "SELECT actual_delivery_date FROM " . $this->table_name . " WHERE id = :id";
                    $stmt_select = $this->conn->prepare($query_select);
                    $stmt_select->bindParam(":id", $this->id);
                    $stmt_select->execute();
                    $row = $stmt_select->fetch(PDO::FETCH_ASSOC);
                    $this->actual_delivery_date = $row['actual_delivery_date'];
                }
                return true;
            }
        }
        // printf("Erreur d'exécution : %s.\n", $stmt->errorInfo()[2]);
        return false;
    }

    // Supprimer une livraison (DELETE /deliveries/{id})
    public function delete()
    {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            if ($stmt->rowCount() > 0) {
                return true; // Suppression réussie
            } else {
                return false; // Aucune ligne supprimée (peut-être l'id n'existe pas)
            }
        }
        return false;
    }


    // Obtenir l'historique des livraisons d'un client
    public function getCustomerDeliveries($customer_id, $page = 1, $per_page = 10)
    {
        $offset = ($page - 1) * $per_page;

        $query = "SELECT d.*, o.delivery_address, o.status as order_status
                FROM " . $this->table_name . " d
                LEFT JOIN orders o ON d.order_id = o.id
                WHERE o.customer_id = :customer_id
                ORDER BY d.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    // Obtenir les statistiques de livraison d'un client
    public function getCustomerStats($customer_id)
    {
        $query = "SELECT 
                    COUNT(*) as total_deliveries,
                    SUM(CASE WHEN d.status = 'en_cours' OR d.status = 'en_preparation' THEN 1 ELSE 0 END) as ongoing_deliveries, -- Modifié pour inclure 'en_preparation'
                    SUM(CASE WHEN d.status = 'livree' THEN 1 ELSE 0 END) as completed_deliveries,
                    AVG(CASE 
                        WHEN d.status = 'livree' AND d.actual_delivery_date IS NOT NULL AND d.created_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, d.created_at, d.actual_delivery_date)
                        ELSE NULL 
                    END) as avg_delivery_time
                FROM " . $this->table_name . " d
                LEFT JOIN orders o ON d.order_id = o.id
                WHERE o.customer_id = :customer_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>