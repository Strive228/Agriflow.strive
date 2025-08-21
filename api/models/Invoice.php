<?php
class Invoice {
    private $conn;
    private $table_name = "invoices";

    // Propriétés de l'objet Invoice
    public $id;
    public $order_id;
    public $invoice_number;
    public $amount;
    public $status; // ENUM('en_attente', 'payee', 'annulee')
    public $payment_date;
    public $pdf_url;
    public $created_at;
    public $updated_at;

    // Pour jointure optionnelle
    public $customer_id; // De la commande associée

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une facture
    public function create() {
        if (empty($this->order_id) || empty($this->invoice_number) || !isset($this->amount)) {
            return false; // Champs requis
        }

        // Vérifier l'unicité de order_id et invoice_number
        $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE order_id = :order_id OR invoice_number = :invoice_number";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bindParam(':order_id', $this->order_id);
        $stmtCheck->bindParam(':invoice_number', $this->invoice_number);
        $stmtCheck->execute();
        if ($stmtCheck->rowCount() > 0) {
             // Soit la commande a déjà une facture, soit le numéro de facture est déjà utilisé.
            error_log("Invoice::create() - order_id ou invoice_number déjà existant.");
            return "duplicate_entry";
        }

        // Vérifier si order_id existe dans la table orders
        $orderCheckQuery = "SELECT id FROM orders WHERE id = :order_id_check";
        $stmtOrderCheck = $this->conn->prepare($orderCheckQuery);
        $stmtOrderCheck->bindParam(':order_id_check', $this->order_id);
        $stmtOrderCheck->execute();
        if ($stmtOrderCheck->rowCount() == 0) {
            error_log("Invoice::create() - order_id non trouvé dans la table orders.");
            return "order_not_found";
        }


        $query = "INSERT INTO " . $this->table_name . "
                SET
                    order_id=:order_id, invoice_number=:invoice_number, amount=:amount,
                    status=:status, payment_date=:payment_date, pdf_url=:pdf_url";

        $stmt = $this->conn->prepare($query);

        $this->order_id = htmlspecialchars(strip_tags($this->order_id));
        $this->invoice_number = htmlspecialchars(strip_tags($this->invoice_number));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'en_attente'));
        $this->payment_date = htmlspecialchars(strip_tags($this->payment_date ?? null));
        $this->pdf_url = htmlspecialchars(strip_tags($this->pdf_url ?? null));

        $stmt->bindParam(":order_id", $this->order_id);
        $stmt->bindParam(":invoice_number", $this->invoice_number);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payment_date", $this->payment_date);
        $stmt->bindParam(":pdf_url", $this->pdf_url);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        error_log("Invoice::create() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Lire toutes les factures (avec filtres et pagination)
    public function readAll($filters = [], $page = 1, $per_page = 10) {
        $offset = ($page - 1) * $per_page;
        $query = "SELECT inv.*, o.customer_id
                  FROM " . $this->table_name . " inv
                  LEFT JOIN orders o ON inv.order_id = o.id";

        $where_clauses = [];
        $params = [];

        if (!empty($filters['customer_id'])) { // Filtrer par customer_id (via la commande)
            $where_clauses[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        if (!empty($filters['order_id'])) {
            $where_clauses[] = "inv.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        if (!empty($filters['status'])) {
            $where_clauses[] = "inv.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }
        $query .= " ORDER BY inv.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) { $stmt->bindParam($key, $val); }
        $stmt->bindParam(":limit", $per_page, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt;
    }

    // Compter toutes les factures (avec filtres)
    public function countAll($filters = []) {
        $query = "SELECT COUNT(inv.id) as total_rows
                  FROM " . $this->table_name . " inv
                  LEFT JOIN orders o ON inv.order_id = o.id";
        $where_clauses = [];
        $params = [];
         if (!empty($filters['customer_id'])) {
            $where_clauses[] = "o.customer_id = :customer_id";
            $params[':customer_id'] = $filters['customer_id'];
        }
        if (!empty($filters['order_id'])) {
            $where_clauses[] = "inv.order_id = :order_id";
            $params[':order_id'] = $filters['order_id'];
        }
        if (!empty($filters['status'])) {
            $where_clauses[] = "inv.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (count($where_clauses) > 0) { $query .= " WHERE " . implode(" AND ", $where_clauses); }

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => &$val) { $stmt->bindParam($key, $val); }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total_rows'] ?? 0;
    }

    // Lire une facture par son ID
    public function readOne() {
        $query = "SELECT inv.*, o.customer_id
                  FROM " . $this->table_name . " inv
                  LEFT JOIN orders o ON inv.order_id = o.id
                  WHERE inv.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id = (int)$row['id'];
            $this->order_id = (int)$row['order_id'];
            $this->invoice_number = $row['invoice_number'];
            $this->amount = (float)$row['amount'];
            $this->status = $row['status'];
            $this->payment_date = $row['payment_date'];
            $this->pdf_url = $row['pdf_url'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->customer_id = isset($row['customer_id']) ? (int)$row['customer_id'] : null;
            return true;
        }
        return false;
    }

    // Mettre à jour une facture (principalement status, payment_date, pdf_url)
    public function update() {
        if(empty($this->id)) return false;

        $fields_to_update = [];
        // Utiliser array_key_exists pour permettre de setter explicitement à null
        if(property_exists($this, 'status') && $this->status !== null) $fields_to_update['status'] = $this->status;

        // Pour payment_date et pdf_url, on veut pouvoir les mettre à null.
        // Donc, on vérifie si la propriété a été settée dans l'objet avant de l'ajouter.
        // Cela requiert que le contrôleur sette explicitement la propriété (même à null) si une maj est voulue.
        $object_vars = get_object_vars($this);
        if(array_key_exists('payment_date', $object_vars)) $fields_to_update['payment_date'] = $this->payment_date;
        if(array_key_exists('pdf_url', $object_vars)) $fields_to_update['pdf_url'] = $this->pdf_url;


        if(empty($fields_to_update)) return false;

        $query_set_parts = [];
        foreach(array_keys($fields_to_update) as $field) {
            $query_set_parts[] = $field . " = :" . $field;
        }
        $query_set_string = implode(", ", $query_set_parts);

        $query = "UPDATE " . $this->table_name . "
                  SET " . $query_set_string . ", updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        foreach($fields_to_update as $field_key => $field_value){
             $clean_value = is_null($field_value) ? null : htmlspecialchars(strip_tags($field_value));
             $stmt->bindValue(":" . $field_key, $clean_value);
        }
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()){
            return $stmt->rowCount() > 0;
        }
        error_log("Invoice::update() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }

    // Supprimer une facture (rare, peut-être seulement si 'annulee' et aucune trace financière)
    public function delete() {
        if(empty($this->id)) return false;

        $this->readOne();
        if ($this->status !== 'annulee' && $this->status !== 'en_attente') {
             error_log("Invoice::delete() - Suppression non autorisée pour statut: " . $this->status);
            return "delete_not_allowed_status";
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(':id', $this->id);
        if($stmt->execute()){
            return $stmt->rowCount() > 0;
        }
        error_log("Invoice::delete() - Erreur PDO: " . implode(", ", $stmt->errorInfo()));
        return false;
    }
}
?>
