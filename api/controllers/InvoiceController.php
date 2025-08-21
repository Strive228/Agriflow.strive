<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Invoice.php';
// User model pour vérifier les rôles si nécessaire
// require_once '../models/User.php';

class InvoiceController {
    private $db;
    private $invoice;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->invoice = new Invoice($this->db);
    }

    // Helper pour vérifier les permissions (simplifié)
    // Admin peut tout faire. Client peut voir ses factures.
    private function checkPermission($invoice_id_to_check = null, $action = 'read') {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return "unauthorized";
        }
        $user_role = $_SESSION['user_role'];
        $connected_user_id = $_SESSION['user_id']; // C'est users.id

        if ($user_role === 'admin') return true;

        if ($action === 'create' || $action === 'delete') { // Seul l'admin peut créer/supprimer des factures directement
            return "forbidden_role";
        }

        if ($user_role === 'client') {
            // Pour lire ou mettre à jour (si permis), le client doit être propriétaire de la commande associée.
            // Récupérer le customers.id du client connecté
            $customerQuery = "SELECT id FROM customers WHERE user_id = :user_id_session LIMIT 1";
            $stmtCust = $this->db->prepare($customerQuery);
            $stmtCust->bindParam(':user_id_session', $connected_user_id);
            $stmtCust->execute();
            $customer_row = $stmtCust->fetch(PDO::FETCH_ASSOC);
            if (!$customer_row) return "not_found_profile_for_user";
            $connected_customer_id = $customer_row['id'];

            if ($action === 'read_one' || $action === 'update') {
                if ($invoice_id_to_check === null) return "bad_request";
                $this->invoice->id = $invoice_id_to_check;
                if (!$this->invoice->readOne()) return "not_found"; // Charge $this->invoice->customer_id

                if ($this->invoice->customer_id != $connected_customer_id) return "forbidden_owner";

                // Un client peut-il mettre à jour une facture? Probablement pas directement.
                if ($action === 'update') return "forbidden_client_update";
                return true; // Autorisé à lire sa propre facture
            }
            if ($action === 'read_all') return true; // Client peut lister SES factures (filtrage dans la méthode)
        }
        return "forbidden_generic";
    }

    // POST /invoices (Admin seulement)
    public function createInvoice() {
        $perm = $this->checkPermission(null, 'create');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else ApiResponse::forbidden("Seul un administrateur peut créer des factures.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        if (!isset($data->order_id) || !isset($data->invoice_number) || !isset($data->amount)) {
            ApiResponse::badRequest("Champs order_id, invoice_number, et amount requis.");
            return;
        }

        $this->invoice->order_id = $data->order_id;
        $this->invoice->invoice_number = $data->invoice_number;
        $this->invoice->amount = $data->amount;
        $this->invoice->status = $data->status ?? 'en_attente';
        $this->invoice->payment_date = $data->payment_date ?? null;
        $this->invoice->pdf_url = $data->pdf_url ?? null;

        $create_result = $this->invoice->create();
        if ($create_result === true) {
            $this->invoice->readOne(); // Recharger pour avoir toutes les données
            ApiResponse::created((array)$this->invoice, "Facture créée.");
        } elseif ($create_result === "duplicate_entry") {
            ApiResponse::conflict("Une facture pour cette commande existe déjà ou le numéro de facture est dupliqué.");
        } elseif ($create_result === "order_not_found") {
            ApiResponse::badRequest("La commande (order_id) spécifiée n'existe pas.");
        }else {
            ApiResponse::error("Impossible de créer la facture.", 500);
        }
    }

    // GET /invoices (Admin: toutes; Client: les siennes)
    public function getAllInvoices() {
        $perm = $this->checkPermission(null, 'read_all');
         if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else ApiResponse::forbidden("Accès non autorisé à cette liste de factures.");
            return;
        }

        $filters = [];
        if ($_SESSION['user_role'] === 'client') {
            $customerQuery = "SELECT id FROM customers WHERE user_id = :uid LIMIT 1";
            $stmtC = $this->db->prepare($customerQuery);
            $stmtC->bindParam(":uid", $_SESSION['user_id']);
            $stmtC->execute();
            $custRow = $stmtC->fetch(PDO::FETCH_ASSOC);
            if(!$custRow) { ApiResponse::success(["items"=>[], "pagination"=>["totalItems"=>0]]); return;}
            $filters['customer_id'] = $custRow['id'];
        }
        if(isset($_GET['order_id'])) $filters['order_id'] = (int)$_GET['order_id'];
        if(isset($_GET['status'])) $filters['status'] = $_GET['status'];

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

        $stmt = $this->invoice->readAll($filters, $page, $per_page);
        $invoices_arr = ["items" => []];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id'] = (int)$row['id'];
            $row['order_id'] = (int)$row['order_id'];
            $row['amount'] = (float)$row['amount'];
            if(isset($row['customer_id'])) $row['customer_id'] = (int)$row['customer_id'];
            array_push($invoices_arr["items"], $row);
        }
        $total_items = $this->invoice->countAll($filters);
        $invoices_arr["pagination"] = [
            "currentPage" => $page, "itemsPerPage" => $per_page,
            "totalItems" => (int)$total_items, "totalPages" => ceil($total_items / $per_page)
        ];
        ApiResponse::success($invoices_arr);
    }

    // GET /invoices/{id} (Admin ou Client propriétaire)
    public function getInvoiceById($invoice_id) {
        $perm = $this->checkPermission((int)$invoice_id, 'read_one');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found_profile_for_user") ApiResponse::notFound("Profil client non trouvé pour l'utilisateur en session.");
            else if($perm === "not_found") ApiResponse::notFound("Facture non trouvée.");
            else if($perm === "forbidden_owner") ApiResponse::forbidden("Vous ne pouvez voir que vos propres factures.");
            else ApiResponse::forbidden("Accès non autorisé à cette facture.");
            return;
        }
        // $this->invoice est chargé par checkPermission
        ApiResponse::success((array)$this->invoice);
    }

    // PUT /invoices/{id} (Admin seulement pour l'instant)
    public function updateInvoice($invoice_id) {
        $perm = $this->checkPermission((int)$invoice_id, 'update');
         if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else if($perm === "not_found") ApiResponse::notFound("Facture non trouvée.");
            else if($perm === "forbidden_client_update") ApiResponse::forbidden("Les clients ne peuvent pas modifier les factures.");
            else ApiResponse::forbidden("Seul un administrateur peut modifier une facture.");
            return;
        }

        $data = json_decode(file_get_contents("php://input"));
        if (empty($data)) {
            ApiResponse::badRequest("Aucune donnée fournie."); return;
        }

        // $this->invoice est chargé par checkPermission->readOne()
        // On utilise la présence des clés dans $data pour savoir quoi mettre à jour.
        // Pour que le modèle mette à jour une propriété à NULL, le contrôleur doit explicitement
        // setter la propriété de l'objet $this->invoice à null AVANT d'appeler $this->invoice->update().

        $update_needed = false; // Pour suivre si une modification est demandée

        if(isset($data->status)) {
            if (!in_array($data->status, ['en_attente', 'payee', 'annulee'])) {
                 ApiResponse::badRequest("Statut de facture invalide."); return;
            }
            $this->invoice->status = $data->status;
            $update_needed = true;
        }
        if(property_exists($data, 'payment_date')) { // Permet de setter à null
            $this->invoice->payment_date = $data->payment_date;
            $update_needed = true;
        }
        if(property_exists($data, 'pdf_url')) { // Permet de setter à null
            $this->invoice->pdf_url = $data->pdf_url;
            $update_needed = true;
        }

        if(!$update_needed){
             ApiResponse::badRequest("Aucun champ modifiable fourni ou reconnu pour la mise à jour."); return;
        }

        if ($this->invoice->update()) {
            $this->invoice->readOne(); // Recharger
            ApiResponse::success((array)$this->invoice, "Facture mise à jour.");
        } else {
            // Si rowCount() est 0, cela peut signifier que les données étaient identiques.
            $this->invoice->readOne(); // Recharger pour renvoyer l'état actuel
            ApiResponse::success((array)$this->invoice, "Aucune modification effective ou mise à jour échouée.", 200);
        }
    }

    // DELETE /invoices/{id} (Admin seulement)
    public function deleteInvoice($invoice_id) {
        $perm = $this->checkPermission((int)$invoice_id, 'delete');
        if ($perm !== true) {
            if($perm === "unauthorized") ApiResponse::unauthorized();
            else ApiResponse::forbidden("Seul un administrateur peut supprimer une facture.");
            return;
        }
        // $this->invoice est chargé par checkPermission->readOne()
        $delete_result = $this->invoice->delete();
        if ($delete_result === true) {
            ApiResponse::success(null, "Facture supprimée.", 204);
        } elseif ($delete_result === "delete_not_allowed_status") {
            ApiResponse::forbidden("Suppression non autorisée pour le statut actuel de la facture.");
        }
        else {
            ApiResponse::error("Impossible de supprimer la facture (peut-être déjà supprimée ou introuvable).", 500);
        }
    }
}
?>
