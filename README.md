# Push Notification for Post and BuddyPress (PNFPB)

**Version:** 3.14  
**Author:** [Muralidharan Ramasamy](https://www.muraliwebworld.com)  
**License:** [GPLv2 or later](http://www.gnu.org/licenses/gpl-2.0.html)  
**Requires WordPress:** 6.2+  
**Requires PHP:** 8.1+  
**Tested up to:** WordPress 6.9  

---

Send free push notifications for WordPress posts, custom post types, BuddyPress activities, and mobile app webviews — and generate a Progressive Web App (PWA) — all powered by Firebase Cloud Messaging (FCM HTTP v1), OneSignal, Progressier, or Web Push.

---

## Features

### Push Notification Providers
- **Firebase FCM HTTP v1** — free push notifications for desktop, PWA, and mobile apps
- **Self-hosted Web Push** — using the Web Push standard
- **OneSignal** — free push notifications for desktop, PWA, and mobile apps
- **Progressier** — push notifications for PWA
- **webtoapp.design** — push notifications for mobile apps

Supports sending to Firebase/OneSignal and webtoapp.design users **simultaneously**.

---

### Notification Triggers

| Event | Audience |
|-------|----------|
| New post / custom post type published (including bbPress, WooCommerce) | All subscribers |
| New BuddyPress activity | All subscribers |
| New BuddyPress group activity | Group members only |
| BuddyPress mentions in activities | Recipient only |
| BuddyPress group invite | Recipient only |
| BuddyPress group details updated | All group subscribers |
| New BuddyPress comment | All subscribers |
| BuddyPress private message (also compatible with BetterMessages) | Recipient only |
| New BuddyPress member joined | All subscribers |
| BuddyPress friend request | Recipient only |
| BuddyPress friendship accepted | Requestor only |
| BuddyPress user avatar change | All subscribers |
| BuddyPress cover image change | All subscribers |
| Mark as favourite / Like on BuddyPress activity | All subscribers |
| Contact Form 7 submitted | Admin only |
| New user registration | Admin only |

---

### Subscription Management

- Custom popup and bell-prompt for subscribing/unsubscribing
- Per-user category preferences (post, activity, comments, friendship, etc.)
- BuddyPress profile settings nav for per-user control
- Shortcode `[subscribe_PNFPB_push_notification]` for anywhere-on-site subscription UI
- Shortcode `[PNFPB_PWA_PROMPT]` for PWA install button
- Dynamic shortcodes: `[member name]`, `[group name]` in notification body

---

### Performance & Scale

- Supports **200,000+ subscribers** via background Action Scheduler
- **Three-tier async topic subscription** for fast AJAX responses on any hosting type:
  1. `fastcgi_finish_request()` / `litespeed_finish_request()` — closes HTTP connection before Firebase IID calls (PHP-FPM / LiteSpeed)
  2. Named cumulative transient queue (`pnfpb_pending_topic_sync`) — flushed synchronously before every FCM dispatch (shared hosting)
  3. WP-Cron safety drain — +5 minute backstop
- Scheduled notifications: hourly, twice daily, daily, weekly
- Action Scheduler integration for high-volume background dispatch

---

### Progressive Web App (PWA)

- Generate a full PWA with offline cache
- Customise app name, icon, theme colour, background colour
- Offline fallback page support
- Compatible with Progressier PWA
- NGINX static file support for dynamic service worker / manifest

---

### REST API (Mobile App Integration)

Receive Firebase push subscription tokens from native/hybrid Android and iOS apps.

```
POST https://<your-domain>/wp-json/PNFPBpush/v1/subscriptiontoken
```

- [Android integration example](https://github.com/muraliwebworld/android-app-to-integrate-push-notification-wordpress-plugin/)
- [iOS (Swift) integration example](https://github.com/muraliwebworld/ios-swift-app-to-integrate-push-notification-wordpress-plugin/)

---

### Analytics

Firebase Analytics reports in the Firebase console for `notification_open`, `notification_read`, and `page_view` events.

---

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin.
3. Navigate to **Settings → Push Notification using FCM**.
4. Configure your preferred notification provider (Firebase, OneSignal, Progressier, etc.).
5. Enable notification triggers for the content types you need.
6. *(Optional)* Enable PWA in the PWA settings tab.
7. *(Optional)* Place `[subscribe_PNFPB_push_notification]` on any page for a front-end subscription UI.

---

## Firebase Configuration

### Required fields (Firebase HTTP v1)

| Field | Where to find it |
|-------|-----------------|
| Firebase Server Key | Project Settings → Cloud Messaging → Server key |
| Firebase Config (fields 2–8) | Project Settings → General → Your apps → Config |
| Firebase Public Key (VAPID) | Project Settings → Cloud Messaging → Web Push certificates → Generate Key Pair |

**Video tutorial:** [YouTube — How to configure Firebase](https://www.youtube.com/watch?v=02oymYLt3qo)

---

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[subscribe_PNFPB_push_notification]` | Subscribe/unsubscribe UI for front-end users |
| `[PNFPB_PWA_PROMPT]` | PWA install button |

Dynamic variables usable in notification body: `[member name]`, `[group name]`

---

## NGINX Extra Configuration

If your server is NGINX and cannot serve the dynamic service worker (`/pnfpb_icpush_pwa_sw.js`) or PWA manifest (`/pnfpbmanifest.json`), go to  
**Plugin Settings → NGINX tab** and enable **Static file creation**.

---

## Developer Notes

### Async Topic Subscription Architecture

On shared hosting (no PHP-FPM), Firebase IID topic subscription calls are queued in a named WordPress transient (`pnfpb_pending_topic_sync`) and **flushed synchronously at the start of every notification dispatch** — eliminating race conditions between subscription changes and FCM sends.

Key hooks:
- `pnfpb_async_topic_subscription` — WP-Cron hook for the safety drain
- `PNFPB_flush_pending_topic_sync()` — called before all FCM dispatch methods
- `PNFPB_execute_async_topic_sync()` — WP-Cron callback

### Action Scheduler Integration

All notification events support background dispatch via Action Scheduler. Enable this per notification type in plugin settings to handle large subscriber volumes without blocking the main thread.

### BuddyBoss Compatibility

Full compatibility with BuddyBoss Platform Plugin and BetterMessages.

---

## Frequently Asked Questions

**Q: Does this plugin work with BuddyBoss?**  
Yes. Full compatibility with BuddyBoss Platform Plugin is included.

**Q: Can I use it with a native mobile app?**  
Yes. Use the REST API endpoint to register device tokens from Android/iOS apps.

**Q: How many subscribers does it support?**  
Unlimited, via background Action Scheduler processing for high-volume sends.

**Q: How do I report a security vulnerability?**  
Via the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/wordpress/plugin/push-notification-for-post-and-buddypress/vdp).

---

## Changelog

### 3.14
- Performance improvement: three-tier async topic subscription (fastcgi → named transient queue → WP-Cron)
- AJAX handler refactored: `if/else` chains replaced with `switch/case`
- Pending topic sync flushed before every FCM dispatch to eliminate race conditions on shared hosting
- Bug fixes

### 3.13
- Bug fixes

---

## License

This plugin is licensed under the [GNU General Public License v2.0 or later](http://www.gnu.org/licenses/gpl-2.0.html).

Copyright © 2024 [Indiacitys.com Technologies Private Limited](https://indiacitys.com), Coimbatore, India.

---

## Links

- [Plugin Demo](https://www.muraliwebworld.com/)
- [Support Forum](https://www.muraliwebworld.com/groups/wordpress-plugins-by-muralidharan-indiacitys-com-technologies/forum/topic/push-notification-for-post-and-buddypress/)
- [Contact](https://indiacitys.com/#contact)
- [Donate / Support](https://www.muraliwebworld.com/support-to-push-notification-plugin-for-buddypress-and-for-post/)
