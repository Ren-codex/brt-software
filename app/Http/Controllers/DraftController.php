<?php

namespace App\Http\Controllers;

use App\Models\FormDraft;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The server side of the "set aside and resume" floating bar.
 *
 * A draft belongs to one user and is never shared, so every action is scoped to
 * the signed-in user and a draft for anyone else is simply invisible here.
 */
class DraftController extends Controller
{
    /** The forms that may be set aside, mirroring draft.js DRAFT_KINDS. */
    private const KINDS = ['sales-order', 'purchase-request'];

    /** This user's drafts, keyed by kind, for the store to load on start-up. */
    public function index()
    {
        $drafts = FormDraft::where('user_id', Auth::id())
            ->get()
            ->keyBy('kind')
            ->map(fn (FormDraft $d) => $this->present($d));

        return response()->json(['data' => $drafts]);
    }

    /** Save (or replace) the current user's draft for one kind. */
    public function update(Request $request, string $kind)
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        $validated = $request->validate([
            'summary' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
            'return_path' => ['nullable', 'string', 'max:255'],
        ]);

        $draft = FormDraft::updateOrCreate(
            ['user_id' => Auth::id(), 'kind' => $kind],
            [
                'summary' => $validated['summary'] ?? null,
                'payload' => $validated['payload'] ?? null,
                'return_path' => $validated['return_path'] ?? null,
                'saved_at' => now(),
            ]
        );

        return response()->json(['data' => $this->present($draft)]);
    }

    /** Discard the current user's draft for one kind. Idempotent. */
    public function destroy(string $kind)
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);

        FormDraft::where('user_id', Auth::id())->where('kind', $kind)->delete();

        return response()->json(['status' => true]);
    }

    /** The shape the client stores, matching a localStorage draft. */
    private function present(FormDraft $draft): array
    {
        return [
            'summary' => $draft->summary,
            'payload' => $draft->payload,
            'return_path' => $draft->return_path,
            'saved_at' => optional($draft->saved_at)->toISOString(),
        ];
    }
}
