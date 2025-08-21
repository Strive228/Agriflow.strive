<?php
require_once '../config/Database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Order.php';
require_once '../models/OrderItem.php';
// require_once '../models/User.php'; // Pourrait être utile pour vérifier les rôles

class OrderController
{
    private $db;
    private $order;
    private $orderItem;
    // private $user;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->order = new Order($this->db);
        $this->orderItem = new OrderItem($this->db);
        // $this->user = new User($this->db);
    }

    // Helper pour vérifier les permissions (simplifié)
    // Un client peut voir/modifier ses commandes.
    // Un admin/producteur peut voir/modifier les commandes (logique de producteur plus complexe non implémentée ici)
    private function checkOrderPermission($order_id, $action = 'read') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return "unauthorized";
        }
        $connected_user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];

        $this->order->id = $order_id;
        // Pour admin/producteur, on ne vérifie pas customer_id ici.
        // Pour client, on charge la commande en s'assurant qu'il est le propriétaire.
        $is_owner_or_privileged = false;
        if ($user_role === 'admin' || $user_role === 'producteur') {
             // Pour producteur, il faudrait une logique pour vérifier s'il est lié aux produits de la commande.
             // Pour simplifier, on suppose qu'un producteur peut voir/modifier si le rôle est producteur.
            if ($this->order->readOne(false)) { // false pour ne pas checker customer_id
                $is_owner_or_privileged = true;
            }
        } elseif ($user_role === 'client') {
            // Récupérer le customers.id à partir de users.id en session
            $customerQuery = "SELECT id FROM customers WHERE user_id = :user_id_session LIMIT 1";
            $stmtCust = $this->db->prepare($customerQuery);
            $stmtCust->bindParam(':user_id_session', $connected_user_id);
            $stmtCust->execute();
            $customer_row = $stmtCust->fetch(PDO::FETCH_ASSOC);
            if (!$customer_row) return "not_found_profile_for_user";

            $this->order->customer_id = $customer_row['id']; // Assigner pour le check dans readOne
            if ($this->order->readOne(true)) { // true pour checker customer_id
                 if ($this->order->customer_id == $customer_row['id']) {
                    $is_owner_or_privileged = true;
                 }
            }
        }

        if (!$is_owner_or_privileged) return "not_found_or_forbidden";

        // Logique spécifique à l'action
        if ($action === 'update' || $action === 'cancel') {
            if ($user_role === 'client') {
                // Le client ne peut modifier/annuler que certains statuts.
                // Cette logique est en partie dans le modèle (cancel), mais peut être renforcée ici.
                if ($action === 'update' && !in_array($this->order->status, ['en_attente'])) {
                    // Ex: Client ne peut modifier que si 'en_attente'
                    return "forbidden_status_client";
                }
            }
            // Admin/producteur peuvent modifier plus de statuts.
        }
        return true;
    }


    // GET /orders (Liste des commandes du client OU toutes les commandes pour admin/producteur)
    public function getAllOrCustomerOrders()
    {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            ApiResponse::unauthorized(); return;
        }

        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];
        $connected_producer_profile_id = $_SESSION['producer_profile_id'] ?? null;
        $connected_customer_profile_id = $_SESSION['customer_profile_id'] ?? null;

        $filters = [];

        if ($user_role === 'client') {
            if ($connected_customer_profile_id) {
                $filters['customer_id'] = $connected_customer_profile_id;
            } else {
                 ApiResponse::success(["items" => [], "pagination" => ["currentPage" => 1, "itemsPerPage" => 10, "totalItems" => 0, "totalPages" => 0]], "Profil client non trouvé pour l'utilisateur connecté.");
                 return;
            }
        } elseif ($user_role === 'producteur') {
            $producer_id_to_filter = null;
            if (isset($_GET['producer_id'])) { // Un producteur demande explicitement pour un ID
                if ($_GET['producer_id'] == $connected_producer_profile_id) {
                    $producer_id_to_filter = (int)$_GET['producer_id'];
                } else {
                    // Un producteur essaie de voir les commandes d'un autre producteur
                    ApiResponse::forbidden("Vous ne pouvez consulter que vos propres commandes de producteur.");
                    return;
                }
            } else { // Par défaut, le producteur voit ses commandes
                if ($connected_producer_profile_id) {
                    $producer_id_to_filter = $connected_producer_profile_id;
                } else {
                    ApiResponse::error("Impossible de déterminer l'identifiant producteur pour filtrer les commandes.", 400);
                    return;
                }
            }
            $filters['producer_id'] = $producer_id_to_filter;

        } elseif ($user_role === 'admin') {
            // L'admin peut filtrer par customer_id ou producer_id s'ils sont dans GET
            if (isset($_GET['customer_id'])) {
                $filters['customer_id'] = (int)$_GET['customer_id'];
            }
            if (isset($_GET['producer_id'])) {
                $filters['producer_id'] = (int)$_GET['producer_id'];
            }
            // Si aucun filtre, l'admin voit tout (limité par pagination)
        } else {
            ApiResponse::forbidden("Accès non autorisé pour lister les commandes.");
            return;
        }

        if(isset($_GET['status'])) $filters['status'] = $_GET['status'];
        // Ajoutez d'autres filtres communs ici si nécessaire (ex: date range)

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        try {
            $stmt = $this->order->readAll($filters, $page, $per_page);
            $orders_arr = ["items" => []];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $order_item = [
                    'id' => (int)$row['id'],
                    'customer_id' => (int)$row['customer_id'],
                    'total_amount' => (float)$row['total_amount'],
                    'status' => $row['status'],
                    'payment_status' => $row['payment_status'],
                    'items_count' => (int)($row['items_count'] ?? 0), // S'assurer que items_count est bien retourné
                    'delivery_id' => isset($row['delivery_id']) ? (int)$row['delivery_id'] : null,
                    'delivery_status' => $row['delivery_status'],
                    'estimated_delivery_date' => $row['estimated_delivery_date'],
                    'created_at' => $row['created_at']
                ];
                array_push($orders_arr["items"], $order_item);
            }
            $total_items = $this->order->countAll($filters);
            $orders_arr["pagination"] = [
                "currentPage" => $page, "itemsPerPage" => $per_page,
                "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page)
            ];
            ApiResponse::success($orders_arr);
        } catch (Exception $e) {
            ApiResponse::error($e->getMessage());
        }
    }

    // GET /orders/{id}
    public function getOrderDetails($order_id)
    {
        $perm = $this->checkOrderPermission($order_id, 'read');
        if ($perm !== true) {
             if($perm === "unauthorized") ApiResponse::unauthorized();
             else if($perm === "not_found_profile_for_user") ApiResponse::notFound("Profil client non trouvé pour l'utilisateur en session.");
             else ApiResponse::notFoundOrForbidden("Commande non trouvée ou accès refusé.");
             return;
        }
        // $this->order est chargé par checkOrderPermission
        try {
            $stmt_items = $this->order->getOrderItems(); // Utilise $this->order->id
            $items = [];
            while ($row_item = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
                $items[] = [
                    'item_id' => (int)$row_item['id'], // ID de order_items
                    'product_id' => (int)$row_item['product_id'],
                    'product_name' => $row_item['product_name'],
                    'product_image_url' => $row_item['product_image_url'],
                    'quantity' => (int)$row_item['quantity'],
                    'unit_price' => (float)$row_item['unit_price'],
                    'total_price' => (float)$row_item['total_price']
                ];
            }

            $order_data_response = [
                'id' => (int)$this->order->id,
                'customer_id' => (int)$this->order->customer_id,
                'status' => $this->order->status,
                'payment_status' => $this->order->payment_status,
                'payment_method' => $this->order->payment_method,
                'total_amount' => (float)$this->order->total_amount,
                'delivery_address' => $this->order->delivery_address,
                'delivery_notes' => $this->order->delivery_notes,
                'created_at' => $this->order->created_at,
                'updated_at' => $this->order->updated_at,
                'delivery_info' => null,
                'items' => $items
            ];
            if ($this->order->delivery_id) {
                $order_data_response['delivery_info'] = [
                    'delivery_id' => (int)$this->order->delivery_id,
                    'status' => $this->order->delivery_status,
                    'estimated_date' => $this->order->estimated_delivery_date,
                    'tracking_number' => $this->order->tracking_number,
                    'person_name' => $this->order->delivery_person_name,
                    'person_phone' => $this->order->delivery_person_phone
                ];
            }
            ApiResponse::success($order_data_response);
        } catch (Exception $e) {
            ApiResponse::error($e->getMessage());
        }
    }

    // POST /orders (Création)
    public function createOrder() {
        // ... (Logique existante de createOrder, s'assurer que customer_id est bien celui de la table customers)
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
            ApiResponse::unauthorized("Seuls les clients connectés peuvent passer commande.");
            return;
        }
        // Récupérer le customers.id à partir de users.id en session
        $customerQuery = "SELECT id FROM customers WHERE user_id = :user_id_session LIMIT 1";
        $stmtCust = $this->db->prepare($customerQuery);
        $stmtCust->bindParam(':user_id_session', $_SESSION['user_id']);
        $stmtCust->execute();
        $customer_row = $stmtCust->fetch(PDO::FETCH_ASSOC);

        if (!$customer_row) {
            ApiResponse::forbidden("Profil client non trouvé ou incomplet pour passer commande.");
            return;
        }
        $actual_customer_id = $customer_row['id'];


        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->items) || empty($data->items) || !isset($data->delivery_address) || empty($data->delivery_address)) {
            ApiResponse::badRequest("La commande doit contenir des produits et une adresse de livraison.");
            return;
        }

        try {
            $this->db->beginTransaction();

            $this->order->customer_id = $actual_customer_id; // Utiliser customers.id
            $this->order->status = 'en_attente';
            $this->order->payment_status = 'en_attente';
            $this->order->delivery_address = $data->delivery_address;
            $this->order->delivery_notes = $data->delivery_notes ?? null;
            $this->order->payment_method = $data->payment_method ?? 'non_defini';

            // Calculer le total_amount basé sur les items et les prix actuels des produits
            $calculated_total = 0;
            foreach ($data->items as $item_input) {
                if (!isset($item_input->product_id) || !isset($item_input->quantity) || $item_input->quantity <=0) {
                     throw new Exception("Données d'item invalides.");
                }
                // Récupérer le prix du produit depuis la DB pour éviter manipulation côté client
                $productQuery = "SELECT price, stock_quantity FROM products WHERE id = :product_id AND is_available = TRUE";
                $stmtProd = $this->db->prepare($productQuery);
                $stmtProd->bindParam(':product_id', $item_input->product_id);
                $stmtProd->execute();
                $product_db = $stmtProd->fetch(PDO::FETCH_ASSOC);
                if (!$product_db) {
                    throw new Exception("Produit ID " . $item_input->product_id . " non trouvé ou non disponible.");
                }
                if ($product_db['stock_quantity'] < $item_input->quantity) {
                    throw new Exception("Stock insuffisant pour le produit ID " . $item_input->product_id);
                }
                $item_input->unit_price = $product_db['price']; // Utiliser le prix de la DB
                $calculated_total += ($item_input->quantity * $item_input->unit_price);
            }
            $this->order->total_amount = $calculated_total;


            if (!$this->order->create()) { // create() dans le modèle devrait retourner l'ID ou true
                 throw new Exception('Erreur lors de la création de l\'enregistrement de commande.');
            }
            $order_id = $this->order->id; // L'ID est maintenant sur l'objet $this->order

            foreach ($data->items as $item_input) {
                $this->orderItem->order_id = $order_id;
                $this->orderItem->product_id = $item_input->product_id;
                $this->orderItem->quantity = $item_input->quantity;
                $this->orderItem->unit_price = $item_input->unit_price; // Prix vérifié depuis la DB
                $this->orderItem->total_price = $item_input->quantity * $item_input->unit_price;

                if (!$this->orderItem->create()) {
                    throw new Exception('Erreur lors de l\'ajout d\'un produit à la commande.');
                }
                // Décrémenter le stock
                $updateStockQuery = "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id AND stock_quantity >= :quantity_to_decrement";
                $stmtUpdateStock = $this->db->prepare($updateStockQuery);
                $stmtUpdateStock->bindParam(':quantity', $item_input->quantity, PDO::PARAM_INT);
                $stmtUpdateStock->bindParam(':product_id', $item_input->product_id, PDO::PARAM_INT);
                $stmtUpdateStock->bindParam(':quantity_to_decrement', $item_input->quantity, PDO::PARAM_INT); // Pour la condition

                if (!$stmtUpdateStock->execute() || $stmtUpdateStock->rowCount() == 0) {
                    // Soit l'exécution a échoué, soit le stock était insuffisant (rowCount == 0 à cause de la condition stock_quantity >= quantity)
                    // La vérification initiale de stock_quantity devrait déjà attraper ça, mais c'est une double sécurité.
                    throw new Exception("Impossible de mettre à jour le stock pour le produit ID " . $item_input->product_id . ". Le stock pourrait être devenu insuffisant.");
                }
            }
            $this->db->commit();
            ApiResponse::created(['order_id' => $order_id, 'total_amount' => $this->order->total_amount], 'Commande créée avec succès.');

        } catch (Exception $e) {
            $this->db->rollBack();
            ApiResponse::error($e->getMessage(), 400); // 400 pour erreur client potentielle (ex: stock)
        }
    }

    // PUT /orders/{id} (Mise à jour de la commande)
    public function updateOrder($order_id) {
        $perm = $this->checkOrderPermission($order_id, 'update');
         if ($perm !== true) {
             if($perm === "unauthorized") ApiResponse::unauthorized();
             else if($perm === "not_found_profile_for_user") ApiResponse::notFound("Profil client non trouvé pour l'utilisateur en session.");
             else if ($perm === "forbidden_status_client") ApiResponse::forbidden("La commande ne peut plus être modifiée par le client à ce stade.");
             else ApiResponse::notFoundOrForbidden("Commande non trouvée ou modification non autorisée.");
             return;
        }
        // $this->order est chargé par checkOrderPermission

        $data = json_decode(file_get_contents("php://input"));
        if (empty($data)) {
            ApiResponse::badRequest("Aucune donnée fournie pour la mise à jour.");
            return;
        }

        // Champs modifiables par un client (si statut le permet)
        if ($_SESSION['user_role'] === 'client' && $this->order->status === 'en_attente') {
            $this->order->delivery_address = $data->delivery_address ?? $this->order->delivery_address;
            $this->order->delivery_notes = $data->delivery_notes ?? $this->order->delivery_notes;
        }

        // Champs modifiables par admin/producteur
        if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'producteur') {
            $this->order->status = $data->status ?? $this->order->status;
            // Valider le statut
            $valid_statuses = ['en_attente', 'confirmee', 'en_preparation', 'en_livraison', 'livree', 'annulee'];
            if (isset($data->status) && !in_array($data->status, $valid_statuses)) {
                 ApiResponse::badRequest("Statut de commande invalide: " . $data->status); return;
            }
            $this->order->payment_status = $data->payment_status ?? $this->order->payment_status;
            $this->order->payment_method = $data->payment_method ?? $this->order->payment_method;
            // Admin pourrait aussi modifier delivery_address/notes
            if ($_SESSION['user_role'] === 'admin') {
                 $this->order->delivery_address = $data->delivery_address ?? $this->order->delivery_address;
                 $this->order->delivery_notes = $data->delivery_notes ?? $this->order->delivery_notes;
            }
        }

        // S'assurer que l'objet $this->order a bien les valeurs null pour les champs non modifiés
        // pour que la méthode update du modèle ne les inclue pas par erreur.
        // La méthode update du modèle Order doit être intelligente pour ne mettre à jour que les champs passés.
        // L'implémentation actuelle de $this->order->update() gère cela.

        if ($this->order->update()) {
            $this->order->readOne(false); // Recharger
            ApiResponse::success((array)$this->order, "Commande mise à jour.");
        } else {
            ApiResponse::success((array)$this->order, "Aucune modification ou mise à jour échouée.", 200); // Ou 304
        }
    }


    // DELETE /orders/{id} (Annulation)
    public function cancelOrder($order_id)
    {
        // La permission de base est vérifiée (connecté, rôle)
        // La logique métier (statut, propriétaire) est dans le modèle Order->cancel()
         if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            ApiResponse::unauthorized(); return;
        }

        $this->order->id = $order_id;
        // Le modèle cancel a besoin de user_id et user_role pour sa logique interne de permission.
        // Pour un client, il faut passer son customers.id
        $user_id_for_cancel = $_SESSION['user_id']; // Par défaut users.id (pour admin/producteur)
        if ($_SESSION['user_role'] === 'client') {
            $customerQuery = "SELECT id FROM customers WHERE user_id = :user_id_session LIMIT 1";
            $stmtCust = $this->db->prepare($customerQuery);
            $stmtCust->bindParam(':user_id_session', $_SESSION['user_id']);
            $stmtCust->execute();
            $customer_row = $stmtCust->fetch(PDO::FETCH_ASSOC);
            if ($customer_row) $user_id_for_cancel = $customer_row['id']; // Utiliser customers.id pour client
            else { ApiResponse::forbidden("Profil client non trouvé."); return; }
        }


        $cancel_result = $this->order->cancel($user_id_for_cancel, $_SESSION['user_role']);

        if ($cancel_result === true) {
            ApiResponse::success(null, 'Commande annulée avec succès.');
        } elseif ($cancel_result === "not_owner") {
            ApiResponse::forbidden('Vous n\'êtes pas autorisé à annuler cette commande.');
        } elseif ($cancel_result === "cannot_cancel_status" || $cancel_result === "cannot_cancel_status_admin") {
            ApiResponse::badRequest('La commande ne peut plus être annulée à ce stade.');
        } elseif ($cancel_result === "invalid_role") {
             ApiResponse::forbidden('Rôle non valide pour cette action.');
        } elseif ($cancel_result === "restock_failed") {
            ApiResponse::error('La commande a été annulée, mais une erreur est survenue lors de la réintégration du stock. Veuillez contacter le support.', 500);
        }
        else {
            ApiResponse::error('Impossible d\'annuler la commande (elle n\'existe peut-être pas ou une autre erreur).', 500);
        }
    }

    // GET /orders/stats
    public function getOrderStats()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
            ApiResponse::unauthorized("Accès réservé aux clients connectés.");
            return;
        }

        $customerQuery = "SELECT id FROM customers WHERE user_id = :user_id_session LIMIT 1";
        $stmtCust = $this->db->prepare($customerQuery);
        $stmtCust->bindParam(':user_id_session', $_SESSION['user_id']);
        $stmtCust->execute();
        $customer_row = $stmtCust->fetch(PDO::FETCH_ASSOC);
        if (!$customer_row) {
            ApiResponse::success([
                'total_orders' => 0, 'pending_orders' => 0, 'ongoing_processing' => 0,
                'total_spent' => 0, 'last_order_date' => null
            ]); return;
        }
        $actual_customer_id = $customer_row['id'];

        try {
            $stats = $this->order->getCustomerStats($actual_customer_id);
            if ($stats) {
                 $stats['total_orders'] = (int)($stats['total_orders'] ?? 0);
                 $stats['pending_orders'] = (int)($stats['pending_orders'] ?? 0);
                 $stats['ongoing_processing'] = (int)($stats['ongoing_processing'] ?? 0);
                 $stats['total_spent'] = (float)($stats['total_spent'] ?? 0);
            } else {
                $stats = [
                    'total_orders' => 0, 'pending_orders' => 0, 'ongoing_processing' => 0,
                    'total_spent' => 0, 'last_order_date' => null
                ];
            }
            ApiResponse::success($stats);
        } catch (Exception $e) {
            ApiResponse::error($e->getMessage());
        }
    }
}
?>