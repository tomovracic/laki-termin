<?php

declare(strict_types=1);

namespace App\Http\Controllers\AppSettings;

use App\Actions\AppSettings\AcknowledgeTerrainUsageRulesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppSettings\AcknowledgeTerrainUsageRulesRequest;
use Illuminate\Http\RedirectResponse;

class TerrainUsageRulesAcknowledgementController extends Controller
{
    public function __invoke(
        AcknowledgeTerrainUsageRulesRequest $request,
        AcknowledgeTerrainUsageRulesAction $acknowledgeTerrainUsageRulesAction,
    ): RedirectResponse {
        $acknowledgeTerrainUsageRulesAction->execute($request->user());

        return back();
    }
}
