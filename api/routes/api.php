<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/ApiResponse.php';
require_once '../controllers/OrderController.php';
require_once '../controllers/DeliveryController.php';
require_once '../controllers/FavoriteController.php';
require_once '../controllers/ProductController.php';
require_once '../controllers/UserController.php';
require_once '../controllers/ProducerController.php';
require_once '../controllers/CustomerController.php';
require_once '../controllers/InvoiceController.php';
require_once '../controllers/NotificationController.php';
require_once '../controllers/UserSettingController.php'; // Ajout UserSettingController

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/agriflow/api';
$endpoint = str_replace($base_path, '', $request_uri);
$method = $_SERVER['REQUEST_METHOD'];

$orderController = new OrderController();
$deliveryController = new DeliveryController();
$favoriteController = new FavoriteController();
$productController = new ProductController();
$userController = new UserController();
$producerController = new ProducerController();
$customerController = new CustomerController();
$invoiceController = new InvoiceController();
$notificationController = new NotificationController();
$userSettingController = new UserSettingController(); // Initialisation UserSettingController

try {
    // Routes pour les commandes (/orders)
    if (preg_match('#^/orders$#', $endpoint)) {
        switch ($method) {
            case 'GET': $orderController->getAllOrCustomerOrders(); break; // Nom de méthode mis à jour
            case 'POST': $orderController->createOrder(); break;
            default: ApiResponse::methodNotAllowed(); break;
        }
    } elseif (preg_match('#^/orders/([0-9]+)$#', $endpoint, $matches)) {
        $order_id = (int)$matches[1];
        switch ($method) {
            case 'GET': $orderController->getOrderDetails($order_id); break;
            case 'PUT': $orderController->updateOrder($order_id); break; // Route PUT activée
            case 'DELETE': $orderController->cancelOrder($order_id); break;
            default: ApiResponse::methodNotAllowed(); break;
        }
    } elseif (preg_match('#^/orders/stats$#', $endpoint) && $method === 'GET') {
        // ... (code existant)
    }

    // Routes pour les livraisons (/deliveries)
    elseif (preg_match('#^/deliveries$#', $endpoint)) {
        // ... (code existant)
    } elseif (preg_match('#^/deliveries/([0-9]+)$#', $endpoint, $matches)) {
        // ... (code existant)
    } elseif (preg_match('#^/deliveries/([0-9]+)/status$#', $endpoint, $matches)) {
        // ... (code existant)
    } elseif (preg_match('#^/deliveries/stats$#', $endpoint) && $method === 'GET') {
        // ... (code existant)
    }

    // Routes pour les favoris (/favorites)
    elseif (preg_match('#^/favorites$#', $endpoint)) {
        // ... (code existant)
    } elseif (preg_match('#^/favorites/([0-9]+)$#', $endpoint, $matches)) {
        // ... (code existant)
    } elseif (preg_match('#^/favorites/check$#', $endpoint) && $method === 'POST') {
        // ... (code existant)
    }

    // Routes pour les produits (/products)
    elseif (preg_match('#^/products$#', $endpoint)) {
        switch ($method) {
            case 'GET':
                $productController->getAllProducts();
                break;
            case 'POST':
                $productController->createProduct();
                break;
            default:
                ApiResponse::methodNotAllowed();
                break;
        }
    } elseif (preg_match('#^/products/([0-9]+)$#', $endpoint, $matches)) {
        $product_id = (int)$matches[1];
        switch ($method) {
            case 'GET':
                $productController->getProductById($product_id);
                break;
            case 'POST': // Ajout pour gérer la mise à jour avec FormData (pour l'image)
                $productController->updateProduct($product_id);
                break;
            case 'PUT':
                // updateProduct gère maintenant JSON et FormData (détecte le type de contenu)
                $productController->updateProduct($product_id);
                break;
            case 'DELETE':
                $productController->deleteProduct($product_id);
                break;
            default:
                ApiResponse::methodNotAllowed();
                break;
        }
    }

    // Routes pour les utilisateurs (/users)
    elseif (preg_match('#^/users/me/change-password$#', $endpoint) && $method === 'POST') { // NOUVELLE ROUTE
        $userController->changeMyPassword();
    }
    elseif (preg_match('#^/users/me$#', $endpoint) && $method === 'GET') {
        $userController->getCurrentUserProfile();
    }
    elseif (preg_match('#^/users$#', $endpoint)) {
        // ... (code existant)
    } elseif (preg_match('#^/users/([0-9]+)$#', $endpoint, $matches)) {
        // ... (code existant)
    } elseif (preg_match('#^/users/([0-9]+)/password$#', $endpoint, $matches)) {
        // ... (code existant)
    }

    // Routes pour les profils producteurs (/producers)
    elseif (preg_match('#^/producers/my-profile$#', $endpoint)) {
        // ... (code existant)
         if ($method === 'GET') {
            $producerController->getMyProducerProfile();
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $producerController->updateProducerProfile(null);
        }
        else {
            ApiResponse::methodNotAllowed();
        }
    }
    elseif (preg_match('#^/producers$#', $endpoint)) {
        // ... (code existant)
    } elseif (preg_match('#^/producers/([0-9]+)$#', $endpoint, $matches)) {
        // ... (code existant)
    }

    // Routes pour les profils clients (/customers)
    elseif (preg_match('#^/customers/my-profile$#', $endpoint)) {
        if ($method === 'GET') {
            $customerController->getCustomerProfile(null);
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            $customerController->updateCustomerProfile(null);
        } else {
            ApiResponse::methodNotAllowed();
        }
    }
    elseif (preg_match('#^/customers$#', $endpoint)) {
        switch ($method) {
            case 'GET': $customerController->getAllCustomerProfiles(); break;
            case 'POST': $customerController->createCustomerProfile(); break;
            default: ApiResponse::methodNotAllowed(); break;
        }
    } elseif (preg_match('#^/customers/([0-9]+)$#', $endpoint, $matches)) {
        $customer_id = (int)$matches[1];
        switch ($method) {
            case 'GET': $customerController->getCustomerProfile($customer_id); break;
            case 'PUT': $customerController->updateCustomerProfile($customer_id); break;
            case 'DELETE': $customerController->deleteCustomerProfile($customer_id); break;
            default: ApiResponse::methodNotAllowed(); break;
        }
    }

    // Routes pour les factures (/invoices) - NOUVEAU
    elseif (preg_match('#^/invoices$#', $endpoint)) {
        switch ($method) {
            case 'GET':
                $invoiceController->getAllInvoices(); // Admin ou Client (filtré)
                break;
            case 'POST':
                $invoiceController->createInvoice(); // Admin seulement
                break;
            default:
                ApiResponse::methodNotAllowed();
                break;
        }
    } elseif (preg_match('#^/invoices/([0-9]+)$#', $endpoint, $matches)) {
        $invoice_id = (int)$matches[1];
        switch ($method) {
            case 'GET':
                $invoiceController->getInvoiceById($invoice_id); // Admin ou Client propriétaire
                break;
            case 'PUT':
                $invoiceController->updateInvoice($invoice_id); // Admin seulement (pour l'instant)
                break;
            case 'DELETE':
                $invoiceController->deleteInvoice($invoice_id); // Admin seulement
                break;
            default:
                ApiResponse::methodNotAllowed();
                break;
        }
    }

    // Routes pour les notifications (/notifications) - NOUVEAU
    elseif (preg_match('#^/notifications/mark-all-read$#', $endpoint) && $method === 'POST') {
        $notificationController->markAllNotificationsAsRead();
    }
    elseif (preg_match('#^/notifications/all-read$#', $endpoint) && $method === 'DELETE') {
        $notificationController->deleteAllReadNotifications();
    }
    elseif (preg_match('#^/notifications$#', $endpoint) && $method === 'GET') {
        $notificationController->getUserNotifications();
    }
    elseif (preg_match('#^/notifications/([0-9]+)/read$#', $endpoint, $matches) && $method === 'PATCH') {
        $notification_id = (int)$matches[1];
        $notificationController->markNotificationAsRead($notification_id);
    }
     elseif (preg_match('#^/notifications/([0-9]+)/unread$#', $endpoint, $matches) && $method === 'PATCH') {
        $notification_id = (int)$matches[1];
        $notificationController->markNotificationAsUnread($notification_id);
    }
    elseif (preg_match('#^/notifications/([0-9]+)$#', $endpoint, $matches) && $method === 'DELETE') {
        $notification_id = (int)$matches[1];
        $notificationController->deleteNotification($notification_id);
    }
    // Note: POST /notifications (création) n'est pas exposé, géré en interne.
    // GET /notifications/{id} (lecture d'une seule) n'est pas exposé, lecture de la liste suffit.

    // Routes pour les paramètres utilisateur (/user-settings) - NOUVEAU
    elseif (preg_match('#^/user-settings$#', $endpoint)) {
        switch ($method) {
            case 'GET':
                $userSettingController->getUserSettings(); // Pour l'utilisateur connecté
                break;
            case 'PUT':
                $userSettingController->updateUserSettings(); // Pour l'utilisateur connecté
                break;
            default:
                ApiResponse::methodNotAllowed();
                break;
        }
    }


    // Si aucune route ne correspond
    else {
        ApiResponse::notFound('Endpoint non trouvé.');
    }

} catch (PDOException $e) {
    error_log("PDOException: " . $e->getMessage());
    ApiResponse::error('Erreur de base de données. Veuillez réessayer plus tard.', 500);
} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    ApiResponse::error("Une erreur interne est survenue: " . $e->getMessage(), 500);
}
?>