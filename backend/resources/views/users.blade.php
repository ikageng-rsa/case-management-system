@extends('layouts.app')

@section('title', 'Users & roles — Example Firm')

@section('content')
    @php
        // What this person may do on the page is each its own permission, so each control follows its own.
        $canEdit = auth()->user()->can('edit_users');
        $canInvite = auth()->user()->can('invite_users');
        $canAssign = auth()->user()->can('assign_roles');
        $canShape = auth()->user()->can('manage_roles');
        $label = fn (string $value) => Str::headline($value);

        // A sheet reopens on its own errors, and only those; another form's
        // failed input must not leak into this one.
        $input = fn (string $bag, string $field, mixed $default) => $errors->getBag($bag)->any() ? old($field, $default) : $default;
    @endphp

    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <h1 class="mb-1">Users &amp; roles</h1>
            <p class="text-secondary mb-4">{{ $total }} active · {{ $invitations->filter->isPending()->count() }} invited</p>
            {{-- Planned: needs deactivation
            <p class="text-secondary mb-4">9 active · 1 invited · 2 disabled</p>
            --}}
        </div>

        <div class="d-flex align-items-center gap-2">
            <x-ui.button size="sm" variant="outline-secondary" icon="table"
                x-data x-on:click="$dispatch('sheet-open', 'permission-matrix')">Permission matrix</x-ui.button>

            @if ($canShape)
                <x-ui.button size="sm" variant="outline-secondary" icon="shield"
                    x-data x-on:click="$dispatch('sheet-open', 'roles')">Roles</x-ui.button>
            @endif

            @if ($canInvite)
                <x-ui.button size="sm" variant="dark" icon="plus"
                    x-data x-on:click="$dispatch('sheet-open', 'invite-user')">Invite user</x-ui.button>
            @endif
        </div>
    </div>

    <div class="card mb-4">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Last active</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span @class(['avatar avatar-rounded', 'bg-primary text-dark' => $user->is(auth()->user()), 'bg-secondary-lt' => ! $user->is(auth()->user())])>{{ mb_substr(Str::initials($user->name, capitalize: true), 0, 2) }}</span>
                                    <div>
                                        <div class="fw-bold">{{ $user->name }}</div>
                                        <div class="text-meta">{{ \App\Services\Clients\MaskIdentifier::email($user->email) }}@if ($user->job_title) · {{ $user->job_title }}@endif</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $user->roles->pluck('name')->map($label)->implode(' · ') ?: '—' }}</td>
                            <td class="text-secondary">{{ ($lastActive[$user->getKey()] ?? null)?->diffForHumans() ?? '—' }}</td>
                            <td><x-ui.stage-badge tone="success">Active</x-ui.stage-badge></td>
                            <td class="text-end text-nowrap">
                                @if ($canEdit)
                                    <x-ui.button variant="ghost-secondary" size="sm"
                                        x-data x-on:click="$dispatch('sheet-open', 'edit-user-{{ $user->getKey() }}')">Details</x-ui.button>
                                @endif

                                @if ($canAssign)
                                    <x-ui.button variant="ghost-secondary" size="sm"
                                        x-data x-on:click="$dispatch('sheet-open', 'user-roles-{{ $user->getKey() }}')">Roles</x-ui.button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="card-footer d-flex align-items-center justify-content-between">
                <span class="text-meta">showing {{ $users->count() }} of {{ $users->total() }}</span>
                <div class="d-flex gap-3">
                    @if (! $users->onFirstPage())
                        <a href="{{ $users->previousPageUrl() }}" class="text-meta">← previous</a>
                    @endif
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="text-meta">next →</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($invitations->isNotEmpty())
        <h3 class="text-meta text-uppercase mb-2">Invitations</h3>

        <div class="card mb-4">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Invited</th>
                            <th>Roles</th>
                            <th>Invited by</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invitations as $invitation)
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $invitation->name }}</div>
                                    <div class="text-meta">{{ \App\Services\Clients\MaskIdentifier::email($invitation->email) }}</div>
                                </td>
                                <td>{{ $roles->whereIn('id', $invitation->roles)->pluck('name')->map($label)->implode(' · ') ?: '—' }}</td>
                                <td class="text-secondary">{{ $invitation->inviter?->name ?? '—' }}</td>
                                <td class="text-secondary text-nowrap">{{ $invitation->expires_at->format('d M Y') }}</td>
                                <td>
                                    @if ($invitation->isPending())
                                        <x-ui.stage-badge tone="accent">Invited</x-ui.stage-badge>
                                    @else
                                        <x-ui.stage-badge tone="neutral">Expired</x-ui.stage-badge>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($canInvite)
                                        <form method="POST" action="{{ route('invitation.resend', $invitation) }}" class="d-inline">
                                            @csrf
                                            <x-ui.button type="submit" variant="ghost-secondary" size="sm">Resend</x-ui.button>
                                        </form>

                                        <form method="POST" action="{{ route('invitation.destroy', $invitation) }}" class="d-inline"
                                            data-confirm="Withdraw the invitation for {{ $invitation->name }}? The link stops working."
                                            data-confirm-title="Withdraw invitation" data-confirm-action="Withdraw" data-confirm-tone="danger" data-confirm-icon="mail-off">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="ghost-danger" size="sm">Withdraw</x-ui.button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($canInvite)
        <x-ui.sheet name="invite-user" title="Invite a user" lede="They set their own password from the link we email them." bag="invite"
            :action="route('invitation.store')">
            <x-ui.input label="Name" name="name" id="invite_name" :value="old('name')" :error="$errors->invite->first('name')" />
            <x-ui.input label="Email" name="email" id="invite_email" type="email" :value="old('email')" :error="$errors->invite->first('email')" />

            <x-ui.select label="Link expires after" name="lifetime_days" id="invite_lifetime" :error="$errors->invite->first('lifetime_days')"
                hint="After this the link stops working; it can be sent again.">
                @foreach ($lifetimes as $days)
                    <option value="{{ $days }}" @selected((int) old('lifetime_days', $defaultLifetime) === $days)>{{ $days }} {{ Str::plural('day', $days) }}</option>
                @endforeach
            </x-ui.select>

            @if ($canAssign)
                <div class="form-label form-label-mono">Roles</div>
                @foreach ($roles as $role)
                    <label class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->getKey() }}"
                            @checked(in_array($role->getKey(), array_map('intval', (array) old('roles', [])), true))>
                        <span class="form-check-label">{{ $label($role->name) }}</span>
                    </label>
                @endforeach
                @if ($errors->invite->has('roles'))
                    <div class="text-danger">{{ $errors->invite->first('roles') }}</div>
                @endif
            @endif

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button size="sm" variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                    <x-ui.button size="sm" type="submit" variant="primary">Send invitation</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>
    @endif

    @if ($canEdit)
        @foreach ($users as $person)
            @php
                $detailsBag = 'user_details_'.$person->getKey();
                $detail = fn (string $field, mixed $current) => $errors->getBag($detailsBag)->any() ? old($field, $current) : $current;
            @endphp

            <x-ui.sheet name="edit-user-{{ $person->getKey() }}" title="Staff details" :lede="$person->name" :bag="$detailsBag" method="PATCH"
                :action="route('user.details.update', $person)">
                <p class="text-secondary">These are kept by the firm. The person can see them on their account page but cannot change them.</p>

                <x-ui.input label="Name" name="name" id="user_name_{{ $person->getKey() }}" :value="$detail('name', $person->name)"
                    :error="$errors->getBag($detailsBag)->first('name')" />
                <x-ui.input label="Email" name="email" id="user_email_{{ $person->getKey() }}" type="email" :value="$detail('email', '')"
                    :placeholder="\App\Services\Clients\MaskIdentifier::email($person->email)"
                    hint="Leave empty to keep the current address. Changing it signs the person out of their other sessions."
                    :error="$errors->getBag($detailsBag)->first('email')" />
                <x-ui.input label="Job title" name="job_title" id="user_title_{{ $person->getKey() }}" :value="$detail('job_title', $person->job_title)"
                    :error="$errors->getBag($detailsBag)->first('job_title')" />
                <x-ui.input label="Mobile" name="mobile" id="user_mobile_{{ $person->getKey() }}" type="tel" :value="$detail('mobile', '')"
                    :placeholder="$person->mobile ? \App\Services\Clients\MaskIdentifier::mobile($person->mobile) : null"
                    :hint="$person->mobile ? 'Leave empty to keep the current number.' : null" :error="$errors->getBag($detailsBag)->first('mobile')" />
                <x-ui.input label="Office phone" name="office_phone" id="user_office_{{ $person->getKey() }}" type="tel" :value="$detail('office_phone', '')"
                    :placeholder="$person->office_phone ? \App\Services\Clients\MaskIdentifier::mobile($person->office_phone) : null"
                    :hint="$person->office_phone ? 'Leave empty to keep the current number.' : null" :error="$errors->getBag($detailsBag)->first('office_phone')" />
                <x-ui.input label="Employee number" name="employee_number" id="user_number_{{ $person->getKey() }}" :value="$detail('employee_number', $person->employee_number)"
                    :error="$errors->getBag($detailsBag)->first('employee_number')" />

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button size="sm" variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button size="sm" type="submit" variant="primary">Save details</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    <x-ui.sheet name="permission-matrix" title="Permission matrix" lede="What each role may do. Permissions are fixed by the system; roles choose among them." width="min(1180px, 96vw)">
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Capability</th>
                        @foreach ($roles as $role)
                            <th class="text-center">{{ $label($role->name) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $permission)
                        <tr>
                            <td>{{ $label($permission->value) }}</td>
                            @foreach ($roles as $role)
                                @if ($role->permissions->contains('name', $permission->value))
                                    <td class="text-center">✓</td>
                                @else
                                    <td class="text-center text-secondary">—</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <x-slot:footer>
            <x-ui.button size="sm" variant="outline-secondary" x-on:click="close()">Close</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    @if ($canShape)
        <x-ui.sheet name="roles" title="Roles" lede="Rename, add or remove roles. Users keep the permissions their roles carry." bag="roles">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th class="text-center">Permissions</th>
                        <th class="text-center">Users</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($roles as $role)
                        <tr>
                            <td>{{ $label($role->name) }}</td>
                            <td class="text-center text-secondary">{{ $role->permissions->count() }}</td>
                            <td class="text-center text-secondary">{{ $role->users_count }}</td>
                            <td class="text-end text-nowrap">
                                <x-ui.button variant="ghost-secondary" size="sm" x-data
                                    x-on:click="close(); setTimeout(() => $dispatch('sheet-open', 'edit-role-{{ $role->getKey() }}'), 350)">Edit</x-ui.button>

                                <form method="POST" action="{{ route('role.destroy', $role) }}" class="d-inline"
                                    data-confirm="Delete the {{ $label($role->name) }} role? This cannot be undone."
                                    data-confirm-title="Delete role" data-confirm-action="Delete" data-confirm-tone="danger" data-confirm-icon="trash">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="ghost-danger" size="sm">Delete</x-ui.button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-slot:footer>
                <div class="d-flex align-items-center justify-content-between">
                    <x-ui.button size="sm" variant="outline-secondary" x-on:click="close()">Close</x-ui.button>
                    <x-ui.button size="sm" variant="primary" icon="plus" x-data
                        x-on:click="close(); setTimeout(() => $dispatch('sheet-open', 'new-role'), 350)">New role</x-ui.button>
                </div>
            </x-slot:footer>
        </x-ui.sheet>

        @php
            $roleForms = $roles->map(fn ($role) => [
                'sheet' => 'edit-role-'.$role->getKey(),
                'bag' => 'role_'.$role->getKey(),
                'action' => route('role.update', $role),
                'method' => 'PATCH',
                'title' => 'Edit '.$label($role->name),
                'name' => $role->name,
                'granted' => $role->permissions->pluck('name')->all(),
                'submit' => 'Save role',
            ])->push([
                'sheet' => 'new-role',
                'bag' => 'role_new',
                'action' => route('role.store'),
                'method' => 'POST',
                'title' => 'New role',
                'name' => '',
                'granted' => [],
                'submit' => 'Create role',
            ]);
        @endphp

        @foreach ($roleForms as $form)
            <x-ui.sheet :name="$form['sheet']" :title="$form['title']" :bag="$form['bag']" wide
                :action="$form['action']" :method="$form['method']">
                <x-ui.input label="Role name" name="name" id="{{ $form['sheet'] }}_name" maxlength="50"
                    :value="$input($form['bag'], 'name', $form['name'])" :error="$errors->getBag($form['bag'])->first('name')" />

                @if ($errors->getBag($form['bag'])->has('permissions'))
                    <div class="text-danger mb-3">{{ $errors->getBag($form['bag'])->first('permissions') }}</div>
                @endif

                <div class="form-label form-label-mono">Permissions</div>
                <div class="row">
                    @php
                        $checked = $input($form['bag'], 'permissions', $form['granted']);
                    @endphp
                    @foreach ($permissions as $permission)
                        <div class="col-md-6">
                            <label class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->value }}"
                                    @checked(in_array($permission->value, (array) $checked, true))>
                                <span class="form-check-label">{{ $label($permission->value) }}</span>
                            </label>
                        </div>
                    @endforeach
                </div>

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button size="sm" variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button size="sm" type="submit" variant="primary">{{ $form['submit'] }}</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif

    @if ($canAssign)
        @foreach ($users as $user)
            @php
                $bag = 'user_roles_'.$user->getKey();
                $held = $input($bag, 'roles', $user->roles->pluck('id')->all());
            @endphp

            <x-ui.sheet name="user-roles-{{ $user->getKey() }}" title="Roles for {{ $user->name }}" lede="A user carries the permissions of every role they hold." :bag="$bag"
                :action="route('user.roles.update', $user)" method="PATCH">
                @foreach ($roles as $role)
                    <label class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->getKey() }}"
                            @checked(in_array($role->getKey(), array_map('intval', (array) $held), true))>
                        <span class="form-check-label">{{ $label($role->name) }}</span>
                    </label>
                @endforeach

                <x-slot:footer>
                    <div class="d-flex align-items-center justify-content-between">
                        <x-ui.button size="sm" variant="outline-secondary" x-on:click="close()">Cancel</x-ui.button>
                        <x-ui.button size="sm" type="submit" variant="primary">Save roles</x-ui.button>
                    </div>
                </x-slot:footer>
            </x-ui.sheet>
        @endforeach
    @endif
@endsection
