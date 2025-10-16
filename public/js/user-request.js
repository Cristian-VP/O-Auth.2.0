async function loadDataUser() {
    await axios.get('../src/user.php', {withCredentials: true})
    .then(function (response) {
        const user = response.data || {};
        const imgElement = document.querySelector('.profile-picture');
        const picture = (user.profile_picture ?? '').trim();

        
        if(!picture) {
           imgElement.src = '../public/assets/default-profile.png';
        } else {
           imgElement.src = picture;
        }

        document.querySelector('.user-name').textContent = user.username;
        document.querySelector('.user-email').textContent = user.email;
    })
        .catch(function (error) {
            const pathBackHome = '../index.html';
            const authTitleElement = document.querySelector('.title-auth-article');
            const currentPath = window.location.pathname;

            // Handle kind of errors
            switch (error.response.status) {
                case 400:
                    console.error('Bad request');
                    authTitleElement.textContent = 'Vaya, no tienes permiso para estar aquí';
                    break;
                case 401:
                    console.error('Unauthorized access');
                    authTitleElement.textContent = 'Tsst ... parece que no has iniciado sesión';
                    break;
                case 404:
                    console.error('User not found');
                    authTitleElement.textContent = 'No hemos podido encontrar tu usuario';
                    break;  
                case 500:
                    console.error('Server error');
                    authTitleElement.textContent = 'Oops ... algo salió mal en el servidor';
                    break;
                default:
                    console.error('An unexpected error occurred');
            }
        });
}

// Doom is ready
document.addEventListener('DOMContentLoaded', () => {
    loadDataUser().catch(err => console.error(err));
});