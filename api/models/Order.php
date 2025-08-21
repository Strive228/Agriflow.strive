<?php
class Order
{
    private $conn;
    private $table_name = "orders";

    // Propriétés de l'objet
    public $id;
    public $customer_id;
    public $total_amount;
    public $status; // ENUM('en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee')
    public $payment_status; // ENUM('en_attente', 'payee', 'remboursee', 'echec')
    public $payment_method;
    public $delivery_address;
    public $delivery_notes;
    public $created_at;
    public $updated_at;

    // Propriétés de livraison (jointure)
    public $delivery_id; // Ajout pour référence
    public $delivery_status;
    public $estimated_delivery_date;
    public $tracking_number;
    public $delivery_person_name;
    public $delivery_person_phone;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // Créer une nouvelle commande
    public function create()
    {
        // ... (code existant de create) ...
        $query = "INSERT INTO " . $this->table_name . "
                (customer_id, total_amount, status, payment_status, payment_method, delivery_address, delivery_notes)
                VALUES (:customer_id, :total_amount, :status, :payment_status, :payment_method, :delivery_address, :delivery_notes)";

        $stmt = $this->conn->prepare($query);

        $this->customer_id = htmlspecialchars(strip_tags($this->customer_id));
        $this->total_amount = htmlspecialchars(strip_tags($this->total_amount));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'en_attente'));
        $this->payment_status = htmlspecialchars(strip_tags($this->payment_status ?? 'en_attente'));
        $this->payment_method = htmlspecialchars(strip_tags($this->payment_method ?? null));
        $this->delivery_address = htmlspecialchars(strip_tags($this->delivery_address));
        $this->delivery_notes = htmlspecialchars(strip_tags($this->delivery_notes ?? null));

        $stmt->bindParam(":customer_id", $this->customer_id);
        $stmt->bindParam(":total_amount", $this->total_amount);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payment_status", $this->payment_status);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":delivery_address", $this->delivery_address);
        $stmt->bindParam(":delivery_notes", $this->delivery_notes);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId(); // Récupérer l'ID de la commande créée
            return true;
        }
        error_log("Order::create() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire toutes les commandes d'un client (ou toutes les commandes pour admin/producteur)
    public function readAll($filters = [], $page = 1, $per_page = 10)
    {
        $offset = ($page - 1) * $per_page;
        $select_part = "SELECT DISTINCT o.*,
                        COUNT(DISTINCT oi.id) as items_count,
                        d.id as delivery_id, d.status as delivery_status, d.estimated_delivery_date";

        $from_part = " FROM " . $this->table_name . " o
                       LEFT JOIN order_items oi ON o.id = oi.order_id
                       LEFT JOIN deliveries d ON o.id = d.order_id";

        $where_clauses = [];
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where_clauses[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $where_clauses[] = "o.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['producer_id'])) {
            // Jointure supplémentaire pour filtrer par producer_id
            $from_part .= " LEFT JOIN products p ON oi.product_id = p.id";
            $where_clauses[] = "p.producer_id = :producer_id";
            $params[':producer_id'] = $filters['producer_id'];
        }

        $query = $select_part . $from_part;
        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        $query .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        foreach ($params as $key => &$val) { // Bind des paramètres de filtre
            $stmt->bindParam($key, $val);
        }
        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // Compter les commandes (avec filtres)
    public function countAll($filters = []) {
        $select_part = "SELECT COUNT(DISTINCT o.id) as total_rows";
        $from_part = " FROM " . $this->table_name . " o ";

        $where_clauses = [];
        $params = [];

        if (!empty($filters['customer_id'])) {
            $where_clauses[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $where_clauses[] = "o.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['producer_id'])) {
            // Jointure supplémentaire pour filtrer par producer_id
            // Note: Pour COUNT DISTINCT o.id, la jointure avec order_items doit être présente
            // si producer_id est un filtre, pour s'assurer que la commande contient bien un produit du producteur.
            $from_part .= " LEFT JOIN order_items oi_count ON o.id = oi_count.order_id
                            LEFT JOIN products p_count ON oi_count.product_id = p_count.id";
            $where_clauses[] = "p_count.producer_id = :producer_id";
            $params[':producer_id'] = $filters['producer_id'];
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


    // Lire une commande spécifique avec ses détails
    // Modifié pour permettre à un admin/producteur de lire sans customer_id check direct ici
    public function readOne($check_customer = true)
    {
        $sql_customer_check = $check_customer ? " AND o.customer_id = :customer_id " : "";
        $query = "SELECT o.*, 
                    d.id as delivery_id, d.status as delivery_status,
                    d.estimated_delivery_date, d.tracking_number,
                    d.delivery_person_name, d.delivery_person_phone
                FROM " . $this->table_name . " o
                LEFT JOIN deliveries d ON o.id = d.order_id
                WHERE o.id = :id " . $sql_customer_check . "LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        if ($check_customer) {
            $stmt->bindParam(":customer_id", $this->customer_id);
        }
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->id = (int)$row['id'];
            $this->customer_id = (int)$row['customer_id'];
            $this->status = $row['status'];
            $this->payment_status = $row['payment_status'];
            $this->payment_method = $row['payment_method'];
            $this->total_amount = (float)$row['total_amount'];
            $this->delivery_address = $row['delivery_address'];
            $this->delivery_notes = $row['delivery_notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            $this->delivery_id = isset($row['delivery_id']) ? (int)$row['delivery_id'] : null;
            $this->delivery_status = $row['delivery_status'];
            $this->estimated_delivery_date = $row['estimated_delivery_date'];
            $this->tracking_number = $row['tracking_number'];
            $this->delivery_person_name = $row['delivery_person_name'];
            $this->delivery_person_phone = $row['delivery_person_phone'];
            return true;
        }
        return false;
    }

    // Mettre à jour une commande (plus générique)
    public function update() {
        if(empty($this->id)) return false;

        // Construire la requête dynamiquement basé sur les champs fournis
        $fields_to_update = [];
        if($this->status !== null) $fields_to_update['status'] = $this->status;
        if($this->payment_status !== null) $fields_to_update['payment_status'] = $this->payment_status;
        if($this->payment_method !== null) $fields_to_update['payment_method'] = $this->payment_method;
        if($this->delivery_address !== null) $fields_to_update['delivery_address'] = $this->delivery_address;
        if($this->delivery_notes !== null) $fields_to_update['delivery_notes'] = $this->delivery_notes;

        if(empty($fields_to_update)) return false; // Rien à mettre à jour

        $query_set_parts = [];
        foreach(array_keys($fields_to_update) as $field) {
            $query_set_parts[] = $field . " = :" . $field;
        }
        $query_set_string = implode(", ", $query_set_parts);

        $query = "UPDATE " . $this->table_name . "
                  SET " . $query_set_string . ", updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        // Ajouter une condition sur customer_id si la mise à jour est faite par le client lui-même
        // ou une vérification de rôle producteur/admin dans le contrôleur.
        // Pour l'instant, la vérification des droits est dans le contrôleur.

        $stmt = $this->conn->prepare($query);

        foreach($fields_to_update as $field => $value) {
            $clean_value = htmlspecialchars(strip_tags($value));
            $stmt->bindParam(":" . $field, $clean_value);
        }
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        error_log("Order::update() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }


    // Mettre à jour le statut d'une commande (méthode spécifique existante)
    // Peut être conservée ou fusionnée/supprimée si update() est suffisant.
    // Pour l'instant, on la garde pour compatibilité si elle est utilisée ailleurs.
    public function updateStatus()
    {
        // ... (code existant de updateStatus) ...
        // Il faudrait s'assurer que cette méthode est appelée avec les bons droits (admin/producteur)
        // La clause WHERE customer_id est problématique si un admin/producteur met à jour.
        // On la retire ici, la vérification des droits doit se faire dans le contrôleur.
        $query = "UPDATE " . $this->table_name . "
                SET status = :status,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $this->status = htmlspecialchars(strip_tags($this->status));
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":id", $this->id);
        // $stmt->bindParam(":customer_id", $this->customer_id); // Retiré

        if ($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    // Annuler une commande
    public function cancel($user_id_making_request, $user_role_making_request)
    {
        // La logique pour savoir si une commande peut être annulée dépend de son statut actuel
        // et potentiellement du rôle de l'utilisateur qui fait la demande.
        // Un client ne peut annuler que si 'en_attente' ou 'confirmee'.
        // Un admin/producteur pourrait avoir plus de flexibilité.

        $this->readOne(false); // Charger la commande actuelle sans check client pour avoir le statut.
                               // Le false est pour que l'admin/producteur puisse la charger.

        if ($user_role_making_request === 'client') {
            if ($this->customer_id != $user_id_making_request) { // Double check que c'est bien sa commande
                return "not_owner";
            }
            if (!in_array($this->status, ['en_attente', 'confirmee'])) {
                return "cannot_cancel_status"; // Statut ne permet plus l'annulation par le client
            }
        } elseif ($user_role_making_request === 'admin' || $user_role_making_request === 'producteur') {
            // Les admins/producteurs peuvent annuler des commandes dans plus de statuts,
            // mais peut-être pas 'livree' ou 'deja_annulee'.
            if (in_array($this->status, ['livree', 'annulee'])) {
                 return "cannot_cancel_status_admin";
            }
            // Pour un producteur, il faudrait vérifier qu'il est bien lié à cette commande (via les produits)
            // Ce qui est plus complexe et hors scope de cette simple méthode de modèle.
            // Cette vérification se fera dans le contrôleur.
        } else {
            return "invalid_role";
        }


        $query = "UPDATE " . $this->table_name . "
                SET status = 'annulee',
                    payment_status = CASE
                                        WHEN payment_status = 'payee' THEN 'remboursee'
                                        ELSE payment_status
                                     END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";
        // La condition sur customer_id est retirée, la permission est gérée avant.

        try {
            $this->conn->beginTransaction();

            // Récupérer les items de la commande AVANT de l'annuler pour réintégrer les stocks
            $items_stmt = $this->getOrderItems(); // Utilise $this->id qui est déjà setté
            $items_to_restock = [];
            while($item_row = $items_stmt->fetch(PDO::FETCH_ASSOC)) {
                $items_to_restock[] = $item_row;
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);

            if (!$stmt->execute() || $stmt->rowCount() == 0) {
                $this->conn->rollBack();
                error_log("Order::cancel() - Failed to update order status for order ID: " . $this->id);
                return false; // La mise à jour du statut de la commande a échoué
            }

            // Réintégrer le stock pour chaque produit
            // On ne réintègre que si la commande n'était pas déjà 'livree' ou 'annulee' avant cette annulation.
            // La logique de permission $this->status (chargé par readOne) nous donne le statut *avant* cette annulation.
            if (!in_array($this->status, ['livree'])) { // Ne pas restocker si déjà livrée. 'annulee' est déjà géré par la logique de permission.
                foreach ($items_to_restock as $item) {
                    $update_stock_query = "UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :product_id";
                    $stmt_stock = $this->conn->prepare($update_stock_query);
                    $stmt_stock->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt_stock->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                    if (!$stmt_stock->execute()) {
                        // Si la mise à jour du stock échoue, on rollback.
                        $this->conn->rollBack();
                        error_log("Order::cancel() - Failed to restock product ID: " . $item['product_id'] . " for order ID: " . $this->id);
                        return "restock_failed"; // Erreur spécifique
                    }
                }
            }

            $this->conn->commit();
            return true; // Succès

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Order::cancel() - Exception: " . $e->getMessage());
            return false;
        }
    }

    // Obtenir les statistiques des commandes d'un client
    public function getCustomerStats($customer_id)
    {
        // ... (code existant de getCustomerStats) ...
         $query = "SELECT
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN status = 'en_livraison' OR status = 'en_preparation' THEN 1 ELSE 0 END) as ongoing_processing,
                    SUM(total_amount) as total_spent,
                    MAX(created_at) as last_order_date
                FROM " . $this->table_name . "
                WHERE customer_id = :customer_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":customer_id", $customer_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtenir les items d'une commande
    public function getOrderItems() {
        if(empty($this->id)) return false;
        $query = "SELECT oi.id, oi.product_id, oi.quantity, oi.unit_price, oi.total_price, p.name as product_name, p.image_url as product_image_url
                  FROM order_items oi
                  LEFT JOIN products p ON oi.product_id = p.id
                  WHERE oi.order_id = :order_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $this->id);
        $stmt->execute();
        return $stmt;
    }

}
?>