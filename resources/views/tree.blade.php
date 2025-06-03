<!DOCTYPE html>
<html>
<head>
    <title>User Tree</title>
</head>
<body>
    <ul>
        @foreach ($users as $user)
            @include('partials.user_node', ['user' => $user])
        @endforeach
    </ul>
</body>
</html>
