<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string',
            'phone_number' => 'required|string',
            'email' => 'required|string|email',
            'message' => 'required|string',
        ]);
        $contact = Contact::query()->create($validated);
        return response()->json(ContactResource::make($contact));
    }

    public function get()
    {
        $contacts = Contact::query()->orderBy('is_seen')->orderBy('id', 'desc')->get();
        return response()->json(ContactResource::collection($contacts));
    }

    public function get_by_id(string $id)
    {
        $contact = Contact::query()->findOrFail($id);
        $contact->is_seen = true;
        $contact->save();
        return response()->json(ContactResource::make($contact));
    }
}
