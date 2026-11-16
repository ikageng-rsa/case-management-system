@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <div class="mb-3">
        <h1 class="mb-1">Reports</h1>
        <p class="text-secondary mb-0">Where the firm's money stands, on the matters you may see.</p>
    </div>

    <ul class="nav nav-underline mb-3">
        @foreach ($tabs as $key => $name)
            <li class="nav-item">
                <a href="{{ route('reports', ['tab' => $key]) }}" @class(['nav-link', 'active' => $tab === $key])>{{ $name }}</a>
            </li>
        @endforeach
    </ul>

    @include('reports.'.$tab)
@endsection
