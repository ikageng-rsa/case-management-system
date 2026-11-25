@props([
    'placeholder' => 'Search clients, references, narrations…',
    'search' => true,
])

<header {{ $attributes->class(['navbar', 'app-header']) }}>
    @if ($search)
        <div class="position-relative d-none d-md-block" x-data="siteSearch({{ \Illuminate\Support\Js::from(['url' => route('search')]) }})"
             x-on:click.outside="open = false" x-on:keydown.escape="close()" x-on:keydown.window="shortcut($event)">
            <x-ui.search-field :placeholder="$placeholder" x-ref="input" x-model="q" autocomplete="off"
                role="combobox" aria-label="Search" aria-controls="site-search-results" x-bind:aria-expanded="open"
                x-on:input.debounce.250ms="run()" x-on:focus="open = searched"
                x-on:keydown.enter.prevent="first()" x-on:keydown.arrow-down.prevent="move($event, 1)" />

            <div class="dropdown-menu p-0" id="site-search-results" x-bind:class="{ show: open }" x-cloak
                 style="left: 0; width: 100%; min-width: 22rem; max-height: 70vh; overflow-x: hidden; overflow-y: auto;">
                <template x-for="group in groups" x-bind:key="group.label">
                    <div>
                        <div class="text-meta text-uppercase px-3 pt-2 pb-1" x-text="group.label"></div>
                        <template x-for="item in group.items" x-bind:key="item.url + item.title">
                            <a class="dropdown-item d-block" x-bind:href="item.url" style="white-space: normal;"
                               x-on:keydown.arrow-down.prevent="move($event, 1)" x-on:keydown.arrow-up.prevent="move($event, -1)">
                                <span class="d-block text-body" x-text="item.title"></span>
                                <span class="d-block text-meta" x-text="item.meta"></span>
                            </a>
                        </template>
                    </div>
                </template>

                <div class="px-3 py-2 text-secondary" x-show="loading && groups.length === 0">Searching…</div>
                <div class="px-3 py-2 text-secondary" x-show="! loading && searched && groups.length === 0">Nothing found.</div>
            </div>
        </div>
    @endif

    <div class="app-header-actions">
        {{ $slot }}
    </div>
</header>
