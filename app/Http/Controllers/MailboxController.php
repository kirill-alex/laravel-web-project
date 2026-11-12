<?php

namespace App\Http\Controllers;

use App\Models\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MailboxController extends Controller
{
    public function index(): View
    {
        Gate::authorize('moderate-content');

        return view('mailbox.index', ['messages' => MailMessage::latest('id')->paginate(10)]);
    }

    public function show(MailMessage $message): View
    {
        Gate::authorize('moderate-content');

        return view('mailbox.show', ['message' => $message]);
    }
}
