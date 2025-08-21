<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Delivery.php';
// Il serait bien d'avoir un modèle User pour vérifier les rôles. Pour l'instant, on se base sur la session.
// require_once '../models/User.php';

class DeliveryController
{
    private $db;
    private $delivery;
    // private $user; // Pour la gestion des rôles

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->delivery = new Delivery($this->db);
        // $this->user = new User($this->db); // Supposant qu'un modèle User existe
    }

    private function getConnectedCustomerId() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
            return null;
        }
        $customerQuery = "SELECT id FROM customers WHERE user_id = :user_id_session LIMIT 1";
        $stmtCust = $this->db->prepare($customerQuery);
        $stmtCust->bindParam(':user_id_session', $_SESSION['user_id']);
        $stmtCust->execute();
        $customer_row = $stmtCust->fetch(PDO::FETCH_ASSOC);
        return $customer_row ? (int)$customer_row['id'] : null;
    }

    // POST /deliveries
    public function createDelivery() {
        if (!isset($_SESSION['user_id'])) {
            // ApiResponse::unauthorized est une méthode statique, donc pas besoin de echo.
            ApiResponse::unauthorized("Accès non autorisé. Veuillez vous connecter.");
            return;
        }
        // Idéalement, vérifier le rôle de l'utilisateur ici (ex: producteur ou admin)
        // $this->user->id = $_SESSION['user_id'];
        // $this->user->readOne(); // Charger les infos de l'utilisateur, y compris le rôle
        // if ($this->user->role !== 'producteur' && $this->user->role !== 'admin') {
        //     echo ApiResponse::forbidden("Vous n'avez pas les droits pour créer une livraison.");
        //     return;
        // }

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->order_id)) {
            echo ApiResponse::badRequest("L'ID de commande (order_id) est requis.");
            return;
        }

        $this->delivery->order_id = $data->order_id;
        $this->delivery->status = $data->status ?? 'en_attente';
        $this->delivery->tracking_number = $data->tracking_number ?? null;
        $this->delivery->estimated_delivery_date = $data->estimated_delivery_date ?? null;
        $this->delivery->actual_delivery_date = $data->actual_delivery_date ?? null;
        $this->delivery->delivery_person_name = $data->delivery_person_name ?? null;
        $this->delivery->delivery_person_phone = $data->delivery_person_phone ?? null;
        $this->delivery->delivery_notes = $data->delivery_notes ?? null;

        try {
            if ($this->delivery->create()) {
                $created_delivery_data = [
                    'id' => $this->delivery->id,
                    'order_id' => $this->delivery->order_id,
                    'status' => $this->delivery->status,
                    'tracking_number' => $this->delivery->tracking_number,
                    'estimated_delivery_date' => $this->delivery->estimated_delivery_date,
                    'actual_delivery_date' => $this->delivery->actual_delivery_date,
                    'delivery_person_name' => $this->delivery->delivery_person_name,
                    'delivery_person_phone' => $this->delivery->delivery_person_phone,
                    'delivery_notes' => $this->delivery->delivery_notes
                ];
                echo ApiResponse::created($created_delivery_data, "Livraison créée avec succès.");
            } else {
                echo ApiResponse::error("Impossible de créer la livraison. Vérifiez que la commande (order_id) existe.", 500);
            }
        } catch (Exception $e) {
            echo ApiResponse::error("Erreur lors de la création de la livraison: " . $e->getMessage(), 500);
        }
    }

    // GET /deliveries/{id}
    // Modifié pour permettre aux admins/producteurs de voir, et aux clients de voir leurs propres livraisons.
    public function getDeliveryDetails($delivery_id)
    {
        if (!isset($_SESSION['user_id'])) {
            echo ApiResponse::unauthorized();
            return;
        }

        try {
            $this->delivery->id = $delivery_id;
            $delivery_data_row = $this->delivery->readOne(); // readOne retourne maintenant le $row

            if ($delivery_data_row) {
                // Vérification des droits:
                // Supposons qu'on ait un moyen de vérifier le rôle de l'utilisateur connecté
                // Pour l'instant, si l'utilisateur connecté est le client associé à la commande de cette livraison, il peut voir.
                // Un admin ou le producteur concerné devrait aussi pouvoir voir.
                // Cette logique de droits doit être affinée avec un système de rôles complet.

                // $current_user_id = $_SESSION['user_id'];
                // $is_customer_of_this_delivery = ($delivery_data_row['customer_id'] == $current_user_id);
                // Pour simplifier pour l'instant, on autorise si connecté et la livraison existe.
                // Une vraie appli aurait : if ($is_customer_of_this_delivery || $currentUser->isAdmin() || $currentUser->isProducerOfOrder($delivery_data_row['order_id']))

                $delivery_output_data = [
                    'id' => (int)$this->delivery->id, // ou (int)$delivery_data_row['id']
                    'order_id' => (int)$delivery_data_row['order_id'],
                    'status' => $delivery_data_row['status'],
                    'tracking_number' => $delivery_data_row['tracking_number'],
                    'estimated_delivery_date' => $delivery_data_row['estimated_delivery_date'],
                    'actual_delivery_date' => $delivery_data_row['actual_delivery_date'],
                    'delivery_person_name' => $delivery_data_row['delivery_person_name'],
                    'delivery_person_phone' => $delivery_data_row['delivery_person_phone'],
                    'delivery_notes' => $delivery_data_row['delivery_notes'],
                    'delivery_address' => $delivery_data_row['delivery_address'], // Adresse de la commande
                    'customer_id' => (int)$delivery_data_row['customer_id'], // ID du client de la commande
                    'created_at' => $delivery_data_row['created_at'],
                    'updated_at' => $delivery_data_row['updated_at']
                ];
                echo ApiResponse::success($delivery_output_data);
            } else {
                echo ApiResponse::notFound('Livraison non trouvée');
            }
        } catch (Exception $e) {
            echo ApiResponse::error($e->getMessage());
        }
    }

    // GET /deliveries (Liste les livraisons pour client, producteur ou admin)
    public function getAllOrCustomerDeliveries() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            ApiResponse::unauthorized(); return;
        }

        $user_role = $_SESSION['user_role'];
        $connected_customer_profile_id = $this->getConnectedCustomerId(); // Peut être null si pas client
        $connected_producer_profile_id = $_SESSION['producer_profile_id'] ?? null;

        $filters = [];

        if ($user_role === 'client') {
            if ($connected_customer_profile_id) {
                $filters['customer_id'] = $connected_customer_profile_id;
            } else {
                 ApiResponse::success(["items" => [], "pagination" => ["currentPage" => 1, "itemsPerPage" => 10, "totalItems" => 0, "totalPages" => 0]], "Profil client non trouvé.");
                 return;
            }
        } elseif ($user_role === 'producteur') {
            $producer_id_to_filter = null;
            if (isset($_GET['producer_id'])) {
                if ($_GET['producer_id'] == $connected_producer_profile_id) {
                    $producer_id_to_filter = (int)$_GET['producer_id'];
                } else {
                    ApiResponse::forbidden("Vous ne pouvez consulter que les livraisons liées à vos produits.");
                    return;
                }
            } else {
                if ($connected_producer_profile_id) {
                    $producer_id_to_filter = $connected_producer_profile_id;
                } else {
                    ApiResponse::error("ID Producteur non trouvé pour filtrer les livraisons.", 400);
                    return;
                }
            }
            $filters['producer_id'] = $producer_id_to_filter;
        } elseif ($user_role === 'admin') {
            if (isset($_GET['customer_id'])) {
                $filters['customer_id'] = (int)$_GET['customer_id'];
            }
            if (isset($_GET['producer_id'])) {
                $filters['producer_id'] = (int)$_GET['producer_id'];
            }
        } else {
            ApiResponse::forbidden("Accès non autorisé pour lister les livraisons.");
            return;
        }

        if(isset($_GET['status'])) $filters['status'] = $_GET['status'];

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        try {
            $stmt = $this->delivery->readAll($filters, $page, $per_page);
            $deliveries_arr = ["items" => []];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $delivery_item = [
                    'id' => (int)$row['id'],
                    'order_id' => (int)$row['order_id'],
                    'customer_id' => (int)$row['customer_id'], // ID de la table customers
                    'customer_email' => $row['customer_email'] ?? null,
                    'status' => $row['status'],
                    'tracking_number' => $row['tracking_number'],
                    'estimated_delivery_date' => $row['estimated_delivery_date'],
                    'actual_delivery_date' => $row['actual_delivery_date'],
                    'delivery_person_name' => $row['delivery_person_name'],
                    'delivery_person_phone' => $row['delivery_person_phone'],
                    'order_delivery_address' => $row['order_delivery_address'],
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
                array_push($deliveries_arr["items"], $delivery_item);
            }
            $total_items = $this->delivery->countAll($filters);
            $deliveries_arr["pagination"] = [
                "currentPage" => $page, "itemsPerPage" => $per_page,
                "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page)
            ];
            ApiResponse::success($deliveries_arr);
        } catch (Exception $e) {
            ApiResponse::error($e->getMessage());
        }
    }


    // PUT /deliveries/{id}
    public function updateDelivery($delivery_id) {
        if (!isset($_SESSION['user_id'])) {
            echo ApiResponse::unauthorized("Accès non autorisé.");
            return;
        }
        // TODO: Vérifier les droits (admin, ou producteur associé à la commande/livraison)

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data)) {
            echo ApiResponse::badRequest("Aucune donnée fournie pour la mise à jour.");
            return;
        }

        // Vérifier si la livraison existe avant de tenter la mise à jour
        $this->delivery->id = $delivery_id;
        if (!$this->delivery->readOne()) {
            echo ApiResponse::notFound("Livraison non trouvée avec l'ID $delivery_id.");
            return;
        }

        // Assigner les valeurs à mettre à jour. Utiliser les valeurs existantes si non fournies.
        $this->delivery->order_id = $data->order_id ?? $this->delivery->order_id;
        $this->delivery->status = $data->status ?? $this->delivery->status;
        $this->delivery->tracking_number = $data->tracking_number ?? $this->delivery->tracking_number;
        $this->delivery->estimated_delivery_date = $data->estimated_delivery_date ?? $this->delivery->estimated_delivery_date;
        $this->delivery->actual_delivery_date = $data->actual_delivery_date ?? $this->delivery->actual_delivery_date;
        $this->delivery->delivery_person_name = $data->delivery_person_name ?? $this->delivery->delivery_person_name;
        $this->delivery->delivery_person_phone = $data->delivery_person_phone ?? $this->delivery->delivery_person_phone;
        $this->delivery->delivery_notes = $data->delivery_notes ?? $this->delivery->delivery_notes;


        try {
            if ($this->delivery->update()) {
                // Récupérer les données mises à jour pour les retourner
                $this->delivery->readOne(); // Recharge les données depuis la BDD
                 $updated_delivery_data = [
                    'id' => (int)$this->delivery->id,
                    'order_id' => (int)$this->delivery->order_id,
                    'status' => $this->delivery->status,
                    'tracking_number' => $this->delivery->tracking_number,
                    'estimated_delivery_date' => $this->delivery->estimated_delivery_date,
                    'actual_delivery_date' => $this->delivery->actual_delivery_date,
                    'delivery_person_name' => $this->delivery->delivery_person_name,
                    'delivery_person_phone' => $this->delivery->delivery_person_phone,
                    'delivery_notes' => $this->delivery->delivery_notes,
                    'updated_at' => $this->delivery->updated_at
                ];
                echo ApiResponse::success($updated_delivery_data, "Livraison mise à jour avec succès.");
            } else {
                echo ApiResponse::error("Impossible de mettre à jour la livraison.", 500);
            }
        } catch (Exception $e) {
            echo ApiResponse::error("Erreur lors de la mise à jour de la livraison: " . $e->getMessage(), 500);
        }
    }

    // DELETE /deliveries/{id}
    public function deleteDelivery($delivery_id) {
        if (!isset($_SESSION['user_id'])) {
            echo ApiResponse::unauthorized("Accès non autorisé.");
            return;
        }
        // TODO: Vérifier les droits (admin, ou producteur associé)

        $this->delivery->id = $delivery_id;

        // Optionnel: vérifier si la livraison existe avant de tenter de la supprimer
        if (!$this->delivery->readOne()) {
             echo ApiResponse::notFound("Livraison non trouvée avec l'ID $delivery_id.");
             return;
        }

        try {
            if ($this->delivery->delete()) {
                echo ApiResponse::success(null, "Livraison supprimée avec succès.");
            } else {
                // Cela peut arriver si l'ID n'existe pas, déjà couvert par la vérification readOne,
                // ou si la suppression échoue pour une autre raison (contrainte FK non ON DELETE CASCADE par ex.)
                echo ApiResponse::error("Impossible de supprimer la livraison. Elle a peut-être déjà été supprimée ou une erreur s'est produite.", 500);
            }
        } catch (Exception $e) {
            echo ApiResponse::error("Erreur lors de la suppression de la livraison: " . $e->getMessage(), 500);
        }
    }


    // Obtenir l'historique des livraisons d'un client (Endpoint original GET /deliveries)
    public function getCustomerDeliveries()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
            ApiResponse::unauthorized("Accès réservé aux clients.");
            return;
        }

        $actual_customer_id = $this->getConnectedCustomerId();
        if (!$actual_customer_id) {
            ApiResponse::notFound("Profil client non trouvé pour l'utilisateur connecté.");
            return;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        try {
            $stmt = $this->delivery->getCustomerDeliveries($actual_customer_id, $page, $per_page);
            $deliveries = [];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $delivery_item = [
                    'id' => (int)$row['id'],
                    'order_id' => (int)$row['order_id'],
                    'status' => $row['status'],
                    'tracking_number' => $row['tracking_number'],
                    'estimated_delivery_date' => $row['estimated_delivery_date'],
                    'actual_delivery_date' => $row['actual_delivery_date'],
                    'delivery_person_name' => $row['delivery_person_name'],
                    'delivery_person_phone' => $row['delivery_person_phone'],
                    'delivery_address' => $row['delivery_address'], // De la commande
                    'order_status' => $row['order_status'], // Statut de la commande
                    'created_at' => $row['created_at']
                ];
                array_push($deliveries, $delivery_item);
            }

            // TODO: Ajouter des informations de pagination à la réponse
            echo ApiResponse::success($deliveries);
        } catch (Exception $e) {
            echo ApiResponse::error($e->getMessage());
        }
    }

    // Obtenir les statistiques de livraison d'un client
    public function getDeliveryStats()
    {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
            ApiResponse::unauthorized("Accès réservé aux clients.");
            return;
        }

        $actual_customer_id = $this->getConnectedCustomerId();
        if (!$actual_customer_id) {
            ApiResponse::notFound("Profil client non trouvé pour l'utilisateur connecté.");
            return;
        }

        try {
            $stats = $this->delivery->getCustomerStats($actual_customer_id);

            if ($stats) {
                if ($stats['avg_delivery_time'] !== null) {
                    $stats['avg_delivery_time'] = round((float)$stats['avg_delivery_time'], 1);
                    $stats['avg_delivery_time_formatted'] = $this->formatDeliveryTime($stats['avg_delivery_time']);
                } else {
                    $stats['avg_delivery_time_formatted'] = "N/A";
                }
                $stats['total_deliveries'] = (int)($stats['total_deliveries'] ?? 0);
                $stats['ongoing_deliveries'] = (int)($stats['ongoing_deliveries'] ?? 0);
                $stats['completed_deliveries'] = (int)($stats['completed_deliveries'] ?? 0);
            } else {
                // Initialiser stats si aucune livraison trouvée pour éviter des erreurs côté client
                 $stats = [
                    'total_deliveries' => 0,
                    'ongoing_deliveries' => 0,
                    'completed_deliveries' => 0,
                    'avg_delivery_time' => null,
                    'avg_delivery_time_formatted' => "N/A"
                ];
            }


            echo ApiResponse::success($stats);
        } catch (Exception $e) {
            echo ApiResponse::error($e->getMessage());
        }
    }

    // Formater le temps de livraison en heures et minutes
    private function formatDeliveryTime($hours)
    {
        if ($hours === null) return "N/A";
        $fullHours = floor($hours);
        $minutes = round(($hours - $fullHours) * 60);

        if ($fullHours > 0 && $minutes > 0) {
            return "$fullHours h $minutes min";
        } elseif ($fullHours > 0) {
            return "$fullHours h";
        } elseif ($minutes > 0) { // Afficher les minutes même si 0 heures
            return "$minutes min";
        } else {
            return "Moins d'1 min"; // ou "0 min" ou ce qui est pertinent
        }
    }

    // Obtenir le statut en temps réel d'une livraison
    public function getDeliveryStatus($delivery_id)
    {
        if (!isset($_SESSION['user_id'])) {
            echo ApiResponse::unauthorized();
            return;
        }

        try {
            $this->delivery->id = $delivery_id;
            // La méthode readOne du modèle ne prend plus customer_id comme condition directe.
            // La vérification des droits doit être faite ici si un client ne doit voir que ses livraisons.
            $delivery_data_row = $this->delivery->readOne();

            if ($delivery_data_row) {
                // Vérification des droits (simplifiée)
                // if ($delivery_data_row['customer_id'] != $_SESSION['user_id'] && !isCurrentUserAdmin()) {
                //     echo ApiResponse::forbidden("Vous n'êtes pas autorisé à voir le statut de cette livraison.");
                //     return;
                // }

                $status_data = [
                    'id' => (int)$delivery_data_row['id'],
                    'order_id' => (int)$delivery_data_row['order_id'],
                    'status' => $delivery_data_row['status'],
                    'estimated_delivery_date' => $delivery_data_row['estimated_delivery_date'],
                    'actual_delivery_date' => $delivery_data_row['actual_delivery_date'],
                    'delivery_person_name' => $delivery_data_row['delivery_person_name'],
                    'delivery_person_phone' => $delivery_data_row['delivery_person_phone'],
                    'tracking_number' => $delivery_data_row['tracking_number']
                ];

                echo ApiResponse::success($status_data);
            } else {
                echo ApiResponse::notFound('Livraison non trouvée ou accès non autorisé.');
            }
        } catch (Exception $e) {
            echo ApiResponse::error($e->getMessage());
        }
    }

    // Nouvelle méthode pour mettre à jour UNIQUEMENT le statut d'une livraison (PATCH /deliveries/{id}/status)
    // Ceci est différent de PUT /deliveries/{id} qui met à jour potentiellement plusieurs champs.
    public function patchDeliveryStatus($delivery_id) {
        if (!isset($_SESSION['user_id'])) {
            echo ApiResponse::unauthorized("Accès non autorisé.");
            return;
        }
        // TODO: Vérifier les droits (admin, ou producteur associé à la commande/livraison)

        $data = json_decode(file_get_contents("php://input"));

        if (empty($data->status)) {
            echo ApiResponse::badRequest("Le nouveau statut est requis.");
            return;
        }

        $this->delivery->id = $delivery_id;
        // Il faut s'assurer que la livraison existe et potentiellement que l'utilisateur a les droits
        // avant d'appeler updateStatus. updateStatus ne retourne que true/false.
        $existing_delivery = $this->delivery->readOne();
        if (!$existing_delivery) {
            echo ApiResponse::notFound("Livraison non trouvée avec l'ID $delivery_id.");
            return;
        }
        // TODO: Vérifier les droits ici sur $existing_delivery['customer_id'] ou producteur associé.

        $this->delivery->status = $data->status;

        try {
            if ($this->delivery->updateStatus()) { // updateStatus s'occupe de mettre à jour updated_at et actual_delivery_date si 'livree'
                // Retourner le statut mis à jour et la date de livraison si applicable
                $response_data = [
                    'id' => (int)$this->delivery->id,
                    'status' => $this->delivery->status,
                    'actual_delivery_date' => $this->delivery->actual_delivery_date // actual_delivery_date est mis à jour dans le modèle
                ];
                echo ApiResponse::success($response_data, "Statut de la livraison mis à jour.");
            } else {
                echo ApiResponse::error("Impossible de mettre à jour le statut de la livraison. Vérifiez que la livraison existe et que le statut est valide.", 500);
            }
        } catch (Exception $e) {
            echo ApiResponse::error("Erreur lors de la mise à jour du statut: " . $e->getMessage(), 500);
        }
    }
}
?>