<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Producer.php';
require_once '../models/User.php'; // Pour vérifier le rôle de l'utilisateur connecté

class ProducerController {
    private $db;
    private $producer;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->producer = new Producer($this->db);
    }

    // Helper pour vérifier les permissions
    // Seul un admin peut créer un profil producteur directement via /producers.
    // Un producteur modifie son propre profil.
    // Un admin peut modifier/supprimer n'importe quel profil producteur.
    private function checkPermission($producer_id_to_check = null, $action = 'read') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return "unauthorized";
        }

        $user_role = $_SESSION['user_role'];
        $connected_user_id = $_SESSION['user_id'];

        if ($user_role === 'admin') {
            return true; // L'admin a tous les droits
        }

        if ($action === 'create' && $user_role !== 'admin') {
            return "forbidden_role";
        }

        if ($user_role === 'producteur') {
            if ($action === 'update' || $action === 'delete') {
                if ($producer_id_to_check === null) return "bad_request";

                $this->producer->id = $producer_id_to_check;
                if (!$this->producer->readOne()) {
                    return "not_found";
                }
                if ($this->producer->user_id != $connected_user_id) {
                    return "forbidden_owner";
                }
                return true;
            }
            if ($action === 'read_one' && $producer_id_to_check !== null) {
                 $this->producer->id = $producer_id_to_check;
                 if (!$this->producer->readOne()) return "not_found";
                 return ($this->producer->user_id == $connected_user_id);
            }
            if ($action === 'read_all') return true;

            return "forbidden_generic";
        }

        if (($action === 'read_all' || $action === 'read_one') && ($user_role === 'client' || !isset($_SESSION['user_id']))) { // Aussi pour non connectés
            return true;
        }

        return "forbidden_role_action";
    }


    // POST /producers (Création par Admin uniquement pour un user_id existant avec rôle producteur)
    public function createProducerProfile() {
        $perm = $this->checkPermission(null, 'create');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else ApiResponse::forbidden("Seul un administrateur peut créer un profil producteur via cette route.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->user_id) || !isset($data->farm_name)) {
            ApiResponse::badRequest("Les champs user_id et farm_name sont requis.");
            return;
        }

        $this->producer->user_id = $data->user_id;
        $this->producer->farm_name = $data->farm_name;
        $this->producer->siret = $data->siret ?? null;
        $this->producer->experience_years = $data->experience_years ?? null;
        $this->producer->farm_type = $data->farm_type ?? null;
        $this->producer->surface_hectares = $data->surface_hectares ?? null;
        $this->producer->farm_address = $data->farm_address ?? null;
        $this->producer->certifications = $data->certifications ?? null;
        $this->producer->delivery_availability = $data->delivery_availability ?? null;
        $this->producer->farm_description = $data->farm_description ?? null;
        $this->producer->farm_photo_url = $data->farm_photo_url ?? null;

        $create_result = $this->producer->create();

        if ($create_result === true) {
            $this->producer->readOne();
            ApiResponse::created((array)$this->producer, "Profil producteur créé avec succès.");
        } elseif ($create_result === "producer_exists_for_user") {
            ApiResponse::conflict("Un profil producteur existe déjà pour cet utilisateur (user_id).");
        } elseif ($create_result === "invalid_user_for_producer") {
            ApiResponse::badRequest("L'user_id fourni n'existe pas ou n'a pas le rôle 'producteur'.");
        }else {
            ApiResponse::error("Impossible de créer le profil producteur.", 500);
        }
    }

    // GET /producers (Liste publique)
    public function getAllProducerProfiles() {
        $perm = $this->checkPermission(null, 'read_all');
         if ($perm !== true) {
             if($perm === "unauthorized") ApiResponse::unauthorized();
             else ApiResponse::forbidden("Action non autorisée."); // Devrait être public, donc ceci est une sauvegarde
             return;
         }

        $filters = [];
        if(isset($_GET['farm_type'])) $filters['farm_type'] = $_GET['farm_type'];

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
         if ($page < 1) $page = 1;
        if ($per_page < 1 || $per_page > 100) $per_page = 10;

        $stmt = $this->producer->readAll($filters, $page, $per_page);
        $producers_arr = ["items" => []];

        $num = $stmt->rowCount();
        if($num > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $row['id'] = (int)$row['id'];
                $row['user_id'] = (int)$row['user_id'];
                if (isset($row['experience_years'])) $row['experience_years'] = (int)$row['experience_years'];
                if (isset($row['surface_hectares'])) $row['surface_hectares'] = (float)$row['surface_hectares'];
                array_push($producers_arr["items"], $row);
            }
        }

        $total_items = $this->producer->countAll($filters);
        $producers_arr["pagination"] = [
            "currentPage" => $page, "itemsPerPage" => $per_page,
            "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page)
        ];
        ApiResponse::success($producers_arr);
    }

    // GET /producers/{id} (Profil public d'un producteur par son ID producers.id)
    public function getProducerProfileById($producer_id) {
        // Pour un endpoint public, on vérifie surtout si la ressource existe.
        // checkPermission est plus pour les actions d'écriture ou les lectures restreintes.
        // Cependant, on peut l'utiliser pour le "not_found" check.

        $this->producer->id = (int)$producer_id;
        if ($this->producer->readOne()) { // readOne charge les données dans $this->producer
            $producer_data = (array)$this->producer;
            // On pourrait vouloir exclure certains champs pour un profil public vs profil perso/admin
            // mais pour l'instant on retourne tout ce que le modèle charge.
            ApiResponse::success($producer_data);
        } else {
            ApiResponse::notFound("Profil producteur non trouvé.");
        }
    }

    // GET /producers/my-profile (Profil du producteur connecté)
    public function getMyProducerProfile() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'producteur') {
            ApiResponse::unauthorized("Accès réservé aux producteurs connectés.");
            return;
        }
        $this->producer->user_id = $_SESSION['user_id'];
        if ($this->producer->readByUserId()) {
            ApiResponse::success((array)$this->producer);
        } else {
            // Ce cas peut arriver si le User producteur a été créé mais que createBasic pour Producer a échoué
            // ou si le profil a été supprimé d'une autre manière.
            ApiResponse::notFound("Profil producteur non trouvé pour l'utilisateur connecté. Veuillez contacter l'administrateur ou compléter votre profil si une option est disponible.");
        }
    }

    // PUT /producers/{id} ou PUT /producers/my-profile
    public function updateProducerProfile($producer_id_param = null) {
        $producer_id_to_update = null;
        $is_my_profile_route = ($producer_id_param === null); // True si on est sur /my-profile

        if ($is_my_profile_route) {
            if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'producteur') {
                ApiResponse::unauthorized("Accès réservé aux producteurs connectés pour modifier leur profil.");
                return;
            }
            $temp_producer = new Producer($this->db);
            $temp_producer->user_id = $_SESSION['user_id'];
            if (!$temp_producer->readByUserId()) {
                ApiResponse::notFound("Profil producteur non trouvé pour le modifier.");
                return;
            }
            $producer_id_to_update = $temp_producer->id;
        } else {
            $producer_id_to_update = (int)$producer_id_param;
        }

        $perm = $this->checkPermission($producer_id_to_update, 'update');
         if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found") ApiResponse::notFound("Profil producteur non trouvé.");
            else if($perm === "forbidden_owner") ApiResponse::forbidden("Vous n'êtes pas autorisé à modifier ce profil.");
            else if($perm === "bad_request") ApiResponse::badRequest("ID Producteur manquant.");
            else ApiResponse::forbidden("Action non autorisée.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        if (empty($data)) {
            ApiResponse::badRequest("Aucune donnée fournie.");
            return;
        }

        // $this->producer a été chargé par checkPermission si $producer_id_to_check n'était pas null
        // Si on vient de /my-profile, il faut charger $this->producer->id = $producer_id_to_update; et $this->producer->readOne();
        if ($is_my_profile_route || $this->producer->id != $producer_id_to_update) {
            $this->producer->id = $producer_id_to_update;
            $this->producer->readOne(); // S'assurer que l'objet $this->producer est bien celui qu'on modifie
        }

        $this->producer->farm_name = $data->farm_name ?? $this->producer->farm_name;
        $this->producer->siret = $data->siret ?? $this->producer->siret;
        $this->producer->experience_years = $data->experience_years ?? $this->producer->experience_years;
        $this->producer->farm_type = $data->farm_type ?? $this->producer->farm_type;
        $this->producer->surface_hectares = $data->surface_hectares ?? $this->producer->surface_hectares;
        $this->producer->farm_address = $data->farm_address ?? $this->producer->farm_address;
        $this->producer->certifications = $data->certifications ?? $this->producer->certifications;
        $this->producer->delivery_availability = $data->delivery_availability ?? $this->producer->delivery_availability;
        $this->producer->farm_description = $data->farm_description ?? $this->producer->farm_description;
        $this->producer->farm_photo_url = $data->farm_photo_url ?? $this->producer->farm_photo_url;

        if ($this->producer->update()) {
            $this->producer->readOne();
            ApiResponse::success((array)$this->producer, "Profil producteur mis à jour.");
        } else {
             ApiResponse::success((array)$this->producer, "Aucune modification détectée ou profil mis à jour.", 200);
        }
    }

    // DELETE /producers/{id} (Admin seulement)
    public function deleteProducerProfile($producer_id) {
        $perm = $this->checkPermission((int)$producer_id, 'delete');
         if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found") ApiResponse::notFound("Profil producteur non trouvé.");
             else if($perm === "forbidden_owner" || $perm === "forbidden_role" || $perm === "forbidden_generic") ApiResponse::forbidden("Seul un administrateur peut supprimer un profil producteur.");
            else ApiResponse::forbidden("Action non autorisée.");
            return;
        }

        // $this->producer est déjà chargé par checkPermission
        // Le user associé n'est PAS supprimé ici, seulement le profil producteur.
        // Les produits sont supprimés par ON DELETE CASCADE.
        if ($this->producer->delete()) {
            ApiResponse::success(null, "Profil producteur et produits associés supprimés.", 204);
        } else {
            ApiResponse::error("Impossible de supprimer le profil producteur.", 500);
        }
    }
}
?>
