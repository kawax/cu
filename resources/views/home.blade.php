@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header bg-white"><strong>Your License</strong></div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        <dl class="row">
                            <dt class="col-2">GitHub</dt>
                            <dd class="col-10">{{ auth()->user()->name }}</dd>
                            <dt class="col-2">Expired</dt>
                            <dd class="col-10">{{ optional(auth()->user()->expired_at)->toDateString() ?? 'forever' }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-white">
                        <strong>Your Repos</strong>
                        <span class="badge badge-pill badge-secondary">Sort by pushed</span>
                        <span class="badge badge-pill badge-secondary">Public only</span>
                    </div>

                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            @foreach($repos as $repo)
                                <li class="list-group-item">{{ $repo }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
