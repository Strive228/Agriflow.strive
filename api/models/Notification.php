<?php
class Notification {
    private $conn;
    private $table_name = "notifications";

    // Propriétés
    public $id;
    public $user_id; // Destinataire de la notification
    public $type; // ENUM('commande', 'livraison', 'paiement', 'system')
    public $title;
    public $message;
    public $is_read; // BOOLEAN
    public $related_id; // ID de l'entité liée (ex: order_id, delivery_id)
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une notification (généralement par le système)
    public function create() {
        if (empty($this->user_id) || empty($this->type) || empty($this->title) || empty($this->message)) {
            return false;
        }
        $query = "INSERT INTO " . $this->table_name . "
                SET user_id=:user_id, type=:type, title=:title, message=:message,
                    is_read=:is_read, related_id=:related_id";
        $stmt = $this->conn->prepare($query);

        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->title = htmlspecialchars(strip_tags($this->title));
        $this->message = htmlspecialchars(strip_tags($this->message)); // Peut contenir du HTML simple si géré à l'affichage
        $this->is_read = isset($this->is_read) ? (bool)$this->is_read : false;
        $this->related_id = isset($this->related_id) ? htmlspecialchars(strip_tags($this->related_id)) : null;

        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":title", $this->title);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":is_read", $this->is_read, PDO::PARAM_BOOL);
        $stmt->bindParam(":related_id", $this->related_id);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        error_log("Notification::create() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire les notifications d'un utilisateur (avec filtres et pagination)
    public function readUserNotifications($user_id, $filters = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id";

        $params = [':user_id' => $user_id];
        if (isset($filters['is_read'])) {
            $query .= " AND is_read = :is_read";
            $params[':is_read'] = (bool)$filters['is_read'];
        }
        if (isset($filters['type'])) {
            $query .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }
        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset_val"; // :offset est un mot clé réservé parfois

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
             if($key === ':is_read') $stmt->bindParam($key, $val, PDO::PARAM_BOOL);
             else $stmt->bindParam($key, $val);
        }
        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset_val", $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // Compter les notifications d'un utilisateur (avec filtres)
    public function countUserNotifications($user_id, $filters = []) {
        $query = "SELECT COUNT(*) as total_rows FROM " . $this->table_name . " WHERE user_id = :user_id";
        $params = [':user_id' => $user_id];
        if (isset($filters['is_read'])) {
            $query .= " AND is_read = :is_read";
            $params[':is_read'] = (bool)$filters['is_read'];
        }
         if (isset($filters['type'])) {
            $query .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) {
            if($key === ':is_read') $stmt->bindParam($key, $val, PDO::PARAM_BOOL);
            else $stmt->bindParam($key, $val);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'] ?? 0;
    }

    // Lire une notification spécifique (pourrait être utile pour un admin ou pour marquer comme lue)
    public function readOne() {
        if(empty($this->id) || empty($this->user_id)) return false; // Nécessite user_id pour vérifier la propriété
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row){
            // ... populer les propriétés de l'objet ...
            $this->type = $row['type'];
            $this->title = $row['title'];
            $this->message = $row['message'];
            $this->is_read = (bool)$row['is_read'];
            $this->related_id = $row['related_id'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }


    // Marquer une notification comme lue/non lue
    public function markAsRead($is_read_status = true) {
        if (empty($this->id) || empty($this->user_id)) return false; // Nécessite user_id pour s'assurer que seul le propriétaire la modifie

        $query = "UPDATE " . $this->table_name . " SET is_read = :is_read
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $is_read_param = (bool)$is_read_status;

        $stmt->bindParam(":is_read", $is_read_param, PDO::PARAM_BOOL);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if ($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    // Marquer toutes les notifications d'un utilisateur comme lues
    public function markAllAsReadForUser($user_id) {
        if(empty($user_id)) return false;
        $query = "UPDATE " . $this->table_name . " SET is_read = TRUE
                  WHERE user_id = :user_id AND is_read = FALSE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        if ($stmt->execute()) {
            return $stmt->rowCount(); // Retourne le nombre de notifications mises à jour
        }
        return false; // Ou -1 en cas d'erreur
    }


    // Supprimer une notification (par l'utilisateur)
    public function delete() {
        if (empty($this->id) || empty($this->user_id)) return false;
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);
        if ($stmt->execute()) {
            return $stmt->rowCount() > 0;
        }
        return false;
    }

    // Supprimer toutes les notifications (lues) d'un utilisateur (par l'utilisateur)
    public function deleteAllReadForUser($user_id) {
        if(empty($user_id)) return false;
        $query = "DELETE FROM " . $this->table_name . " WHERE user_id = :user_id AND is_read = TRUE";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
         if ($stmt->execute()) {
            return $stmt->rowCount();
        }
        return false;
    }
}
?>
