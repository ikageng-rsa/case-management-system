@extends('layouts.app')

@section('title', 'Your account — Example Firm')

@section('content')
    @php
        $label = fn (string $value) => Str::headline($value);
    @endphp

    <div class="mb-4">
        <h1 class="mb-1">Your account</h1>
        <p class="text-secondary mb-0">Your staff record is kept by the firm. Here you choose what you are told about, and look after your own sign-in.</p>
    </div>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <h3 class="card-title text-meta text-uppercase mb-3">Staff record</h3>

                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-meta text-uppercase">Name</dt>
                        <dd class="col-sm-8">{{ $user->name }}</dd>

                        <dt class="col-sm-4 text-meta text-uppercase">Email</dt>
                        <dd class="col-sm-8">{{ $user->email }}</dd>

                        <dt class="col-sm-4 text-meta text-uppercase">Job title</dt>
                        <dd class="col-sm-8">{{ $user->job_title ?: '—' }}</dd>

                        <dt class="col-sm-4 text-meta text-uppercase">Mobile</dt>
                        <dd class="col-sm-8">{{ $user->mobile ?: '—' }}</dd>

                        <dt class="col-sm-4 text-meta text-uppercase">Office phone</dt>
                        <dd class="col-sm-8">{{ $user->office_phone ?: '—' }}</dd>

                        <dt class="col-sm-4 text-meta text-uppercase">Employee number</dt>
                        <dd class="col-sm-8">{{ $user->employee_number ?: '—' }}</dd>

                        <dt class="col-sm-4 text-meta text-uppercase">Roles</dt>
                        <dd class="col-sm-8 mb-0">{{ $user->roles->pluck('name')->map($label)->implode(' · ') ?: '—' }}</dd>
                    </dl>

                    <p class="text-meta mt-3 mb-0">Something wrong here? Ask a director to correct it. It is kept by them so the firm's record stays accurate.</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="card-title text-meta text-uppercase mb-0">Notifications</h3>

                        <x-ui.button variant="ghost-secondary" size="sm"
                            x-data x-on:click="$dispatch('sheet-open', 'notification-choices')">Change</x-ui.button>
                    </div>

                    @foreach ($notificationTypes as $type)
                        @php
                            $where = collect($channelsByType[$type->value])
                                ->map(fn ($channel) => $channel === 'mail' ? 'emailed to you' : 'shown in the system')
                                ->implode(' and ');
                        @endphp

                        <div @class(['mb-3' => ! $loop->last])>
                            <div class="mb-0">{{ $type->label() }}</div>
                            <div class="text-meta">{{ $where !== '' ? ucfirst($where) : 'Not sent to you' }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="card-title text-meta text-uppercase mb-0">Password</h3>

                        <x-ui.button variant="ghost-secondary" size="sm"
                            x-data x-on:click="$dispatch('sheet-open', 'change-password')">Change</x-ui.button>
                    </div>

                    <p class="text-secondary mb-0">Choose a password only you know. You will need your current one to change it.</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="card-title text-meta text-uppercase mb-0">Signing in</h3>

                        {{-- The switch never flips by itself: either way it opens the dialogue, which asks for the password. --}}
                        <label class="form-check form-switch mb-0" x-data>
                            <input class="form-check-input" type="checkbox" role="switch" aria-label="Sign-in code"
                                @checked($user->usesSignInCode())
                                x-on:click.prevent="$dispatch('sheet-open', 'sign-in-code')">
                        </label>
                    </div>

                    @if ($user->usesSignInCode())
                        <p class="mb-2">After your password, a six digit code is emailed to <strong>{{ $user->email }}</strong>. You enter it to finish signing in.</p>
                        <p class="text-secondary mb-0">The code works once and runs out in {{ config('security.sign_in_code.minutes') }} minutes. If it goes to the wrong place, ask a director to correct your email.</p>
                    @else
                        <p class="text-secondary mb-0">Your password is the only thing between this account and the firm's files. We recommend turning the code back on.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <x-ui.sheet name="notification-choices" title="Notifications" lede="Anything that has to do with you. Choose where each is sent." bag="notifications" wide
        method="PATCH" :action="route('account.notifications')">
        <table class="table table-vcenter">
            <thead>
                <tr>
                    <th>When</th>
                    <th class="text-center">In the system</th>
                    <th class="text-center">Email</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($notificationTypes as $type)
                    <tr>
                        <td>
                            <div>{{ $type->label() }}</div>
                            <div class="text-meta">{{ $type->description() }}</div>
                        </td>
                        @foreach ($channels as $channel)
                            @php
                                $on = $errors->notifications->any()
                                    ? (bool) old("channels.{$type->value}.{$channel}")
                                    : in_array($channel, $channelsByType[$type->value], true);
                            @endphp
                            <td class="text-center">
                                <input type="hidden" name="channels[{{ $type->value }}][{{ $channel }}]" value="0">
                                <input class="form-check-input" type="checkbox" name="channels[{{ $type->value }}][{{ $channel }}]" value="1"
                                    aria-label="{{ $type->label() }} — {{ $channel === 'mail' ? 'email' : 'in the system' }}" @checked($on)>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Save</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.sheet name="sign-in-code" :title="$user->usesSignInCode() ? 'Turn off the sign-in code' : 'Turn on the sign-in code'"
        :lede="$user->usesSignInCode() ? 'You will sign in with your password alone.' : 'A code will be emailed to '.$user->email.' each time you sign in.'"
        bag="signInCode" method="PATCH" :action="route('account.sign-in-code')">
        <input type="hidden" name="enabled" value="{{ $user->usesSignInCode() ? 0 : 1 }}">

        <x-ui.input label="Your password" name="current_password" id="sign_in_code_password" type="password" autocomplete="current-password"
            hint="Needed to change how you are let in." :error="$errors->signInCode->first('current_password')" />

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" :variant="$user->usesSignInCode() ? 'danger' : 'primary'">{{ $user->usesSignInCode() ? 'Turn off' : 'Turn on' }}</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.sheet name="change-password" title="Change password" lede="You need your current password to do this." bag="updatePassword"
        method="PUT" :action="route('user-password.update')">
        <x-ui.input label="Current password" name="current_password" id="current_password" type="password" autocomplete="current-password"
            :error="$errors->updatePassword->first('current_password')" />
        <x-ui.input label="New password" name="password" id="new_password" type="password" autocomplete="new-password"
            :error="$errors->updatePassword->first('password')" />
        <x-ui.input label="Confirm new password" name="password_confirmation" id="new_password_confirmation" type="password" autocomplete="new-password" />

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Change password</x-ui.button>
            </div>
        </x-slot:footer>
    </x-ui.sheet>
@endsection
