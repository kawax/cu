@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card text-white bg-dark">
                    <div class="card-header"><strong>Your License</strong></div>

                    <div class="card-body bg-secondary">
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
            </div>

            <div class="col-md-5">
                @isset($github_repos)
                    <div class="card text-white bg-dark mt-3">
                        <div class="card-header">
                            <h4>GitHub Repos</h4>
                            <span class="badge badge-pill badge-secondary">Sort by Pushed</span>
                            <span class="badge badge-pill badge-secondary">Public and Private</span>
                        </div>

                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach($github_repos as $repo)
                                    <li class="list-group-item list-group-item-secondary">{{ $repo }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endisset
            </div>
            <div class="col-md-5">

                @isset($gitlab_repos)
                    <div class="card text-white bg-dark mt-3">
                        <div class="card-header">
                            <h4>GitLab Repos</h4>
                            <span class="badge badge-pill badge-secondary">Sort by Activity</span>
                            <span class="badge badge-pill badge-secondary">Public and Private</span>
                        </div>

                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach($gitlab_repos as $repo)
                                    <li class="list-group-item list-group-item-secondary">{{ $repo }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endisset
            </div>
        </div>
    </div>
@endsection
