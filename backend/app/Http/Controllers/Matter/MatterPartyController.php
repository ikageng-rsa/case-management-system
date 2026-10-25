<?php

declare(strict_types=1);

namespace App\Http\Controllers\Matter;

use App\Http\Controllers\Controller;
use App\Http\Requests\Matter\SaveMatterPartyRequest;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterParty;
use App\Services\Matters\CannotChangePartiesOfClosedMatter;
use App\Services\Matters\SaveMatterParty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MatterPartyController extends Controller
{
    public function store(SaveMatterPartyRequest $request, Matter $matter, SaveMatterParty $saveParty): RedirectResponse
    {
        Gate::authorize('update', $matter);

        try {
            $saveParty->save($matter, null, $request->validated());
        } catch (CannotChangePartiesOfClosedMatter $e) {
            return $this->back($matter)->withErrors(['party' => $e->getMessage()], 'parties');
        }

        return $this->back($matter)->with('status', 'Party added.');
    }

    public function update(SaveMatterPartyRequest $request, Matter $matter, MatterParty $party, SaveMatterParty $saveParty): RedirectResponse
    {
        Gate::authorize('update', $matter);
        abort_unless($party->matter_id === $matter->getKey(), 404);

        try {
            $saveParty->save($matter, $party, $request->validated());
        } catch (CannotChangePartiesOfClosedMatter $e) {
            return $this->back($matter)->withErrors(['party' => $e->getMessage()], 'parties');
        }

        return $this->back($matter)->with('status', 'Party updated.');
    }

    public function destroy(Matter $matter, MatterParty $party): RedirectResponse
    {
        Gate::authorize('update', $matter);
        abort_unless($party->matter_id === $matter->getKey(), 404);

        if ($matter->isClosed()) {
            return $this->back($matter)->withErrors(['party' => (new CannotChangePartiesOfClosedMatter($matter))->getMessage()], 'parties');
        }

        $party->delete();

        return $this->back($matter)->with('status', 'Party removed.');
    }

    protected function back(Matter $matter): RedirectResponse
    {
        return redirect()->route('matter.show', [$matter, 'tab' => 'parties']);
    }
}
