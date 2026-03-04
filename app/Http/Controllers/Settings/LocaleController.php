<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateLocaleAction;
use App\Enums\Locale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(
        UpdateLocaleRequest $request,
        UpdateLocaleAction $updateLocaleAction,
    ): RedirectResponse {
        $locale = Locale::from($request->string('locale')->value());

        $updateLocaleAction->handle($request, $locale);

        return back();
    }
}
