// Le menu de l’accueil fonctionne nativement ; ce complément le referme après un choix.
document.addEventListener('click', (event) => {
    const link = event.target instanceof Element ? event.target.closest('.landing-mobile-menu a') : null;
    if (link) link.closest('details').open = false;
});

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
