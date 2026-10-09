<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The staff panel's picture for someone without a photo: their initials,
 * drawn here as an inline image.
 *
 * Filament's default fetches the picture from ui-avatars.com: every page
 * of the panel would send a staff member's name, with their address, to a
 * service the privacy notice does not mention. It says it keeps nothing,
 * but the request still leaves the platform. The colours are those of
 * the app's own initials avatar (Flux), so a person looks the same on
 * both sides.
 */
class InitialsAvatar implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = $record instanceof User ? $record->initials() : '';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#e4e4e7"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#27272a" '
            .'font-family="Geist, ui-sans-serif, system-ui, sans-serif" font-size="24" font-weight="500">'
            .e(mb_strtoupper($initials))
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
