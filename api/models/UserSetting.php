<?php
class UserSetting {
    private $conn;
    private $table_name = "user_settings";

    // Propriétés
    public $id;
    public $user_id;
    public $notification_email; // BOOLEAN
    public $notification_sms;   // BOOLEAN
    public $notification_push;  // BOOLEAN
    public $language;           // VARCHAR(10)
    public $theme;              // VARCHAR(20)
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Lire les paramètres d'un utilisateur (ou les créer avec des valeurs par défaut si n'existent pas)
    public function readByUserIdOrCreate($user_id_param) {
        $this->user_id = htmlspecialchars(strip_tags($user_id_param));

        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            $this->notification_email = (bool)$row['notification_email'];
            $this->notification_sms = (bool)$row['notification_sms'];
            $this->notification_push = (bool)$row['notification_push'];
            $this->language = $row['language'];
            $this->theme = $row['theme'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        } else {
            // Aucun paramètre trouvé, créer avec les valeurs par défaut
            return $this->createDefaults();
        }
    }

    // Créer des paramètres par défaut pour un nouvel utilisateur ou si non existants
    private function createDefaults() {
        // Vérifier si user_id existe dans la table users
        $userCheckQuery = "SELECT id FROM users WHERE id = :user_id_check";
        $stmtUserCheck = $this->conn->prepare($userCheckQuery);
        $stmtUserCheck->bindParam(':user_id_check', $this->user_id);
        $stmtUserCheck->execute();
        if ($stmtUserCheck->rowCount() == 0) {
            error_log("UserSetting::createDefaults() - user_id non trouvé dans la table users.");
            return false; // Ne peut pas créer de settings pour un user inexistant
        }

        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id = :user_id, notification_email = :def_email, notification_sms = :def_sms,
                      notification_push = :def_push, language = :def_lang, theme = :def_theme";
        $stmt = $this->conn->prepare($query);

        // Valeurs par défaut
        $this->notification_email = true;
        $this->notification_sms = false;
        $this->notification_push = true;
        $this->language = 'fr';
        $this->theme = 'light';

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":def_email", $this->notification_email, PDO::PARAM_BOOL);
        $stmt->bindParam(":def_sms", $this->notification_sms, PDO::PARAM_BOOL);
        $stmt->bindParam(":def_push", $this->notification_push, PDO::PARAM_BOOL);
        $stmt->bindParam(":def_lang", $this->language);
        $stmt->bindParam(":def_theme", $this->theme);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            // Re-lire pour created_at/updated_at
            return $this->readByUserIdOrCreate($this->user_id); // Ceci va re-populer l'objet
        }
        error_log("UserSetting::createDefaults() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }


    // Mettre à jour les paramètres d'un utilisateur
    // L'objet doit être préalablement chargé avec readByUserIdOrCreate
    public function update() {
        if (empty($this->id) || empty($this->user_id)) return false;

        $query = "UPDATE " . $this->table_name . "
                SET
                    notification_email = :notification_email,
                    notification_sms = :notification_sms,
                    notification_push = :notification_push,
                    language = :language,
                    theme = :theme,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);

        // Assurer que les booléens sont bien des booléens pour le bind
        $this->notification_email = (bool)($this->notification_email ?? true);
        $this->notification_sms = (bool)($this->notification_sms ?? false);
        $this->notification_push = (bool)($this->notification_push ?? true);
        $this->language = htmlspecialchars(strip_tags($this->language ?? 'fr'));
        $this->theme = htmlspecialchars(strip_tags($this->theme ?? 'light'));

        $stmt->bindParam(":notification_email", $this->notification_email, PDO::PARAM_BOOL);
        $stmt->bindParam(":notification_sms", $this->notification_sms, PDO::PARAM_BOOL);
        $stmt->bindParam(":notification_push", $this->notification_push, PDO::PARAM_BOOL);
        $stmt->bindParam(":language", $this->language);
        $stmt->bindParam(":theme", $this->theme);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if ($stmt->execute()) {
            return $stmt->rowCount() >= 0; // Peut être 0 si aucune valeur n'a changé, mais c'est un succès.
        }
        error_log("UserSetting::update() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Supprimer les paramètres d'un utilisateur (si l'utilisateur est supprimé)
    // Généralement appelé en interne, pas directement via API CRUD sur UserSettings.
    public function deleteByUserId() {
        if(empty($this->user_id)) return false;
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $this->user_id);
        if($stmt->execute()){
            return $stmt->rowCount() > 0;
        }
        return false;
    }

}
?>
