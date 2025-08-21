<?php
require_once '../config/database.php';
require_once '../config/ApiResponse.php';
require_once '../models/Product.php';
require_once '../models/User.php'; // Pour vérifier le rôle et l'ID du producteur
require_once '../models/Producer.php'; // Pour obtenir le producer_id à partir du user_id

class ProductController
{
    private $db;
    private $product;
    private $user; // Pour la gestion des rôles/permissions
    private $producer_model; // Pour lier user_id à producer_id

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->product = new Product($this->db);
        $this->user = new User($this->db); // Assurez-vous que User.php existe et est correct
        $this->producer_model = new Producer($this->db); // Assurez-vous que Producer.php existe
    }

    // Helper pour obtenir le producer_id de l'utilisateur connecté si c'est un producteur
    private function getProducerIdFromSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            return null;
        }
        if ($_SESSION['user_role'] === 'producteur') {
            // On suppose que $_SESSION['user_id'] est l'ID de la table 'users'
            // Il faut trouver le 'producers.id' correspondant
            $producerQuery = "SELECT id FROM producers WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($producerQuery);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $producer_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($producer_row) {
                return $producer_row['id']; // C'est producers.id
            }
        }
        return null;
    }

    // Helper pour vérifier les permissions
    // Pour Update/Delete, on vérifie si l'utilisateur est admin ou le producteur propriétaire du produit.
    private function checkPermission($product_id_to_check = null) {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            // ApiResponse::unauthorized("Authentification requise."); // Sera envoyé par le routeur ou ici
            // http_response_code(401); echo json_encode(["status" => "error", "message" => "Authentification requise."]); exit;
            return false; // Le routeur devrait gérer la réponse non autorisée
        }

        $user_role = $_SESSION['user_role'];

        if ($user_role === 'admin') {
            return true; // L'admin a tous les droits
        }

        if ($user_role === 'producteur') {
            $connected_producer_id = $this->getProducerIdFromSession();
            if (!$connected_producer_id) {
                 // http_response_code(403); echo json_encode(["status" => "error", "message" => "Profil producteur non trouvé."]); exit;
                 return false; // Le routeur devrait gérer la réponse non autorisée
            }
            // Pour la création, il suffit d'être producteur.
            // Pour Update/Delete, il faut être le producteur propriétaire.
            if ($product_id_to_check !== null) { // Cas de Update/Delete
                $this->product->id = $product_id_to_check;
                if (!$this->product->readOne()) {
                    // http_response_code(404); echo json_encode(["status" => "error", "message" => "Produit non trouvé."]); exit;
                    return "not_found";
                }
                if ($this->product->producer_id != $connected_producer_id) {
                    // http_response_code(403); echo json_encode(["status" => "error", "message" => "Action non autorisée sur ce produit."]); exit;
                    return "forbidden";
                }
            }
            return true; // Le producteur est autorisé pour son propre produit ou pour créer
        }

        // Les clients ne peuvent pas gérer les produits directement via ces endpoints CRUD.
        // http_response_code(403); echo json_encode(["status" => "error", "message" => "Permissions insuffisantes."]); exit;
        return "forbidden_role";
    }


    // POST /products
    public function createProduct()
    {
        $permission_check = $this->checkPermission();
        if ($permission_check !== true) {
            // Gérer les cas spécifiques de checkPermission ou laisser le routeur le faire
            if ($permission_check === false) ApiResponse::unauthorized("Authentification requise.");
            else if ($permission_check === "forbidden_role") ApiResponse::forbidden("Permissions insuffisantes pour créer un produit.");
            else ApiResponse::forbidden("Action non autorisée."); // Cas générique
            return;
        }

        $connected_producer_id = null;
        if ($_SESSION['user_role'] === 'producteur') {
            $connected_producer_id = $this->getProducerIdFromSession();
            if (!$connected_producer_id) {
                ApiResponse::error("Impossible de lier l'utilisateur à un profil producteur.", 403);
                return;
            }
        }

        // Gérer les données FormData (POST) et les fichiers (FILES)
        // Les noms des champs POST doivent correspondre à ceux envoyés par le FormData du JS
        // Exemple: 'name', 'price', 'stock_quantity', 'category', 'description', 'unit', 'image' (pour le fichier)
        if (
            !isset($_POST['name']) || !isset($_POST['price']) || !isset($_POST['stock_quantity']) || !isset($_POST['category']) ||
            ($_SESSION['user_role'] === 'admin' && !isset($_POST['producer_id']))
        ) {
            // On pourrait ajouter 'unit' ici si c'est strictement requis à la création
            ApiResponse::badRequest("Les champs obligatoires (nom, prix, quantité, catégorie) sont requis. Le producer_id est requis si créé par un admin.");
            return;
        }

        $this->product->producer_id = ($_SESSION['user_role'] === 'admin') ? (int)$_POST['producer_id'] : $connected_producer_id;
        $this->product->name = htmlspecialchars(strip_tags($_POST['name']));
        $this->product->description = isset($_POST['description']) ? htmlspecialchars(strip_tags($_POST['description'])) : "";
        $this->product->price = (float)$_POST['price'];
        $this->product->unit = isset($_POST['unit']) ? htmlspecialchars(strip_tags($_POST['unit'])) : "pièce"; // Valeur par défaut si non fourni ou rendre obligatoire
        $this->product->category = htmlspecialchars(strip_tags($_POST['category'])); // La catégorie est attendue
        $this->product->stock_quantity = (int)$_POST['stock_quantity'];

        // Pour is_bio et is_available, le formulaire HTML devrait envoyer une valeur (ex: '1' ou 'true') si coché.
        // Si non coché, le champ pourrait ne pas être envoyé, d'où le isset.
        $this->product->is_bio = isset($_POST['is_bio']) ? filter_var($_POST['is_bio'], FILTER_VALIDATE_BOOLEAN) : false;
        $this->product->is_available = isset($_POST['is_available']) ? filter_var($_POST['is_available'], FILTER_VALIDATE_BOOLEAN) : true; // Par défaut disponible
        $this->product->image_url = null; // Sera défini si une image est uploadée

        // Gestion de l'upload d'image
        // Le nom du champ dans FormData doit être 'image' (correspondant à $_FILES['image'])
        if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
            $upload_base_dir = __DIR__ . '/../../uploads/product_images/'; // Chemin absolu pour la création du dossier
            $upload_url_base = 'uploads/product_images/'; // Chemin relatif pour l'URL stockée en BDD

            if (!is_dir($upload_base_dir)) {
                if (!mkdir($upload_base_dir, 0775, true)) {
                    ApiResponse::error("Impossible de créer le répertoire d'upload.", 500);
                    return;
                }
            }

            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($file_extension, $allowed_extensions)) {
                ApiResponse::badRequest("Type de fichier image non autorisé. Uniquement JPG, JPEG, PNG, GIF.");
                return;
            }

            // Vérification du type MIME réel si possible (plus sécurisé)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);
            $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mime_type, $allowed_mime_types)) {
                 ApiResponse::badRequest("Type MIME de fichier image non autorisé.");
                 return;
            }


            $image_filename = uniqid('prod_img_') . '.' . $file_extension;
            $target_file_path = $upload_base_dir . $image_filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file_path)) {
                $this->product->image_url = $upload_url_base . $image_filename;
            } else {
                ApiResponse::error("Erreur lors du déplacement du fichier image uploadé.", 500);
                return;
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
            ApiResponse::badRequest("Erreur lors de l'upload du fichier image (code: " . $_FILES['image']['error'] . ").");
            return;
        }

        try {
            // Vérifier si producer_id existe (surtout si admin le fournit)
            $producerCheckQuery = "SELECT id FROM producers WHERE id = :producer_id";
            $stmtProdCheck = $this->db->prepare($producerCheckQuery);
            $stmtProdCheck->bindParam(':producer_id', $this->product->producer_id, PDO::PARAM_INT);
            $stmtProdCheck->execute();
            if ($stmtProdCheck->rowCount() == 0) {
                ApiResponse::badRequest("Le producer_id fourni n'existe pas.");
                return;
            }

            if ($this->product->create()) {
                $this->product->readOne(); // Pour récupérer created_at, updated_at
                $created_product_data = [
                    'id' => (int)$this->product->id,
                    'producer_id' => (int)$this->product->producer_id,
                    'name' => $this->product->name,
                    'description' => $this->product->description,
                    'price' => (float)$this->product->price,
                    'unit' => $this->product->unit,
                    'stock_quantity' => (int)$this->product->stock_quantity,
                    'image_url' => $this->product->image_url,
                    'is_bio' => (bool)$this->product->is_bio,
                    'is_available' => (bool)$this->product->is_available,
                    'created_at' => $this->product->created_at,
                    'updated_at' => $this->product->updated_at
                ];
                ApiResponse::created($created_product_data, "Produit créé avec succès.");
            } else {
                ApiResponse::error("Impossible de créer le produit.", 500);
            }
        } catch (Exception $e) {
            ApiResponse::error("Erreur lors de la création du produit: " . $e->getMessage(), 500);
        }
    }

    // GET /products
    public function getAllProducts()
    {
        $filters = [];
        if(isset($_GET['producer_id'])) $filters['producer_id'] = (int)$_GET['producer_id'];

        // Gestion de is_available et is_bio comme booléens stricts depuis la query string
        if(isset($_GET['is_available'])) {
            if ($_GET['is_available'] === 'true') $filters['is_available'] = true;
            elseif ($_GET['is_available'] === 'false') $filters['is_available'] = false;
        }
        if(isset($_GET['is_bio'])) {
             if ($_GET['is_bio'] === 'true') $filters['is_bio'] = true;
             elseif ($_GET['is_bio'] === 'false') $filters['is_bio'] = false;
        }


        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        if ($page < 1) $page = 1;
        if ($per_page < 1 || $per_page > 100) $per_page = 10; // Limiter per_page

        try {
            $stmt = $this->product->readAll($filters, $page, $per_page);
            $products_arr = [];
            $products_arr["items"] = [];

            $num = $stmt->rowCount();

            if($num > 0) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    $product_item = [
                        "id" => (int)$id,
                        "producer_id" => (int)$producer_id,
                        "name" => $name,
                        "description" => $description,
                        "price" => (float)$price,
                        "unit" => $unit,
                        "stock_quantity" => (int)$stock_quantity,
                        "image_url" => $image_url,
                        "is_bio" => (bool)$is_bio,
                        "is_available" => (bool)$is_available,
                        "created_at" => $created_at,
                        "updated_at" => $updated_at
                    ];
                    array_push($products_arr["items"], $product_item);
                }
            }

            $total_items = $this->product->countAll($filters);
            $products_arr["pagination"] = [
                "currentPage" => $page,
                "itemsPerPage" => $per_page,
                "totalItems" => (int)$total_items,
                "totalPages" => ceil($total_items / $per_page)
            ];

            ApiResponse::success($products_arr);
        } catch (Exception $e) {
            ApiResponse::error("Erreur lors de la récupération des produits: " . $e->getMessage());
        }
    }

    // GET /products/{id}
    public function getProductById($product_id)
    {
        $this->product->id = (int)$product_id;
        try {
            if ($this->product->readOne()) {
                // Par défaut, un client ne devrait pas voir un produit non disponible directement par son ID
                // à moins que ce ne soit via une commande passée, etc.
                // La logique de `is_available` pour la visibilité directe est importante.
                if (!$this->product->is_available) {
                    // Si l'utilisateur n'est pas authentifié, ou est un client, il ne voit pas le produit non disponible.
                    if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] === 'client') {
                        ApiResponse::notFound("Produit non trouvé ou non disponible.");
                        return;
                    }
                    // Admin et producteur propriétaire peuvent voir même si non disponible.
                    if ($_SESSION['user_role'] === 'producteur') {
                        $connected_producer_id = $this->getProducerIdFromSession();
                        if ($this->product->producer_id != $connected_producer_id) {
                            ApiResponse::notFound("Produit non trouvé ou non disponible."); // Ou forbidden si on veut être plus précis
                            return;
                        }
                    }
                }

                $product_data = [
                    "id" => (int)$this->product->id,
                    "producer_id" => (int)$this->product->producer_id,
                    "name" => $this->product->name,
                    "description" => $this->product->description,
                    "price" => (float)$this->product->price,
                    "unit" => $this->product->unit,
                    "stock_quantity" => (int)$this->product->stock_quantity,
                    "image_url" => $this->product->image_url,
                    "is_bio" => (bool)$this->product->is_bio,
                    "is_available" => (bool)$this->product->is_available,
                    "created_at" => $this->product->created_at,
                    "updated_at" => $this->product->updated_at
                ];
                ApiResponse::success($product_data);
            } else {
                ApiResponse::notFound("Produit non trouvé.");
            }
        } catch (Exception $e) {
            ApiResponse::error("Erreur lors de la récupération du produit: " . $e->getMessage());
        }
    }

    // PUT /products/{id}
    public function updateProduct($product_id)
    {
        $permission_check = $this->checkPermission((int)$product_id);
        if ($permission_check !== true) {
            if($permission_check === "not_found") ApiResponse::notFound("Produit non trouvé pour la mise à jour.");
            else if($permission_check === "forbidden") ApiResponse::forbidden("Action non autorisée sur ce produit.");
            else if ($permission_check === false) ApiResponse::unauthorized("Authentification requise.");
            else ApiResponse::forbidden("Permissions insuffisantes.");
            return;
        }

        // this->product est déjà chargé par readOne() dans checkPermission lors de l'appel à checkPermission()

        $is_form_data = false;
        // On détecte si c'est du FormData en vérifiant $_POST (car $_FILES peut être vide même avec FormData)
        // ou si le Content-Type commence par multipart/form-data
        if ((isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) || !empty($_POST)) {
            $is_form_data = true;
        }

        if ($is_form_data) {
            // Logique pour FormData (similaire à createProduct)
            // Les champs non fournis dans $_POST ne mettront pas à jour les propriétés correspondantes si on utilise ?? $this->product->property
            // Il faut donc vérifier leur existence avant de les assigner.

            if (isset($_POST['name'])) $this->product->name = htmlspecialchars(strip_tags($_POST['name']));
            if (isset($_POST['description'])) $this->product->description = htmlspecialchars(strip_tags($_POST['description']));
            if (isset($_POST['price'])) $this->product->price = (float)$_POST['price'];
            if (isset($_POST['unit'])) $this->product->unit = htmlspecialchars(strip_tags($_POST['unit']));
            if (isset($_POST['category'])) $this->product->category = htmlspecialchars(strip_tags($_POST['category']));
            if (isset($_POST['stock_quantity'])) $this->product->stock_quantity = (int)$_POST['stock_quantity'];
            if (isset($_POST['is_bio'])) $this->product->is_bio = filter_var($_POST['is_bio'], FILTER_VALIDATE_BOOLEAN);
            if (isset($_POST['is_available'])) $this->product->is_available = filter_var($_POST['is_available'], FILTER_VALIDATE_BOOLEAN);

            // Gestion de l'upload d'une nouvelle image
            if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                $upload_base_dir = __DIR__ . '/../../uploads/product_images/';
                $upload_url_base = 'uploads/product_images/'; // Doit correspondre à la structure du site
                if (!is_dir($upload_base_dir)) {
                    if (!mkdir($upload_base_dir, 0775, true)) {
                         ApiResponse::error("Impossible de créer le répertoire d'upload pour la mise à jour.", 500); return;
                    }
                }

                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($file_extension, $allowed_extensions)) {
                    ApiResponse::badRequest("Type de fichier image non autorisé pour la mise à jour (extension)."); return;
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
                $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($mime_type, $allowed_mime_types)) {
                     ApiResponse::badRequest("Type MIME de fichier image non autorisé pour la mise à jour."); return;
                }

                // Supprimer l'ancienne image si elle existe et qu'une nouvelle est fournie
                if ($this->product->image_url) {
                    $old_image_path = __DIR__ . '/../../' . $this->product->image_url;
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }

                $image_filename = uniqid('prod_img_update_') . '.' . $file_extension;
                $target_file_path = $upload_base_dir . $image_filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file_path)) {
                    $this->product->image_url = $upload_url_base . $image_filename;
                } else {
                    ApiResponse::error("Erreur lors du déplacement du fichier image (mise à jour).", 500); return;
                }
            } elseif (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
                 ApiResponse::badRequest("Erreur d'upload de fichier image (mise à jour, code: " . $_FILES['image']['error'] . ")."); return;
            }
            // Si aucune nouvelle image n'est fournie via $_FILES, this->product->image_url conserve son ancienne valeur.

            $producer_id_from_post = $_POST['producer_id'] ?? null;
            if ($_SESSION['user_role'] === 'admin' && isset($producer_id_from_post)) {
                $this->product->producer_id = (int)$producer_id_from_post;
            } elseif ($_SESSION['user_role'] === 'producteur' && isset($producer_id_from_post) && (int)$producer_id_from_post != $this->product->producer_id) {
                ApiResponse::forbidden("Un producteur ne peut pas changer l'appartenance du produit."); return;
            }


        } else { // Gestion des données JSON (envoyées via PUT typiquement)
            $data = json_decode(file_get_contents("php://input"));
            if (empty($data)) {
                ApiResponse::badRequest("Aucune donnée JSON fournie pour la mise à jour."); return;
            }

            if(isset($data->name)) $this->product->name = $data->name;
            if(isset($data->description)) $this->product->description = $data->description;
            if(isset($data->price)) $this->product->price = $data->price;
            if(isset($data->unit)) $this->product->unit = $data->unit;
            if(isset($data->category)) $this->product->category = $data->category;
            if(isset($data->stock_quantity)) $this->product->stock_quantity = $data->stock_quantity;
            // La mise à jour de l'image via JSON se ferait en passant une nouvelle URL.
            // Si on veut permettre de supprimer l'image, on pourrait passer image_url = null.
            if(property_exists($data, 'image_url')) $this->product->image_url = $data->image_url;
            if(isset($data->is_bio)) $this->product->is_bio = (bool)$data->is_bio;
            if(isset($data->is_available)) $this->product->is_available = (bool)$data->is_available;

            if ($_SESSION['user_role'] === 'admin' && isset($data->producer_id)) {
                 $this->product->producer_id = $data->producer_id;
            } elseif ($_SESSION['user_role'] === 'producteur' && isset($data->producer_id) && $data->producer_id != $this->product->producer_id) {
                ApiResponse::forbidden("Un producteur ne peut pas changer l'appartenance du produit."); return;
            }
        }

        // Validation du producer_id si admin l'a changé
        if ($_SESSION['user_role'] === 'admin' &&
            (($is_form_data && isset($_POST['producer_id'])) || (!$is_form_data && isset($data->producer_id))) ) {
            $pid_to_validate = $is_form_data ? (int)$_POST['producer_id'] : (int)$data->producer_id;
            $producerCheckQuery = "SELECT id FROM producers WHERE id = :producer_id";
            $stmtProdCheck = $this->db->prepare($producerCheckQuery);
            $stmtProdCheck->bindParam(':producer_id', $pid_to_validate, PDO::PARAM_INT);
            $stmtProdCheck->execute();
            if ($stmtProdCheck->rowCount() == 0) {
                ApiResponse::badRequest("Le nouveau producer_id fourni n'existe pas."); return;
            }
        }

        try {
            if ($this->product->update()) {
                $this->product->readOne(); // Recharger pour avoir updated_at
                 $updated_product_data = [
                    "id" => (int)$this->product->id,
                    "producer_id" => (int)$this->product->producer_id,
                    "name" => $this->product->name,
                    "description" => $this->product->description,
                    "price" => (float)$this->product->price,
                    "unit" => $this->product->unit,
                    "stock_quantity" => (int)$this->product->stock_quantity,
                    "image_url" => $this->product->image_url,
                    "is_bio" => (bool)$this->product->is_bio,
                    "is_available" => (bool)$this->product->is_available,
                    "created_at" => $this->product->created_at, // Ne change pas
                    "updated_at" => $this->product->updated_at
                ];
                ApiResponse::success($updated_product_data, "Produit mis à jour avec succès.");
            } else {
                // Soit aucune modif, soit erreur non catchée. update() retourne rowCount > 0.
                // On peut renvoyer le produit actuel si aucune modif n'a été faite.
                $current_product_data = [ /* ... idem que $updated_product_data ... */ ];
                 ApiResponse::success($this->product, "Aucune modification détectée ou produit mis à jour.", 200); // Ou 304 si on veut être strict
            }
        } catch (Exception $e) {
            ApiResponse::error("Erreur lors de la mise à jour du produit: " . $e->getMessage(), 500);
        }
    }

    // DELETE /products/{id}
    public function deleteProduct($product_id)
    {
        $permission_check = $this->checkPermission((int)$product_id);
         if ($permission_check !== true) {
            if($permission_check === "not_found") ApiResponse::notFound("Produit non trouvé pour la suppression.");
            else if($permission_check === "forbidden") ApiResponse::forbidden("Action non autorisée sur ce produit.");
            else if ($permission_check === false) ApiResponse::unauthorized("Authentification requise.");
            else ApiResponse::forbidden("Permissions insuffisantes.");
            return;
        }
        // $this->product->id est setté et produit vérifié par checkPermission

        try {
            if ($this->product->delete()) {
                ApiResponse::success(null, "Produit supprimé avec succès.", 204); // 204 No Content
            } else {
                // Devrait être couvert par not_found dans checkPermission si l'ID n'existe pas
                ApiResponse::error("Impossible de supprimer le produit. Il a peut-être déjà été supprimé.", 500);
            }
        } catch (PDOException $e) { // Capturer spécifiquement PDOException pour les contraintes FK
            if ($e->getCode() == '23000') { // Code d'erreur SQL pour violation de contrainte d'intégrité
                 ApiResponse::error("Impossible de supprimer le produit car il est référencé (ex: commandes, favoris).", 409); // 409 Conflict
            } else {
                ApiResponse::error("Erreur base de données lors de la suppression: " . $e->getMessage(), 500);
            }
        }
         catch (Exception $e) {
            ApiResponse::error("Erreur lors de la suppression du produit: " . $e->getMessage(), 500);
        }
    }
}
?>
