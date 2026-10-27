// Le menu de l’accueil fonctionne nativement ; ce complément le referme après un choix.
document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.landing-mobile-menu a') : null;
    if (link) link.closest('details').open = false;
});

// Efface le seul brouillon dont la clé est donnée, après une publication réussie (la clé vient du serveur, jamais d’un balayage du stockage).
window.gamadDraftClear = (key) => {
    try { localStorage.removeItem(key); } catch {}
};

// Texte uniquement : aucun fichier ni réglage de visibilité conservé sur l’appareil.
window.gamadDraft = (wire, key) => ({
    saved: null, status: '', ready: false,
    fields: ['body', 'needPurpose', 'needTiming'],
    init() {
        try {
            const item = JSON.parse(localStorage.getItem(key) || 'null');
            if (item && Date.now() - item.at < 7 * 86400000 && item.at <= Date.now()
                && this.fields.every(field => typeof item.text?.[field] === 'string' && item.text[field].length <= 1000)) {
                this.saved = item;
            } else localStorage.removeItem(key);
        } catch { this.status = 'Sauvegarde locale indisponible sur cet appareil.'; }
        this.ready = !this.saved;
        this.fields.forEach(field => this.$watch(`$wire.${field}`, () => this.save()));
    },
    save() {
        if (!this.ready) return;
        try {
            const text = Object.fromEntries(this.fields.map(field => [field, wire[field]]));
            if (Object.values(text).some(value => value.trim())) {
                localStorage.setItem(key, JSON.stringify({ at: Date.now(), text }));
                this.status = 'Texte sauvegardé sur cet appareil pendant 7 jours.';
            } else {
                localStorage.removeItem(key); this.status = '';
            }
        } catch { this.status = 'Sauvegarde locale indisponible. Gardez cette page ouverte.'; }
    },
    resume() {
        if (!this.saved) return;
        for (const field of this.fields) wire.$set(field, this.saved.text[field], false);
        this.saved = null; this.ready = true; this.save();
    },
    discard() {
        try { localStorage.removeItem(key); } catch {}
        this.saved = null; this.ready = true;
        this.status = 'Ancien brouillon effacé.';
    },
});

// Choix d’un lieu par recherche (D-217) : des milliers de lieux ne tiennent pas dans une liste déroulante. Le serveur renvoie quinze
// lieux au plus ; le choix est écrit dans la propriété Livewire `model` (immédiatement si `live`, sinon à l’envoi du formulaire).
window.gamadCityPicker = (wire, cfg) => ({
    open: false, q: '', items: [], active: -1, selectedId: cfg.selectedId, selectedLabel: cfg.selectedLabel, timer: null, message: '',
    get country() { return this.$el.dataset.country || ''; },
    async search() {
        const url = new URL(cfg.url, window.location.origin);
        url.searchParams.set('q', this.q);
        if (this.country) url.searchParams.set('pays', this.country);
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            this.items = response.ok ? await response.json() : [];
            this.message = this.items.length ? '' : (this.q.trim().length < 2 && !this.country ? 'Tapez au moins deux lettres.' : 'Aucun lieu trouvé.');
        } catch { this.items = []; this.message = 'Recherche indisponible.'; }
        this.active = this.items.length ? 0 : -1;
    },
    openList() { this.open = true; this.search(); },
    typing() { this.open = true; clearTimeout(this.timer); this.timer = setTimeout(() => this.search(), 200); },
    choose(item) {
        this.selectedId = item.id; this.selectedLabel = item.label; this.q = ''; this.open = false;
        wire.$set(cfg.model, item.id, cfg.live);
    },
    clear() {
        this.selectedId = ''; this.selectedLabel = ''; this.q = ''; this.open = false;
        wire.$set(cfg.model, '', cfg.live);
    },
    move(step) {
        if (!this.open) return this.openList();
        if (this.items.length) this.active = (this.active + step + this.items.length) % this.items.length;
    },
    pick() { if (this.open && this.items[this.active]) this.choose(this.items[this.active]); },
});
