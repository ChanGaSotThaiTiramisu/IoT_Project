(() => {
    const form = document.querySelector('[data-register-form]');
    if (!form) return;

    const password = form.querySelector('#register_password');
    const confirmation = form.querySelector('#confirm_password');
    const mismatch = form.querySelector('#confirm-password-note');
    const capsLock = form.querySelector('#register-caps-lock');
    const submit = form.querySelector('[type="submit"]');
    const submitLabel = submit.querySelector('[data-submit-label]');
    const initialLabel = submitLabel.textContent;

    form.querySelectorAll('[data-password-toggle]').forEach(toggle => {
        const input = document.getElementById(toggle.getAttribute('aria-controls'));
        const label = input === confirmation ? 'confirmation password' : 'password';
        toggle.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(visible));
            toggle.setAttribute('aria-label', `${visible ? 'Hide' : 'Show'} ${label}`);
        });
    });

    const validateConfirmation = () => {
        const invalid = confirmation.value !== '' && confirmation.value !== password.value;
        confirmation.setCustomValidity(invalid ? 'Passwords do not match.' : '');
        mismatch.hidden = !invalid;
        if (invalid) confirmation.setAttribute('aria-invalid', 'true');
        else confirmation.removeAttribute('aria-invalid');
    };

    [password, confirmation].forEach(input => {
        input.addEventListener('input', validateConfirmation);
        const updateCapsLock = event => { capsLock.hidden = !event.getModifierState('CapsLock'); };
        input.addEventListener('keydown', updateCapsLock);
        input.addEventListener('keyup', updateCapsLock);
        input.addEventListener('blur', () => { capsLock.hidden = true; });
    });

    form.addEventListener('submit', event => {
        validateConfirmation();
        if (!form.reportValidity()) {
            event.preventDefault();
            return;
        }
        submit.disabled = true;
        submitLabel.textContent = 'Creating your account…';
        form.setAttribute('aria-busy', 'true');
    });

    window.addEventListener('pageshow', () => {
        submit.disabled = false;
        submitLabel.textContent = initialLabel;
        form.removeAttribute('aria-busy');
        validateConfirmation();
    });
})();
