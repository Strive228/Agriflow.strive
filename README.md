# Agriflow

Agriflow est une plateforme web conçue pour connecter directement les producteurs agricoles avec les clients. Elle simplifie le processus de vente et d'achat de produits agricoles en fournissant des outils pour la gestion des produits, des commandes et des livraisons.

## Fonctionnalités

Agriflow offre une gamme de fonctionnalités pour les producteurs et les clients :

**Pour les Producteurs :**
*   **Gestion des Produits :** Ajoutez, mettez à jour et gérez facilement les listes de produits avec images, descriptions et prix.
*   **Gestion des Commandes :** Recevez et suivez les commandes, gérez les stocks et communiquez avec les clients.
*   **Système de Livraison :** Gérez les livraisons, suivez l'état des livraisons et coordonnez-vous avec le personnel de livraison.
*   **Tableau de Bord :** Accédez aux statistiques de ventes et aux rapports pour suivre les performances.
*   **Compte Professionnel :** Créez et gérez un profil professionnel pour présenter vos produits.

**Pour les Clients :**
*   **Découverte de Produits :** Parcourez et recherchez des produits agricoles frais auprès des producteurs locaux.
*   **Commande en Ligne :** Passez des commandes facilement grâce à une interface intuitive.
*   **Paiements Sécurisés :** Effectuez des paiements en ligne de manière sécurisée.
*   **Options de Livraison :** Choisissez entre la livraison ou le retrait sur place.
*   **Suivi des Commandes :** Suivez l'état des commandes et la progression de la livraison en temps réel.
*   **Favoris :** Enregistrez vos produits préférés pour les recommander rapidement.
*   **Évaluations et Avis :** Consultez et laissez des commentaires sur les produits et les producteurs.

## Technologies Utilisées

*   **Frontend :**
    *   HTML
    *   Tailwind CSS
    *   JavaScript
*   **Backend :**
    *   PHP
*   **Base de Données :**
    *   MySQL

## Démarrage

Pour obtenir une copie locale opérationnelle, suivez ces étapes simples.

### Prérequis

*   Un serveur web (par exemple, Apache, Nginx)
*   PHP (version 7.4 ou supérieure recommandée)
*   MySQL (ou MariaDB)

### Installation

1.  **Clonez le dépôt :**
    ```bash
    git clone https://github.com/votre-nom-utilisateur/agriflow.git
    ```
    *(Remplacez `votre-nom-utilisateur/agriflow.git` par l'URL réelle du dépôt si différente)*
2.  **Configuration de la Base de Données :**
    *   Créez une nouvelle base de données dans votre interface d'administration MySQL (par exemple, phpMyAdmin).
    *   Importez le fichier `database/agriflow.sql` dans votre base de données nouvellement créée. Cela configurera les tables nécessaires et les données initiales.
3.  **Configuration de la Connexion à la Base de Données :**
    *   Accédez au répertoire `api/config/`.
    *   Renommez `database.php.example` en `database.php` (si un fichier d'exemple existe, sinon créez `database.php`).
    *   Ouvrez `api/config/database.php` et mettez à jour les informations d'identification de la base de données (hôte, nom de la base de données, nom d'utilisateur, mot de passe) pour qu'elles correspondent à votre configuration MySQL locale.
    ```php
    <?php
    class Database {
        private $host = "votre_hote"; // par exemple, "localhost"
        private $db_name = "nom_votre_bdd_agriflow";
        private $username = "votre_nom_utilisateur_bdd";
        private $password = "votre_mot_de_passe_bdd";
        public $conn;

        public function getConnection() {
            $this->conn = null;
            try {
                $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
                $this->conn->exec("set names utf8");
            } catch(PDOException $exception) {
                echo "Erreur de connexion : " . $exception->getMessage();
            }
            return $this->conn;
        }
    }
    ?>
    ```
4.  **Lancement du Projet :**
    *   Placez le dossier du projet dans le répertoire racine de votre serveur web (par exemple, `htdocs/` pour XAMPP/Apache, ou `www/` pour WampServer/Nginx).
    *   Ouvrez votre navigateur web et accédez à l'URL du projet (par exemple, `http://localhost/agriflow/` ou `http://localhost:votre_port/agriflow/`). La page principale devrait être `accueil.html`.

Vous devriez maintenant pouvoir accéder et interagir avec l'application Agriflow localement.

## Points d'Accès API (Endpoints)

L'API backend fournit des points d'accès pour gérer divers aspects de la plateforme. Les routes API clés sont situées dans `api/routes/api.php` et gérées par les contrôleurs dans `api/controllers/`.

Voici un bref aperçu de quelques fonctionnalités essentielles :

*   **Authentification (`auth/`) :**
    *   `auth/process.php` : Gère l'inscription des utilisateurs, la connexion, la déconnexion et les vérifications d'authentification.
*   **Commandes (`api/controllers/OrderController.php`) :**
    *   `GET /orders` : Récupère les commandes des clients.
    *   `GET /orders/{id}` : Récupère les détails d'une commande spécifique.
    *   `POST /orders` : Crée une nouvelle commande.
    *   `PUT /orders/{id}/cancel` : Annule une commande existante.
    *   `GET /orders/stats` : Obtient les statistiques des commandes pour un client.
*   **Livraisons (`api/controllers/DeliveryController.php`) :**
    *   `GET /deliveries/{id}` : Obtient les détails d'une livraison spécifique.
    *   `GET /deliveries` : Récupère l'historique des livraisons des clients.
    *   `GET /deliveries/stats` : Récupère les statistiques de livraison pour un client.
    *   `GET /deliveries/{id}/status` : Obtient l'état en temps réel d'une livraison.
*   **Favoris (`api/controllers/FavoriteController.php`) :**
    *   `GET /favorites` : Liste les produits favoris d'un client.
    *   `POST /favorites` : Ajoute un produit aux favoris.
    *   `DELETE /favorites/{product_id}` : Supprime un produit des favoris.
    *   `POST /favorites/check` : Vérifie si plusieurs produits sont dans les favoris.
    *   `GET /favorites/{product_id}/is-favorite` : Vérifie si un produit spécifique est un favori.

Consultez les fichiers des contrôleurs PHP dans `api/controllers/` et les routes dans `api/routes/api.php` pour des informations plus détaillées sur les paramètres de requête et les réponses.

## Contribuer

Les contributions sont les bienvenues et appréciées ! Si vous souhaitez contribuer à Agriflow, veuillez suivre ces étapes :

1.  **Forkez le Dépôt :** Créez votre propre fork du projet sur GitHub.
2.  **Créez une Branche :** Créez une nouvelle branche dans votre fork pour votre fonctionnalité ou votre correction de bug.
    ```bash
    git checkout -b fonctionnalite/votre-nom-de-fonctionnalite
    ```
    ou
    ```bash
    git checkout -b correction/description-du-probleme
    ```
3.  **Effectuez Vos Modifications :** Implémentez votre fonctionnalité ou corrigez le bug. Assurez-vous que votre code respecte le style de codage du projet (si spécifié).
4.  **Testez Vos Modifications :** Testez vos modifications minutieusement pour vous assurer qu'elles fonctionnent comme prévu et n'introduisent pas de nouveaux problèmes.
5.  **Commitez Vos Modifications :** Commitez vos modifications avec un message de commit clair et descriptif.
    ```bash
    git commit -m "Ajout : Brève description de votre fonctionnalité"
    ```
    ou
    ```bash
    git commit -m "Correction : Description du bug corrigé"
    ```
6.  **Poussez vers Votre Fork :** Poussez vos modifications vers votre dépôt forké.
    ```bash
    git push origin fonctionnalite/votre-nom-de-fonctionnalite
    ```
7.  **Soumettez une Pull Request :** Ouvrez une pull request de votre branche vers le dépôt principal d'Agriflow. Fournissez une description claire de vos modifications dans la pull request.

Nous examinerons votre pull request dès que possible. Merci pour votre contribution !

## Licence

Ce projet est sous licence MIT. Consultez le fichier [LICENSE](LICENSE.md) pour plus de détails.

---

