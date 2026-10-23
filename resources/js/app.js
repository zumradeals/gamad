// Le menu de l’accueil fonctionne nativement ; ce complément le referme après un choix.
document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.landing-mobile-menu a') : null;
    if (link) link.closest('details').open = false;
});
