/**
 * Previews a theme color on the Admin Settings -> Theme tab the moment a
 * swatch is picked, without saving: it rewrites the page's #appThemeColors
 * <style> tag (the one includes/header.php prints) with the same light and
 * dark rules the server would produce, so the preview also follows the
 * light/dark toggle. Saving the form is what makes it permanent.
 */
(function () {
    var picker = document.getElementById('themePicker');
    var style = document.getElementById('appThemeColors');
    if (!picker || !style) {
        return;
    }

    picker.addEventListener('change', function (event) {
        var input = event.target;
        if (!(input instanceof HTMLInputElement) || input.name !== 'color') {
            return;
        }
        var d = input.dataset;
        style.textContent =
            ':root{--brand:' + d.lightBrand + ';--brand-hover:' + d.lightHover + ';}' +
            '[data-bs-theme="dark"]{--brand:' + d.darkBrand + ';--brand-hover:' + d.darkHover + ';}';
    });
})();
