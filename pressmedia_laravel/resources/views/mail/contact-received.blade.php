{{ $msg->name() }}{{ $msg->organisation ? ' · '.$msg->organisation : '' }}
{{ $msg->email }}{{ $msg->phone ? ' · '.$msg->phone : '' }}{{ $msg->city || $msg->country ? ' · '.trim($msg->city.' '.$msg->country) : '' }}
{{ strtoupper($msg->locale) }} · {{ $msg->created_at->format('Y-m-d H:i') }}

{{ $msg->message }}

Admin: {{ route('admin.messages.show', $msg) }}
