(() => {
    const counter = document.querySelector('[data-devices-online]');
    const badges = document.querySelectorAll('[data-device-id]');

    async function refreshPresence() {
        try {
            const response = await fetch('api_device_presence.php', {
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (response.status === 401) return;
            if (!response.ok) throw new Error('Unable to read device status');
            const data = await response.json();
            if (!data.success || !Array.isArray(data.devices)) {
                throw new Error('Invalid device status');
            }
            const online = new Set(
                data.devices.filter(device => device.status === 'online')
                    .map(device => String(device.id))
            );
            if (counter) counter.textContent = String(online.size);
            badges.forEach(badge => {
                const connected = online.has(badge.dataset.deviceId);
                badge.textContent = connected ? 'ONLINE' : 'OFFLINE';
                badge.classList.toggle('device-online', connected);
                badge.classList.toggle('device-offline', !connected);
            });
        } catch (error) {
            // Keep the last known display on a temporary network failure.
        }
        // Schedule after completion so slow requests cannot overlap.
        window.setTimeout(refreshPresence, 2000);
    }

    refreshPresence();
})();
