<?php
session_start();

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'agriflow');
define('DB_USER', 'root');
define('DB_PASS', '');

class Auth
{
    private $pdo;
    private $errors = [];

    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die('Erreur de connexion : ' . $e->getMessage());
        }
    }

    // Fonction de connexion
    public function login($email, $password)
    {
        ob_clean(); // Nettoie tout output précédent
        try {
            // Vérification des champs
            if (empty($email) || empty($password)) {
                $this->errors[] = "Tous les champs sont obligatoires";
                return false;
            }

            // Vérification de l'email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = "Format d'email invalide";
                return false;
            }

            // Recherche de l'utilisateur
            $stmt = $this->pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->errors[] = "Email ou mot de passe incorrect";
                return false;
            }

            // Création de la session
            $token = bin2hex(random_bytes(32));
            $stmt = $this->pdo->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))");
            $stmt->execute([$user['id'], $token]);

            // Mise à jour de la dernière connexion
            $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Configuration de la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['token'] = $token;

            error_log('Session créée - user_id: ' . $user['id'] . ', role: ' . $user['role']);

            // Stockage des données utilisateur dans localStorage via JSON
            $userData = [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'token' => $token
            ];

            // Ajout des infos de profil selon le rôle
            if ($user['role'] === 'producteur') {
                $stmt = $this->pdo->prepare("SELECT id as producer_profile_id, farm_name, farm_photo_url FROM producers WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $producer = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($producer) {
                    $userData['producer_profile_id'] = $producer['producer_profile_id'];
                    $userData['farm_name'] = $producer['farm_name'];
                    $userData['farm_photo_url'] = $producer['farm_photo_url'];
                }
            } else if ($user['role'] === 'client') {
                $stmt = $this->pdo->prepare("SELECT id as customer_profile_id, delivery_address, food_preferences FROM customers WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($customer) {
                    $userData['customer_profile_id'] = $customer['customer_profile_id'];
                    $userData['delivery_address'] = $customer['delivery_address'];
                    $userData['food_preferences'] = $customer['food_preferences'];
                }
            }

            echo json_encode([
                'success' => true,
                'user' => $userData
            ]);

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Erreur lors de la connexion";
            return false;
        }
    }

    // Fonction d'inscription
    public function register($data)
    {
        try {
            // Validation des données
            if (!$this->validateRegistrationData($data)) {
                return false;
            }

            $this->pdo->beginTransaction();

            // Création de l'utilisateur
            $stmt = $this->pdo->prepare("INSERT INTO users (email, password_hash, phone, role) VALUES (?, ?, ?, ?)");
            $password_hash = password_hash($data['password'], PASSWORD_ARGON2ID);
            $stmt->execute([$data['email'], $password_hash, $data['phone'], $data['role']]);
            $userId = $this->pdo->lastInsertId();

            // Création du profil selon le rôle
            if ($data['role'] === 'producteur') {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO producers (user_id, farm_name, siret, experience_years, farm_type, surface_hectares, farm_address, certifications, delivery_availability, farm_description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    $data['farm_name'],
                    $data['siret'],
                    $data['experience_years'],
                    $data['farm_type'],
                    $data['surface_hectares'],
                    $data['farm_address'],
                    $data['certifications'],
                    $data['delivery_availability'],
                    $data['farm_description']
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO customers (user_id, delivery_address, food_preferences) 
                    VALUES (?, ?, ?)"
                );
                $stmt->execute([
                    $userId,
                    $data['delivery_address'],
                    $data['food_preferences']
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->errors[] = "Erreur lors de l'inscription";
            return false;
        }
    }

    // Validation des données d'inscription
    private function validateRegistrationData($data)
    {
        // Validation de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "Format d'email invalide";
            return false;
        }

        // Vérification si l'email existe déjà
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $this->errors[] = "Cet email est déjà utilisé";
            return false;
        }

        // Validation du mot de passe
        if (strlen($data['password']) < 8) {
            $this->errors[] = "Le mot de passe doit contenir au moins 8 caractères";
            return false;
        }

        // Validation du rôle
        if (!in_array($data['role'], ['producteur', 'client'])) {
            $this->errors[] = "Rôle invalide";
            return false;
        }

        // Validation spécifique au rôle
        if ($data['role'] === 'producteur') {
            if (empty($data['farm_name'])) {
                $this->errors[] = "Le nom de la ferme est obligatoire";
                return false;
            }
            if (!empty($data['siret']) && !preg_match('/^[0-9]{14}$/', $data['siret'])) {
                $this->errors[] = "Format SIRET invalide";
                return false;
            }
        } else {
            if (empty($data['delivery_address'])) {
                $this->errors[] = "L'adresse de livraison est obligatoire";
                return false;
            }
        }

        return true;
    }

    // Déconnexion
    public function logout()
    {
        if (isset($_SESSION['user_id']) && isset($_SESSION['token'])) {
            // Suppression du token de session
            $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE user_id = ? AND token = ?");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
        }

        // Destruction de la session
        session_destroy();
        return true;
    }

    // Vérification de la session
    public function checkSession()
    {
        error_log('Vérification de session - user_id: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'non défini'));
        error_log('Vérification de session - token: ' . (isset($_SESSION['token']) ? 'présent' : 'non défini'));

        if (!isset($_SESSION['user_id']) || !isset($_SESSION['token'])) {
            error_log('Session invalide - identifiants manquants');
            return false;
        }

        // Vérification du token en base
        $stmt = $this->pdo->prepare(
            "SELECT id, expires_at FROM sessions 
            WHERE user_id = ? AND token = ? AND expires_at > NOW()"
        );

        $stmt->execute([$_SESSION['user_id'], $_SESSION['token']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log('Résultat vérification session: ' . ($session ? 'valide' : 'invalide'));
        if ($session) {
            error_log('Session expire le: ' . $session['expires_at']);
            return true;
        }

        return false;
    }

    // Récupération des erreurs
    public function getErrors()
    {
        return $this->errors;
    }
}