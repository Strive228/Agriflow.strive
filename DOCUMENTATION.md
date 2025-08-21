# Documentation Technique Complète – Agriflow

## Table des matières
1. [Présentation générale](#présentation-générale)
2. [Architecture du projet](#architecture-du-projet)
3. [Base de données](#base-de-données)
4. [API Backend](#api-backend)
5. [Authentification & Sécurité](#authentification--sécurité)
6. [Fonctionnalités principales](#fonctionnalités-principales)
7. [Front-end & Tableaux de bord](#front-end--tableaux-de-bord)
8. [Annexes](#annexes)

---

## Présentation générale
Agriflow est une plateforme de gestion de commandes, livraisons et favoris pour un marché entre producteurs et clients. Elle propose une API REST, une gestion d'authentification sécurisée, et des interfaces web pour chaque type d'utilisateur.

## Architecture du projet

```
agriflow/
├── api/                # Backend PHP (API REST)
│   ├── config/         # Configuration (DB, réponses API)
│   ├── controllers/    # Contrôleurs métier (Commandes, Livraisons, Favoris)
│   ├── models/         # Modèles de données (ORM léger)
│   └── routes/         # Définition des routes API
├── auth/               # Authentification (PHP + JS)
├── database/           # Schéma SQL
├── assets/js/          # Scripts JS front (dashboard)
├── js/                 # Autres scripts JS
├── *.html              # Pages web (accueil, login, dashboard, etc.)
```

## Base de données

La base de données `agriflow` est structurée pour supporter :
- Utilisateurs (`users`), Producteurs (`producers`), Clients (`customers`)
- Produits, Commandes, Items de commande, Livraisons, Factures
- Favoris, Notifications, Sessions, Paramètres utilisateur

### Extrait du schéma principal :
- **users** : email, mot de passe, rôle (producteur/client), etc.
- **producers** : profil producteur (ferme, SIRET, etc.)
- **customers** : profil client (adresse, préférences)
- **products** : produits proposés
- **orders** : commandes passées
- **order_items** : items d'une commande
- **deliveries** : suivi des livraisons
- **favorites** : produits favoris d'un client
- **sessions** : gestion des connexions

Voir `database/agriflow.sql` pour le détail complet.

## API Backend

L'API REST (PHP) expose les routes suivantes (voir `api/routes/api.php`) :

### Utilisateurs (`/users`)
- `GET /users` : (Admin) Liste de tous les utilisateurs avec filtres optionnels (`role`, `is_active`) et pagination.
- `POST /users` : (Admin) Crée un nouvel utilisateur (client, producteur, ou admin). Nécessite `email`, `password`, `role`. Crée aussi le profil client/producteur de base.
- `GET /users/me` : (Connecté) Récupère le profil de l'utilisateur actuellement connecté.
- `GET /users/{id}` : (Admin ou Propriétaire) Récupère les détails d'un utilisateur spécifique.
- `PUT /users/{id}` : (Admin ou Propriétaire) Met à jour les informations d'un utilisateur (Admin peut changer le rôle et `is_active`).
  - *Note sur la connexion (gérée via `auth/process.php`)* : En cas de succès, la réponse de connexion inclut également `producer_profile_id` (si rôle producteur) ou `customer_profile_id` (si rôle client), en plus des informations utilisateur de base et du token de session.
- `DELETE /users/{id}` : (Admin) Désactive un utilisateur (soft delete) et supprime les profils associés (Customer/Producer).
- `PUT /users/{id}/password` : (Admin ou Propriétaire) Met à jour le mot de passe d'un utilisateur.

### Produits (`/products`)
- `GET /products` : Liste tous les produits avec filtres optionnels (`producer_id`, `is_available`, `is_bio`) et pagination.
- `POST /products` : (Admin ou Producteur) Crée un nouveau produit. Si Producteur, `producer_id` est automatiquement assigné.
- `GET /products/{id}` : Récupère les détails d'un produit spécifique. (Les produits non disponibles peuvent être cachés aux clients non connectés/non propriétaires).
- `PUT /products/{id}` : (Admin ou Producteur propriétaire) Met à jour un produit.
- `DELETE /products/{id}` : (Admin ou Producteur propriétaire) Supprime un produit.

### Commandes (`/orders`)
- `GET /orders` : Liste les commandes.
    - Pour un Client connecté : Liste ses propres commandes (filtré par `customer_id` automatiquement).
    - Pour un Producteur connecté : Liste les commandes contenant ses produits (filtré par `producer_id` automatiquement ou via query param `producer_id` si égal à son propre ID).
    - Pour un Admin connecté : Peut lister toutes les commandes, ou filtrer par `customer_id` ou `producer_id` via query params.
    - Supporte la pagination et le filtrage par `status`.
- `POST /orders` : (Client) Crée une nouvelle commande. Le `customer_id` est automatiquement assigné. Les prix sont vérifiés côté serveur.
- `GET /orders/{id}` : (Client propriétaire, Admin, Producteur concerné par un item de la commande) Récupère le détail d'une commande, incluant les items et informations de livraison.
- `PUT /orders/{id}` : (Admin, Producteur concerné, ou Client sous conditions) Met à jour une commande (ex: statut, adresse de livraison si statut le permet).
- `DELETE /orders/{id}` : (Client propriétaire sous conditions, Admin, Producteur concerné) Annule une commande. Le statut de paiement peut passer à 'remboursée'.
- `GET /orders/stats` : (Client) Statistiques des commandes du client connecté.

### Livraisons (`/deliveries`)
- `GET /deliveries` : (Client) Liste ses livraisons. (Admin/Producteur) Liste toutes les livraisons ou celles filtrées. Pagination.
- `POST /deliveries` : (Admin/Producteur) Crée une nouvelle livraison associée à une commande.
- `GET /deliveries/{id}` : (Client propriétaire de la commande, Admin, Producteur concerné) Détail d'une livraison.
- `PUT /deliveries/{id}` : (Admin/Producteur) Met à jour les informations d'une livraison (ex: livreur, notes, statut).
- `DELETE /deliveries/{id}` : (Admin/Producteur) Supprime une livraison.
- `PATCH /deliveries/{id}/status` : (Admin/Producteur) Met à jour spécifiquement le statut d'une livraison.
- `GET /deliveries/{id}/status` : (Client propriétaire de la commande, Admin, Producteur concerné) Statut temps réel d'une livraison.
- `GET /deliveries/stats` : (Client) Statistiques de livraison du client connecté.

### Profils Producteurs (`/producers`)
- `GET /producers` : Liste publique des profils producteurs avec pagination et filtres (`farm_type`).
- `POST /producers` : (Admin) Crée un nouveau profil producteur pour un `user_id` existant (rôle producteur).
- `GET /producers/my-profile` : (Producteur connecté) Récupère son propre profil producteur.
- `PUT /producers/my-profile` : (Producteur connecté) Met à jour son propre profil producteur.
- `GET /producers/{id}` : Récupère le profil public d'un producteur spécifique par son `producers.id`.
- `PUT /producers/{id}` : (Admin ou Producteur propriétaire) Met à jour un profil producteur.
- `DELETE /producers/{id}` : (Admin) Supprime un profil producteur (et les produits associés via CASCADE).

### Profils Clients (`/customers`)
- `GET /customers` : (Admin) Liste tous les profils clients avec pagination et filtres (`food_preferences`).
- `POST /customers` : (Admin) Crée un nouveau profil client pour un `user_id` existant (rôle client).
- `GET /customers/my-profile` : (Client connecté) Récupère son propre profil client.
- `PUT /customers/my-profile` : (Client connecté) Met à jour son propre profil client.
- `GET /customers/{id}` : (Admin ou Client propriétaire) Récupère un profil client spécifique par son `customers.id`.
- `PUT /customers/{id}` : (Admin ou Client propriétaire) Met à jour un profil client.
- `DELETE /customers/{id}` : (Admin) Supprime un profil client (et commandes/favoris associés via CASCADE).

### Factures (`/invoices`)
- `GET /invoices` : (Admin) Liste toutes les factures. (Client) Liste ses propres factures. Filtres (`customer_id`, `order_id`, `status`) et pagination.
- `POST /invoices` : (Admin) Crée une nouvelle facture.
- `GET /invoices/{id}` : (Admin ou Client propriétaire de la commande) Récupère une facture spécifique.
- `PUT /invoices/{id}` : (Admin) Met à jour une facture (statut, date paiement, URL PDF).
- `DELETE /invoices/{id}` : (Admin) Supprime une facture (sous conditions, ex: si statut 'annulee').

### Notifications (`/notifications`)
- `GET /notifications` : (Connecté) Liste les notifications de l'utilisateur connecté, avec filtres (`is_read`, `type`) et pagination. Retourne aussi un `unreadCount`.
- `PATCH /notifications/{id}/read` : (Connecté) Marque une de ses notifications comme lue.
- `PATCH /notifications/{id}/unread` : (Connecté) Marque une de ses notifications comme non lue.
- `POST /notifications/mark-all-read` : (Connecté) Marque toutes les notifications de l'utilisateur comme lues.
- `DELETE /notifications/{id}` : (Connecté) Supprime une de ses notifications.
- `DELETE /notifications/all-read` : (Connecté) Supprime toutes les notifications lues de l'utilisateur.

### Paramètres Utilisateur (`/user-settings`)
- `GET /user-settings` : (Connecté) Récupère les paramètres de l'utilisateur connecté (notifications, langue, thème). Crée des paramètres par défaut si inexistants.
- `PUT /user-settings` : (Connecté) Met à jour les paramètres de l'utilisateur connecté.

### Favoris (`/favorites`)
- `GET /favorites` : (Client connecté) Liste des favoris du client.
- `POST /favorites` : (Client connecté) Ajoute un produit aux favoris.
- `DELETE /favorites/{product_id}` : (Client connecté) Retire un produit des favoris.
- `GET /favorites/{product_id}` : (Client connecté) Vérifie si un produit spécifique est en favori.
- `POST /favorites/check` : (Client connecté) Vérifie le statut de favori pour plusieurs produits.

#### Réponses API
- Succès : `{ status: 'success', data: ..., message: ... }` (Codes HTTP: 200 OK, 201 Created, 204 No Content)
- Erreur : `{ status: 'error', message: ..., errors: ... }`

Voir `api/config/ApiResponse.php` pour le formatage des réponses.

## Authentification & Sécurité

- Authentification via sessions PHP et tokens (voir `auth/auth.php`, `auth/process.php`)
- Inscription : Producteur ou Client (création de profil spécifique)
- Connexion : Vérification email/mot de passe, création de session et token
- Déconnexion : Suppression du token et destruction de la session
- Vérification de session : Contrôle du token et de l'expiration
- Sécurité : Hashage des mots de passe (Argon2id), validation des entrées, gestion des erreurs

## Fonctionnalités principales

### Pour les clients :
- Parcourir les produits
- Passer commande
- Suivre ses commandes et livraisons
- Gérer ses favoris
- Recevoir des notifications

### Pour les producteurs :
- Gérer leur profil de ferme
- Ajouter/éditer des produits
- Suivre les commandes reçues
- Gérer les livraisons

### Pour tous :
- Gestion du compte, préférences, notifications

## Front-end & Tableaux de bord

- Pages HTML : `accueil.html`, `login.html`, `tableau-client.html`, `tableau-producteur.html`, etc.
- Scripts JS :
  - `assets/js/dashboard.js` : Gestion du tableau de bord client (onglets, stats, commandes, livraisons, favoris)
  - `auth/auth.js` : Gestion de l'authentification côté client (login, register, logout, protection des pages)
  - `js/profile-menu.js` : Menu utilisateur

## Annexes

- **Configuration** : Voir `api/config/database.php` pour la connexion MySQL
- **Modèles** : Voir `api/models/` pour la logique métier (Order, Delivery, Favorite, OrderItem)
- **Contrôleurs** : Voir `api/controllers/` pour la logique d'API
- **Routes** : Voir `api/routes/api.php`
- **Authentification** : Voir `auth/`
- **Base de données** : Voir `database/agriflow.sql`

---

*Document généré automatiquement. Pour toute question, contactez l'équipe technique Agriflow.* 