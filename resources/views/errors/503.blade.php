<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Unavailable</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: sans-serif; background:#f8fafc; color:#1e293b; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;">
    <div style="text-align:center; max-width:28rem; padding:2rem;">
        <h1 style="font-size:1.5rem; margin-bottom:0.5rem;">Service temporarily unavailable</h1>
        <p style="color:#64748b;">{{ $message ?? 'A required service (database) could not be reached. Please try again in a moment.' }}</p>
    </div>
</body>
</html>
