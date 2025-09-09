<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Demo Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2563eb;">New Demo Request</h2>

    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-top: 0;">Contact Information</h3>
        <p><strong>Name:</strong> {{ $data['name'] }}</p>
        <p><strong>Email:</strong> {{ $data['email'] }}</p>
        <p><strong>Website:</strong> {{ $data['website'] }}</p>
    </div>

    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-top: 0;">Location</h3>
        <p><strong>City:</strong> {{ $data['city'] }}</p>
        <p><strong>Postal Code:</strong> {{ $data['postal_code'] }}</p>
        <p><strong>Country:</strong> {{ $data['country'] }}</p>
    </div>

    <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
        <h3 style="margin-top: 0;">Usage Timeline</h3>
        <p>{{ $data['usage_timeline'] }}</p>
    </div>

</div>
</body>
</html>
