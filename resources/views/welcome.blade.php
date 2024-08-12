<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email List</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .container {
            max-width: 500px;
            margin: 20px auto;
            border: 1px solid #ccc;
            padding: 20px;
        }

        #copy-content {
            max-height: 50vh; /* Adjust this value as needed */
            overflow-y: auto;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>


<body>
    <div class="container">

        <h1>Grouped Email List</h1>

        @if (!!$status->active)
            <form action="{{ route('stop-crawling') }}" method="POST">
                @csrf
                <button type="submit">Stop Crawling</button>
            </form>
        @else
            <form action="{{ route('blastemail') }}" method="post">
                @csrf
                <input type="text" name="url" id="">
                <button type="submit">Fetch</button>
            </form>
        @endif

        <h5>Date: {{ date('d M y') }}</h5>
        <h5>Status: {{ !!$status->active ? 'Running' : 'Stopped' }}</h5>
        <h5>Total: {{ count($emails) }} / {{ count($emails_count) }}</h5>
        <button id="copy-to-clipboard">Copy to Clipboard</button>
        <br>
        <br>
        <div id="copy-content">
            @foreach ($emails as $email)
                {{ $email->email }} <br>
            @endforeach
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
