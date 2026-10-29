@extends('admin.layout')
@section('title', 'Settings')
@section('content')
<div class="a-page-head"><div><h1>Settings</h1></div></div>
@php($locked = ! auth()->user()->can('settings.edit'))
<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
    @csrf @method('PUT')
    @foreach ($sections as $section => $fields)
        <fieldset class="a-card a-card-b mb-4" @disabled($locked)>
            <legend class="a-eyebrow">{{ $section }}</legend>
            @foreach ($fields as $key => [, $type])
                <div class="mb-3">
                    @if ($type === 'bool')
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $values[$key]))> {{ $key }}</label>
                    @else
                        <label class="a-label" for="{{ $key }}">{{ $key }}</label>
                        @if ($type === 'text')
                            <textarea class="a-textarea" id="{{ $key }}" name="{{ $key }}" rows="2">{{ old($key, $values[$key]) }}</textarea>
                        @elseif (str_starts_with($type, 'enum:'))
                            <select class="a-select" id="{{ $key }}" name="{{ $key }}">
                                @foreach (explode(',', substr($type, 5)) as $opt)<option value="{{ $opt }}" @selected(old($key, $values[$key]) === $opt)>{{ $opt }}</option>@endforeach
                            </select>
                        @elseif ($type === 'secret')
                            <input class="a-input" id="{{ $key }}" name="{{ $key }}" type="password" autocomplete="off" placeholder="{{ $values[$key] !== '' ? '•••••••• (stored, leave blank to keep)' : 'Not set' }}">
                            @if ($values[$key] !== '')<label class="mt-1 flex items-center gap-2 text-xs"><input type="checkbox" name="{{ $key }}_clear" value="1"> Remove stored key</label>@endif
                        @else
                            <input class="a-input" id="{{ $key }}" name="{{ $key }}" type="{{ $type === 'int' ? 'number' : ($type === 'color' ? 'color' : 'text') }}" value="{{ old($key, $values[$key]) }}">
                        @endif
                    @endif
                </div>
            @endforeach
        </fieldset>
    @endforeach
    @unless ($locked)<button class="a-btn a-btn-primary" type="submit">Save settings</button>@endunless
</form>
@endsection
