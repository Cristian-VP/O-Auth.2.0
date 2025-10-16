async function loadDataUser() {
    await axios.get('../src/user.php', {withCredentials: true})
    .then(function (response) {
        const user = response.data || {};
        const imgElement = document.querySelector('.profile-picture');
        const picture = user.profile_picture;

        if(!picture || picture === null) {
            imgElement.src = '../assets/default_user.webp';
        } else {
            imgElement.src = picture.substring(
                picture.indexOf('h'),
                picture.length 
            )
        }

        document.querySelector('.user-name').textContent = reformatString('-', user.username);
        document.querySelector('.user-email').textContent = user.email;
    })
        .catch(function (error) {
            const authTitleElement = document.querySelector('.title-auth-article');
            
            // Manjando los errores y redirigiendo 
            switch (error.response.status) {
                case 400:
                    console.error('Bad request');
                    authTitleElement.textContent = 'Parece que tu solicitud es incorrecta';
                    navToHome()
                    break;
                case 401:
                    console.error('Unauthorized access');
                    authTitleElement.textContent = 'Tsst ... parece que no has iniciado sesión, no tienes acceso';
                    navToHome()
                    break;
                case 404:
                    console.error('User not found');
                    authTitleElement.textContent = 'No hemos podido encontrar tu usuario';
                    navToHome()
                    break;  
                case 500:
                    console.error('Server error');
                    authTitleElement.textContent = 'Oops ... algo salió mal en el servidor';
                    navToHome()
                    break;
                default:
                    console.error('An unexpected error occurred');
                    navToHome()
            }
        }
    );
}

function navToHome() {
    const pathBackHome = '../index.html';
    const currentPath = window.location.pathname;

    if (currentPath !== pathBackHome) {
        window.location.href = pathBackHome;
    }
}

function reformatString(separator, string) {
    return string.charAt(0).toUpperCase() + string.slice(1).split(separator).join(' ');
}
   

// Doom is ready
document.addEventListener('DOMContentLoaded', () => {
    loadDataUser().catch(err => console.error(err));
});