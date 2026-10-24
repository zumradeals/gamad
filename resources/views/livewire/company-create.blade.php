<div class="flex min-w-0 flex-col gap-5">
    <a href="{{ route('entreprises.index') }}" class="inline-flex min-h-11 items-center gap-2 self-start text-[13px] font-bold"><x-icon name="back" :size="18"/> Entreprises</a>
    <section class="rounded-card bg-ink px-5 py-7 text-white md:px-8">
        <span class="text-[12px] font-extrabold tracking-widest text-accent">ESPACE ENTREPRISES · PHASE PILOTE</span>
        <h1 class="mt-2 text-[29px] font-extrabold leading-tight md:text-[36px]">Présentez votre entreprise sur GAMAD.</h1>
        <p class="mt-3 text-[14px] leading-relaxed text-white/85">Votre page sera disponible publiquement après le contrôle de vos documents administratifs. Le contrôle n'implique pas une vérification personnelle du gestionnaire.</p>
    </section>

    @if ($myCompanies->isNotEmpty())
        <section class="rounded-card border border-line bg-card p-5">
            <h2 class="text-[18px] font-extrabold">Mes pages entreprises</h2>
            @foreach ($myCompanies as $company)
                <a href="{{ route('entreprises.show', $company) }}" class="mt-3 flex min-h-11 items-center justify-between gap-3 border-t border-line-soft py-2 text-[14px] font-bold">
                    <span class="min-w-0 break-words">{{ $company->name }}</span>
                    <span class="text-[12px] text-muted">{{ $company->verified() ? 'Vérifiée' : 'En contrôle ou non publiée' }}</span>
                </a>
            @endforeach
        </section>
    @endif

    <form wire:submit="save" class="space-y-4">
        @foreach ($errors->all() as $error)<p class="rounded-button border border-danger bg-card p-3 text-[13px] text-danger" role="alert">{{ $error }}</p>@endforeach
        <section class="rounded-card border border-line bg-card p-5 md:p-6">
            <h2 class="mb-4 text-[20px] font-extrabold">1. L'entreprise</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Nom affiché *<input wire:model="name" required maxlength="160" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="Nom commercial"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Raison sociale *<input wire:model="legalName" required maxlength="160" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="Nom légal de l'entreprise"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold">Pays d'immatriculation (code ISO) *<input wire:model="country" required maxlength="2" minlength="2" autocapitalize="characters" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="CI"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold">Numéro d'immatriculation *<input wire:model="registration" required maxlength="100" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="RCCM ou équivalent"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold">Secteur principal *<input wire:model="sector" required maxlength="120" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="Services, industrie, bâtiment…"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold">Ville<input wire:model="city" maxlength="120" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="Abidjan"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold">Site web<input wire:model="website" type="url" maxlength="255" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="https://…"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold">E-mail professionnel<input wire:model="email" type="email" maxlength="255" class="min-h-12 rounded-button border border-chip-line bg-card px-3 text-[15px]" placeholder="contact@…"></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Présentation courte (280 caractères)<textarea wire:model="summary" maxlength="280" rows="3" class="rounded-button border border-chip-line bg-card px-3 py-3 text-[15px]" placeholder="Ce que vous apportez et à qui…"></textarea></label>
                <label class="flex flex-col gap-1.5 text-[13px] font-bold sm:col-span-2">Présentation détaillée<textarea wire:model="description" maxlength="1800" rows="5" class="rounded-button border border-chip-line bg-card px-3 py-3 text-[15px]" placeholder="Votre activité, votre expertise, vos services…"></textarea></label>
            </div>
        </section>

        <section class="rounded-card border border-line bg-card p-5 md:p-6">
            <h2 class="text-[20px] font-extrabold">2. Justificatifs administratifs</h2>
            <p class="mt-2 text-[14px] leading-relaxed text-muted">Ces pièces sont privées et réservées aux vérificateurs habilités de GAMAD. Formats PDF, JPG ou PNG, 5 Mo maximum par document. Aucune confirmation n'est automatique.</p>
            <div class="mt-5 grid gap-4">
                <label class="flex flex-col gap-2 text-[13px] font-bold">RCCM ou justificatif officiel d'immatriculation *<input type="file" wire:model="rccm" required accept=".pdf,.png,.jpg,.jpeg" class="w-full rounded-button border border-chip-line p-3 text-[13px]"></label>
                <label class="flex flex-col gap-2 text-[13px] font-bold">DFE ou équivalent (facultatif)<input type="file" wire:model="dfe" accept=".pdf,.png,.jpg,.jpeg" class="w-full rounded-button border border-chip-line p-3 text-[13px]"></label>
                <label class="flex flex-col gap-2 text-[13px] font-bold">IDU ou équivalent (facultatif)<input type="file" wire:model="idu" accept=".pdf,.png,.jpg,.jpeg" class="w-full rounded-button border border-chip-line p-3 text-[13px]"></label>
            </div>
            <p class="mt-3 text-[12px] leading-relaxed text-muted">GAMAD vérifiera la cohérence du dossier et, si disponible, l'immatriculation dans le registre public compétent du pays.</p>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-card border border-line bg-card p-5">
            <p class="max-w-md text-[13px] text-muted">En soumettant la page, vous déclarez que ces documents concernent réellement l'entreprise présentée.</p>
            <x-button variant="accent" wire:loading.attr="disabled" wire:target="save,rccm,dfe,idu">Soumettre ma page à vérification</x-button>
        </div>
        <p wire:loading wire:target="save,rccm,dfe,idu" role="status" class="text-[13px] font-bold text-muted">Enregistrement des informations…</p>
    </form>
</div>
