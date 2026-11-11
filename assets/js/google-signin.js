/**
 * Google's sign-in button calls this (data-callback on login.php) with a
 * signed token; post it to our own server, which checks it (login-google.php).
 */
window.googleSignIn = function (response) {
    var form = document.getElementById('google-form');
    if (form && response && response.credential) {
        form.elements.credential.value = response.credential;
        form.submit();
    }
};
