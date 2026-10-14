(() => {
    const tools = document.querySelector('[data-library-tools]');
    const search = document.querySelector('#qb-search');
    if (!tools || !search) return;

    const rows = [...document.querySelectorAll('[data-question-set]')];
    const count = document.querySelector('[data-search-count]');
    const empty = document.querySelector('[data-no-results]');
    const normalize = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();
    const entries = rows.map(row => ({
        row,
        text: normalize(row.querySelector('.qb-set-cell').textContent),
    }));

    const filter = () => {
        const query = normalize(search.value.trim());
        let visible = 0;
        entries.forEach(({ row, text }) => {
            row.hidden = !text.includes(query);
            if (!row.hidden) visible += 1;
        });
        count.textContent = query ? `${visible} / ${rows.length} sets` : `${rows.length} ${rows.length === 1 ? 'set' : 'sets'}`;
        empty.hidden = visible > 0;
    };

    tools.hidden = false;
    search.addEventListener('input', filter);
    window.addEventListener('pageshow', filter);
    filter();
})();
