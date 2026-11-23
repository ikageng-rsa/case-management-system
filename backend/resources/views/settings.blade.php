@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <div class="mb-3">
        <h1 class="mb-1">Settings</h1>
        <p class="text-secondary mb-0">How the firm bills and how it presents itself, kept here rather than in the code.</p>
    </div>

    <ul class="nav nav-underline mb-3">
        @foreach ($tabs as $key => $name)
            <li class="nav-item">
                <a href="{{ route('settings', ['tab' => $key]) }}" @class(['nav-link', 'active' => $tab === $key])>{{ $name }}</a>
            </li>
        @endforeach
    </ul>

    @include('settings.'.$tab)
@endsection
