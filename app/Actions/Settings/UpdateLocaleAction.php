<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Enums\Locale;
use Illuminate\Http\Request;

class UpdateLocaleAction
{
    public function handle(Request $request, Locale $locale): void
    {
        $request->session()->put('locale', $locale->value);
    }
}
