// Gestionnaire du tableau de bord client
const Dashboard = {
    // État initial
    state: {
        activeTab: 'dashboard',
        orders: [],
        deliveries: [],
        favorites: [],
        currentPage: 1,
        itemsPerPage: 10
    },

    // Initialisation
    init() {
        this.bindEvents();
        this.loadDashboardData();
        this.setupTabNavigation();
    },

    // Liaison des événements
    bindEvents() {
        // Gestion des onglets
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const tabId = e.target.closest('.tab-button').dataset.tab;
                this.switchTab(tabId);
            });
        });

        // Gestion des favoris
        document.querySelectorAll('.favorite-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const productId = e.target.closest('.favorite-button').dataset.productId;
                this.toggleFavorite(productId);
            });
        });

        // Gestion de la pagination
        document.querySelectorAll('.pagination-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const page = parseInt(e.target.dataset.page);
                this.changePage(page);
            });
        });
    },

    // Récupération des statistiques
    async fetchStats() {
        try {
            const response = await fetch('/agriflow/api/orders/stats');
            const data = await response.json();

            if (data.status === 'success') {
                return data.data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Erreur lors de la récupération des statistiques:', error);
            throw error;
        }
    },

    // Récupération des commandes
    async fetchOrders() {
        try {
            const response = await fetch(`/agriflow/api/orders?page=${this.state.currentPage}&per_page=${this.state.itemsPerPage}`);
            const data = await response.json();

            if (data.status === 'success') {
                return data.data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Erreur lors de la récupération des commandes:', error);
            throw error;
        }
    },

    // Récupération des livraisons
    async fetchDeliveries() {
        try {
            const response = await fetch(`/agriflow/api/deliveries?page=${this.state.currentPage}&per_page=${this.state.itemsPerPage}`);
            const data = await response.json();

            if (data.status === 'success') {
                return data.data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Erreur lors de la récupération des livraisons:', error);
            throw error;
        }
    },

    // Récupération des favoris
    async fetchFavorites() {
        try {
            const response = await fetch(`/agriflow/api/favorites?page=${this.state.currentPage}&per_page=${this.state.itemsPerPage}`);
            const data = await response.json();

            if (data.status === 'success') {
                return data.data;
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Erreur lors de la récupération des favoris:', error);
            throw error;
        }
    },

    // Chargement des données initiales
    async loadDashboardData() {
        try {
            // Charger les statistiques
            const stats = await this.fetchStats();
            this.updateDashboardStats(stats);

            // Charger les commandes récentes
            const orders = await this.fetchOrders();
            this.updateOrdersList(orders);

            // Charger les livraisons en cours
            const deliveries = await this.fetchDeliveries();
            this.updateDeliveriesList(deliveries);

            // Charger les favoris
            const favorites = await this.fetchFavorites();
            this.updateFavoritesList(favorites);
        } catch (error) {
            console.error('Erreur lors du chargement des données:', error);
            this.showError('Erreur lors du chargement des données');
        }
    },

    // Navigation entre les onglets
    switchTab(tabId) {
        // Masquer tous les contenus
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Désactiver tous les boutons
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('active');
        });

        // Afficher le contenu sélectionné
        document.getElementById(`${tabId}-content`).classList.remove('hidden');
        document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');

        // Mettre à jour l'état
        this.state.activeTab = tabId;

        // Charger les données spécifiques à l'onglet si nécessaire
        switch (tabId) {
            case 'orders':
                this.loadOrders();
                break;
            case 'deliveries':
                this.loadDeliveries();
                break;
            case 'favorites':
                this.loadFavorites();
                break;
        }
    },

    // Chargement des commandes
    async loadOrders() {
        try {
            const orders = await this.fetchOrders();
            this.state.orders = orders;
            this.updateOrdersList(orders);
        } catch (error) {
            console.error('Erreur lors du chargement des commandes:', error);
            this.showError('Erreur lors du chargement des commandes');
        }
    },

    // Chargement des livraisons
    async loadDeliveries() {
        try {
            const deliveries = await this.fetchDeliveries();
            this.state.deliveries = deliveries;
            this.updateDeliveriesList(deliveries);
        } catch (error) {
            console.error('Erreur lors du chargement des livraisons:', error);
            this.showError('Erreur lors du chargement des livraisons');
        }
    },

    // Chargement des favoris
    async loadFavorites() {
        try {
            const favorites = await this.fetchFavorites();
            this.state.favorites = favorites;
            this.updateFavoritesList(favorites);
        } catch (error) {
            console.error('Erreur lors du chargement des favoris:', error);
            this.showError('Erreur lors du chargement des favoris');
        }
    },

    // Gestion des favoris
    async toggleFavorite(productId) {
        try {
            const response = await fetch(`/agriflow/api/favorites/${productId}`, {
                method: 'DELETE'
            });
            const data = await response.json();

            if (data.status === 'success') {
                // Recharger la liste des favoris
                await this.loadFavorites();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Erreur lors de la gestion des favoris:', error);
            this.showError('Erreur lors de la gestion des favoris');
        }
    },

    // Mise à jour de l'affichage des statistiques
    updateDashboardStats(stats) {
        document.getElementById('pending-orders').textContent = stats.pending_orders;
        document.getElementById('ongoing-deliveries').textContent = stats.ongoing_deliveries;
        document.getElementById('total-spent').textContent = this.formatPrice(stats.total_spent);
    },

    // Mise à jour de la liste des commandes
    updateOrdersList(orders) {
        const container = document.getElementById('orders-list');
        container.innerHTML = orders.map(order => `
            <div class="order-item p-4 bg-white rounded-lg shadow-md mb-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">Commande #${order.id}</h3>
                        <p class="text-gray-600">${this.formatDate(order.created_at)}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold">${this.formatPrice(order.total_amount)}</p>
                        <span class="${this.getStatusClass(order.status)}">
                            ${this.formatStatus(order.status)}
                        </span>
                    </div>
                </div>
            </div>
        `).join('');
    },

    // Mise à jour de la liste des livraisons
    updateDeliveriesList(deliveries) {
        const container = document.getElementById('deliveries-list');
        container.innerHTML = deliveries.map(delivery => `
            <div class="delivery-item p-4 bg-white rounded-lg shadow-md mb-4">
                <div class="flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-semibold">Livraison #${delivery.tracking_number}</h3>
                        <p class="text-gray-600">
                            ${delivery.delivery_person_name ?
                `Livreur: ${delivery.delivery_person_name}` : 'Livreur non assigné'}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-gray-600">
                            Estimée: ${this.formatDate(delivery.estimated_delivery_date)}
                        </p>
                        <span class="${this.getDeliveryStatusClass(delivery.status)}">
                            ${this.formatDeliveryStatus(delivery.status)}
                        </span>
                    </div>
                </div>
            </div>
        `).join('');
    },

    // Mise à jour de la liste des favoris
    updateFavoritesList(favorites) {
        const container = document.getElementById('favorites-list');
        container.innerHTML = favorites.map(favorite => `
            <div class="favorite-item p-4 bg-white rounded-lg shadow-md mb-4">
                <div class="flex items-center">
                    <img src="${favorite.image_url}" alt="${favorite.product_name}" 
                         class="w-20 h-20 object-cover rounded-lg mr-4">
                    <div class="flex-grow">
                        <h3 class="text-lg font-semibold">${favorite.product_name}</h3>
                        <p class="text-gray-600">${favorite.producer_name}</p>
                        <p class="text-lg font-bold mt-2">${this.formatPrice(favorite.price)}</p>
                    </div>
                    <button class="favorite-button text-red-500 hover:text-red-700"
                            data-product-id="${favorite.product_id}">
                        <i class="ri-heart-fill text-2xl"></i>
                    </button>
                </div>
            </div>
        `).join('');
    },

    // Utilitaires
    formatPrice(price) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'XOF'
        }).format(price);
    },

    formatDate(date) {
        return new Intl.DateTimeFormat('fr-FR', {
            dateStyle: 'long',
            timeStyle: 'short'
        }).format(new Date(date));
    },

    formatStatus(status) {
        const statusMap = {
            'en_attente': 'En attente',
            'confirmee': 'Confirmée',
            'en_preparation': 'En préparation',
            'en_livraison': 'En livraison',
            'livree': 'Livrée',
            'annulee': 'Annulée'
        };
        return statusMap[status] || status;
    },

    formatDeliveryStatus(status) {
        const labels = {
            'en_attente': 'En attente',
            'en_preparation': 'En préparation',
            'en_cours': 'En cours',
            'livree': 'Livrée',
            'annulee': 'Annulée'
        };
        return labels[status] || status;
    },

    getStatusClass(status) {
        const classMap = {
            'en_attente': 'bg-yellow-100 text-yellow-800',
            'confirmee': 'bg-blue-100 text-blue-800',
            'en_preparation': 'bg-purple-100 text-purple-800',
            'en_livraison': 'bg-orange-100 text-orange-800',
            'livree': 'bg-green-100 text-green-800',
            'annulee': 'bg-red-100 text-red-800'
        };
        return `px-2 py-1 rounded-full text-sm ${classMap[status] || 'bg-gray-100 text-gray-800'}`;
    },

    getDeliveryStatusClass(status) {
        const classes = {
            'en_attente': 'bg-yellow-100 text-yellow-800',
            'en_preparation': 'bg-purple-100 text-purple-800',
            'en_cours': 'bg-blue-100 text-blue-800',
            'livree': 'bg-green-100 text-green-800',
            'annulee': 'bg-red-100 text-red-800'
        };
        return `px-3 py-1 rounded-full text-sm ${classes[status] || ''}`;
    },

    showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
        errorDiv.innerHTML = `
            <strong class="font-bold">Erreur!</strong>
            <span class="block sm:inline">${message}</span>
        `;

        const container = document.querySelector('.dashboard-container');
        container.insertBefore(errorDiv, container.firstChild);

        setTimeout(() => {
            errorDiv.remove();
        }, 5000);
    }
};

// Initialisation du tableau de bord au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    Dashboard.init();
});