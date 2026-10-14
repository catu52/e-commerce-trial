<x-filament-panels::page>
    <style>
        .media-card { border: 1px solid color-mix(in srgb, var(--primary-500, #f59e0b) 45%, transparent); transition: border-color .15s, box-shadow .15s; }
        .media-card:hover { border-color: var(--primary-500, #f59e0b); box-shadow: 0 0 0 1px var(--primary-500, #f59e0b); }
        .media-list { border: 1px solid color-mix(in srgb, var(--primary-500, #f59e0b) 45%, transparent); border-radius: .5rem; overflow-x: auto; }
        .media-list table { border-collapse: separate; border-spacing: 0 .5rem; width: 100%; padding: 0 .5rem; }
        .media-list thead th { border-bottom: 2px solid var(--primary-500, #f59e0b); padding: .75rem; }
        .media-list tbody td { padding: .75rem; border-top: 1px solid color-mix(in srgb, var(--primary-500, #f59e0b) 35%, transparent); border-bottom: 1px solid color-mix(in srgb, var(--primary-500, #f59e0b) 35%, transparent); vertical-align: middle; }
        .media-list tbody td:first-child { border-left: 1px solid color-mix(in srgb, var(--primary-500, #f59e0b) 35%, transparent); border-radius: .5rem 0 0 .5rem; }
        .media-list tbody td:last-child { border-right: 1px solid color-mix(in srgb, var(--primary-500, #f59e0b) 35%, transparent); border-radius: 0 .5rem .5rem 0; }
        .media-list tbody tr:hover td { background: color-mix(in srgb, var(--primary-500, #f59e0b) 8%, transparent); }
    </style>
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:1.5rem">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:1rem;flex:1;min-width:0">
            <div style="flex:1 1 16rem;max-width:24rem">
                <x-filament::input.wrapper>
                    <x-filament::input type="search" wire:model.live.debounce.300ms="search" placeholder="Search files…" />
                </x-filament::input.wrapper>
            </div>
            <div style="flex:0 0 11rem">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="folder">
                        <option value="">All folders</option>
                        @foreach ($folders as $f)
                            <option value="{{ $f }}">{{ $f }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div style="flex:0 0 10rem">
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="usage">
                        <option value="all">All files</option>
                        <option value="used">In use</option>
                        <option value="unused">Unused</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
        <div style="display:inline-flex;flex:none;gap:.5rem;padding:.375rem;border-radius:.5rem" class="bg-white ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/20">
            @foreach (['grid' => ['heroicon-o-squares-2x2', 'Large grid'], 'compact' => ['heroicon-o-table-cells', 'Compact grid'], 'list' => ['heroicon-o-list-bullet', 'List']] as $key => [$icon, $label])
                <x-filament::icon-button :icon="$icon" :label="$label" :tooltip="$label" :color="$viewMode === $key ? 'primary' : 'gray'" wire:click="setViewMode('{{ $key }}')" style="padding:.375rem" />
            @endforeach
        </div>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $total }} file(s)</p>

    @if ($files->isEmpty())
        <p class="py-12 text-center text-gray-500 dark:text-gray-400">No files found.</p>
    @else
        @if ($viewMode === 'list')
            <div class="media-list bg-white dark:bg-gray-900">
                <table class="text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr><th class="p-3">Preview</th><th class="p-3">File</th><th class="p-3">Folder</th><th class="p-3">Size</th><th class="p-3">Modified</th><th class="p-3">Used by</th><th class="p-3"></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($files as $file)
                            <tr wire:key="media-row-{{ md5($file['path']) }}">
                                <td class="p-3">
                                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="rounded bg-gray-100 dark:bg-gray-800" style="display:flex;align-items:center;justify-content:center;overflow:hidden;width:100px;height:100px">
                                        @if ($file['isImage'])
                                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy" style="width:100%;height:100%;object-fit:contain;display:block" />
                                        @else
                                            <x-filament::icon icon="heroicon-o-document" class="text-gray-400" style="width:2rem;height:2rem" />
                                        @endif
                                    </a>
                                </td>
                                <td class="max-w-xs truncate p-3 font-medium text-gray-950 dark:text-white" title="{{ $file['path'] }}">{{ $file['name'] }}</td>
                                <td class="p-3">{{ $file['folder'] ?: 'root' }}</td>
                                <td class="p-3 whitespace-nowrap">{{ \Illuminate\Support\Number::fileSize($file['size']) }}</td>
                                <td class="p-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modifiedAt'])->format('Y-m-d H:i') }}</td>
                                <td class="p-3">{{ $file['usedBy'] ? implode(', ', $file['usedBy']) : 'Unused' }}</td>
                                <td class="p-3">
                                    <div class="flex gap-2" x-data>
                                        <x-filament::button size="xs" color="gray" x-on:click="navigator.clipboard.writeText(@js($file['url'])); $tooltip('Copied', { timeout: 1500 })">Copy URL</x-filament::button>
                                        @if (! $file['usedBy'])
                                            <x-filament::button size="xs" color="danger" wire:click="deleteFile(@js($file['path']))" wire:confirm="Delete this file permanently?">Delete</x-filament::button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
        <div style="display:grid;gap:1rem;grid-template-columns: repeat(auto-fill, minmax({{ $viewMode === 'compact' ? 'min(100%, 7rem), 400px' : 'min(100%, 13rem), 600px' }}));justify-content:start;">
            @foreach ($files as $file)
                <div wire:key="media-{{ md5($file['path']) }}" class="media-card rounded-lg bg-white dark:bg-gray-900" style="display:flex;flex-direction:column;overflow:hidden;min-width:0">
                    <a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="bg-gray-100 dark:bg-gray-800" style="display:flex;align-items:center;justify-content:center;aspect-ratio:1/1;width:100%;overflow:hidden">
                        @if ($file['isImage'])
                            <img src="{{ $file['url'] }}" alt="{{ $file['name'] }}" loading="lazy" style="width:100%;height:100%;object-fit:contain;display:block" />
                        @else
                            <x-filament::icon icon="heroicon-o-document" class="text-gray-400" style="width:2.5rem;height:2.5rem" />
                        @endif
                    </a>
                    <div class="text-xs" style="display:flex;flex:1;flex-direction:column;gap:.25rem;padding:{{ $viewMode === 'compact' ? '.5rem' : '.75rem' }};min-width:0">
                        <p class="truncate font-medium text-gray-950 dark:text-white" title="{{ $file['path'] }}">{{ $file['name'] }}</p>
                        @if ($viewMode !== 'compact')
                        <p class="text-gray-500 dark:text-gray-400">{{ $file['folder'] ?: 'root' }} · {{ \Illuminate\Support\Number::fileSize($file['size']) }}</p>
                        @if ($file['usedBy'])
                            <p class="text-primary-600 dark:text-primary-400" title="{{ implode(', ', $file['usedBy']) }}">Used by {{ count($file['usedBy']) }}: {{ $file['usedBy'][0] }}</p>
                        @else
                            <p class="text-gray-400">Unused</p>
                        @endif
                        @else
                            <p class="text-gray-400">{{ \Illuminate\Support\Number::fileSize($file['size']) }}@if ($file['usedBy']) · in use @endif</p>
                        @endif
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:auto;padding-top:.5rem" x-data>
                            <x-filament::button size="xs" color="gray" x-on:click="navigator.clipboard.writeText(@js($file['url'])); $tooltip('Copied', { timeout: 1500 })">Copy URL</x-filament::button>
                            @if (! $file['usedBy'])
                                <x-filament::button size="xs" color="danger" wire:click="deleteFile(@js($file['path']))" wire:confirm="Delete this file permanently?">Delete</x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    @endif

    <x-filament::pagination :paginator="$paginator" :page-options="[12, 24, 48, 96]" :current-page-option-property="'perPage'" />
</x-filament-panels::page>
