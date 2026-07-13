<?php

declare(strict_types=1);

namespace App\Http\Controllers\AppSettings;

use App\Actions\AppSettings\AcknowledgeLoginMessageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\AcknowledgeLoginMessageRequest;
use Illuminate\Http\RedirectResponse;

class LoginMessageAcknowledgementController extends Controller
{
    public function __invoke(
        AcknowledgeLoginMessageRequest $request,
        AcknowledgeLoginMessageAction $acknowledgeLoginMessageAction,
    ): RedirectResponse {
        $acknowledgeLoginMessageAction->execute($request->user());

        return back();
    }
}
