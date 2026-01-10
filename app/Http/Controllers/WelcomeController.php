<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WelcomeController extends Controller
{
    public function index(){
        return inertia('Auth/Login');
    }

    public function verification()
    {
        return inertia('Public/Verification/Index', [
            'result' => null
        ]);
    }

   public function verify(Request $request)
{
    $request->validate([
        'file' => 'required|mimes:pdf|max:10240',
    ]);

    $content = file_get_contents($request->file('file')->getRealPath());
    $secret = config('app.key');

    // Extract HMAC
    if (!preg_match('/% ValidationHMAC:\s*([a-f0-9]{64})/i', $content, $m)) {
        return inertia('Public/Verification/Index', [
            'result' => [
                'status' => 'missing',
                'message' => '⚠️ Missing validation metadata.',
            ],
        ]);
    }

    $embedded = $m[1];

    // Remove metadata, but keep final %%EOF untouched
    $clean = preg_replace('/\n%--- DOC META ---.*?%--- END META ---\n(?=%%EOF)/s', '', $content, 1);

    // Recompute HMAC
    $recomputed = hash_hmac('sha256', $clean, $secret);

    $match = hash_equals($embedded, $recomputed);
    
    return inertia('Public/Verification/Index', [
        'result' => [
            'status'  => $match ? 'valid' : 'tampered',
            'message' => $match
                ? '✅ Verified — the uploaded document is authentic.'
                : '❌ Tampered — the document has been altered.',
            'embedded'  => $embedded,
            'recomputed' => $recomputed,
        ],
    ]);
}
}
