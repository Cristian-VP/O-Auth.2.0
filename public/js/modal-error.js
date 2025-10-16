document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const error = params.get('error');
    if (error) {
        const modal = document.getElementById('error-modal');
        const img = document.getElementById('error-img');
        img.src = `https://http.cat/${error}`;
        modal.classList.add('active');
    }
});