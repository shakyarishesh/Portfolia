<?php

namespace App\Http\Controllers;

use App\Services\Portfolio\Assistant;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    //
    public function __invoke(Request $request, Assistant $assistant)
    {
        abort_unless($assistant->enabled(), 404);

        $data = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:500'],
            'history' => ['nullable', 'array', 'max:6'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.text' => ['required', 'string', 'max:2000'],
        ]);

        $answer = $assistant->ask($data['question'], $data['history'] ?? []);

        return $answer === null
            ? response()->json(['answer' => null, 'message' => "I can't answer that right now. Please use the contact form or email instead."], 503)
            : response()->json(['answer' => $answer]);
    }
}
