// assets/js/producer-dashboard.js

const ProducerDashboard = {
    state: {
        producerId: null, // This should be set ideally from session or a global var
        products: [],
        orders: [],
        deliveries: [],
        stats: {},
        salesChartInstance: null,
        editingProductId: null, // Pour stocker l'ID du produit en cours de modification
        // productsChartInstance: null, // If we add a products chart later
    },

    init() {
        // Attempt to get producerId (e.g., from a global variable set by PHP)
        // For now, this is a placeholder. In a real scenario, this needs to be securely obtained.
        // if (typeof currentProducerId !== 'undefined') {
        //     this.state.producerId = currentProducerId;
        // } else {
        //     console.warn('Producer ID not found. Dashboard may not function correctly.');
        //     // Potentially redirect to login or show error
        // }

        this.bindEvents();
        this.loadInitialData();
        this.initSalesChart(); // Initialize chart structure, data will be loaded
        // this.initProductsChart(); // If we have one
    },

    bindEvents() {
        // Tab navigation (if not handled by inline script in HTML)
        // Example: document.querySelectorAll('.nav-tab').forEach(tab => tab.addEventListener('click', this.handleTabClick.bind(this)));

        // Add product form submission
        const addProductForm = document.getElementById('product-form');
        if (addProductForm) {
            const saveProductButton = document.getElementById('save-product-button');
            if (saveProductButton) {
                saveProductButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.handleFormSubmit(); // Nouvelle fonction de dispatch
                });
            } else {
                console.warn("Save product button not found");
            }
        } else {
            console.warn("Product form not found");
        }

        // Listener for "Ajouter un produit" button to toggle form visibility
        const addProductBtn = document.querySelector('#mes-produits-content button.bg-primary'); // Sélecteur corrigé
        if (addProductBtn && addProductBtn.textContent.includes('Ajouter un produit')) {
            addProductBtn.addEventListener('click', () => {
                const form = document.getElementById('product-form');
                if (form) form.classList.toggle('hidden');
            });
        }

        // Potentially listeners for edit/delete product buttons once products are rendered

        // Logout button listeners
        const logoutButton = document.getElementById('logout-button');
        if (logoutButton) {
            logoutButton.addEventListener('click', this.handleLogout);
        }
        const mobileLogoutButton = document.getElementById('mobile-logout-button');
        if (mobileLogoutButton) {
            mobileLogoutButton.addEventListener('click', this.handleLogout);
        }

        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    },

    handleFormSubmit() {
        if (this.state.editingProductId) {
            this.handleUpdateProductInternal();
        } else {
            this.handleAddProductInternal();
        }
    },

    handleLogout() {
        // Appel réel de la déconnexion
        fetch('/agriflow/auth/process.php?action=logout')
            .then(response => {
                // Rediriger même si la réponse n'est pas parfaite, car la session sera probablement détruite côté serveur
                window.location.href = '/agriflow/index.html';
            })
            .catch(error => {
                console.error('Erreur de déconnexion:', error);
                // Tenter de rediriger quand même
                window.location.href = '/agriflow/index.html';
            });
    },

    async loadInitialData() {
        console.log("Loading initial data for producer dashboard...");
        await this.fetchDashboardStats();
        await this.fetchRecentOrdersForDashboard();
        // Data for specific tabs can be loaded when tabs are clicked or all at once
        await this.fetchProducts();
        await this.fetchOrders();
        await this.fetchDeliveries();
        await this.fetchFullStats(); // For the statistics tab
    },

    // --- API Fetching Functions ---
    // These functions will use hypothetical API endpoints.
    // Backend will need to implement these for producers.

    async fetchDashboardStats() {
        // HYPOTHETICAL ENDPOINT: GET /api/producer/dashboard-stats
        // Expected response:
        // {
        //   status: "success",
        //   data: {
        //     salesToday: { amount: 50000, currency: "FCFA", trend: "+5%" },
        //     ordersToday: { count: 5, new: 2 },
        //     totalProducts: { count: 50, lowStock: 3 },
        //     activeCustomers: { count: 20, newThisWeek: 4 },
        //     monthlySalesData: [ { month: "Jan", sales: 120000 }, ... ]
        //   }
        // }
        console.log("Fetching dashboard stats...");
        try {
            // const response = await fetch('/api/producer/dashboard-stats'); // Replace with actual API call
            // if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            // const result = await response.json();
            // if (result.status === 'success') {
            //     this.state.stats.dashboard = result.data;
            //     this.updateDashboardStatsUI(result.data);
            //     if (result.data.monthlySalesData) {
            //        this.updateSalesChart(result.data.monthlySalesData);
            //     }
            // } else {
            //     console.error('Failed to fetch dashboard stats:', result.message);
            // }
            // --- MOCK DATA FOR NOW ---
            const mockData = {
                salesToday: { amount: 386000, currency: "FCFA", trend: "+12%" },
                ordersToday: { count: 24, new: 5 },
                totalProducts: { count: 42, lowStock: 3 },
                activeCustomers: { count: 156, newThisWeek: "+8" }, // Assuming 'clients' means customers who ordered from this producer
                monthlySalesData: [
                    { month: 'Jan', sales: 1200 }, { month: 'Fév', sales: 1350 }, { month: 'Mar', sales: 1800 },
                    { month: 'Avr', sales: 2100 }, { month: 'Mai', sales: 1950 }, { month: 'Juin', sales: 2300 },
                    { month: 'Juil', sales: 2450 }, { month: 'Août', sales: 2800 }, { month: 'Sep', sales: 3100 },
                    { month: 'Oct', sales: 3450 }, { month: 'Nov', sales: 3800 }, { month: 'Déc', sales: 4200 }
                ]
            };
            this.state.stats.dashboard = mockData;
            this.updateDashboardStatsUI(mockData);
            this.updateSalesChart(mockData.monthlySalesData);
            console.log("Mock dashboard stats loaded.");
            // --- END MOCK DATA ---
        } catch (error) {
            console.error('Error fetching dashboard stats:', error);
            // Display error to user
        }
    },

    async fetchRecentOrdersForDashboard() {
        // HYPOTHETICAL ENDPOINT: GET /api/producer/orders?limit=5&sort=recent
        // Expected response:
        // {
        //   status: "success",
        //   data: [
        //     { id: 1, customerName: "Client A", totalAmount: 15000, currency: "FCFA", status: "En préparation", itemsPreview: "Tomates, Courgettes" }, ...
        //   ]
        // }
        console.log("Fetching recent orders for dashboard...");
        try {
            // const response = await fetch('/api/producer/orders?limit=5&sort=recent');
            // if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            // const result = await response.json();
            // if (result.status === 'success') {
            //     this.updateRecentOrdersUI(result.data);
            // } else {
            //     console.error('Failed to fetch recent orders:', result.message);
            // }
            const userData = JSON.parse(localStorage.getItem('user'));
            if (!userData || !userData.producer_profile_id) {
                console.error('Producer ID not found. Cannot fetch recent orders.');
                this.updateRecentOrdersUI([]);
                return;
            }
            const producerId = userData.producer_profile_id;

            // Fetch first 5 orders for this producer
            const response = await fetch(`/agriflow/api/orders?producer_id=${producerId}&page=1&per_page=5`);
            if (!response.ok) {
                const err = await response.json().catch(() => ({ message: response.statusText }));
                throw new Error(`HTTP error ${response.status}: ${err.message}`);
            }
            const result = await response.json();
            if (result.status === 'success' && result.data && Array.isArray(result.data.items)) {
                // Adapter les données pour updateRecentOrdersUI
                const ordersForUI = result.data.items.map(order => ({
                    id: order.id,
                    // customerName: order.customer_email || `Client ID ${order.customer_id}`, // customer_email n'est pas toujours là
                    customerName: `Client ${order.customer_id}`, // Simplifié, idéalement on aurait le nom du client
                    itemsPreview: `Items: ${order.items_count}`, // Simplifié
                    totalAmount: order.total_amount,
                    currency: "FCFA", // En dur pour l'instant
                    status: this.translateOrderStatus(order.status) // Traduire le statut si besoin
                }));
                this.updateRecentOrdersUI(ordersForUI);
                console.log("Recent orders loaded from API for dashboard.");
            } else {
                console.error('Failed to fetch recent orders or data format incorrect:', result.message);
                this.updateRecentOrdersUI([]);
            }
        } catch (error) {
            console.error('Error fetching recent orders:', error);
            this.updateRecentOrdersUI([]);
        }
    },

    async fetchProducts() {
        // HYPOTHETICAL ENDPOINT: GET /api/producer/products
        // Expected response:
        // {
        //   status: "success",
        //   data: [
        //     { id: 1, name: "Tomates Bio", price: 3.50, unit: "kg", stockQuantity: 100, imageUrl: "url", status: "En stock" }, ...
        //   ]
        // }
        console.log("Fetching products...");
        try {
            const userData = JSON.parse(localStorage.getItem('user'));
            if (!userData || !userData.producer_profile_id) {
                console.error('Producer ID not found in local storage. Cannot fetch products.');
                this.updateProductsGridUI([]); // Afficher une grille vide ou un message
                return;
            }
            const producerId = userData.producer_profile_id;

            const response = await fetch(`/agriflow/api/products?producer_id=${producerId}`);
            if (!response.ok) {
                const errorResult = await response.json().catch(() => ({ message: response.statusText }));
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorResult.message}`);
            }
            const result = await response.json();

            if (result.status === 'success' && result.data && Array.isArray(result.data.items)) {
                // Adapter la structure des données si nécessaire pour updateProductsGridUI
                const productsForUI = result.data.items.map(p => ({
                    id: p.id,
                    name: p.name,
                    price: p.price,
                    unit: p.unit,
                    stockQuantity: p.stock_quantity,
                    imageUrl: p.image_url ? `/agriflow/${p.image_url}` : 'placeholder.jpg', // Assurer le chemin correct
                    stockStatus: p.stock_quantity > 10 ? "En stock" : (p.stock_quantity > 0 ? "Stock faible" : "Rupture") // Logique de statut de stock
                }));
                this.state.products = productsForUI;
                this.updateProductsGridUI(productsForUI);
                console.log("Products loaded from API.");
            } else {
                console.error('Failed to fetch products or data format incorrect:', result.message || 'No data items');
                this.updateProductsGridUI([]);
            }
        } catch (error) {
            console.error('Error fetching products:', error);
            this.updateProductsGridUI([]); // Afficher une grille vide ou un message d'erreur
        }
    },

    async editProduct(productId) {
        console.log(`Editing product ID: ${productId}`);
        try {
            const response = await fetch(`/agriflow/api/products/${productId}`);
            if (!response.ok) {
                const err = await response.json().catch(() => ({ message: response.statusText }));
                throw new Error(`Erreur HTTP ${response.status}: ${err.message}`);
            }
            const product = await response.json();
            if (product.status === 'success' && product.data) {
                const productData = product.data;
                // Pré-remplir le formulaire
                document.getElementById('product-name').value = productData.name || '';
                document.getElementById('product-category').value = productData.category || '';
                document.getElementById('product-price').value = productData.price || '';
                document.getElementById('product-unit').value = productData.unit || '';
                document.getElementById('product-quantity-value').value = productData.stock_quantity || '';
                document.getElementById('product-description').value = productData.description || '';
                // Pour l'image, on ne peut pas pré-remplir un <input type="file"> pour des raisons de sécurité.
                // On pourrait afficher l'image actuelle à côté.
                const imagePreview = document.getElementById('product-image-preview'); // Supposons qu'un tel élément existe ou sera ajouté
                if (imagePreview && productData.image_url) {
                    imagePreview.src = `/agriflow/${productData.image_url}`; // Adapter le chemin si nécessaire
                    imagePreview.style.display = 'block';
                } else if (imagePreview) {
                    imagePreview.style.display = 'none';
                }


                this.state.editingProductId = productId;
                document.getElementById('save-product-button').textContent = 'Modifier le produit';
                document.getElementById('product-form').classList.remove('hidden');
                document.getElementById('product-name').focus(); // Mettre le focus sur le premier champ
            } else {
                alert(`Impossible de récupérer les détails du produit: ${product.message || 'Format de réponse incorrect.'}`);
            }
        } catch (error) {
            console.error('Error fetching product for edit:', error);
            alert(`Une erreur est survenue lors de la récupération du produit: ${error.message}`);
        }
    },

    async handleUpdateProductInternal() {
        if (!this.state.editingProductId) return;

        const productId = this.state.editingProductId;
        const name = document.getElementById('product-name').value;
        const category = document.getElementById('product-category').value;
        const price = document.getElementById('product-price').value;
        const unit = document.getElementById('product-unit').value;
        const quantity = document.getElementById('product-quantity-value').value;
        const description = document.getElementById('product-description').value;
        const imageFile = document.getElementById('product-image-file').files[0]; // Peut être undefined si pas de nouvelle image

        if (!name || !category || !price || !unit || !quantity) {
            alert('Nom, catégorie, prix, unité et quantité sont requis pour la modification.');
            return;
        }

        const formData = new FormData();
        formData.append('name', name);
        formData.append('category', category);
        formData.append('price', parseFloat(price));
        formData.append('unit', unit);
        formData.append('stock_quantity', parseInt(quantity));
        formData.append('description', description);
        if (imageFile) { // N'ajouter l'image que si une nouvelle a été sélectionnée
            formData.append('image', imageFile);
        }
        // Il faut aussi envoyer les autres champs comme is_bio, is_available si gérés
        // formData.append('_method', 'PUT'); // Si le backend est configuré pour utiliser _method avec POST

        console.log(`Updating product ID: ${productId} with FormData:`, Object.fromEntries(formData));

        try {
            // Utiliser POST car FormData avec PUT peut être problématique et notre API route POST vers updateProduct
            const response = await fetch(`/agriflow/api/products/${productId}`, {
                method: 'POST', // Ou PUT si le backend gère bien FormData avec PUT et que le client l'envoie correctement
                body: formData,
                // headers: { 'Authorization': `Bearer ${this.getAuthToken()}` }
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                alert(result.message || 'Produit modifié avec succès!');
                document.getElementById('product-form-html').reset();
                const imagePreview = document.getElementById('product-image-preview');
                if (imagePreview) imagePreview.style.display = 'none';
                document.getElementById('product-form').classList.add('hidden');
                document.getElementById('save-product-button').textContent = 'Enregistrer';
                this.state.editingProductId = null;
                this.fetchProducts();
            } else {
                console.error('Failed to update product:', result.message, result.errors);
                let errorMessage = result.message || `Erreur HTTP: ${response.status}`;
                if (result.errors) errorMessage += "\nDétails: " + JSON.stringify(result.errors);
                alert(`Erreur lors de la modification du produit: ${errorMessage}`);
            }
        } catch (error) {
            console.error('Error updating product:', error);
            alert('Une erreur réseau ou une exception JavaScript est survenue lors de la modification du produit.');
        }
    },

    async handleAddProductInternal() { // Renommée
        const name = document.getElementById('product-name').value;
        const category = document.getElementById('product-category').value;
        const price = document.getElementById('product-price').value;
        const unit = document.getElementById('product-unit').value; // Décommenté et lu
        const quantity = document.getElementById('product-quantity-value').value;
        const description = document.getElementById('product-description').value;
        const imageFile = document.getElementById('product-image-file').files[0];

        // Basic validation - ajout de unit et category pour la validation si nécessaire
        if (!name || !price || !quantity || !unit || !category) {
            alert('Nom, catégorie, prix, unité et quantité sont requis.');
            return;
        }

        const formData = new FormData();
        formData.append('name', name);
        formData.append('category', category);
        formData.append('price', parseFloat(price));
        formData.append('unit', unit); // Ajouté au FormData
        formData.append('stock_quantity', parseInt(quantity));
        formData.append('description', description);
        if (imageFile) {
            formData.append('image', imageFile); // 'image' est le nom attendu par le backend pour $_FILES['image']
        }
        // Ajouter is_bio et is_available si ces champs existent dans le formulaire HTML
        // Exemple:
        // const isBioCheckbox = document.getElementById('product-is-bio');
        // if (isBioCheckbox) formData.append('is_bio', isBioCheckbox.checked);


        console.log("Adding product with FormData:", Object.fromEntries(formData));

        try {
            const response = await fetch('/agriflow/api/products', { // Endpoint corrigé
                method: 'POST',
                body: formData,
                // Les headers pour FormData sont généralement définis automatiquement par le navigateur,
                // y compris le Content-Type: multipart/form-data; boundary=...
                // Ne pas mettre 'Content-Type': 'application/json' pour FormData.
                // headers: { 'Authorization': `Bearer ${this.getAuthToken()}` } // Si authentification par token
            });

            // Tenter de lire la réponse comme JSON, même si ce n'est pas ok, pour avoir le message d'erreur du backend
            const result = await response.json();

            if (response.ok && result.status === 'success') {
                alert(result.message || 'Produit ajouté avec succès!');
                document.getElementById('product-form-html').reset(); // ID du formulaire
                document.getElementById('product-form').classList.add('hidden'); // Cacher la section formulaire
                this.fetchProducts(); // Rafraîchir la liste des produits
            } else {
                console.error('Failed to add product:', result.message, result.errors);
                let errorMessage = result.message || `Erreur HTTP: ${response.status}`;
                if (result.errors) {
                    errorMessage += "\nDétails: " + JSON.stringify(result.errors);
                }
                alert(`Erreur lors de l'ajout du produit: ${errorMessage}`);
            }
        } catch (error) {
            console.error('Error adding product:', error);
            alert('Une erreur réseau ou une exception JavaScript est survenue lors de l\'ajout du produit.');
        }
    },


    async fetchOrders() {
        // HYPOTHETICAL ENDPOINT: GET /api/producer/orders
        // Expected response:
        // {
        //   status: "success",
        //   data: [
        //     { id: 1, customerName: "Client A", itemsPreview: "Produit X, Produit Y", totalAmount: 12000, status: "En attente" }, ...
        //   ]
        // }
        console.log("Fetching orders for 'Commandes' tab...");
        try {
            // const response = await fetch('/api/producer/orders');
            // if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            // const result = await response.json();
            // if (result.status === 'success') {
            //     this.state.orders = result.data;
            //     this.updateOrdersTableUI(result.data);
            // } else {
            //     console.error('Failed to fetch orders:', result.message);
            // }
            const userData = JSON.parse(localStorage.getItem('user'));
            if (!userData || !userData.producer_profile_id) {
                console.error('Producer ID not found. Cannot fetch orders for tab.');
                this.updateOrdersTableUI([]);
                return;
            }
            const producerId = userData.producer_profile_id;
            // TODO: Gérer la pagination pour cet onglet
            const page = this.state.ordersCurrentPage || 1; // Supposons une gestion de pagination
            const perPage = 10;

            const response = await fetch(`/agriflow/api/orders?producer_id=${producerId}&page=${page}&per_page=${perPage}`);
            if (!response.ok) {
                const err = await response.json().catch(() => ({ message: response.statusText }));
                throw new Error(`HTTP error ${response.status}: ${err.message}`);
            }
            const result = await response.json();

            if (result.status === 'success' && result.data && Array.isArray(result.data.items)) {
                 const ordersForUI = result.data.items.map(order => ({
                    id: order.id,
                    client: `Client ${order.customer_id}`, // Idéalement, joindre le nom du client
                    produits: `Items: ${order.items_count}`, // Simplifié, pourrait lister les produits
                    montant: `${order.total_amount.toLocaleString('fr-FR')} FCFA`,
                    statut: this.translateOrderStatus(order.status)
                }));
                this.state.orders = ordersForUI; // Mettre à jour l'état si utilisé ailleurs
                this.updateOrdersTableUI(ordersForUI);
                // TODO: Mettre à jour la pagination UI avec result.data.pagination
                console.log("Orders loaded from API for tab.");
            } else {
                console.error('Failed to fetch orders for tab or data format incorrect:', result.message);
                this.updateOrdersTableUI([]);
            }
        } catch (error) {
            console.error('Error fetching orders:', error);
            this.updateOrdersTableUI([]);
        }
    },

    translateOrderStatus(statusKey) { // Helper pour traduire les statuts si besoin
        const statusMap = {
            'en_attente': 'En attente',
            'confirmee': 'Confirmée',
            'en_preparation': 'En préparation',
            'en_livraison': 'En livraison',
            'livree': 'Livrée',
            'annulee': 'Annulée'
        };
        return statusMap[statusKey] || statusKey;
    },

    async fetchDeliveries() {
        // HYPOTHETICAL ENDPOINT: GET /api/producer/deliveries
        // Expected response:
        // {
        //   status: "success",
        //   data: [
        //     { id: 1, orderId: 101, customerName: "Client A", estimatedDate: "2024-07-20", status: "Prêt pour collecte" }, ...
        //   ]
        // }
        console.log("Fetching deliveries...");
        try {
            // const response = await fetch('/api/producer/deliveries');
            // if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            // const result = await response.json();
            // if (result.status === 'success') {
            //     this.state.deliveries = result.data;
            //     this.updateDeliveriesTableUI(result.data);
            // } else {
            //     console.error('Failed to fetch deliveries:', result.message);
            // }
            // --- MOCK DATA FOR NOW ---
            const mockData = [
                { numero: 'LIVP001', client: 'Alice Dupont', date: '2024-06-01', statut: 'Livré' },
                { numero: 'LIVP002', client: 'Bob Martin', date: '2024-06-02', statut: 'En cours de préparation' },
                { numero: 'LIVP003', client: 'Charles D.', date: '2024-06-05', statut: 'En attente de livreur' }
            ];
            this.state.deliveries = mockData;
            this.updateDeliveriesTableUI(mockData);
            console.log("Mock deliveries loaded.");
            // --- END MOCK DATA ---
        } catch (error) {
            console.error('Error fetching deliveries:', error);
        }
    },

    async fetchFullStats() {
        // HYPOTHETICAL ENDPOINT: GET /api/producer/statistics
        // Expected response:
        // {
        //   status: "success",
        //   data: {
        //     totalOrders: 152,
        //     totalSales: 1250000, currency: "FCFA",
        //     // ... other detailed stats for the "Statistiques" tab
        //   }
        // }
        console.log("Fetching full stats for 'Statistiques' tab...");
        try {
            // const response = await fetch('/api/producer/statistics');
            // if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            // const result = await response.json();
            // if (result.status === 'success') {
            //     this.state.stats.full = result.data;
            //     this.updateStatisticsTabUI(result.data);
            // } else {
            //     console.error('Failed to fetch full stats:', result.message);
            // }
            // --- MOCK DATA FOR NOW ---
            const mockData = {
                totalOrders: 152,
                totalSales: 1250000, currency: "FCFA",
            };
            this.state.stats.full = mockData;
            this.updateStatisticsTabUI(mockData);
            console.log("Mock full stats loaded.");
            // --- END MOCK DATA ---
        } catch (error) {
            console.error('Error fetching full stats:', error);
        }
    },

    // --- UI Update Functions ---
    updateDashboardStatsUI(data) {
        document.getElementById('stats-ventes-jour-montant').textContent = `${data.salesToday.amount.toLocaleString('fr-FR')} ${data.salesToday.currency}`;
        document.getElementById('stats-ventes-jour-variation').textContent = data.salesToday.trend;
        document.getElementById('stats-commandes-total').textContent = data.ordersToday.count;
        document.getElementById('stats-commandes-nouvelles').textContent = `${data.ordersToday.new} nouvelles aujourd'hui`;
        document.getElementById('stats-produits-total').textContent = data.totalProducts.count;
        document.getElementById('stats-produits-stock-faible').textContent = `${data.totalProducts.lowStock} stocks faibles`;
        document.getElementById('stats-clients-total').textContent = data.activeCustomers.count; // Name needs to match HTML
        document.getElementById('stats-clients-nouveaux').textContent = `${data.activeCustomers.newThisWeek} cette semaine`; // Name needs to match HTML
    },

    initSalesChart() {
        const chartDom = document.getElementById('salesChart');
        if (!chartDom || typeof echarts === 'undefined') {
            console.warn('Sales chart DOM or ECharts library not found.');
            return;
        }
        this.state.salesChartInstance = echarts.init(chartDom);
        const option = {
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '4%', bottom: '3%', containLabel: true },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: [], // Will be populated by updateSalesChart
                axisLine: { lineStyle: { color: '#ddd' } },
                axisLabel: { color: '#1f2937' }
            },
            yAxis: {
                type: 'value',
                axisLine: { show: false },
                axisLabel: { color: '#1f2937', formatter: '{value} FCFA' }, // Assuming FCFA
                splitLine: { lineStyle: { color: '#eee' } }
            },
            series: [{
                name: 'Ventes',
                type: 'line',
                smooth: true,
                data: [], // Will be populated
                lineStyle: { width: 3, color: 'rgba(76, 175, 80, 1)' }, // Primary color
                areaStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: 'rgba(76, 175, 80, 0.3)' },
                        { offset: 1, color: 'rgba(76, 175, 80, 0.05)' }
                    ])
                },
                emphasis: { focus: 'series' },
                showSymbol: false,
            }]
        };
        this.state.salesChartInstance.setOption(option);
        window.addEventListener('resize', () => this.state.salesChartInstance.resize());
    },

    updateSalesChart(monthlySalesData) { // monthlySalesData = [{ month: "Jan", sales: 1200 }, ...]
        if (!this.state.salesChartInstance || !monthlySalesData) return;
        const months = monthlySalesData.map(d => d.month);
        const sales = monthlySalesData.map(d => d.sales);
        this.state.salesChartInstance.setOption({
            xAxis: { data: months },
            series: [{ data: sales }]
        });
    },

    updateRecentOrdersUI(orders) {
        const tbody = document.getElementById('dashboard-recent-orders-tbody');
        if (!tbody) return;
        tbody.innerHTML = ''; // Clear existing
        if (!orders || orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Aucune commande récente.</td></tr>';
            return;
        }
        orders.forEach(order => {
            const row = tbody.insertRow();
            row.className = 'border-t border-gray-100';
            row.innerHTML = `
                <td class="py-3">${order.customerName}</td>
                <td class="py-3">${order.itemsPreview}</td>
                <td class="py-3">${order.totalAmount.toLocaleString('fr-FR')} ${order.currency}</td>
                <td class="py-3"><span class="px-2 py-1 ${this.getOrderStatusClass(order.status)} rounded text-xs">${order.status}</span></td>
                <td class="py-3">
                    <button class="text-primary hover:text-primary-dark" onclick="ProducerDashboard.viewOrder(${order.id})">Détails</button>
                </td>
            `;
        });
    },

    updateProductsGridUI(products) {
        const grid = document.getElementById('products-grid');
        if (!grid) return;
        grid.innerHTML = ''; // Clear existing
        if (!products || products.length === 0) {
            grid.innerHTML = '<p class="col-span-full text-center py-4">Aucun produit trouvé.</p>';
            return;
        }
        products.forEach(product => {
            const productCard = `
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition">
                    <div class="h-48 bg-gray-100 relative">
                        <img src="${product.imageUrl || 'placeholder.jpg'}" alt="${product.name}" class="w-full h-full object-cover">
                        <span class="absolute top-2 right-2 ${this.getProductStockClass(product.stockStatus)} text-white px-2 py-1 rounded text-xs">${product.stockStatus}</span>
                    </div>
                    <div class="p-4">
                        <h4 class="font-semibold text-lg">${product.name}</h4>
                        <div class="flex justify-between items-center mt-2">
                            <span class="text-primary font-bold">${product.price.toLocaleString('fr-FR')} FCFA/${product.unit}</span>
                            <div class="flex items-center">
                                <i class="ri-edit-box-line text-gray-500 hover:text-primary cursor-pointer mr-2" onclick="ProducerDashboard.editProduct(${product.id})"></i>
                                <i class="ri-delete-bin-line text-gray-500 hover:text-red-500 cursor-pointer" onclick="ProducerDashboard.deleteProduct(${product.id})"></i>
                            </div>
                        </div>
                        <p class="text-sm text-gray-600 mt-1">Stock: ${product.stockQuantity}</p>
                    </div>
                </div>
            `;
            grid.innerHTML += productCard;
        });
    },

    updateOrdersTableUI(orders) {
        const tbody = document.getElementById('orders-tbody');
        if (!tbody) return;
        tbody.innerHTML = ''; // Clear existing
        if (!orders || orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Aucune commande.</td></tr>';
            return;
        }
        orders.forEach(order => {
            const row = tbody.insertRow();
            row.className = 'border-t border-gray-100';
            // Columns: Client, Produits, Montant, Statut, Action
            row.innerHTML = `
                <td class="py-3">${order.client}</td>
                <td class="py-3">${order.produits}</td>
                <td class="py-3">${order.montant}</td>
                <td class="py-3"><span class="px-2 py-1 ${this.getOrderStatusClass(order.statut)} rounded text-xs">${order.statut}</span></td>
                <td class="py-3"><button class="text-primary hover:text-primary-dark" onclick="ProducerDashboard.viewOrder(${order.id})">Détails</button></td>
            `;
        });
    },

    updateDeliveriesTableUI(deliveries) {
        const tbody = document.getElementById('deliveries-tbody');
        if (!tbody) return;
        tbody.innerHTML = ''; // Clear existing
        if (!deliveries || deliveries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Aucune livraison.</td></tr>';
            return;
        }
        deliveries.forEach(delivery => {
            const row = tbody.insertRow();
            row.className = 'border-t border-gray-100';
            // Columns: Numéro, Client, Date estimée, Statut, Action
            row.innerHTML = `
                <td class="py-3">${delivery.numero}</td>
                <td class="py-3">${delivery.client}</td>
                <td class="py-3">${delivery.date}</td>
                <td class="py-3"><span class="px-2 py-1 ${this.getDeliveryStatusClass(delivery.statut)} rounded text-xs">${delivery.statut}</span></td>
                <td class="py-3"><button class="text-primary hover:text-primary-dark" onclick="ProducerDashboard.viewDeliveryDetails(${delivery.numero.replace('LIVP','')})">Détails</button></td>
            `;
        });
    },

    updateStatisticsTabUI(stats) {
        const ordersCountEl = document.getElementById('stat-orders-count');
        const salesAmountEl = document.getElementById('stat-sales-amount');
        if (ordersCountEl) ordersCountEl.textContent = stats.totalOrders.toLocaleString('fr-FR');
        if (salesAmountEl) salesAmountEl.textContent = `${stats.totalSales.toLocaleString('fr-FR')} ${stats.currency}`;
    },

    // --- Helper Functions ---
    getOrderStatusClass(status) {
        // Similar to client dashboard, adjust if producer statuses differ
        const statusMap = {
            'En préparation': 'bg-yellow-100 text-yellow-800',
            'En attente': 'bg-orange-100 text-orange-800',
            'Prêt pour collecte': 'bg-blue-100 text-blue-800',
            'En livraison': 'bg-blue-100 text-blue-800', // Consider different color
            'Livré': 'bg-green-100 text-green-800',
            'Annulé': 'bg-red-100 text-red-800'
        };
        return statusMap[status] || 'bg-gray-100 text-gray-800';
    },

    getDeliveryStatusClass(status) {
        // May need specific statuses for producer view
        const classMap = {
            'En attente de livreur': 'bg-yellow-100 text-yellow-800',
            'En cours de préparation': 'bg-purple-100 text-purple-800',
            'Prêt pour envoi': 'bg-indigo-100 text-indigo-800',
            'En transit': 'bg-blue-100 text-blue-800',
            'Livré': 'bg-green-100 text-green-800',
            'Problème': 'bg-red-100 text-red-800'
        };
        return classMap[status] || 'bg-gray-100 text-gray-800';
    },

    getProductStockClass(stockStatus) {
        const classMap = {
            'En stock': 'bg-primary', // Green
            'Stock faible': 'bg-yellow-500', // Yellow/Orange
            'Rupture': 'bg-red-500' // Red
        };
        return classMap[stockStatus] || 'bg-gray-500';
    },

    // Placeholder for actual action handlers
    viewOrder(orderId) {
        console.log(`Viewing order details for ID: ${orderId}`);
        // Implement modal or navigation to order detail page
        alert(`Afficher les détails de la commande: ${orderId} (à implémenter)`);
    },
    editProduct(productId) {
        console.log(`Editing product ID: ${productId}`);
        alert(`Modifier le produit: ${productId} (à implémenter - pré-remplir le formulaire)`);
         // 1. Fetch product details by ID
         // 2. Populate the product form with these details
         // 3. Change "Ajouter un produit" button to "Modifier le produit"
         // 4. Change form submission to call an updateProduct(productId) method
         // 5. Show the form
    },
    async deleteProduct(productId) { // Ajout de async
        console.log(`Deleting product ID: ${productId}`);
        if (confirm(`Voulez-vous vraiment supprimer le produit ID ${productId}?`)) {
            try {
                const response = await fetch(`/agriflow/api/products/${productId}`, {
                    method: 'DELETE',
                    // headers: { 'Authorization': `Bearer ${this.getAuthToken()}` } // Si authentification par token
                });

                if (response.ok) { // Statut 200-299, inclut 204 No Content pour DELETE réussi
                    // Pour une réponse 204, response.json() causerait une erreur car le corps est vide.
                    // On peut vérifier le statut explicitement.
                    if (response.status === 204) {
                        alert('Produit supprimé avec succès!');
                    } else {
                        // Si on attendait un JSON avec un message de succès (pas typique pour 204)
                        const result = await response.json().catch(() => null); // Gérer le cas où il n'y a pas de JSON
                        alert(result?.message || 'Produit supprimé avec succès!');
                    }
                    this.fetchProducts(); // Rafraîchir la liste des produits
                } else {
                    const result = await response.json().catch(() => null); // Essayer de parser l'erreur JSON
                    console.error('Failed to delete product:', result?.message || response.statusText);
                    alert(`Erreur lors de la suppression du produit: ${result?.message || response.statusText}`);
                }
            } catch (error) {
                console.error('Error deleting product:', error);
                alert('Une erreur réseau ou une exception JavaScript est survenue lors de la suppression du produit.');
            }
        }
    },
    viewDeliveryDetails(deliveryId){
        console.log(`Viewing delivery details for ID: ${deliveryId}`);
        alert(`Afficher les détails de la livraison: ${deliveryId} (à implémenter)`);
    }
};

// Initialize the dashboard when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    ProducerDashboard.init();
});

// --- Fonctions pour la section Paramètres ---

ProducerDashboard.loadProducerProfileSettings = async function() {
    console.log("Loading producer profile settings...");
    try {
        const response = await fetch('/agriflow/api/producers/my-profile');
        if (!response.ok) {
            const err = await response.json().catch(() => ({ message: response.statusText }));
            throw new Error(`Erreur HTTP ${response.status}: ${err.message}`);
        }
        const result = await response.json();
        if (result.status === 'success' && result.data) {
            const profileData = result.data;
            document.getElementById('settings-farm-name').value = profileData.farm_name || '';
            document.getElementById('settings-description').value = profileData.farm_description || ''; // farm_description dans le modèle Producer
            document.getElementById('settings-address').value = profileData.farm_address || ''; // farm_address dans le modèle Producer
            document.getElementById('settings-phone').value = profileData.phone_number || ''; // Ce champ n'est pas dans ProducerModel, peut-être dans UserModel?
            // Si phone_number est dans User, il faudrait un autre appel ou joindre les données.
            // Pour l'instant, on suppose qu'il pourrait être dans producer profile ou qu'on l'ajoute.
            // Le modèle Producer a farm_photo_url, pas profile_picture_url.
            document.getElementById('settings-profile-picture').value = profileData.farm_photo_url || '';

            const imgPreview = document.getElementById('settings-profile-preview');
            if (profileData.farm_photo_url) {
                imgPreview.src = profileData.farm_photo_url; // Supposant que c'est une URL absolue ou gérée correctement
                imgPreview.classList.remove('hidden');
            } else {
                imgPreview.src = '#';
                imgPreview.classList.add('hidden');
            }
        } else {
            console.error("Failed to load producer profile settings:", result.message);
            alert("Impossible de charger les informations du profil producteur.");
        }
    } catch (error) {
        console.error('Error fetching producer profile settings:', error);
        alert(`Une erreur est survenue: ${error.message}`);
    }
};

ProducerDashboard.handleSaveProducerProfile = async function(event) {
    event.preventDefault();
    console.log("Saving producer profile...");

    const farmName = document.getElementById('settings-farm-name').value;
    const description = document.getElementById('settings-description').value;
    const address = document.getElementById('settings-address').value;
    const phone = document.getElementById('settings-phone').value; // À voir où stocker ce champ
    const profilePictureUrl = document.getElementById('settings-profile-picture').value;

    const dataToUpdate = {
        farm_name: farmName,
        farm_description: description,
        farm_address: address,
        // phone_number: phone, // Si on décide de le stocker dans le profil producteur
        farm_photo_url: profilePictureUrl
        // Ajouter d'autres champs du ProducerModel si nécessaire (siret, experience_years, etc.)
    };

    try {
        const response = await fetch('/agriflow/api/producers/my-profile', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dataToUpdate)
        });
        const result = await response.json();
        if (response.ok && result.status === 'success') {
            alert(result.message || "Profil mis à jour avec succès!");
            // Mettre à jour le nom de la ferme dans la navbar si changé
            if (dataToUpdate.farm_name && document.getElementById('farm-name')) {
                 document.getElementById('farm-name').textContent = dataToUpdate.farm_name;
            }
             if (dataToUpdate.farm_name && document.getElementById('mobile-farm-name')) {
                 document.getElementById('mobile-farm-name').textContent = dataToUpdate.farm_name;
            }
        } else {
            console.error("Failed to save producer profile:", result.message);
            alert(`Erreur lors de la sauvegarde du profil: ${result.message || response.statusText}`);
        }
    } catch (error) {
        console.error('Error saving producer profile:', error);
        alert(`Une erreur réseau ou JavaScript est survenue: ${error.message}`);
    }
};

ProducerDashboard.handleChangePassword = async function(event) {
    event.preventDefault();
    console.log("Changing password...");

    const currentPassword = document.getElementById('settings-current-password').value;
    const newPassword = document.getElementById('settings-new-password').value;
    const confirmPassword = document.getElementById('settings-confirm-password').value;

    if (newPassword !== confirmPassword) {
        alert("Le nouveau mot de passe et sa confirmation ne correspondent pas.");
        return;
    }
    if (newPassword.length < 6) {
        alert("Le nouveau mot de passe doit contenir au moins 6 caractères.");
        return;
    }

    const data = {
        current_password: currentPassword,
        new_password: newPassword,
        confirm_new_password: confirmPassword // Le backend s'attend à confirm_new_password
    };

    try {
        const response = await fetch('/agriflow/api/users/me/change-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        if (response.ok && result.status === 'success') {
            alert(result.message || "Mot de passe changé avec succès!");
            document.getElementById('change-password-form').reset();
        } else {
            console.error("Failed to change password:", result.message);
            alert(`Erreur lors du changement de mot de passe: ${result.message || response.statusText}`);
        }
    } catch (error) {
        console.error('Error changing password:', error);
        alert(`Une erreur réseau ou JavaScript est survenue: ${error.message}`);
    }
};


// Modification de bindEvents pour ajouter les listeners des formulaires de paramètres
// et pour charger les données quand l'onglet est activé.
const originalBindEvents = ProducerDashboard.bindEvents;
ProducerDashboard.bindEvents = function() {
    originalBindEvents.call(this); // Appelle la fonction bindEvents originale

    const producerProfileForm = document.getElementById('producer-profile-form');
    if (producerProfileForm) {
        producerProfileForm.addEventListener('submit', this.handleSaveProducerProfile.bind(this));
    }

    const changePasswordForm = document.getElementById('change-password-form');
    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', this.handleChangePassword.bind(this));
    }

    // Charger les données du profil producteur lorsque l'onglet Paramètres est activé
    const settingsTab = document.querySelector('.nav-tab[data-target="parametres"]');
    if (settingsTab) {
        settingsTab.addEventListener('click', () => {
            // S'assurer que l'onglet est bien actif avant de charger (au cas où le clic est intercepté)
            // La logique d'activation d'onglet est dans tableau-producteur.html, elle devrait fonctionner avant.
            // Un petit délai peut aider si l'activation est asynchrone (peu probable ici)
            setTimeout(() => {
                if (document.getElementById('parametres-content').classList.contains('active')) {
                    this.loadProducerProfileSettings();
                }
            }, 0);
        });
    }
     // Gérer le cas où l'onglet paramètres est déjà actif au chargement de la page (si implémenté)
    if (document.getElementById('parametres-content') && document.getElementById('parametres-content').classList.contains('active')) {
        this.loadProducerProfileSettings();
    }
};
