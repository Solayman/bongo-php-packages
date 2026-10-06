# Bongo Honeypot Anti-Spam Package

Invisible, zero-friction anti-spam and anti-bot form protection for Bongo PHP. Eliminates spam comments, fake contact submissions, and automated registrations without irritating users with math problems or image captchas.

## How It Works
1. **Decoy Field Trap**: Renders a visually hidden form input (`my_name`) that automated bots greedily fill, but real human users never see.
2. **Signed Timestamp Token**: Encrypts and cryptographically signs (`HMAC-SHA256`) the render timestamp. Bots submitting forms instantaneously (< 2 seconds) or with tampered timestamps are rejected.

## Installation
```bash
php bongo package:add honeypot
```

## Form Integration

Add `{!! honeypot() !!}` anywhere inside your HTML `<form>`:

```html
<form action="/contact" method="POST">
    {!! csrf_field() !!}
    {!! honeypot() !!}

    <input type="text" name="name" placeholder="Your Name" required>
    <input type="email" name="email" placeholder="Your Email" required>
    <textarea name="message" placeholder="Message"></textarea>

    <button type="submit">Send Message</button>
</form>
```

## Controller Validation

```php
use Bongo\Packages\Honeypot\Honeypot;

class ContactController
{
    public function submit()
    {
        // Option 1: Fluent guard (throws SpamDetectedException on failure)
        Honeypot::guard();

        // Option 2: Inspect result manually
        $result = Honeypot::check();
        if ($result->isSpam()) {
            return back()->with('error', $result->message());
        }

        // Process message...
    }
}
```

## Automated Testing Support
Disable honeypot checks during PHP test suites:
```php
Honeypot::fake();
```
