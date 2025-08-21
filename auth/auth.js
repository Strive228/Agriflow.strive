// Fonction pour vérifier l'état de l'authentification
async function checkAuth() {
    try {
        const response = await fetch('/agriflow/auth/process.php?action=check_auth');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Erreur de vérification d\'authentification:', error);
        return { authenticated: false };
    }
}

// Protection des pages réservées
async function protectPage(allowedRole) {
    const auth = await checkAuth();
    if (!auth.authenticated || (allowedRole && auth.role !== allowedRole)) {
        window.location.href = '/agriflow/login.html';
    }
}

// Gestion du formulaire de connexion
function handleLogin(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    // Suppression de la ligne en double
    formData.append('action', 'login');

    // Change fetch URL to relative path
    fetch('/agriflow/auth/process.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            console.log('Réponse brute:', response);
            return response.text().then(text => {
                console.log('Texte de la réponse:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erreur de parsing JSON:', e);
                    throw new Error('Réponse invalide du serveur');
                }
            });
        })
        .then(data => {
            console.log('Données parsées:', data);
            if (data.success && data.user) {
                console.log('Connexion réussie:', data);
                localStorage.setItem('user', JSON.stringify(data.user));
                window.location.replace('/agriflow/accueil.html');
            } else if (data.errors) {
                console.error('Erreurs de connexion:', data.errors);
                showError(data.errors.join(', '));
            } else {
                console.error('Réponse inattendue:', data);
                showError('Une erreur inattendue est survenue');
            }
        })
        .catch(error => {
            console.error('Erreur de connexion:', error);
            showError(error.message || 'Une erreur est survenue lors de la connexion');
        });
}

// Gestion du formulaire d'inscription
function handleRegister(event) {
    event.preventDefault();
    if (!validateRegistrationForm(event.target)) {
        return;
    }

    const form = event.target;
    const formData = new FormData(form);
    // Remove the duplicate action parameter
    formData.append('action', 'register'); // Keep only this line

    // Ensure the fetch URL is relative
    fetch('/agriflow/auth/process.php', {
        method: 'POST',
        body: formData
    })
        .then(response => {
            if (response.redirected) {
                window.location.href = '/agriflow/accueil.html';
            }
        })
        .catch(error => {
            console.error('Erreur d\'inscription:', error);
            showError('Une erreur est survenue lors de l\'inscription');
        });
}

// Validation du formulaire d'inscription
function validateRegistrationForm(form) {
    const role = form.querySelector('input[name="role"]:checked').value;
    const email = form.querySelector('input[name="email"]').value;
    const password = form.querySelector('input[name="password"]').value;
    const phone = form.querySelector('input[name="phone"]').value;

    // Validation de l'email
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showError('Veuillez entrer une adresse email valide');
        return false;
    }

    // Validation du mot de passe
    if (!password || password.length < 8) {
        showError('Le mot de passe doit contenir au moins 8 caractères');
        return false;
    }

    // Validation du téléphone
    if (!phone || !/^[0-9\s\+\-\(\)]{7,20}$/.test(phone)) {
        showError('Please enter a valid phone number');
        return false;
    }

    // Validation spécifique au rôle
    if (role === 'producteur') {
        const farmName = form.querySelector('input[name="farm_name"]').value;
        const siret = form.querySelector('input[name="siret"]').value;

        if (!farmName) {
            showError('Le nom de la ferme est obligatoire');
            return false;
        }

        if (siret && !/^[0-9]{14}$/.test(siret)) {
            showError('Le numéro SIRET doit contenir 14 chiffres');
            return false;
        }
    } else {
        const deliveryAddressInput = form.querySelector('input[name="delivery_address"], textarea[name="delivery_address"]');
        const deliveryAddress = deliveryAddressInput ? deliveryAddressInput.value : '';

        if (!deliveryAddress) {
            showError('L\'adresse de livraison est obligatoire');
            return false;
        }
    }

    return true;
}

// Affichage des erreurs
function showError(message) {
    const errorDiv = document.getElementById('error-message');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    } else {
        alert(message);
    }
}

// Gestion de la déconnexion
function logout() {
    fetch('/agriflow/auth/process.php?action=logout')
        .then(() => {
            window.location.href = '/agriflow/index.html'; // Assurer la cohérence du chemin
        })
        .catch(error => {
            console.error('Erreur de déconnexion:', error);
        });
}

// Basculement entre les formulaires de connexion et d'inscription
function toggleForms() {
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const toggleButton = document.getElementById('toggle-form');

    const isLoginVisible = !loginForm.classList.contains('hidden');

    if (isLoginVisible) {
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        toggleButton.textContent = 'Se connecter';
    } else {
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
        toggleButton.textContent = 'Créer un compte';
    }
}

// Affichage des champs spécifiques au rôle
function toggleRoleFields() {
    const checkedRole = document.querySelector('input[name="role"]:checked');
    if (!checkedRole) return;

    const role = checkedRole.value;
    const producerFields = document.getElementById('producer-fields');
    const customerFields = document.getElementById('customer-fields');

    if (!producerFields || !customerFields) return;

    if (role === 'producteur') {
        producerFields.classList.remove('hidden');
        customerFields.classList.add('hidden');
    } else {
        producerFields.classList.add('hidden');
        customerFields.classList.remove('hidden');
    }
}

// Initialisation des événements
document.addEventListener('DOMContentLoaded', () => {
    // Gestion des formulaires
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const toggleButton = document.getElementById('toggle-form');

    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegister);
    }
    if (toggleButton) {
        toggleButton.addEventListener('click', toggleForms);
    }

    // Gestion du changement de rôle
    const roleInputs = document.querySelectorAll('input[name="role"]');
    roleInputs.forEach(input => {
        input.addEventListener('change', toggleRoleFields);
    });

    // Initialisation de l'affichage des champs selon le rôle
    if (roleInputs.length > 0 && document.getElementById('register-form') && !document.getElementById('register-form').classList.contains('hidden')) {
        toggleRoleFields();
    }

    // Gestion des erreurs dans l'URL
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    if (error) {
        showError(decodeURIComponent(error));
    }

    // Affichage du formulaire d'inscription si demandé
    if (urlParams.get('signup') === 'true') {
        // Par défaut, afficher le formulaire d'inscription client
        loginForm.classList.add('hidden');
        registerForm.classList.remove('hidden');
        // Sélectionner automatiquement le rôle client
        const clientRadio = document.querySelector('input[name="role"][value="client"]');
        if (clientRadio) {
            clientRadio.checked = true;
            toggleRoleFields();
        }
    }
});