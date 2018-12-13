@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-body">
                        <a href="{{ route('login') }}" class="btn btn-secondary">Login with GitHub</a>

                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-white"><strong>.update.yml</strong></div>

                    <div class="card-body">
                       <pre class="bg-light p-2"><code>enabled: true
updates:
  - path: /
</code></pre>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
