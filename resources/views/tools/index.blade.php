@extends('twill::layouts.free')

@section('customPageContent')
    <div style="padding: 20px 0; background: #fff; border-radius: 8px; padding: 24px;">
        @if(session('status'))
            <div style="background: #c6f6d5; border: 1px solid #9ae6b4; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; color: #22543d;">
                {{ session('status') }}
            </div>
        @endif

        @if(session('error'))
            <div style="background: #fed7d7; border: 1px solid #feb2b2; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; color: #742a2a;">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div style="background: #fed7d7; border: 1px solid #feb2b2; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; color: #742a2a;">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div style="display: grid; grid-template-columns: 1fr 1px 1fr; gap: 32px; align-items: start;">
            {{-- Import --}}
            <div>
                <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 16px;">Import CSV</h3>

                <form method="POST" action="{{ route(config('twill.admin_route_name_prefix', 'admin.').'translations.translationTools.importFromCsv') }}" enctype="multipart/form-data">
                    @csrf
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <input type="file" name="csv_file" accept=".csv" required
                            style="font-size: 13px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px;">
                        <select name="csv_delimiter" style="font-size: 13px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px;">
                            <option value=";" {{ config('translation-handler.csvDelimiter', ';') === ';' ? 'selected' : '' }}>; (semicolon)</option>
                            <option value="," {{ config('translation-handler.csvDelimiter', ';') === ',' ? 'selected' : '' }}>, (comma)</option>
                            <option value="tab" {{ config('translation-handler.csvDelimiter', ';') === "\t" ? 'selected' : '' }}>⇥ (tab)</option>
                        </select>
                        <button type="submit" class="btn" style="background: #3182ce; color: #fff; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                            Import
                        </button>
                    </div>
                    <ul style="font-size: 12px; color: #718096; margin: 0;">
                        <li>Only keys present in the file will be updated.</li>
                        <li>All locale columns must be present in the header.</li>
                        <li>Empty values will overwrite the existing translation with an empty string.</li>
                    </ul>
                </form>
            </div>

            {{-- Separator --}}
            <div style="background: #e2e8f0; height: 100%;"></div>

            {{-- Export --}}
            <div>
                <h3 style="font-size: 15px; font-weight: 600; margin-bottom: 16px;">Export CSV</h3>

                <form method="POST" action="{{ route(config('twill.admin_route_name_prefix', 'admin.').'translations.translationTools.exportToCsv') }}">
                    @csrf
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                        <select name="csv_delimiter" style="font-size: 13px; padding: 6px; border: 1px solid #e2e8f0; border-radius: 4px;">
                            <option value=";" {{ config('translation-handler.csvDelimiter', ';') === ';' ? 'selected' : '' }}>; (semicolon)</option>
                            <option value="," {{ config('translation-handler.csvDelimiter', ';') === ',' ? 'selected' : '' }}>, (comma)</option>
                            <option value="tab" {{ config('translation-handler.csvDelimiter', ';') === "\t" ? 'selected' : '' }}>⇥ (tab)</option>
                        </select>
                        <button type="submit" class="btn" style="background: #2f855a; color: #fff; padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                            Download CSV
                        </button>
                    </div>
                </form>
                <ul style="font-size: 12px; color: #718096; margin: 0;">
                    <li>Exports all translations from the database.</li>
                    <li>To download only a specific group, use the Download CSV button on the group's edit page.</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
