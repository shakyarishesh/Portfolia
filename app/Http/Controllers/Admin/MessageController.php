<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $messages = ContactMessage::query()
            ->when($q = trim((string) $request->query('q')), fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('subject', 'like', '%'.addcslashes($q, '%_\\').'%')->orWhere('message', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($request->query('unread'), fn ($query) => $query->whereNull('read_at'))
            ->when($request->query('box') === 'archived', fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.messages.index', ['messages' => $messages]);
    }

    public function show(ContactMessage $message)
    {
        $message->read_at ??= now();
        $message->save();

        return view('admin.messages.show', ['message' => $message]);
    }

    /** Toggle read / replied / archived. */
    public function state(Request $request, ContactMessage $message)
    {
        $data = $request->validate(['field' => ['required', 'in:read_at,replied_at,archived_at'], 'on' => ['required', 'boolean']]);
        $message->{$data['field']} = $data['on'] ? now() : null;
        if ($data['field'] === 'replied_at' && $data['on']) {
            $message->read_at ??= now();
        }
        $message->save();

        // Opening a message marks it read, so "mark unread" goes back to the list instead.
        if ($data['field'] === 'read_at' && ! $data['on']) {
            return redirect()->route('admin.messages.index')->with('success', 'Marked as unread.');
        }

        return back()->with('success', 'Message updated.');
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'Message deleted.');
    }
}
