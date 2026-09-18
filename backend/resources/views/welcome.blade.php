<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="d-flex flex-column">
        <div class="page">
            <header class="navbar navbar-expand-md d-print-none">
                <div class="container-xl">
                    <div class="navbar-brand navbar-brand-autodark">
                        <a href="{{ url('/') }}">{{ config('app.name', 'Laravel') }}</a>
                    </div>
                </div>
            </header>

            <div class="page-wrapper">
                <div class="page-header d-print-none">
                    <div class="container-xl">
                        <div class="row g-2 align-items-center">
                            <div class="col">
                                <div class="page-pretitle">Overview</div>
                                <h2 class="page-title">Case management</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="page-body">
                    <div class="container-xl">
                        <div class="row row-cards">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-body">
                                        <h3 class="card-title">Tabler is wired up</h3>
                                        <p class="text-secondary">
                                            The UI framework is installed and bundled through Vite. Build out the
                                            application views from here.
                                        </p>
                                        <a href="{{ url('/') }}" class="btn btn-primary">Continue</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="footer footer-transparent d-print-none">
                    <div class="container-xl">
                        <div class="text-secondary text-center">
                            &copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }}
                        </div>
                    </div>
                </footer>
            </div>
        </div>
    </body>
</html>
