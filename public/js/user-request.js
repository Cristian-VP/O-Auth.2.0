async function loadDataUser() {
    await axios.get('../src/user.php')
    .then(function (response) {
        const user = response.data || {};
        const imgElement = document.querySelector('.profile-picture');
        const picture = (user.profile_picture ?? '').trim();

        
        if(!picture) {
           imgElement.src = '../public/assets/default-profile.png';
        } else {
           imgElement.src = picture;
        }

        document.getElementsByClassName('user-name')[0].textContent = user.username;
        document.getElementsByClassName('user-email')[0].textContent = user.email;
    })
        .catch(function (error) {
            console.error('Error fetching user data:', error);
        });
}
