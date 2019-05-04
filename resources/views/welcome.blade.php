@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card bg-secondary">
                    <div class="card-body">
                        <a href="{{ route('login') }}" class="btn btn-dark">Login with GitHub</a>

                    </div>
                </div>

                <div class="card mt-3 bg-secondary">
                    <div class="card-header text-white"><strong>.update.yml</strong></div>

                    <div class="card-body bg-dark">
                       <pre class="text-white bg-dark p-2"><code>enabled: true
updates:
  - path: /
</code></pre>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
