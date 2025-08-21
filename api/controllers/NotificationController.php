<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Notification.php';

class NotificationController {
    private $db;
    private $notification;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->notification = new Notification($this->db);
    }

    // GET /notifications (Notifications de l'utilisateur connecté)
    public function getUserNotifications() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $user_id = $_SESSION['user_id'];

        $filters = [];
        if(isset($_GET['is_read'])) {
            $filters['is_read'] = filter_var($_GET['is_read'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filters['is_read'] === null) unset($filters['is_read']); // Si pas un booléen valide, ne pas filtrer
        }
        if(isset($_GET['type'])) $filters['type'] = $_GET['type'];

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20; // Un peu plus par défaut pour les notifs

        $stmt = $this->notification->readUserNotifications($user_id, $filters, $page, $per_page);
        $notifications_arr = ["items" => []];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['is_read'] = (bool)$row['is_read'];
            if(isset($row['related_id'])) $row['related_id'] = (int)$row['related_id'];
            array_push($notifications_arr["items"], $row);
        }
        $total_items = $this->notification->countUserNotifications($user_id, $filters);
        $notifications_arr["pagination"] = [
            "currentPage" => $page, "itemsPerPage" => $per_page,
            "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page),
            "unreadCount" => $this->notification->countUserNotifications($user_id, ['is_read' => false]) // Compte non lu total
        ];
        ApiResponse::success($notifications_arr);
    }

    // PATCH /notifications/{id}/read (Marquer une notif comme lue)
    public function markNotificationAsRead($notification_id) {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $this->notification->id = (int)$notification_id;
        $this->notification->user_id = $_SESSION['user_id']; // Important pour la portée

        if ($this->notification->markAsRead(true)) {
            ApiResponse::success(null, "Notification marquée comme lue.");
        } else {
            ApiResponse::notFoundOrForbidden("Notification non trouvée ou non modifiable.");
        }
    }

    // PATCH /notifications/{id}/unread (Marquer une notif comme non lue)
    public function markNotificationAsUnread($notification_id) {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $this->notification->id = (int)$notification_id;
        $this->notification->user_id = $_SESSION['user_id'];

        if ($this->notification->markAsRead(false)) {
            ApiResponse::success(null, "Notification marquée comme non lue.");
        } else {
            ApiResponse::notFoundOrForbidden("Notification non trouvée ou non modifiable.");
        }
    }

    // POST /notifications/mark-all-read (Marquer toutes les notifs comme lues pour l'utilisateur connecté)
    public function markAllNotificationsAsRead() {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $user_id = $_SESSION['user_id'];
        $updated_count = $this->notification->markAllAsReadForUser($user_id);
        if ($updated_count !== false) {
            ApiResponse::success(['updated_count' => $updated_count], "$updated_count notifications marquées comme lues.");
        } else {
            ApiResponse::error("Erreur lors du marquage des notifications.");
        }
    }

    // DELETE /notifications/{id} (Supprimer une notification spécifique)
    public function deleteNotification($notification_id) {
        if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $this->notification->id = (int)$notification_id;
        $this->notification->user_id = $_SESSION['user_id']; // Assure que seul le propriétaire peut supprimer

        if ($this->notification->delete()) {
            ApiResponse::success(null, "Notification supprimée.", 204);
        } else {
            ApiResponse::notFoundOrForbidden("Notification non trouvée ou suppression non autorisée.");
        }
    }

    // DELETE /notifications/all-read (Supprimer toutes les notifications LUES de l'utilisateur connecté)
    public function deleteAllReadNotifications() {
         if (!isset($_SESSION['user_id'])) {
            ApiResponse::unauthorized(); return;
        }
        $user_id = $_SESSION['user_id'];
        $deleted_count = $this->notification->deleteAllReadForUser($user_id);
        if ($deleted_count !== false) {
             ApiResponse::success(['deleted_count' => $deleted_count], "$deleted_count notifications lues supprimées.");
        } else {
            ApiResponse::error("Erreur lors de la suppression des notifications lues.");
        }
    }

    // La création de notifications (POST /notifications) n'est généralement pas exposée directement à l'utilisateur final.
    // Elles sont créées par le système en réponse à des événements (nouvelle commande, changement de statut, etc.).
    // Si un endpoint de création admin est nécessaire, il faudrait l'ajouter ici avec les permissions admin.
}
?>
