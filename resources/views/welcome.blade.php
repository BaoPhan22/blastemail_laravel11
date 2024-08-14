<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email List</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Roboto
        }

        .container {
            max-width: 500px;
            margin: 20px auto;
            border: 1px solid #ccc;
            padding: 20px;
        }

        #copy-content {
            max-height: 50vh;
            /* Adjust this value as needed */
            overflow-y: auto;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .copy-content {
            margin-top: 10px;
            max-height: 30vh;
            /* Adjust this value as needed */
            border-bottom: 1px solid #ddd;
            border-top: 1px solid #ddd;
            overflow-y: auto;
            padding-top: 10px;
        }
    </style>
</head>


<body>
    <div class="container">

        <h1 class="mb-3">Grouped Email List</h1>

        <div class="row mb-3">
            <div class="col-12">
                @if (!!$status->active)
                    <form action="{{ route('stop-crawling') }}" method="POST">
                        @csrf
                        <button class="btn btn-danger" type="submit">Stop Crawling</button>
                    </form>
                @else
                    <form action="{{ route('blastemail') }}" method="post">
                        @csrf
                        <div class="input-group">
                            <input class="form-control" type="text" name="url" id="">
                            <button class="btn btn-primary" type="submit">Fetch</button>
                        </div>
                    </form>
                @endif
                <div class="mt-3">
                    <h5>Date: {{ date('d M y') }}</h5>
                    <h5>Status: {{ !!$status->active ? 'Running' : 'Stopped' }}</h5>
                    <h5>Total: {{ count($emails) }} / {{ count($emails_count) }}</h5>
                </div>
            </div>

        </div>

        <button class="btn btn-primary mb-3" id="copy-to-clipboard">Copy to Clipboard</button>
        <div id="copy-content">
            @foreach ($emails as $email)
                <a href="{{ route('hideemail', $email->id) }}">{{ $email->email }}</a>
                <br>
            @endforeach
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <form action="{{ route('skip_extension') }}" method="post">
                    @csrf
                    <label for="" class="form-label">Skip Extensions ({{ count($skip_extensions) }})</label>
                    <div class="input-group">
                        <input class="form-control" type="text" name="extension" id="">
                        <button class="btn btn-primary" type="submit">Add</button>
                    </div>
                </form>
                <div class="copy-content">
                    <ul>
                        @foreach ($skip_extensions as $skip_extension)
                            <li>{{ $skip_extension }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <div class="col-12 mt-4">
                <form action="{{ route('skip_site') }}" method="post">
                    @csrf
                    <label for="" class="form-label">Skip Sites ({{ count($skip_sites) }})</label>
                    <div class="input-group">
                        <input class="form-control" type="text" name="url" id="">
                        <button class="btn btn-primary" type="submit">Add</button>
                    </div>
                </form>
                <div class="copy-content">
                    <ul>
                        @foreach ($skip_sites as $skip_site)
                            <li>{{ $skip_site }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('copy-to-clipboard').addEventListener('click', function() {
                var content = document.getElementById('copy-content').innerText;

                var textarea = document.createElement('textarea');
                textarea.value = content;

                document.body.appendChild(textarea);

                textarea.select();
                document.execCommand('copy');

                document.body.removeChild(textarea);

                alert('Content copied to clipboard!');
            });
        });
    </script>

</body>

</html>
