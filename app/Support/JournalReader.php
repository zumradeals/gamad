<?php

namespace App\Support;

use App\Models\AdminAction;
use App\Models\Company;
use App\Models\Publication;
use App\Models\PublicationComment;
use App\Models\User;
use App\Models\Zumra;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Rend lisible le journal d'administration (D-145) : une phrase par acte, son sujet par son nom, ses détails en clair. Le journal
 * lui-même n'est jamais modifié. Le super administrateur lit tout ; un modérateur ne lit que les actes de modération (moindre
 * pouvoir : les réglages et les listes de référence ne le concernent pas).
 */
class JournalReader
{
    /** Familles d'actes, dans l'ordre du filtre : clé => [libellé, préfixes d'action]. */
    public const FAMILIES = [
        'publications' => ['Publications', ['publication.', 'report.']],
        'personnes' => ['Personnes', ['person.']],
        'zumras' => ['ZUMRA', ['zumra.']],
        'reglages' => ['Réglages', ['setting.', 'settings.', 'backup.', 'wallet.', 'contribution.', 'allocation.']],
        'listes' => ['Listes de référence', ['domain.', 'country.', 'city.']],
        'comptes' => ['Comptes', ['account.']],
        'confiance' => ['Attestations', ['trust.']],
        'entreprises' => ['Entreprises', ['companies.']],
        'commentaires' => ['Commentaires', ['comment.']],
        'messages' => ['Messages de contact', ['contact.']],
        'annonces' => ['Annonces', ['ad.']],
    ];

    /** Familles que lit un modérateur. */
    public const MODERATION_FAMILIES = ['publications', 'personnes', 'commentaires', 'annonces'];

    private const LABELS = [
        'trust.identity_reopen' => 'a rouvert un contrôle d’identité après recours pour',
        'trust.work_reopened' => 'a redemandé confirmation après recours sur',
        'trust.identity_verify' => 'a vérifié l’identité de',
        'trust.identity_decline' => 'a refusé la vérification de',
        'trust.identity_revoke' => 'a retiré la vérification de',
        'trust.identity_instruct' => 'a précisé les modalités du contrôle de',
        'trust.work_revoked' => 'a retiré une référence sur',
        'trust.report_closed' => 'a répondu à un signalement concernant',
        'companies.verify' => 'a vérifié l’entreprise',
        'companies.decline' => 'a refusé la vérification de l’entreprise',
        'companies.suspend' => 'a suspendu l’entreprise',
        'companies.report_resolved' => 'a répondu à un signalement concernant l’entreprise',
        'comment.hidden' => 'a masqué un commentaire',
        'comment.restored' => 'a rétabli un commentaire',
        'comment.report.dismissed' => 'a classé des signalements de commentaire sans suite',
        'publication.hidden' => 'a masqué une publication',
        'publication.restored' => 'a rétabli une publication',
        'report.dismissed' => 'a classé des signalements sans suite',
        'person.notice_issued' => 'a envoyé un avis de la modération à',
        'person.notice_closed' => 'a classé l’avis de la modération pour',
        'person.suspended' => 'a suspendu',
        'person.reinstated' => 'a rétabli le compte de',
        'zumra.recognized' => 'a reconnu officiellement la ZUMRA',
        'zumra.recognition_withdrawn' => 'a retiré la reconnaissance de la ZUMRA',
        'zumra.founder_transferred' => 'a confié la fondation de la ZUMRA',
        'zumra.suspended' => 'a suspendu la ZUMRA',
        'zumra.reinstated' => 'a rétabli la ZUMRA',
        'zumra.report.dismissed' => 'a classé sans suite les signalements de la ZUMRA',
        'zumra.post.hidden' => 'a masqué un message de la Discussion de la ZUMRA',
        'zumra.post.restored' => 'a rétabli un message de la Discussion de la ZUMRA',
        'zumra.post.report.dismissed' => 'a classé sans suite les signalements d’un message de ZUMRA',
        'setting.changed' => 'a modifié le réglage',
        'backup.passphrase_revealed' => 'a affiché la phrase de secours de la copie hors serveur',
        'contribution.validated' => 'a validé une dépense de ZUMRA au fonds de contribution',
        'contribution.refused' => 'a refusé une demande de contribution de ZUMRA',
        'allocation.decided' => 'a affecté une somme du fonds de contribution à un projet de la ZUMRA',
        'allocation.paid' => 'a inscrit le versement d’une affectation du fonds de contribution',
        'wallet.sandbox_cleared' => 'a terminé les essais des portefeuilles (soldes d’essai remis à zéro)',
        'settings.test_email_sent' => 'a envoyé un e-mail de test',
        'settings.test_email_failed' => 'a tenté un e-mail de test, sans succès',
        'ad.accepted' => 'a accepté une annonce publicitaire',
        'ad.refused' => 'a refusé une annonce publicitaire',
        'ad.revoked' => 'a retiré de la diffusion une annonce publicitaire signalée',
        'contact.read' => 'a lu un message de contact',
        'contact.handled' => 'a marqué un message de contact comme traité',
        'contact.deleted' => 'a supprimé un message de contact',
        'account.renamed_by_owner' => 'a changé son nom affiché',
        'account.deleted_by_owner' => 'a supprimé son compte',
    ];

    private const REFERENCE_VERBS = ['created' => 'a ajouté', 'renamed' => 'a renommé', 'deactivated' => 'a désactivé', 'reactivated' => 'a réactivé'];

    private const REFERENCE_TYPES = ['domain' => 'un domaine', 'country' => 'un pays', 'city' => 'une ville'];

    /** @return array<int, string> Les clés de famille que cette personne a le droit de lire. */
    public static function familiesFor(User $viewer): array
    {
        return $viewer->isSuperAdmin() ? array_keys(self::FAMILIES) : self::MODERATION_FAMILIES;
    }

    /** Actes lisibles par cette personne, éventuellement limités à une famille (une famille hors de ses droits est ignorée). */
    public static function query(User $viewer, ?string $family = null)
    {
        $allowed = self::familiesFor($viewer);
        $families = $family !== null && in_array($family, $allowed, true) ? [$family] : $allowed;

        $prefixes = collect($families)->flatMap(fn (string $key) => self::FAMILIES[$key][1]);

        return AdminAction::query()
            ->where(function ($query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('action', 'like', $prefix.'%');
                }
            })
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    public static function label(string $action): string
    {
        if (isset(self::LABELS[$action])) {
            return self::LABELS[$action];
        }

        [$type, $verb] = array_pad(explode('.', $action, 2), 2, '');

        if (isset(self::REFERENCE_TYPES[$type], self::REFERENCE_VERBS[$verb])) {
            return self::REFERENCE_VERBS[$verb].' '.self::REFERENCE_TYPES[$type];
        }

        return $action;
    }

    /**
     * Noms des sujets des actes donnés, par identifiant de sujet : un lot de requêtes, pas une par ligne.
     *
     * @param  Collection<int, AdminAction>  $actions
     * @return array<string, string>
     */
    public static function subjects(Collection $actions): array
    {
        $ids = fn (string $class) => $actions->where('subject_type', (new $class)->getMorphClass())->pluck('subject_id')->filter()->unique()->all();

        $names = [];

        foreach (User::query()->whereIn('id', $ids(User::class))->pluck('name', 'id') as $id => $name) {
            $names[$id] = $name;
        }

        foreach (Zumra::query()->whereIn('id', $ids(Zumra::class))->pluck('name', 'id') as $id => $name) {
            $names[$id] = $name;
        }

        foreach (Publication::query()->whereIn('id', $ids(Publication::class))->with('author:id,name')->get() as $publication) {
            $names[$publication->getKey()] = '« '.Str::limit($publication->body, 50).' » de '.($publication->author?->name ?? 'une personne supprimée');
        }

        foreach (Company::query()->whereIn('id', $ids(Company::class))->pluck('name', 'id') as $id => $name) {
            $names[$id] = $name;
        }

        foreach (PublicationComment::query()->whereIn('id', $ids(PublicationComment::class))->with('author:id,name')->get() as $comment) {
            $names[$comment->getKey()] = '« '.Str::limit((string) $comment->body, 50).' » de '.($comment->author?->name ?? 'une personne supprimée');
        }

        return $names;
    }

    /** Le sujet d'un acte en clair, ou null s'il n'a pas de sujet à nommer. */
    public static function subjectName(AdminAction $action, array $names): ?string
    {
        if ($action->subject_id === null) {
            return null;
        }

        if ($action->subject_type === 'setting') {
            return $action->subject_id;
        }

        $known = [(new User)->getMorphClass(), (new Zumra)->getMorphClass(), (new Publication)->getMorphClass(), (new Company)->getMorphClass(), (new PublicationComment)->getMorphClass()];

        if (! in_array($action->subject_type, $known, true)) {
            return null;
        }

        return $names[$action->subject_id] ?? ($action->subject_type === (new User)->getMorphClass() ? 'un compte supprimé' : 'un élément supprimé');
    }

    /** Détails lisibles d'un acte, sans jamais afficher une valeur secrète. @return array<int, string> */
    public static function details(AdminAction $action): array
    {
        $payload = $action->payload ?? [];
        $lines = [];

        if (($payload['secret'] ?? false) === true) {
            return ['Valeur secrète : non affichée'];
        }

        if (array_key_exists('ancien', $payload) || array_key_exists('nouveau', $payload)) {
            $lines[] = 'Avant : '.self::scalar($payload['ancien'] ?? null).' — Après : '.self::scalar($payload['nouveau'] ?? null);
        }

        if (isset($payload['reports_closed']) && $payload['reports_closed'] > 0) {
            $lines[] = $payload['reports_closed'].($payload['reports_closed'] > 1 ? ' signalements clos' : ' signalement clos');
        }

        if (isset($payload['projet'])) {
            $lines[] = 'Projet : '.$payload['projet'].(isset($payload['zahab']) ? ' — '.number_format((int) $payload['zahab'], 0, ',', ' ').' ZAHAB' : '');
        } elseif (isset($payload['zahab']) && ! isset($payload['portefeuilles'])) {
            $lines[] = number_format((int) $payload['zahab'], 0, ',', ' ').' ZAHAB';
        }

        if (isset($payload['portefeuilles'], $payload['zahab'])) {
            $lines[] = $payload['portefeuilles'].' portefeuille(s), '.number_format((int) $payload['zahab'], 0, ',', ' ').' ZAHAB d’essai retirés';
        }

        if (array_key_exists('expliquee', $payload)) {
            $lines[] = $payload['expliquee'] ? 'La personne s’était expliquée.' : 'La personne ne s’était pas expliquée.';
        }

        if (isset($payload['delai_jours'])) {
            $lines[] = 'Délai pour s’expliquer : '.$payload['delai_jours'].($payload['delai_jours'] > 1 ? ' jours' : ' jour');
        }

        return $lines;
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '(vide)',
            is_bool($value) => $value ? 'oui' : 'non',
            is_scalar($value) => Str::limit((string) $value, 80),
            default => '(liste)',
        };
    }
}
