<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/User.php';
// Inclure Customer et Producer pour créer/supprimer les profils associés si nécessaire
require_once '../models/Customer.php';
require_once '../models/Producer.php';

class UserController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    // Helper pour vérifier si l'utilisateur connecté est admin
    private function isAdmin() {
        return (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin');
    }

    // POST /users (Création par admin)
    public function createUser() {
        if (!$this->isAdmin()) {
            ApiResponse::forbidden("Action réservée aux administrateurs.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->email) || !isset($data->password) || !isset($data->role)) {
            ApiResponse::badRequest("Les champs email, password et role sont requis.");
            return;
        }
        if (strlen($data->password) < 6) { // Exemple de validation simple
            ApiResponse::badRequest("Le mot de passe doit contenir au moins 6 caractères.");
            return;
        }
        if (!in_array($data->role, ['client', 'producteur', 'admin'])) {
            ApiResponse::badRequest("Rôle invalide. Doit être 'client', 'producteur' ou 'admin'.");
            return;
        }

        $this->user->email = $data->email;
        $this->user->password_hash = password_hash($data->password, PASSWORD_ARGON2ID);
        $this->user->phone = $data->phone ?? null;
        $this->user->role = $data->role;
        $this->user->is_active = $data->is_active ?? true;

        $create_result = $this->user->create();

        if ($create_result === true) {
            // Si un utilisateur client ou producteur est créé, créer aussi l'entrée dans la table correspondante
            if ($this->user->role === 'client') {
                $customer = new Customer($this->db);
                $customer->user_id = $this->user->id;
                // $customer->delivery_address = $data->delivery_address ?? null; // Optionnel
                if (method_exists($customer, 'createBasic')) {
                    $customer->createBasic();
                }
            } elseif ($this->user->role === 'producteur') {
                $producer = new Producer($this->db);
                $producer->user_id = $this->user->id;
                // $producer->farm_name = $data->farm_name ?? "Ferme de " . $data->email; // Optionnel
                 if (method_exists($producer, 'createBasic')) {
                    $producer->createBasic();
                 }
            }

            $this->user->readOne(); // Pour charger toutes les données, y compris l'ID généré
            $user_data = [
                'id' => (int)$this->user->id, 'email' => $this->user->email, 'phone' => $this->user->phone,
                'role' => $this->user->role, 'is_active' => (bool)$this->user->is_active,
                'created_at' => $this->user->created_at
            ];
            ApiResponse::created($user_data, "Utilisateur créé avec succès.");
        } elseif ($create_result === "email_exists") {
            ApiResponse::conflict("Un utilisateur avec cet email existe déjà.");
        } else {
            ApiResponse::error("Impossible de créer l'utilisateur.", 500);
        }
    }

    // GET /users (Liste pour admin)
    public function getAllUsers() {
        if (!$this->isAdmin()) {
            ApiResponse::forbidden("Action réservée aux administrateurs.");
            return;
        }

        $filters = [];
        if(isset($_GET['role'])) $filters['role'] = $_GET['role'];
        if(isset($_GET['is_active'])) {
             if ($_GET['is_active'] === 'true') $filters['is_active'] = true;
             elseif ($_GET['is_active'] === 'false') $filters['is_active'] = false;
        }

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        if ($page < 1) $page = 1;
        if ($per_page < 1 || $per_page > 100) $per_page = 10;

        $stmt = $this->user->readAll($filters, $page, $per_page);
        $users_arr = ["items" => []];

        $num = $stmt->rowCount();
        if($num > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                extract($row);
                $user_item = [
                    "id" => (int)$id, "email" => $email, "phone" => $phone, "role" => $role,
                    "is_active" => (bool)$is_active, "created_at" => $created_at,
                    "updated_at" => $updated_at, "last_login" => $last_login
                ];
                array_push($users_arr["items"], $user_item);
            }
        }

        $total_items = $this->user->countAll($filters);
        $users_arr["pagination"] = [
            "currentPage" => $page, "itemsPerPage" => $per_page,
            "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page)
        ];
        ApiResponse::success($users_arr);
    }

    // GET /users/{id} (Admin voit tout, utilisateur voit son propre profil)
    public function getUserById($user_id) {
        $target_user_id = (int)$user_id;
        $connected_user_id = $_SESSION['user_id'] ?? null;

        if (!$this->isAdmin() && $target_user_id !== $connected_user_id) {
            ApiResponse::forbidden("Vous ne pouvez voir que votre propre profil.");
            return;
        }

        $this->user->id = $target_user_id;
        if ($this->user->readOne()) {
            $user_data = [
                'id' => (int)$this->user->id, 'email' => $this->user->email, 'phone' => $this->user->phone,
                'role' => $this->user->role, 'is_active' => (bool)$this->user->is_active,
                'created_at' => $this->user->created_at, 'updated_at' => $this->user->updated_at,
                'last_login' => $this->user->last_login
            ];
            ApiResponse::success($user_data);
        } else {
            ApiResponse::notFound("Utilisateur non trouvé.");
        }
    }

    // GET /users/me (Profil de l'utilisateur connecté)
    public function getCurrentUserProfile() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized("Veuillez vous connecter.");
            return;
        }
        $this->user->id = $_SESSION['user_id'];
        if ($this->user->readOne()) {
             $user_data = [
                'id' => (int)$this->user->id, 'email' => $this->user->email, 'phone' => $this->user->phone,
                'role' => $this->user->role, 'is_active' => (bool)$this->user->is_active,
                'created_at' => $this->user->created_at, 'updated_at' => $this->user->updated_at,
                'last_login' => $this->user->last_login
            ];
            // On pourrait vouloir joindre les infos de Customer/Producer ici.
            ApiResponse::success($user_data);
        } else {
             ApiResponse::notFound("Profil utilisateur non trouvé (session invalide?).");
        }
    }


    // PUT /users/{id} (Admin modifie tout, utilisateur modifie son profil limité)
    public function updateUser($user_id) {
        $target_user_id = (int)$user_id;
        $connected_user_id = $_SESSION['user_id'] ?? null;
        $is_admin = $this->isAdmin();

        if (!$is_admin && $target_user_id !== $connected_user_id) {
            ApiResponse::forbidden("Vous ne pouvez modifier que votre propre profil.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        if (empty($data)) {
            ApiResponse::badRequest("Aucune donnée fournie.");
            return;
        }

        $this->user->id = $target_user_id;
        if (!$this->user->readOne()) { // Charger l'utilisateur existant
            ApiResponse::notFound("Utilisateur non trouvé.");
            return;
        }

        // Mettre à jour les champs autorisés
        $original_role = $this->user->role; // Conserver le rôle original pour la logique de profil
        $this->user->email = $data->email ?? $this->user->email;
        $this->user->phone = $data->phone ?? $this->user->phone;

        if ($is_admin) { // L'admin peut changer le rôle et le statut d'activité
            $this->user->role = $data->role ?? $this->user->role;
             if (isset($data->role) && !in_array($data->role, ['client', 'producteur', 'admin'])) {
                ApiResponse::badRequest("Rôle invalide."); return;
            }
            $this->user->is_active = $data->is_active ?? $this->user->is_active;
        } else {
            if (isset($data->role) && $data->role !== $this->user->role) {
                 ApiResponse::forbidden("Vous ne pouvez pas changer votre rôle."); return;
            }
             if (isset($data->is_active) && (bool)$data->is_active !== $this->user->is_active) {
                 ApiResponse::forbidden("Vous ne pouvez pas changer votre statut d'activité."); return;
            }
        }

        if (isset($data->email) && $data->email !== $this->user->email) {
            $tempUserCheck = new User($this->db);
            if ($tempUserCheck->emailExists($data->email)) {
                ApiResponse::conflict("Cet email est déjà utilisé par un autre compte.");
                return;
            }
        }

        if ($this->user->update()) {
            // Si le rôle a changé, il faut potentiellement ajuster les tables customers/producers
            if ($is_admin && isset($data->role) && $data->role !== $original_role) {
                // Supprimer l'ancien profil si le rôle change (ex: client -> producteur)
                if ($original_role === 'client') {
                    $customer = new Customer($this->db); $customer->user_id = $this->user->id;
                    if(method_exists($customer, 'deleteByUserId')) $customer->deleteByUserId();
                } elseif ($original_role === 'producteur') {
                    $producer = new Producer($this->db); $producer->user_id = $this->user->id;
                    if(method_exists($producer, 'deleteByUserId')) $producer->deleteByUserId();
                }
                // Créer le nouveau profil
                if ($this->user->role === 'client') {
                    $customer = new Customer($this->db); $customer->user_id = $this->user->id;
                    if(method_exists($customer, 'createBasic')) $customer->createBasic();
                } elseif ($this->user->role === 'producteur') {
                    $producer = new Producer($this->db); $producer->user_id = $this->user->id;
                    if(method_exists($producer, 'createBasic')) $producer->createBasic();
                }
            }
            $this->user->readOne(); // Recharger pour avoir updated_at
            $updated_user_data = [
                'id' => (int)$this->user->id, 'email' => $this->user->email, 'phone' => $this->user->phone,
                'role' => $this->user->role, 'is_active' => (bool)$this->user->is_active,
                'updated_at' => $this->user->updated_at
            ];
            ApiResponse::success($updated_user_data, "Profil mis à jour.");
        } else {
            ApiResponse::success($this->user, "Aucune modification détectée ou profil mis à jour.", 200);
        }
    }

    // DELETE /users/{id} (Admin seulement, soft delete)
    public function deleteUser($user_id) {
        if (!$this->isAdmin()) {
            ApiResponse::forbidden("Action réservée aux administrateurs.");
            return;
        }
        $this->user->id = (int)$user_id;
         if (!$this->user->readOne()) {
            ApiResponse::notFound("Utilisateur non trouvé.");
            return;
        }
        if ($this->user->id === ($_SESSION['user_id'] ?? null)) {
            ApiResponse::forbidden("Un administrateur ne peut pas se désactiver lui-même via cette API.");
            return;
        }

        $original_role = $this->user->role; // Pour la suppression des profils associés

        if ($this->user->delete()) {
            if ($original_role === 'client') {
                $customer = new Customer($this->db); $customer->user_id = $this->user->id;
                if(method_exists($customer, 'deleteByUserId')) $customer->deleteByUserId();
            } elseif ($original_role === 'producteur') {
                $producer = new Producer($this->db); $producer->user_id = $this->user->id;
                 if(method_exists($producer, 'deleteByUserId')) $producer->deleteByUserId();
            }
            ApiResponse::success(null, "Utilisateur désactivé (soft delete).", 204);
        } else {
            ApiResponse::error("Impossible de désactiver l'utilisateur.", 500);
        }
    }

    // PUT /users/{id}/password (Admin change mdp, ou user change son propre mdp)
    public function updateUserPassword($user_id) {
        $target_user_id = (int)$user_id;
        $connected_user_id = $_SESSION['user_id'] ?? null;
        $is_admin = $this->isAdmin();

        if (!$is_admin && $target_user_id !== $connected_user_id) {
            ApiResponse::forbidden("Vous ne pouvez modifier que votre propre mot de passe.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));

        if ($is_admin) {
            if (!isset($data->new_password) || strlen($data->new_password) < 6) {
                ApiResponse::badRequest("Le nouveau mot de passe est requis et doit faire au moins 6 caractères.");
                return;
            }
            $new_password_hash = password_hash($data->new_password, PASSWORD_ARGON2ID);
        } else {
            if (!isset($data->current_password) || !isset($data->new_password) || strlen($data->new_password) < 6) {
                ApiResponse::badRequest("Mot de passe actuel et nouveau mot de passe (min 6 car.) requis.");
                return;
            }
            $this->user->id = $target_user_id;
            if (!$this->user->readOne(true)) {
                ApiResponse::notFound("Utilisateur non trouvé."); return;
            }
            if (!password_verify($data->current_password, $this->user->password_hash)) {
                ApiResponse::unauthorized("Mot de passe actuel incorrect."); return;
            }
            $new_password_hash = password_hash($data->new_password, PASSWORD_ARGON2ID);
        }

        $this->user->id = $target_user_id;
        if ($this->user->updatePassword($new_password_hash)) {
            ApiResponse::success(null, "Mot de passe mis à jour avec succès.");
        } else {
            ApiResponse::error("Impossible de mettre à jour le mot de passe.", 500);
        }
    }

    // POST /users/me/change-password (Utilisateur connecté change son propre mot de passe)
    public function changeMyPassword() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized("Veuillez vous connecter.");
            return;
        }
        $connected_user_id = (int)$_SESSION['user_id'];

        $data = json_decode(file_get_contents("php://input"));

        if (!isset($data->current_password) || !isset($data->new_password) || !isset($data->confirm_new_password)) {
            ApiResponse::badRequest("Mot de passe actuel, nouveau mot de passe et confirmation sont requis.");
            return;
        }

        if (strlen($data->new_password) < 6) {
            ApiResponse::badRequest("Le nouveau mot de passe doit contenir au moins 6 caractères.");
            return;
        }

        if ($data->new_password !== $data->confirm_new_password) {
            ApiResponse::badRequest("Le nouveau mot de passe et sa confirmation ne correspondent pas.");
            return;
        }

        $this->user->id = $connected_user_id;
        if (!$this->user->readOne(true)) { // true pour récupérer le hash du mot de passe
            ApiResponse::notFound("Utilisateur non trouvé (session invalide?).");
            return;
        }

        if (!password_verify($data->current_password, $this->user->password_hash)) {
            ApiResponse::unauthorized("Mot de passe actuel incorrect.");
            return;
        }

        $new_password_hash = password_hash($data->new_password, PASSWORD_ARGON2ID);

        if ($this->user->updatePassword($new_password_hash)) {
            ApiResponse::success(null, "Mot de passe mis à jour avec succès.");
        } else {
            ApiResponse::error("Impossible de mettre à jour le mot de passe.", 500);
        }
    }
}
?>
