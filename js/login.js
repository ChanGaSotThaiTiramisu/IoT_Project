(() => {
    const form = document.querySelector('[data-signin-form]');
    if (!form) return;

    const password = form.querySelector('#password');
    const toggle = form.querySelector('[data-password-toggle]');
    const capsLock = form.querySelector('#caps-lock');
    const submit = form.querySelector('[type="submit"]');
    const submitLabel = submit.querySelector('[data-submit-label]');

    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(visible));
        toggle.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    });

    const updateCapsLock = (event) => {
        capsLock.hidden = !event.getModifierState('CapsLock');
    };
    password.addEventListener('keydown', updateCapsLock);
    password.addEventListener('keyup', updateCapsLock);
    password.addEventListener('blur', () => { capsLock.hidden = true; });

    form.addEventListener('submit', () => {
        submit.disabled = true;
        submitLabel.textContent = 'Signing in…';
        form.setAttribute('aria-busy', 'true');
    });

    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        submitLabel.textContent = 'Sign in to your workspace';
        form.removeAttribute('aria-busy');
    });
})();
