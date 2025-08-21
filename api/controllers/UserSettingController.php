<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/UserSetting.php';

class UserSettingController {
    private $db;
    private $userSetting;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->userSetting = new UserSetting($this->db);
    }

    // GET /user-settings (Paramètres de l'utilisateur connecté)
    public function getUserSettings() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $user_id = $_SESSION['user_id'];

        if ($this->userSetting->readByUserIdOrCreate($user_id)) {
            $settings_data = [
                // 'id' => $this->userSetting->id, // Pas forcément utile pour le client
                'user_id' => (int)$this->userSetting->user_id,
                'notification_email' => (bool)$this->userSetting->notification_email,
                'notification_sms' => (bool)$this->userSetting->notification_sms,
                'notification_push' => (bool)$this->userSetting->notification_push,
                'language' => $this->userSetting->language,
                'theme' => $this->userSetting->theme,
                'updated_at' => $this->userSetting->updated_at
            ];
            ApiResponse::success($settings_data);
        } else {
            // Ce cas ne devrait pas arriver si createDefaults fonctionne bien ou si user_id est invalide.
            ApiResponse::error("Impossible de récupérer ou créer les paramètres utilisateur.", 500);
        }
    }

    // PUT /user-settings (Mettre à jour les paramètres de l'utilisateur connecté)
    public function updateUserSettings() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $user_id = $_SESSION['user_id'];

        // Charger les paramètres existants ou les créer si absents
        if (!$this->userSetting->readByUserIdOrCreate($user_id)) {
             ApiResponse::error("Impossible de charger les paramètres utilisateur pour la mise à jour.", 500);
             return;
        }

        $data = json_decode(file_get_contents("php://input"));
        // Pas besoin de vérifier empty($data) car on peut vouloir tout désactiver.

        // Mettre à jour les propriétés de l'objet $this->userSetting
        // Les valeurs par défaut de l'objet sont utilisées si $data ne contient pas la clé.
        // Les booléens doivent être gérés correctement (isset vs property_exists)
        if(property_exists($data, 'notification_email')) $this->userSetting->notification_email = (bool)$data->notification_email;
        if(property_exists($data, 'notification_sms')) $this->userSetting->notification_sms = (bool)$data->notification_sms;
        if(property_exists($data, 'notification_push')) $this->userSetting->notification_push = (bool)$data->notification_push;

        if(isset($data->language)) {
            // Ajouter validation pour les langues supportées si nécessaire
            if (strlen($data->language) > 10) {ApiResponse::badRequest("Code langue trop long."); return;}
            $this->userSetting->language = $data->language;
        }
        if(isset($data->theme)) {
             // Ajouter validation pour les thèmes supportés si nécessaire
            if (strlen($data->theme) > 20) {ApiResponse::badRequest("Nom de thème trop long."); return;}
            $this->userSetting->theme = $data->theme;
        }


        if ($this->userSetting->update()) {
            // Recharger pour avoir les timestamps à jour et confirmer les valeurs
            $this->userSetting->readByUserIdOrCreate($user_id);
             $settings_data = [
                'user_id' => (int)$this->userSetting->user_id,
                'notification_email' => (bool)$this->userSetting->notification_email,
                'notification_sms' => (bool)$this->userSetting->notification_sms,
                'notification_push' => (bool)$this->userSetting->notification_push,
                'language' => $this->userSetting->language,
                'theme' => $this->userSetting->theme,
                'updated_at' => $this->userSetting->updated_at
            ];
            ApiResponse::success($settings_data, "Paramètres mis à jour.");
        } else {
            ApiResponse::error("Impossible de mettre à jour les paramètres ou aucune modification.", 500);
        }
    }

    // Un CRUD complet (POST, GET /id, DELETE /id) pour UserSettings n'est généralement pas exposé.
    // La gestion se fait par utilisateur connecté.
    // La suppression des settings est liée à la suppression du User (via UserSetting->deleteByUserId()).
}
?>
