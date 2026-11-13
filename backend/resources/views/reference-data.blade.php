@extends('layouts.app')

@section('title', 'Reference data — Example Firm')

@section('content')
    @php
        $label = fn (string $value) => Str::headline($value);

        // Another form's failed input must not leak into this one.
        $input = fn (string $bag, string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;

        $measureLabel = fn ($measure) => match ($measure->value) {
            'minutes' => 'Minutes',
            'page' => 'Pages',
            'kilometre' => 'Kilometres',
            'item' => 'Items',
        };
    @endphp

    <div class="mb-3">
        <h1 class="mb-1">Reference data</h1>
        <p class="text-secondary mb-0">The lists the firm's forms are filled from. What has been used is retired, not removed, so files keep reading as they were written.</p>
    </div>

    <ul class="nav nav-underline mb-3">
        @foreach ($tabs as $key => $name)
            <li class="nav-item">
                <a href="{{ route('reference-data', ['tab' => $key]) }}" @class(['nav-link', 'active' => $tab === $key])>{{ $name }}</a>
            </li>
        @endforeach
    </ul>

    {{-- ===================================================== Courts --}}
    @if ($tab === 'courts')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title mb-0">Courts</h3>
                    <p class="text-meta mb-0">{{ $courts->whereNull('deleted_at')->count() }} offered on forms</p>
                </div>

                <x-ui.button variant="primary" icon="plus" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-court')">Add court</x-ui.button>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Court</th>
                            <th>Tier</th>
                            <th class="text-center">Matters</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($courts as $court)
                            <tr @class(['text-secondary' => $court->trashed()])>
                                <td>
                                    {{ $court->label }}
                                    @if ($court->trashed())
                                        <x-ui.stage-badge tone="neutral" class="ms-2">Retired</x-ui.stage-badge>
                                    @endif
                                </td>
                                <td>{{ $label($court->tier->value) }}</td>
                                <td class="text-center">{{ $court->matters_count }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($court->trashed())
                                        <form method="POST" action="{{ route('court.restore', $court) }}" class="d-inline">
                                            @csrf
                                            <x-ui.button type="submit" variant="ghost-secondary" size="sm">Restore</x-ui.button>
                                        </form>
                                    @else
                                        <x-ui.button variant="ghost-secondary" size="sm"
                                            x-data x-on:click="$dispatch('sheet-open', 'edit-court-{{ $court->getKey() }}')">Edit</x-ui.button>

                                        @php
$used = $court->matters_count > 0;
@endphp
                                        <form method="POST" action="{{ route('court.destroy', $court) }}" class="d-inline"
                                            data-confirm="{{ $used ? 'Retire '.$court->label.'? It is no longer offered on new forms, and the files that carry it still read correctly.' : 'Delete '.$court->label.'? Nothing uses it. This cannot be undone.' }}"
                                            data-confirm-title="{{ $used ? 'Retire court' : 'Delete court' }}" data-confirm-action="{{ $used ? 'Retire' : 'Delete' }}"
                                            data-confirm-tone="danger" data-confirm-icon="{{ $used ? 'archive' : 'trash' }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost-danger" size="sm">{{ $used ? 'Retire' : 'Delete' }}</x-ui.button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-secondary py-4">No courts yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $courtForms = $courts->reject->trashed()->map(fn ($court) => [
                'sheet' => 'edit-court-'.$court->getKey(), 'bag' => 'court_'.$court->getKey(), 'title' => 'Edit court',
                'action' => route('court.update', $court), 'method' => 'PATCH',
                'tier' => $court->tier->value, 'name' => $court->name, 'seat' => $court->seat, 'submit' => 'Save court',
            ])->push([
                'sheet' => 'new-court', 'bag' => 'court_new', 'title' => 'Add a court',
                'action' => route('court.store'), 'method' => 'POST',
                'tier' => '', 'name' => '', 'seat' => '', 'submit' => 'Add court',
            ]);
        @endphp

        @foreach ($courtForms as $form)
            <x-ui.sheet :name="$form['sheet']" :title="$form['title']" :bag="$form['bag']" :action="$form['action']" :method="$form['method']">
                <x-ui.select label="Tier" name="tier" id="{{ $form['sheet'] }}_tier" :error="$errors->getBag($form['bag'])->first('tier')">
                    <option value="">Choose a tier…</option>
                    @foreach ($tiers as $tier)
                        <option value="{{ $tier->value }}" @selected($input($form['bag'], 'tier', $form['tier']) === $tier->value)>{{ $tier->courtName() }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Name" name="name" id="{{ $form['sheet'] }}_name" hint="As it is cited, e.g. High Court"
                    :value="$input($form['bag'], 'name', $form['name'])" :error="$errors->getBag($form['bag'])->first('name')" />

                <x-ui.input label="Seat" name="seat" id="{{ $form['sheet'] }}_seat" hint="Where it sits, e.g. Johannesburg"
                    :value="$input($form['bag'], 'seat', $form['seat'])" :error="$errors->getBag($form['bag'])->first('seat')" />

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm">{{ $form['submit'] }}</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    {{-- ===================================================== Matter types --}}
    @if ($tab === 'matter-types')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title mb-0">Matter types</h3>
                    <p class="text-meta mb-0">The code is written into every reference; it is fixed once a matter has used it.</p>
                </div>

                <x-ui.button variant="primary" icon="plus" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-matter-type')">Add matter type</x-ui.button>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Litigious</th>
                            <th>Prescribes after</th>
                            <th class="text-center">Matters</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matterTypes as $type)
                            <tr @class(['text-secondary' => $type->trashed()])>
                                <td class="text-meta">{{ $type->code }}</td>
                                <td>
                                    {{ $type->name }}
                                    @if ($type->trashed())
                                        <x-ui.stage-badge tone="neutral" class="ms-2">Retired</x-ui.stage-badge>
                                    @endif
                                </td>
                                <td>{{ $type->is_litigation ? 'Yes' : 'No' }}</td>
                                <td>{{ $type->default_prescription_months ? $type->default_prescription_months.' months' : '—' }}</td>
                                <td class="text-center">{{ $type->matters_count }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($type->trashed())
                                        <form method="POST" action="{{ route('matter-type.restore', $type) }}" class="d-inline">
                                            @csrf
                                            <x-ui.button type="submit" variant="ghost-secondary" size="sm">Restore</x-ui.button>
                                        </form>
                                    @else
                                        <x-ui.button variant="ghost-secondary" size="sm"
                                            x-data x-on:click="$dispatch('sheet-open', 'edit-matter-type-{{ $type->getKey() }}')">Edit</x-ui.button>

                                        @php
$used = $type->matters_count > 0;
@endphp
                                        <form method="POST" action="{{ route('matter-type.destroy', $type) }}" class="d-inline"
                                            data-confirm="{{ $used ? 'Retire '.$type->name.'? It is no longer offered when opening matters, and its '.$type->matters_count.' matters still read correctly.' : 'Delete '.$type->name.'? Nothing uses it. This cannot be undone.' }}"
                                            data-confirm-title="{{ $used ? 'Retire matter type' : 'Delete matter type' }}" data-confirm-action="{{ $used ? 'Retire' : 'Delete' }}"
                                            data-confirm-tone="danger" data-confirm-icon="{{ $used ? 'archive' : 'trash' }}">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost-danger" size="sm">{{ $used ? 'Retire' : 'Delete' }}</x-ui.button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-4">No matter types yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $typeForms = $matterTypes->reject->trashed()->map(fn ($type) => [
                'sheet' => 'edit-matter-type-'.$type->getKey(), 'bag' => 'matter_type_'.$type->getKey(), 'title' => 'Edit matter type',
                'action' => route('matter-type.update', $type), 'method' => 'PATCH',
                'code' => $type->code, 'name' => $type->name, 'months' => $type->default_prescription_months,
                'litigation' => $type->is_litigation, 'locked' => $type->matters_count > 0, 'submit' => 'Save matter type',
            ])->push([
                'sheet' => 'new-matter-type', 'bag' => 'matter_type_new', 'title' => 'Add a matter type',
                'action' => route('matter-type.store'), 'method' => 'POST',
                'code' => '', 'name' => '', 'months' => null, 'litigation' => false, 'locked' => false, 'submit' => 'Add matter type',
            ]);
        @endphp

        @foreach ($typeForms as $form)
            <x-ui.sheet :name="$form['sheet']" :title="$form['title']" :bag="$form['bag']" :action="$form['action']" :method="$form['method']">
                <x-ui.input label="Code" name="code" id="{{ $form['sheet'] }}_code" style="text-transform: uppercase;"
                    :value="$input($form['bag'], 'code', $form['code'])" :disabled="$form['locked']"
                    :hint="$form['locked'] ? 'Fixed: matters of this type carry it in their reference.' : 'Two to eight capital letters. It is written into every reference and cannot change once a matter uses it.'"
                    :error="$errors->getBag($form['bag'])->first('code')" />

                <x-ui.input label="Name" name="name" id="{{ $form['sheet'] }}_name"
                    :value="$input($form['bag'], 'name', $form['name'])" :error="$errors->getBag($form['bag'])->first('name')" />

                <x-ui.input label="Prescribes after (months)" name="default_prescription_months" id="{{ $form['sheet'] }}_months" type="number" min="1" max="600"
                    :value="$input($form['bag'], 'default_prescription_months', $form['months'])" before="Leave empty if it does not prescribe"
                    :error="$errors->getBag($form['bag'])->first('default_prescription_months')" />

                <input type="hidden" name="is_litigation" value="0">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_litigation" value="1" @checked($input($form['bag'], 'is_litigation', $form['litigation']))>
                    <span class="form-check-label">Before a court, so a court is required</span>
                </label>
                <p class="form-hint">Changes apply to matters opened from now on; matters already open keep what they have.</p>

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm">{{ $form['submit'] }}</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    {{-- ===================================================== Kinds of work --}}
    @if ($tab === 'activity-types')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title mb-0">Kinds of work</h3>
                    <p class="text-meta mb-0">How a narration is counted is fixed once one has been filed under the kind.</p>
                </div>

                <x-ui.button variant="primary" icon="plus" size="sm"
                    x-data x-on:click="$dispatch('sheet-open', 'new-activity-type')">Add kind of work</x-ui.button>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Counted in</th>
                            <th>Billable</th>
                            <th>Needs a court</th>
                            <th class="text-center">Narrations</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activityTypes as $type)
                            <tr @class(['text-secondary' => $type->trashed()])>
                                <td class="text-meta">{{ $type->code }}</td>
                                <td>
                                    {{ $type->name }}
                                    @if ($type->isSystem())
                                        <x-ui.stage-badge tone="info" class="ms-2">System</x-ui.stage-badge>
                                    @endif
                                    @if ($type->trashed())
                                        <x-ui.stage-badge tone="neutral" class="ms-2">Retired</x-ui.stage-badge>
                                    @endif
                                </td>
                                <td>{{ $type->unit_label }}</td>
                                <td>{{ $type->default_billable ? 'Yes' : 'No' }}</td>
                                <td>{{ $type->requires_court ? 'Yes' : 'No' }}</td>
                                <td class="text-center">{{ $type->narrations_count }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($type->trashed())
                                        <form method="POST" action="{{ route('activity-type.restore', $type) }}" class="d-inline">
                                            @csrf
                                            <x-ui.button type="submit" variant="ghost-secondary" size="sm">Restore</x-ui.button>
                                        </form>
                                    @else
                                        <x-ui.button variant="ghost-secondary" size="sm"
                                            x-data x-on:click="$dispatch('sheet-open', 'edit-activity-type-{{ $type->getKey() }}')">Edit</x-ui.button>

                                        @unless ($type->isSystem())
                                            @php
$used = $type->narrations_count > 0;
@endphp
                                            <form method="POST" action="{{ route('activity-type.destroy', $type) }}" class="d-inline"
                                                data-confirm="{{ $used ? 'Retire '.$type->name.'? It is no longer offered, and its '.$type->narrations_count.' narrations still read and bill as they did.' : 'Delete '.$type->name.'? Nothing uses it. This cannot be undone.' }}"
                                                data-confirm-title="{{ $used ? 'Retire kind of work' : 'Delete kind of work' }}" data-confirm-action="{{ $used ? 'Retire' : 'Delete' }}"
                                                data-confirm-tone="danger" data-confirm-icon="{{ $used ? 'archive' : 'trash' }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button type="submit" variant="ghost-danger" size="sm">{{ $used ? 'Retire' : 'Delete' }}</x-ui.button>
                                            </form>
                                        @endunless
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-secondary py-4">No kinds of work yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @php
            $workForms = $activityTypes->reject->trashed()->map(fn ($type) => [
                'sheet' => 'edit-activity-type-'.$type->getKey(), 'bag' => 'activity_type_'.$type->getKey(), 'title' => 'Edit kind of work',
                'action' => route('activity-type.update', $type), 'method' => 'PATCH',
                'code' => $type->code, 'name' => $type->name, 'measure' => $type->measure->value, 'increment' => $type->increment,
                'billable' => $type->default_billable, 'court' => $type->requires_court,
                'counting_locked' => $type->narrations_count > 0, 'code_locked' => $type->narrations_count > 0 || $type->isSystem(),
                'submit' => 'Save kind of work',
            ])->push([
                'sheet' => 'new-activity-type', 'bag' => 'activity_type_new', 'title' => 'Add a kind of work',
                'action' => route('activity-type.store'), 'method' => 'POST',
                'code' => '', 'name' => '', 'measure' => 'minutes', 'increment' => 6, 'billable' => true, 'court' => false,
                'counting_locked' => false, 'code_locked' => false, 'submit' => 'Add kind of work',
            ]);
        @endphp

        @foreach ($workForms as $form)
            <x-ui.sheet :name="$form['sheet']" :title="$form['title']" :bag="$form['bag']" :action="$form['action']" :method="$form['method']">
                <x-ui.input label="Code" name="code" id="{{ $form['sheet'] }}_code" style="text-transform: uppercase;"
                    :value="$input($form['bag'], 'code', $form['code'])" :disabled="$form['code_locked']"
                    :hint="$form['code_locked'] ? 'Fixed: narrations or the system itself use this code.' : 'Two to ten capital letters.'"
                    :error="$errors->getBag($form['bag'])->first('code')" />

                <x-ui.input label="Name" name="name" id="{{ $form['sheet'] }}_name"
                    :value="$input($form['bag'], 'name', $form['name'])" :error="$errors->getBag($form['bag'])->first('name')" />

                <x-ui.select label="Counted in" name="measure" id="{{ $form['sheet'] }}_measure" :disabled="$form['counting_locked']"
                    :hint="$form['counting_locked'] ? 'Fixed: narrations have been filed under this kind of work.' : null"
                    :error="$errors->getBag($form['bag'])->first('measure')">
                    @foreach ($measures as $measure)
                        <option value="{{ $measure->value }}" @selected($input($form['bag'], 'measure', $form['measure']) === $measure->value)>{{ $measureLabel($measure) }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.input label="Billed in steps of" name="increment" id="{{ $form['sheet'] }}_increment" type="number" min="1" max="1000" :disabled="$form['counting_locked']"
                    :value="$input($form['bag'], 'increment', $form['increment'])"
                    hint="A quantity rounds up to the next step, so with 6 a 4 minute call bills one unit."
                    :error="$errors->getBag($form['bag'])->first('increment')" />

                <input type="hidden" name="default_billable" value="0">
                <label class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="default_billable" value="1" @checked($input($form['bag'], 'default_billable', $form['billable']))>
                    <span class="form-check-label">Billable</span>
                </label>

                <input type="hidden" name="requires_court" value="0">
                <label class="form-check">
                    <input class="form-check-input" type="checkbox" name="requires_court" value="1" @checked($input($form['bag'], 'requires_court', $form['court']))>
                    <span class="form-check-label">Needs a court (for appearances)</span>
                </label>

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm">{{ $form['submit'] }}</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    {{-- ===================================================== Tariffs --}}
    @if ($tab === 'tariffs')
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title mb-0">Tariffs</h3>
                    <p class="text-meta mb-0">An upload is a draft until it is checked and published. Each tariff is then in force from its date until a newer one starts.</p>
                </div>

                <div class="d-flex gap-2">
                    <x-ui.button :href="route('tariff-schedule.template')" variant="outline-secondary" icon="download" size="sm">Template</x-ui.button>
                    <x-ui.button variant="primary" icon="upload" size="sm"
                        x-data x-on:click="$dispatch('sheet-open', 'new-tariff-schedule')">Upload tariff</x-ui.button>
                </div>
            </div>

            @if ($errors->getBag('tariff_schedules')->any())
                <div class="alert alert-danger m-3 mb-0">{{ $errors->getBag('tariff_schedules')->first() }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Tariff</th>
                            <th>In force from</th>
                            <th class="text-center">Rates</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tariffSchedules as $schedule)
                            <tr>
                                <td>
                                    <a href="{{ route('tariff-schedule.show', $schedule) }}">{{ $schedule->name }}</a>
                                    @php
                                        [$tone, $label] = ['draft' => ['accent', 'Draft'], 'upcoming' => ['info', 'Upcoming'], 'in_force' => ['success', 'In force'], 'replaced' => ['neutral', 'Replaced']][$schedule->status($tariffInForce)];
                                    @endphp
                                    <x-ui.stage-badge :tone="$tone" class="ms-2">{{ $label }}</x-ui.stage-badge>
                                </td>
                                <td>{{ $schedule->effective_from->format('j M Y') }}</td>
                                <td class="text-center">{{ $schedule->items_count }}</td>
                                <td class="text-end text-nowrap">
                                    <x-ui.button :href="route('tariff-schedule.source', $schedule)" variant="ghost-secondary" size="sm">Download</x-ui.button>

                                    @unless ($schedule->hasStarted())
                                        <form method="POST" action="{{ route('tariff-schedule.destroy', $schedule) }}" class="d-inline"
                                            data-confirm="Remove {{ $schedule->name }}? It has not come into force, so nothing has been charged from it."
                                            data-confirm-title="Remove tariff" data-confirm-action="Remove"
                                            data-confirm-tone="danger" data-confirm-icon="trash">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost-danger" size="sm">Remove</x-ui.button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">No tariffs yet. Download the template, copy in the gazette's rates and upload it.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <x-ui.sheet name="new-tariff-schedule" title="Upload a tariff" bag="tariff_schedule_new"
            :action="route('tariff-schedule.store')" method="POST" enctype="multipart/form-data">
            <x-ui.input label="Name" name="name" id="tariff_name" hint="As the gazette calls it, e.g. Magistrates' Courts tariff 2027"
                :value="$input('tariff_schedule_new', 'name', '')" :error="$errors->getBag('tariff_schedule_new')->first('name')" />

            <x-ui.input label="In force from" name="effective_from" id="tariff_effective_from" type="date"
                :value="$input('tariff_schedule_new', 'effective_from', '')" :error="$errors->getBag('tariff_schedule_new')->first('effective_from')" />

            <x-ui.input label="Tariff file" name="file" id="tariff_file" type="file" accept=".csv"
                hint="A .csv in the template's layout: one line per kind of work, with its rate in rand on each scale. Leave a scale empty when the gazette does not quote it." />

            @if ($errors->getBag('tariff_schedule_new')->has('file'))
                <div class="alert alert-danger">
                    <p class="mb-1">Nothing was saved. Fix these lines and upload the file again:</p>
                    <ul class="mb-0">
                        @foreach ($errors->getBag('tariff_schedule_new')->get('file') as $problem)
                            <li>{{ $problem }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button variant="outline-secondary" size="sm" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="primary" size="sm">Upload tariff</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endif
@endsection
