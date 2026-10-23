<?php

use App\Livewire\Espace;
use App\Livewire\ModifierProfil;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileEditor;
use App\Services\ProfilePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** Octets d'un JPEG unicolore ou en deux moitiés, avec EXIF (orientation, GPS), commentaire et XMP injectés. */
function jpegAvecMetadonnees(int $largeur = 100, int $hauteur = 50, int $orientation = 1, bool $gps = true): string
{
    $image = imagecreatetruecolor($largeur, $hauteur);
    $rouge = imagecolorallocate($image, 255, 0, 0);
    $bleu = imagecolorallocate($image, 0, 0, 255);
    imagefilledrectangle($image, 0, 0, intdiv($largeur, 2) - 1, $hauteur, $rouge);
    imagefilledrectangle($image, intdiv($largeur, 2), 0, $largeur, $hauteur, $bleu);
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = (string) ob_get_clean();

    $rationnels = fn (array $valeurs) => implode('', array_map(fn ($v) => pack('VV', $v, 1), $valeurs));
    $entree = fn (int $tag, int $type, int $nombre, int $valeur) => pack('vvVV', $tag, $type, $nombre, $valeur);

    $ifd0 = pack('v', $gps ? 2 : 1).$entree(0x0112, 3, 1, $orientation).($gps ? $entree(0x8825, 4, 1, 38) : '').pack('V', 0);
    $gpsIfd = $gps
        ? pack('v', 4).$entree(0x0001, 2, 2, ord('N')).$entree(0x0002, 5, 3, 92).$entree(0x0003, 2, 2, ord('W')).$entree(0x0004, 5, 3, 116).pack('V', 0)
          .$rationnels([5, 20, 30]).$rationnels([4, 1, 2])
        : '';
    $tiff = 'II'.pack('vV', 42, 8).$ifd0.$gpsIfd;
    $exif = "Exif\0\0".$tiff;
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
    $xmp = 'http://ns.adobe.com/xap/1.0/'."\0".'<x:xmpmeta>XMP-SECRET-MARK</x:xmpmeta>';
    $app1Xmp = "\xFF\xE1".pack('n', strlen($xmp) + 2).$xmp;
    $commentaire = 'COMMENT-SECRET-MARK';
    $com = "\xFF\xFE".pack('n', strlen($commentaire) + 2).$commentaire;

    return substr($jpeg, 0, 2).$app1.$app1Xmp.$com.substr($jpeg, 2);
}

function couleurAu(string $jpeg, int $x, int $y): array
{
    $image = imagecreatefromstring($jpeg);
    $rgb = imagecolorat($image, $x, $y);

    return [($rgb >> 16) & 255, ($rgb >> 8) & 255, $rgb & 255];
}

it('prépare bien le test : le JPEG d’essai porte vraiment une position GPS, un commentaire et du XMP', function () {
    $source = jpegAvecMetadonnees();
    $exif = exif_read_data('data://image/jpeg;base64,'.base64_encode($source));

    expect($exif)->toHaveKey('GPSLatitude')
        ->and($exif['GPSLatitudeRef'])->toBe('N')
        ->and($source)->toContain('COMMENT-SECRET-MARK')->toContain('XMP-SECRET-MARK');
});

it('supprime toutes les métadonnées de la photo, dont la position GPS', function () {
    $sortie = ProfilePhoto::encode(jpegAvecMetadonnees());

    expect($sortie)->not->toContain('Exif')
        ->not->toContain('COMMENT-SECRET-MARK')
        ->not->toContain('XMP-SECRET-MARK')
        ->not->toContain('http://ns.adobe.com');

    $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($sortie));
    expect($exif === false || ! isset($exif['GPSLatitude']))->toBeTrue();

    getimagesizefromstring($sortie, $segments);
    expect(array_keys($segments))->toBe(['APP0']);
});

it('recadre au centre en carré, sans dépasser 512 px', function () {
    $paysage = ProfilePhoto::encode(jpegAvecMetadonnees(800, 400, gps: false));
    $grande = ProfilePhoto::encode(jpegAvecMetadonnees(1600, 1200, gps: false));
    $petite = ProfilePhoto::encode(jpegAvecMetadonnees(60, 40, gps: false));

    expect(getimagesizefromstring($paysage)[0])->toBe(400)->and(getimagesizefromstring($paysage)[1])->toBe(400)
        ->and(getimagesizefromstring($grande)[0])->toBe(512)->and(getimagesizefromstring($grande)[1])->toBe(512)
        ->and(getimagesizefromstring($petite)[0])->toBe(40)->and(getimagesizefromstring($petite)[1])->toBe(40);
});

it('redresse la photo selon l’orientation EXIF avant de l’oublier', function () {
    // Orientation 1 : gauche rouge, droite bleue (recadrage central 50×50).
    $droite = ProfilePhoto::encode(jpegAvecMetadonnees(100, 50, 1, false));
    [$r, , $b] = couleurAu($droite, 5, 25);
    expect($r)->toBeGreaterThan(200)->and($b)->toBeLessThan(60);
    [$r, , $b] = couleurAu($droite, 45, 25);
    expect($b)->toBeGreaterThan(200)->and($r)->toBeLessThan(60);

    // Orientation 6 : quart de tour vers la droite — le rouge de gauche passe en haut, le bleu de droite en bas.
    $tournee = ProfilePhoto::encode(jpegAvecMetadonnees(100, 50, 6, false));
    expect(getimagesizefromstring($tournee)[0])->toBe(50)->and(getimagesizefromstring($tournee)[1])->toBe(50);
    [$r, , $b] = couleurAu($tournee, 25, 5);
    expect($r)->toBeGreaterThan(200)->and($b)->toBeLessThan(60);
    [$r, , $b] = couleurAu($tournee, 25, 45);
    expect($b)->toBeGreaterThan(200)->and($r)->toBeLessThan(60);

    // Orientation 8 : quart de tour vers la gauche — le rouge passe en bas, le bleu en haut.
    $autre = ProfilePhoto::encode(jpegAvecMetadonnees(100, 50, 8, false));
    [$r, , $b] = couleurAu($autre, 25, 5);
    expect($b)->toBeGreaterThan(200)->and($r)->toBeLessThan(60);

    // Orientation 3 : demi-tour — le rouge passe à droite.
    $demiTour = ProfilePhoto::encode(jpegAvecMetadonnees(100, 50, 3, false));
    [$r, , $b] = couleurAu($demiTour, 45, 25);
    expect($r)->toBeGreaterThan(200)->and($b)->toBeLessThan(60);
});

it('met un fond blanc derrière une photo PNG transparente', function () {
    $image = imagecreatetruecolor(80, 80);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    [$r, $g, $b] = couleurAu(ProfilePhoto::encode($png), 40, 40);

    expect($r)->toBeGreaterThan(240)->and($g)->toBeGreaterThan(240)->and($b)->toBeGreaterThan(240);
});

it('accepte JPEG, PNG et WebP', function () {
    $image = imagecreatetruecolor(64, 64);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    ob_start();
    imagewebp($image);
    $webp = (string) ob_get_clean();

    foreach ([jpegAvecMetadonnees(64, 64, gps: false), $png, $webp] as $source) {
        expect(getimagesizefromstring(ProfilePhoto::encode($source))[2])->toBe(IMAGETYPE_JPEG);
    }
});

it('refuse ce qui n’est pas une photo JPEG, PNG ou WebP', function () {
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    foreach (['du texte', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', $gif, '', "\x89PNG\r\n\x1a\n"] as $source) {
        expect(fn () => ProfilePhoto::encode($source))->toThrow(InvalidArgumentException::class, 'JPEG, PNG ou WebP');
    }
});

it('refuse une image trop grande avant de la décoder (25 mégapixels)', function () {
    $ihdr = pack('NN', 6000, 6000)."\x08\x02\x00\x00\x00";
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));

    expect(fn () => ProfilePhoto::encode($png))->toThrow(InvalidArgumentException::class, '25 mégapixels');
});

it('enregistre la photo envoyée depuis l’écran : carrée, sans métadonnées, hors du dossier public', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $fichier = UploadedFile::fake()->createWithContent('photo.jpg', jpegAvecMetadonnees(800, 600));

    Livewire::actingAs($user)->test(ModifierProfil::class)
        ->set('photo', $fichier)
        ->assertHasNoErrors()
        ->call('save')
        ->assertHasNoErrors();

    $profile = $user->fresh()->profile;
    $contenu = Storage::disk('local')->get($profile->photo_path);

    expect($profile->photo_path)->toStartWith('profile-photos/')->toEndWith('.jpg')
        ->and(getimagesizefromstring($contenu)[0])->toBe(512)
        ->and($contenu)->not->toContain('COMMENT-SECRET-MARK')->not->toContain('Exif')
        ->and($profile->photoUrl())->toContain('/personnes/'.$profile->public_id.'/photo');
});

it('remplace l’ancienne photo et efface son fichier', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $composant = Livewire::actingAs($user)->test(ModifierProfil::class);
    $composant->set('photo', UploadedFile::fake()->createWithContent('a.jpg', jpegAvecMetadonnees(300, 300, gps: false)))->call('save');
    $premiere = $user->fresh()->profile->photo_path;

    $composant->set('photo', UploadedFile::fake()->createWithContent('b.jpg', jpegAvecMetadonnees(300, 300, gps: false)))->call('save');
    $seconde = $user->fresh()->profile->photo_path;

    expect($seconde)->not->toBe($premiere);
    Storage::disk('local')->assertMissing($premiere);
    Storage::disk('local')->assertExists($seconde);
});

it('retire la photo et efface son fichier', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $composant = Livewire::actingAs($user)->test(ModifierProfil::class);
    $composant->set('photo', UploadedFile::fake()->createWithContent('a.jpg', jpegAvecMetadonnees(300, 300, gps: false)))->call('save');
    $chemin = $user->fresh()->profile->photo_path;

    $composant->call('dropPhoto')->call('save');

    expect($user->fresh()->profile->photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($chemin);
});

it('refuse un fichier qui n’est pas une photo, et un fichier de plus de 5 Mo', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $composant = Livewire::actingAs($user)->test(ModifierProfil::class);

    $composant->set('photo', UploadedFile::fake()->create('note.pdf', 100, 'application/pdf'))->assertHasErrors(['photo'])->assertSet('photo', null);
    $composant->set('photo', UploadedFile::fake()->create('script.php', 10, 'text/x-php'))->assertHasErrors(['photo']);
    $composant->set('photo', UploadedFile::fake()->image('grande.jpg')->size(5121))->assertHasErrors(['photo'])->assertSeeText('5 Mo');

    expect($user->fresh()->profile->photo_path)->toBeNull();
});

it('refuse à l’enregistrement un faux JPEG qui passe le contrôle du type', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(ModifierProfil::class)
        ->set('photo', UploadedFile::fake()->createWithContent('faux.jpg', "\xFF\xD8\xFF\xE0".str_repeat('x', 200)))
        ->call('save');

    expect($user->fresh()->profile->photo_path)->toBeNull();
    expect(Storage::disk('local')->allFiles('profile-photos'))->toBe([]);
});

it('efface le fichier de la photo quand le compte est supprimé, avec le profil et les liens', function () {
    Storage::fake('local');
    $user = person('Awa', ['Couture'], ['Anglais'], 'Bouaké', domains: ['Sécurité']);
    $profile = $user->profile;
    $profile->update(['photo_path' => 'profile-photos/awa.jpg']);
    Storage::disk('local')->put('profile-photos/awa.jpg', 'x');

    Livewire::actingAs($user)->test(Espace::class)->set('password', 'password')->call('deleteAccount');

    Storage::disk('local')->assertMissing('profile-photos/awa.jpg');
    expect(Profile::query()->count())->toBe(0)
        ->and(DB::table('profile_skill')->count())->toBe(0)
        ->and(DB::table('domain_profile')->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

it('sert la photo avec les bons en-têtes à qui a le droit de la voir, et à lui seul', function () {
    Storage::fake('local');
    $awa = User::factory()->create();
    Storage::disk('local')->put('profile-photos/a.jpg', ProfilePhoto::encode(jpegAvecMetadonnees(100, 100, gps: false)));
    $awa->profile->update(['photo_path' => 'profile-photos/a.jpg']);
    $url = route('personnes.photo', $awa->profile->public_id);

    $this->get($url)->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $controle = $this->get($url)->headers->get('Cache-Control');
    expect($controle)->toContain('private')->toContain('no-cache')->not->toContain('max-age')->not->toContain('public');

    $awa->profile->update(['visibility' => 'members']);
    $this->get($url)->assertNotFound();
    $this->actingAs(User::factory()->create())->get($url)->assertOk();
});

it('répond 404 pour une personne sans photo ou inconnue', function () {
    $sans = User::factory()->create();

    $this->get(route('personnes.photo', $sans->profile->public_id))->assertNotFound();
    $this->get('/personnes/inconnu0000/photo')->assertNotFound();
});

it('ne propose pas d’envoyer une photo si le serveur ne sait pas la traiter', function () {
    expect(ProfilePhoto::available())->toBeTrue();
});

it('envoie un ETag et revalide à chaque affichage : 304 seulement après le contrôle d’accès', function () {
    Storage::fake('local');
    $awa = User::factory()->create();
    Storage::disk('local')->put('profile-photos/a.jpg', ProfilePhoto::encode(jpegAvecMetadonnees(100, 100, gps: false)));
    $awa->profile->update(['photo_path' => 'profile-photos/a.jpg']);
    $url = route('personnes.photo', $awa->profile->public_id);

    $premiere = $this->get($url)->assertOk();
    $etag = $premiere->headers->get('ETag');
    expect($etag)->toBe('"'.md5('profile-photos/a.jpg').'"');

    $this->get($url, ['If-None-Match' => $etag])->assertStatus(304);
    expect($this->get($url, ['If-None-Match' => '"autre-version"'])->getStatusCode())->toBe(200);

    // Une nouvelle photo a un autre ETag.
    Storage::disk('local')->put('profile-photos/b.jpg', ProfilePhoto::encode(jpegAvecMetadonnees(100, 100, gps: false)));
    $awa->profile->update(['photo_path' => 'profile-photos/b.jpg']);
    expect($this->get($url, ['If-None-Match' => $etag])->getStatusCode())->toBe(200);
});

it('ne sert plus la photo à un visiteur sans compte quand le profil passe de « public » à « inscrits seulement », même s’il l’avait déjà', function () {
    Storage::fake('local');
    $awa = User::factory()->create();
    Storage::disk('local')->put('profile-photos/a.jpg', ProfilePhoto::encode(jpegAvecMetadonnees(100, 100, gps: false)));
    $awa->profile->update(['photo_path' => 'profile-photos/a.jpg', 'visibility' => 'public']);
    $url = route('personnes.photo', $awa->profile->public_id);

    // Le visiteur a déjà la photo : son navigateur en garde l'ETag.
    $etag = $this->get($url)->assertOk()->headers->get('ETag');
    $this->get($url, ['If-None-Match' => $etag])->assertStatus(304);

    // Le profil passe à « inscrits seulement » : la revalidation du visiteur n'aboutit plus (404, pas 304 ni 200).
    (new ProfileEditor)->save($awa, ['name' => $awa->name, 'bio' => null, 'country_id' => null, 'city_id' => null, 'visibility' => 'members', 'offered' => [], 'wanted' => [], 'domain_ids' => []]);
    expect($awa->fresh()->profile->visibility)->toBe('members');

    $this->get($url, ['If-None-Match' => $etag])->assertNotFound();
    $this->get($url)->assertNotFound();

    // Connecté et vérifié, il la voit toujours ; le propriétaire aussi.
    $this->actingAs(User::factory()->create())->get($url)->assertOk();
    $this->actingAs($awa)->get($url)->assertOk();
});
