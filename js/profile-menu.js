document.addEventListener('DOMContentLoaded', function () {
    const profileButton = document.getElementById('profile-menu-button');
    const profileDropdown = document.getElementById('profile-dropdown');
    const authButtons = document.getElementById('auth-buttons');
    const userProfile = document.getElementById('user-profile');
    const userEmail = document.getElementById('user-email');

    // Gestion du menu déroulant du profil
    if (profileButton && profileDropdown) {
        profileButton.addEventListener('click', function () {
            const expanded = profileButton.getAttribute('aria-expanded') === 'true';
            profileButton.setAttribute('aria-expanded', !expanded);
            profileDropdown.classList.toggle('hidden');
        });

        // Fermer le menu lors d'un clic à l'extérieur
        document.addEventListener('click', function (event) {
            if (!profileButton.contains(event.target) && !profileDropdown.contains(event.target)) {
                profileButton.setAttribute('aria-expanded', 'false');
                profileDropdown.classList.add('hidden');
            }
        });
    }

    // Vérifier l'état de connexion et mettre à jour l'interface
    function checkAuthState() {
        const user = JSON.parse(localStorage.getItem('user'));
        if (user && user.email) {
            if (authButtons) authButtons.classList.add('hidden');
            if (userProfile) userProfile.classList.remove('hidden');
            // Affichage dynamique du nom (ferme ou client)
            let displayName = user.farm_name ? user.farm_name : user.email;
            if (user.role === 'client' && user.delivery_address) {
                displayName = user.email + ' (' + user.delivery_address + ')';
            }
            if (userEmail) userEmail.textContent = displayName;
            // Affichage de la photo de profil si dispo
            const profileImg = document.getElementById('profile-img');
            if (profileImg) {
                if (user.farm_photo_url) {
                    profileImg.src = user.farm_photo_url;
                } else {
                    profileImg.src = 'https://readdy.ai/api/search-image?query=professional%20farmer%20portrait%2C%20close-up%20headshot%2C%20neutral%20background%2C%2040%20year%20old%20man&width=40&height=40&seq=3&orientation=squarish';
                }
            }
            // Gérer la redirection du tableau de bord en fonction du rôle
            const dashboardLink = document.getElementById('dashboard-link');
            if (dashboardLink) {
                dashboardLink.onclick = function (e) {
                    e.preventDefault();
                    const user = JSON.parse(localStorage.getItem('user'));
                    if (user && user.role) {
                        const dashboardUrl = user.role === 'producteur' ? '/agriflow/tableau-producteur.html' : '/agriflow/tableau-client.html';
                        window.location.replace(dashboardUrl);
                    } else {
                        window.location.replace('/agriflow/login.html');
                    }
                };
            }
        } else {
            if (authButtons) authButtons.classList.remove('hidden');
            if (userProfile) userProfile.classList.add('hidden');
        }
    }

    // Vérifier l'état de connexion au chargement
    checkAuthState();

    // Gérer la déconnexion
    window.logout = async function () {
        try {
            await fetch('/agriflow/auth/process.php?action=logout');
            localStorage.removeItem('user');
            localStorage.removeItem('token');
            checkAuthState();
            window.location.replace('/agriflow/accueil.html');
        } catch (error) {
            console.error('Erreur lors de la déconnexion:', error);
            // On déconnecte quand même localement en cas d'erreur
            localStorage.removeItem('user');
            localStorage.removeItem('token');
            checkAuthState();
            window.location.replace('/agriflow/accueil.html');
        }
    };
});