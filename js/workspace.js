(() => {
    const navigation = document.querySelector('.ars-navigation');
    const activeLink = navigation?.querySelector('[aria-current="page"]');
    if (activeLink && navigation.scrollWidth > navigation.clientWidth) {
        navigation.scrollLeft = activeLink.offsetLeft - navigation.offsetLeft
            - (navigation.clientWidth - activeLink.offsetWidth) / 2;
    }
})();
