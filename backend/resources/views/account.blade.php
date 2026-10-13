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
                        <div @class(['mb-3' => ! $loop->last])>
                            <div class="mb-0">{{ $type->label() }}</div>
                            <div class="text-meta">
                                {{ $user->wantsEmailFor($type) ? 'Shown in the system and emailed to you' : 'Shown in the system only' }}
                            </div>
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
                    <h3 class="card-title text-meta text-uppercase mb-3">Signing in</h3>

                    <p class="mb-2">After your password, a six digit code is emailed to <span class="text-meta">{{ \App\Services\Clients\MaskIdentifier::email($user->email) }}</span>. You enter it to finish signing in.</p>
                    <p class="text-secondary mb-0">The code works once and runs out in {{ config('security.sign_in_code.minutes') }} minutes. If it goes to the wrong place, ask a director to correct your email.</p>
                </div>
            </div>
        </div>
    </div>

    <x-ui.sheet name="notification-choices" title="Notifications" lede="Everything is shown in the system. Choose what is also emailed to you." bag="notifications"
        method="PATCH" :action="route('account.notifications')">
        @foreach ($notificationTypes as $type)
            <label class="form-check mb-3">
                <input type="hidden" name="email[{{ $type->value }}]" value="0">
                <input class="form-check-input" type="checkbox" name="email[{{ $type->value }}]" value="1"
                    @checked($errors->notifications->any() ? old('email.'.$type->value) : $user->wantsEmailFor($type))>
                <span class="form-check-label">
                    <span class="d-block">Email me: {{ lcfirst($type->label()) }}</span>
                    <span class="d-block text-meta">{{ $type->description() }}</span>
                </span>
            </label>
        @endforeach

        <x-slot:footer>
            <div class="d-flex align-items-center justify-content-between">
                <x-ui.button variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                <x-ui.button type="submit" variant="primary">Save</x-ui.button>
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
