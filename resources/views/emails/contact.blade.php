<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Contact form submission</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #4f46e5;">New contact message</h2>

    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <p><strong>Name:</strong> {{ $contact->name }}</p>
        <p><strong>Email:</strong> {{ $contact->email }}</p>
        @if (filled($contact->company))
            <p><strong>Company:</strong> {{ $contact->company }}</p>
        @endif
    </div>

    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-top: 0;">Message</h3>
        <p style="white-space: pre-wrap;">{{ $contact->message }}</p>
    </div>
</div>
</body>
</html>
