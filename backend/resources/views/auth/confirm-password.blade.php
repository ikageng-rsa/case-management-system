@extends('layouts.app')

@section('title', 'Confirm password — Example Firm')

@section('content')
    <h1 class="mb-1">Confirm your password</h1>
    <p class="text-secondary mb-4">This is a secure area. Confirm your password to continue.</p>

    <div class="row row-cards">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('password.confirm.store') }}">
                        @csrf

                        <x-ui.input
                            label="Password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            autofocus
                            :error="$errors->first('password')"
                        />

                        <x-ui.button type="submit">Confirm</x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
