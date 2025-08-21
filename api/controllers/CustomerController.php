<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Customer.php';
require_once '../models/User.php'; // Pour vérifier le rôle et l'ID du client connecté

class CustomerController {
    private $db;
    private $customer;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->customer = new Customer($this->db);
    }

    // Helper pour vérifier les permissions
    private function checkPermission($customer_id_to_check = null, $action = 'read') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return "unauthorized";
        }
        $user_role = $_SESSION['user_role'];
        $connected_user_id = $_SESSION['user_id'];

        if ($user_role === 'admin') {
            return true; // L'admin a tous les droits sur les profils clients
        }

        if ($action === 'create' && $user_role !== 'admin') {
            return "forbidden_role";
        }

        if ($user_role === 'client') {
            $current_client_profile = new Customer($this->db); // Utiliser une instance temporaire
            $current_client_profile->user_id = $connected_user_id;
            if (!$current_client_profile->readByUserId()) {
                 return "not_found_profile_for_user";
            }
            $connected_customer_id = $current_client_profile->id;

            if ($action === 'read_one' || $action === 'update') {
                if ($customer_id_to_check === null) { // Pour /my-profile
                     $this->customer->id = $connected_customer_id; // Charger le profil du client connecté dans $this->customer
                     if(!$this->customer->readOne()) return "not_found"; // Should not happen if readByUserId worked
                    return true;
                }
                if ($customer_id_to_check != $connected_customer_id) {
                    return "forbidden_owner";
                }
                $this->customer->id = $customer_id_to_check;
                if (!$this->customer->readOne()) return "not_found";
                return true;
            }
            if ($action === 'read_all' || $action === 'delete') {
                return "forbidden_client_action";
            }
            return "forbidden_generic";
        }
        return "forbidden_role_action";
    }

    // POST /customers (Création par Admin uniquement pour un user_id existant avec rôle client)
    public function createCustomerProfile() {
        $perm = $this->checkPermission(null, 'create');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else ApiResponse::forbidden("Seul un administrateur peut créer un profil client via cette route.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->user_id)) {
            ApiResponse::badRequest("Le champ user_id est requis.");
            return;
        }

        $this->customer->user_id = $data->user_id;
        $this->customer->delivery_address = $data->delivery_address ?? null;
        $this->customer->food_preferences = $data->food_preferences ?? 'aucune';

        $create_result = $this->customer->create();

        if ($create_result === true) {
            $this->customer->readOne();
            ApiResponse::created((array)$this->customer, "Profil client créé.");
        } elseif ($create_result === "customer_exists_for_user") {
            ApiResponse::conflict("Un profil client existe déjà pour cet utilisateur (user_id).");
        } elseif ($create_result === "invalid_user_for_customer") {
            ApiResponse::badRequest("L'user_id fourni n'existe pas ou n'a pas le rôle 'client'.");
        } else {
            ApiResponse::error("Impossible de créer le profil client.", 500);
        }
    }

    // GET /customers (Liste pour Admin)
    public function getAllCustomerProfiles() {
        $perm = $this->checkPermission(null, 'read_all');
        if ($perm !== true) {
             if($perm === "unauthorized") ApiResponse::unauthorized();
             else ApiResponse::forbidden("Action réservée aux administrateurs.");
             return;
        }

        $filters = [];
        if(isset($_GET['food_preferences'])) $filters['food_preferences'] = $_GET['food_preferences'];

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        if ($page < 1) $page = 1;
        if ($per_page < 1 || $per_page > 100) $per_page = 10;

        $stmt = $this->customer->readAll($filters, $page, $per_page);
        $customers_arr = ["items" => []];

        $num = $stmt->rowCount();
        if($num > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['id'] = (int)$row['id'];
                $row['user_id'] = (int)$row['user_id'];
                array_push($customers_arr["items"], $row);
            }
        }
        $total_items = $this->customer->countAll($filters);
        $customers_arr["pagination"] = [
            "currentPage" => $page, "itemsPerPage" => $per_page,
            "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page)
        ];
        ApiResponse::success($customers_arr);
    }

    // GET /customers/{id} (Admin ou client propriétaire)
    // GET /customers/my-profile (Client propriétaire)
    public function getCustomerProfile($customer_id_param = null) {
        $is_my_profile_route = ($customer_id_param === null);
        $customer_id_to_read = $is_my_profile_route ? null : (int)$customer_id_param;

        $perm = $this->checkPermission($customer_id_to_read, 'read_one');
         if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found_profile_for_user" && $is_my_profile_route) ApiResponse::notFound("Profil client non trouvé pour l'utilisateur connecté.");
            else if($perm === "not_found") ApiResponse::notFound("Profil client non trouvé.");
            else if($perm === "forbidden_owner") ApiResponse::forbidden("Vous ne pouvez voir que votre propre profil client.");
            else ApiResponse::forbidden("Action non autorisée.");
            return;
        }
        ApiResponse::success((array)$this->customer);
    }

    // PUT /customers/{id} (Admin ou client propriétaire)
    // PUT /customers/my-profile (Client propriétaire)
    public function updateCustomerProfile($customer_id_param = null) {
        $is_my_profile_route = ($customer_id_param === null);
        $customer_id_to_update = null;

        if($is_my_profile_route) {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
                 ApiResponse::unauthorized("Accès réservé aux clients connectés."); return;
            }
            // $this->customer sera chargé par checkPermission(null, 'update')
        } else {
            $customer_id_to_update = (int)$customer_id_param;
        }

        $perm = $this->checkPermission($customer_id_to_update, 'update');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found_profile_for_user" && $is_my_profile_route) ApiResponse::notFound("Profil client non trouvé.");
            else if($perm === "not_found") ApiResponse::notFound("Profil client non trouvé.");
            else if($perm === "forbidden_owner") ApiResponse::forbidden("Vous ne pouvez modifier que votre propre profil.");
            else ApiResponse::forbidden("Action non autorisée pour la mise à jour.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        if (empty($data)) {
            ApiResponse::badRequest("Aucune donnée fournie."); return;
        }

        $this->customer->delivery_address = $data->delivery_address ?? $this->customer->delivery_address;
        $this->customer->food_preferences = $data->food_preferences ?? $this->customer->food_preferences;
        if(isset($data->food_preferences) && !in_array($data->food_preferences, ['bio', 'local', 'aucune', null])) {
            ApiResponse::badRequest("Valeur invalide pour food_preferences."); return;
        }

        if ($this->customer->update()) {
            $this->customer->readOne();
            ApiResponse::success((array)$this->customer, "Profil client mis à jour.");
        } else {
            ApiResponse::success((array)$this->customer, "Aucune modification détectée ou profil mis à jour.", 200);
        }
    }

    // DELETE /customers/{id} (Admin seulement)
    public function deleteCustomerProfile($customer_id) {
        $perm = $this->checkPermission((int)$customer_id, 'delete');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found") ApiResponse::notFound("Profil client non trouvé.");
            else if(in_array($perm, ["forbidden_owner", "forbidden_client_action", "forbidden_role"])) ApiResponse::forbidden("Seul un administrateur peut supprimer un profil client.");
            else ApiResponse::forbidden("Action non autorisée.");
            return;
        }

        if ($this->customer->delete()) {
            ApiResponse::success(null, "Profil client supprimé (et données associées via CASCADE).", 204);
        } else {
            ApiResponse::error("Impossible de supprimer le profil client.", 500);
        }
    }
}
?>
