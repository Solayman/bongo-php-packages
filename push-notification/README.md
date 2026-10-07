# push-notification

## How delivery works (and what you must configure)

- **Web Push:** create keys once with `php bongo push:vapid` and put `WEBPUSH_PUBLIC_KEY` / `WEBPUSH_PRIVATE_KEY` in `.env`. Pass the whole subscription from the browser (`PushSubscription.toJSON()`: `endpoint` plus `keys.p256dh` and `keys.auth`) to `sendWebPush()`. The payload is encrypted as required by RFC 8291 (`aes128gcm`, verified against the RFC test vector) and signed with VAPID ES256 (RFC 8292), using only PHP's openssl extension.
- Subscription endpoints come from the browser, so only `https` URLs on public addresses are accepted (no requests to `127.0.0.1`, `10.x`, `169.254.169.254`...).
- **FCM v1:** set `FCM_PROJECT_ID`, `FCM_CLIENT_EMAIL` and `FCM_PRIVATE_KEY` from the Firebase service account JSON. An OAuth2 access token is created from them (RS256) and cached until it expires.
