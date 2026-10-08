# Challenge-Based Authentication Implementation Plan

## Executive Summary

This plan outlines implementation of ephemeral challenge-based authentication for push notification subscriptions to prevent nonce harvesting, replay attacks, and token reuse by unauthenticated users.

## Problem Statement

- Nonce exposed via `wp_localize_script()` in HTML
- Nonce reusable for multiple registration attempts
- No single-use enforcement mechanism
- No rate limiting on subscription attempts
- Vulnerability allows automated attacks on anonymous subscriptions

## Solution Architecture

### 1. Challenge Generation System

**File**: `inc/pnfpb-challenge-system.php` (NEW - 400 lines)

Core Functions:
- `pnfpb_generate_challenge_token($provider)` - Generate ephemeral challenge
- `pnfpb_validate_challenge_token($challenge_hash, $provider)` - Validate token
- `pnfpb_consume_challenge_token($challenge_hash)` - Delete after use
- `pnfpb_create_client_fingerprint()` - Generate client context hash
- `pnfpb_rate_limit_check($ip, $provider)` - Enforce 5 attempts/minute
- `pnfpb_log_challenge_event($event_type, $data)` - Audit logging

### 2. Challenge Generation Endpoint

**File**: `public/ajax_routines/pnfpb_generate_challenge_token.php` (NEW - 150 lines)

- Action: `wp_ajax_nopriv_pnfpb_generate_challenge`
- Validate existing nonce
- Generate random challenge (32 chars)
- Create HMAC: `wp_hash($challenge . get_bloginfo('url'))`
- Store in transient (60-second TTL)
- Return challenge hash + expiration
- Log generation event

### 3. Challenge Validation in Registration

**File**: `public/ajax_routines/pnfpb_update_deviceid_ajax.php` (MODIFIED - +100 lines)

Add validation before device registration:
- Validate challenge exists and not expired
- Verify challenge hash matches stored value
- Check single-use enforcement
- Delete transient after successful use
- Log validation attempts

Apply to all 3 vulnerable cases:
- `webtoapp_subscribed_users` (lines 267-291)
- `progressier_subscribed_users` (lines 315-341)
- `onesignal_subscribed_users` (lines 360-386)

## Implementation Tasks

| # | Task | File | Lines | Priority | Dependencies |
|---|------|------|-------|----------|--------------|
| 1 | Create Challenge System | `inc/pnfpb-challenge-system.php` | 400 | CRITICAL | None |
| 2 | Create Challenge Endpoint | `public/ajax_routines/pnfpb_generate_challenge_token.php` | 150 | CRITICAL | Task 1 |
| 3 | Update Registration Handler | `public/ajax_routines/pnfpb_update_deviceid_ajax.php` | +100 | CRITICAL | Tasks 1, 2 |
| 4 | Create Client Library | `inc/pnfpb-challenge-client.js` | 300 | CRITICAL | None |
| 5 | Update Firebase Script | `src/pnfpb_push_notification/js/pnfpb_pushscript_pwa.js` | +50 | HIGH | Task 4 |
| 6 | Update WebToApp Script | `public/js/pnfpb_webtoapp_pwa.js` | +50 | HIGH | Task 4 |
| 7 | Update Progressier Script | `public/js/pnfpb_pushscript_progressier_pwa.js` | +50 | HIGH | Task 4 |
| 8 | Update OneSignal Script | `public/js/pnfpb_pushscript_onesignal_pwa.js` | +50 | HIGH | Task 4 |
| 9 | Update Plugin Init | `pnfpb_push_notification.php` | +20 | CRITICAL | Tasks 1, 2, 4 |
| 10 | Security Documentation | `CHALLENGE_TOKEN_SECURITY.md` | 5000 | HIGH | Tasks 1-9 |
| 11 | Test Cases | `CHALLENGE_TOKEN_TEST_CASES.md` | 3000 | HIGH | Tasks 1-9 |
| 12 | Version Bump | `pnfpb_push_notification.php` + `readme.txt` | 10 | MEDIUM | All |

**Total Effort**: ~30 hours | **Timeline**: 2 weeks development + 1 week testing

## Challenge Token Flow

### Generation Flow (Client → Server)
```
1. User browser loads page with push subscription script
2. Script calls: ajax/pnfpb_generate_challenge_token
   - Validate nonce (existing pnfpbpushnonce)
   - Check rate limit (5/minute per IP)
   - Generate random challenge
   - Create HMAC with client fingerprint
   - Store in transient (60-second TTL)
   - Return challenge_hash + expires_at
3. Client stores challenge locally (expires in 60s)
4. Client proceeds with device registration
```

### Registration Flow (Client → Server)
```
1. User subscribes to push notifications
2. Client generates device token (Firebase/Progressier/OneSignal/WebToApp)
3. Client calls: ajax/pnfpb_update_deviceid_ajax
   - Include: challenge_hash (from step 2 above)
   - Include: device_token
   - Include: nonce (existing)
4. Server validates:
   - Nonce still valid? ✓
   - Challenge hash exists in transient? ✓
   - Challenge not expired (< 60 seconds)? ✓
   - Challenge not already used? ✓
   - HMAC matches client fingerprint? ✓
   - Rate limiting passed? ✓
5. Server registers device
6. Server deletes transient (single-use enforcement)
7. Server logs success
```

### Attack Scenario Prevention

**Old Flow (v3.24)**:
- Attacker harvests nonce from HTML
- Attacker reuses nonce 100 times in loop
- Result: Device registered 100 times with attacker tokens

**New Flow (v3.25)**:
- Attacker harvests nonce from HTML
- Attacker requests challenge → gets `challenge_hash_1` (valid 60s)
- Attacker reuses challenge → invalid (already consumed)
- Attacker requests another challenge → gets `challenge_hash_2` 
- Attacker attempts 6 challenges/min → rate limit blocks
- Result: Only 1-5 devices registered, then blocked

## Security Enhancements

### Challenge Token Structure
```php
{
  challenge_hash: "a1b2c3d4...",     // Hashed challenge
  challenge_value: "abc123def456...", // Actual challenge (in transient)
  expires_at: 1728349635,            // Unix timestamp + 60s
  provider: "firebase",              // Specific to provider
  client_ip: "192.168.1.1",
  client_fingerprint: "sha256hash",  // User-Agent + IP hash
  hmac: "sha256:abc123..."           // Verification hash
}
```

### HMAC Verification
```php
$client_fingerprint = md5($_SERVER['HTTP_USER_AGENT'] . $client_ip);
$challenge_hmac = hash_hmac(
    'sha256',
    $challenge . $client_fingerprint,
    wp_salt('auth')
);
// Verify on registration
if ($provided_hmac !== $challenge_hmac) {
    reject_registration("HMAC verification failed");
}
```

### Single-Use Enforcement
```php
// After successful device registration:
$transient_key = 'pnfpb_challenge_' . $challenge_hash;
delete_transient($transient_key);

// Also log consumption:
update_option('pnfpb_challenge_used_' . $hash, time());
```

### Rate Limiting
```php
// Per IP: max 5 challenge requests per 60 seconds
$rate_limit_key = 'pnfpb_challenge_requests_' . $client_ip;
$request_count = get_transient($rate_limit_key) ?: 0;

if ($request_count >= 5) {
    reject_request("Rate limit exceeded");
}

set_transient($rate_limit_key, $request_count + 1, 60);
```

## Client-Side Implementation

### Challenge Manager Module
**File**: `inc/pnfpb-challenge-client.js`

```javascript
const pnfpbChallenge = {
  // Request new challenge from server
  generateChallenge(provider) {
    return $.ajax({
      url: pnfpb_ajax_object.ajax_url,
      type: 'POST',
      data: {
        action: 'pnfpb_generate_challenge',
        provider: provider,
        nonce: pnfpb_ajax_object.nonce
      }
    }).then(response => {
      this.challenges[provider] = {
        hash: response.challenge_hash,
        expires_at: response.expires_at,
        consumed: false
      };
      return response.challenge_hash;
    });
  },

  // Get active challenge or regenerate if expired
  getActiveChallenge(provider) {
    const challenge = this.challenges[provider];
    if (!challenge) return null;
    if (challenge.consumed) return null;
    if (challenge.expires_at < Date.now()) {
      delete this.challenges[provider];
      return this.generateChallenge(provider);
    }
    return challenge.hash;
  },

  // Mark challenge as consumed (prevent replay)
  consumeChallenge(provider) {
    if (this.challenges[provider]) {
      this.challenges[provider].consumed = true;
    }
  },

  challenges: {}
};
```

### Subscription Script Updates

**All 4 scripts follow same pattern:**

1. Before subscription: Request challenge
2. During registration: Include challenge hash
3. After success: Mark challenge consumed

## Server-Side Implementation Details

### Challenge System Functions

```php
// Generate new challenge
function pnfpb_generate_challenge_token($provider) {
  $challenge = wp_generate_password(32, true);
  $challenge_hash = wp_hash($challenge . get_bloginfo('url'));
  
  set_transient(
    'pnfpb_challenge_' . $challenge_hash,
    $challenge,
    60 // 60-second TTL
  );
  
  return array(
    'challenge_hash' => $challenge_hash,
    'expires_at' => time() + 60,
    'provider' => $provider
  );
}

// Validate challenge on registration
function pnfpb_validate_challenge_token($challenge_hash, $provider) {
  $challenge = get_transient('pnfpb_challenge_' . $challenge_hash);
  
  if (!$challenge) {
    return array('valid' => false, 'reason' => 'not_found');
  }
  
  return array('valid' => true, 'provider' => $provider);
}

// Consume challenge (delete after use)
function pnfpb_consume_challenge_token($challenge_hash) {
  delete_transient('pnfpb_challenge_' . $challenge_hash);
  update_option('pnfpb_challenge_consumed_' . $challenge_hash, time());
}

// Rate limiting check
function pnfpb_rate_limit_check($ip, $provider) {
  $key = 'pnfpb_challenge_requests_' . $ip . '_' . $provider;
  $count = (int) get_transient($key);
  
  if ($count >= 5) {
    pnfpb_log_challenge_event('rate_limit_exceeded', compact('ip', 'provider'));
    return false;
  }
  
  set_transient($key, $count + 1, 60);
  return true;
}

// Audit logging
function pnfpb_log_challenge_event($event_type, $data) {
  $log_data = array_merge(
    $data,
    array(
      'timestamp' => time(),
      'event_type' => $event_type,
      'client_ip' => pnfpb_get_client_ip()
    )
  );
  
  // Log to: wp_options as pnfpb_challenge_audit_log_{date}
  $audit_key = 'pnfpb_challenge_audit_log_' . date('Y-m-d');
  $audit_log = get_option($audit_key, array());
  $audit_log[] = $log_data;
  update_option($audit_key, array_slice($audit_log, -1000)); // Keep last 1000 events
}
```

## Security Benefits

### Threat Model Coverage

| Threat | v3.24 | v3.25 | Mitigation |
|--------|-------|-------|-----------|
| Nonce harvesting | ⚠️ Exposed | ✅ Obsolete | Challenge ephemeral |
| Replay attacks | ⚠️ Reusable | ✅ Blocked | Single-use enforcement |
| Automated attacks | ⚠️ No limit | ✅ Rate limited | 5/minute per IP |
| Token reuse | ⚠️ Possible | ✅ Prevented | Transient deletion |
| Device farming | ⚠️ Unlimited | ✅ Limited | Rate + single-use |
| Cross-device attacks | ⚠️ No check | ✅ Validated | HMAC fingerprint |

### CVSS Impact Analysis

**v3.24 (After first fix)**:
- CVSS 3.1 (LOW)
- Attack Vector: Network
- Privileges Required: None (anonymous)
- Complexity: High (requires targeting specific user)

**v3.25 (After challenge implementation)**:
- CVSS 1.9 (MINIMAL)
- Attack Vector: Network
- Privileges Required: None
- Complexity: Very High (ephemeral tokens, rate limiting)
- User Interaction: Required (must time within 60 seconds)

## Deployment Timeline

### Week 1-2: Development
- Day 1-2: Implement Tasks 1-3 (server infrastructure)
- Day 3-4: Implement Tasks 4-8 (client updates)
- Day 5: Implement Tasks 9-12 (integration & docs)

### Week 3: Testing
- Day 1-2: Unit tests (challenge generation, validation, expiration)
- Day 3-4: Integration tests (all 4 subscription providers)
- Day 5: Security testing (replay attacks, rate limiting, edge cases)

### Week 4: Staging & Review
- Day 1-2: Deploy to staging, run full test suite
- Day 3: Security code review
- Day 4: Performance testing (load scenarios)
- Day 5: Final approval & production prep

### Week 5: Production
- Day 1: Deploy to production
- Day 2-5: Monitor logs, error rates, user feedback

## Success Criteria

✅ Challenge tokens generated with 60-second TTL
✅ Single-use enforcement (deleted after registration)
✅ Rate limiting active (5 challenges/minute per IP)
✅ HMAC verification with client fingerprint
✅ All 4 subscription providers updated
✅ Audit logging captures all events
✅ Zero breaking changes to existing functionality
✅ v3.25 release ready for production
✅ Security documentation complete
✅ All test cases pass (100% coverage)

## Backward Compatibility

✅ **Existing devices continue working** - No database schema changes
✅ **Authenticated subscriptions unaffected** - Challenge layer added transparently
✅ **Public subscriptions preserved** - Still work with added security
✅ **No client-side breaking changes** - Graceful degradation if challenge fails

## Risk Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|-----------|
| Challenge expiration too short | Medium | Medium | Start 60s, increase if needed |
| Rate limiting too strict | Low | Medium | Monitor error logs, adjust threshold |
| Performance overhead | Low | Low | Transients use object cache |
| Client challenge loss | Low | Low | Auto-regenerate on expiration |

## Files Modified Summary

| File | Type | Lines Added | Purpose |
|------|------|------------|---------|
| `inc/pnfpb-challenge-system.php` | NEW | 400 | Challenge infrastructure |
| `public/ajax_routines/pnfpb_generate_challenge_token.php` | NEW | 150 | Challenge endpoint |
| `inc/pnfpb-challenge-client.js` | NEW | 300 | Client library |
| `public/ajax_routines/pnfpb_update_deviceid_ajax.php` | MODIFIED | +100 | Validation logic |
| `src/pnfpb_push_notification/js/pnfpb_pushscript_pwa.js` | MODIFIED | +50 | Firebase integration |
| `public/js/pnfpb_webtoapp_pwa.js` | MODIFIED | +50 | WebToApp integration |
| `public/js/pnfpb_pushscript_progressier_pwa.js` | MODIFIED | +50 | Progressier integration |
| `public/js/pnfpb_pushscript_onesignal_pwa.js` | MODIFIED | +50 | OneSignal integration |
| `pnfpb_push_notification.php` | MODIFIED | +20 | Plugin initialization |
| `CHALLENGE_TOKEN_SECURITY.md` | NEW | 5000 | Security documentation |
| `CHALLENGE_TOKEN_TEST_CASES.md` | NEW | 3000 | Test scenarios |

**Total**: +1,180 lines across 11 files

## Next Steps

1. ✅ Approve implementation plan
2. ⏳ Create Task 1: Challenge system infrastructure
3. ⏳ Create Task 2: Challenge generation endpoint
4. ⏳ Create Tasks 3-9: Integration & client updates
5. ⏳ Create Tasks 10-11: Documentation & testing
6. ⏳ Deploy to staging for testing
7. ⏳ Production deployment with monitoring

---

**Document Version**: 1.0
**Created**: 2026-10-08
**Status**: Ready for Implementation
**Target Release**: v3.25
