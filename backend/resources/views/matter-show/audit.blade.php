<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title mb-0">Audit trail</h3>
            <p class="text-meta mb-0">Everything recorded against this matter, its narrations, diary, documents and assignments</p>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Detail</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($activities as $activity)
                    <tr>
                        <td class="text-meta text-nowrap">{{ $activity->created_at->format('d M H:i') }}</td>
                        <td>{{ $activity->causer?->name ?? 'System' }}</td>
                        <td class="text-meta text-secondary">{{ $activity->event }}</td>
                        <td>{{ $activity->description }}</td>
                        <td class="text-meta">{{ $activity->properties['ip'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-secondary py-4">Nothing recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($activities->hasPages())
        <div class="card-footer d-flex align-items-center justify-content-between">
            <span class="text-meta">showing {{ $activities->count() }} of {{ $activities->total() }}</span>
            <div class="d-flex gap-3">
                @if (! $activities->onFirstPage())
                    <a href="{{ $activities->previousPageUrl() }}" class="text-meta">← newer</a>
                @endif
                @if ($activities->hasMorePages())
                    <a href="{{ $activities->nextPageUrl() }}" class="text-meta">older →</a>
                @endif
            </div>
        </div>
    @endif
</div>
