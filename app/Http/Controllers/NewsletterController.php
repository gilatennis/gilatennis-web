<?php

namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:newsletter_subscribers,email'],
        ]);

        NewsletterSubscriber::create([
            'email' => $validated['email'],
            'subscribed_at' => now(),
        ]);

        return response()->json(['message' => 'Subscribed successfully.'], 201);
    }
}
