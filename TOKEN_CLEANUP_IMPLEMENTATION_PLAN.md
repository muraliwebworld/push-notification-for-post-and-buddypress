# Token Cleanup Background Job Implementation Plan

## Overview
This plan outlines the implementation of an automated background job system to identify, track, and clean up stale/expired/invalid Firebase Cloud Messaging (FCM) tokens from the `{$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web` table.

**Key Principle**: Instead of deleting invalid tokens immediately, mark them with a `token_status` column and let admins review/delete them manually through the admin interface.

---

## Phase 1: Database Schema Migration

### 1.1 Add `token_status` Column
**File**: New migration file `public/pnfpb_send_notification_routines/database/pnfpb_token_cleanup_migrations.php`

**Changes**:
- Add `token_status` column to `{$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web` table
  - Type: VARCHAR(50)
  - Default: 'valid'
  - Values: 'valid', 'invalid', 'unverified', 'pending_verification'
  
- Add indexed columns for better query performance:
  - INDEX on `token_status` for filtering
  - INDEX on `created_at` (if not exists) for timestamp tracking
  - INDEX on `last_verified_at` (new field) for cleanup scheduling

**Migration Handler**:
```sql
ALTER TABLE {$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web
ADD COLUMN token_status VARCHAR(50) DEFAULT 'valid' AFTER device_id,
ADD COLUMN last_verified_at DATETIME DEFAULT NULL,
ADD INDEX idx_token_status (token_status),
ADD INDEX idx_last_verified_at (last_verified_at);
```

---

## Phase 2: Core Token Validation Service

### 2.1 Create Token Validation Helper Class
**File**: `public/pnfpb_send_notification_routines/pnfpb_token_validation/PNFPB_Token_Validation_Service.php`

**Class**: `PNFPB_Token_Validation_Service`

**Methods**:

1. **`is_stale_token_response( $response )`** - Detect stale tokens
   - Check HTTP 404 (UNREGISTERED)
   - Parse error details for: UNREGISTERED, INVALID_ARGUMENT, SENDER_ID_MISMATCH
   - Return boolean

2. **`validate_token( $token, $validate_only = true )`** - Single token validation
   - Send message to FCM with `validate_only => true`
   - Use existing JWT auth from Firebase HTTP v1
   - Call `is_stale_token_response()` on response
   - Return validation result: ['valid' => bool, 'error_code' => string, 'http_code' => int]

3. **`get_next_batch_tokens( $limit = 100, $status = 'unverified' )`** - Batch fetching
   - Query tokens with `token_status = $status`
   - Order by `last_verified_at` (oldest first) or `created_at`
   - Limit to $limit (1-500)
   - Return array of token records

4. **`update_token_status( $token_id, $status, $error_code = null )`** - Update token
   - Update `token_status` and `last_verified_at`
   - Store `error_code` in new column `last_validation_error`
   - Return success/failure

5. **`mark_batch_for_validation( $batch_size = 100 )`** - Mark unverified tokens
   - Query tokens with NULL `last_verified_at` or `token_status = 'valid'` (older than X days)
   - Set status to 'pending_verification'
   - Return count marked

---

### 2.2 Firebase HTTP v1 Integration
**File**: Extend `public/pnfpb_send_notification_routines/pnfpb_firebase_httpv1_notification/pnfpb_firebase_httpv1_notification.php`

**Add Method**: `send_validation_message( $token )`
- Prepare minimal message payload with `validate_only => true`
- Use existing JWT auth mechanism
- Send to FCM
- Return raw response

**Payload Structure**:
```php
$payload = [
    'message' => [
        'token' => $token,
        'data' => [
            'validation' => 'only',
        ],
        // Minimal notification
        'notification' => [
            'title' => 'Verification',
            'body' => 'Token Verification',
        ],
    ],
];
```

---

## Phase 3: Background Job Implementation

### 3.1 Create Scheduled Background Job
**File**: `public/pnfpb_send_notification_routines/pnfpb_token_cleanup/PNFPB_Token_Cleanup_Background_Job.php`

**Class**: `PNFPB_Token_Cleanup_Background_Job`

**Purpose**: Execute token validation in batches via Action Scheduler

**Methods**:

1. **`schedule_cleanup_job( $frequency = 'daily', $batch_limit = 100 )`**
   - Register recurring action using Action Scheduler
   - Hook: `pnfpb_token_cleanup_batch_job`
   - Frequency: daily, weekly, monthly (via WP Cron)
   - Clear existing scheduled actions to avoid duplicates
   - Validate batch_limit (100-500 max)

2. **`unschedule_cleanup_job()`**
   - Clear all scheduled `pnfpb_token_cleanup_batch_job` actions

3. **`execute_cleanup_batch( $batch_limit = 100 )`** - Main job handler
   - Fetch next batch of tokens marked for validation
   - For each token:
     - Call `validate_token()`
     - Update status based on response
     - Log validation result
   - Handle API rate limiting (Firebase has limits)
   - Sleep between requests (100-500ms) to prevent server overload
   - Track progress in option: `pnfpb_token_cleanup_progress`
   - Set completion flag when all tokens processed

4. **`log_cleanup_event( $event_type, $data )`**
   - Log to custom table `{$wpdb->prefix}pnfpb_token_cleanup_logs`
   - Track: tokens_validated, invalid_count, errors
   - Enable audit trail

5. **`get_cleanup_status()`** - Get current progress
   - Query from options: total_tokens, validated_count, invalid_count, last_run_time
   - Calculate percentage complete

---

### 3.2 Logging Table
**File**: `public/pnfpb_send_notification_routines/database/pnfpb_token_cleanup_migrations.php`

**Table**: `{$wpdb->prefix}pnfpb_token_cleanup_logs`

**Schema**:
```sql
CREATE TABLE {$wpdb->prefix}pnfpb_token_cleanup_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50), -- 'batch_started', 'batch_completed', 'token_validated', 'error'
    tokens_processed INT,
    invalid_tokens INT,
    validation_errors INT,
    batch_number INT,
    completion_percentage INT,
    error_message LONGTEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
);
```

---

## Phase 4: Real-time Token Validation

### 4.1 Enhance Push Notification Sending
**File**: `public/pnfpb_send_notification_routines/pnfpb_firebase_httpv1_notification/pnfpb_firebase_httpv1_notification.php`

**Modifications**:
- After sending individual push notifications (not topic-based)
- Check response for stale token indicators
- If stale token detected:
  - Call `PNFPB_Token_Validation_Service::update_token_status( $token_id, 'invalid', $error_code )`
  - Log the event
  - Do NOT resend to this token

**Code Location**: In the message sending loop where we iterate through `$target_device_ids`

**Integration Point**:
```php
foreach ($target_device_ids as $device_id) {
    // ... existing send logic ...
    $response = wp_remote_post($url, $args);
    
    // NEW: Check for stale token
    if ($this->is_stale_token_response($response)) {
        $validation_service->update_token_status($token_id, 'invalid', 'stale_from_push');
    }
}
```

---

## Phase 5: Admin UI Enhancements

### 5.1 Modify Token List Table
**File**: `admin/pnfpb_icfcm_device_tokens_list.php`

**Changes**:

1. **Add `token_status` Column**
   - In `get_columns()`: Add `token_status` column
   - Create `column_token_status()` method
   - Display badges: Valid (green), Invalid (red), Unverified (yellow), Pending (blue)

2. **Add Filter Dropdown**
   - Query string parameter: `?token_status=value`
   - Options: All, Valid, Invalid, Unverified, Pending
   - Modify `get_devicetokens()` SQL to include WHERE clause

3. **Add Filter Search in Admin Menu**
   - In `prepare_items()`: Add filter logic
   - Update SQL: `WHERE token_status = %s`

4. **Display Validation Info**
   - Add columns: `last_verified_at`, `last_validation_error` (optional, expandable)
   - Show human-readable date format

---

### 5.2 Create Token Cleanup Configuration Tab
**File**: `admin/config_tab/pnfpb_config_token_cleanup.php`

**Location**: New settings page under PNFPB Settings → Token Cleanup Management

**Form Fields**:

1. **Enable Token Cleanup** (Checkbox)
   - Default: unchecked
   - Option key: `pnfpb_token_cleanup_enabled`

2. **Cleanup Frequency** (Radio/Select)
   - Options: daily, weekly, monthly, manual
   - Default: daily
   - Option key: `pnfpb_token_cleanup_frequency`
   - Action: Update Action Scheduler when changed

3. **Batch Size Limit** (Numeric Input)
   - Min: 1, Max: 500
   - Default: 100
   - Help text: "Number of tokens to validate per batch"
   - Option key: `pnfpb_token_cleanup_batch_limit`
   - Validation: Sanitize and cap at 500

4. **Time of Day to Run** (Time Picker) - For daily/weekly/monthly
   - Default: 02:00 (2 AM)
   - Format: HH:MM in 24-hour
   - Option key: `pnfpb_token_cleanup_run_time`

5. **Day of Week** (Select) - For weekly
   - Options: Monday, Tuesday, ... Sunday
   - Default: Monday
   - Option key: `pnfpb_token_cleanup_day_of_week`

6. **Day of Month** (Select) - For monthly
   - Options: 1-31
   - Default: 1
   - Option key: `pnfpb_token_cleanup_day_of_month`

7. **Status Information** (Read-only Display)
   - Show: Last run time, tokens processed, invalid tokens found
   - Refresh every 5 seconds via AJAX (optional)
   - Option key: Fetched from logs

8. **Manual Trigger Button** (Button)
   - AJAX action: `pnfpb_manual_token_cleanup`
   - Effect: Start validation for next batch immediately
   - Shows progress spinner while running

9. **Clear All Validation History** (Button + Confirmation)
   - Reset token_status to 'valid', clear logs
   - Checkbox to confirm action

10. **Re-validate All Tokens** (Button + Warning)
    - Mark all tokens as 'pending_verification'
    - Will take significant time
    - Checkbox: "I understand this will revalidate all tokens"

---

### 5.3 Create Admin Status Card/Widget
**File**: `admin/pnfpb_admin_token_cleanup_dashboard.php`

**Display Location**: 
- Dashboard widget (if using WordPress dashboard)
- OR Primary position in admin menu under PNFPB Settings

**Card Content**:

1. **Tokens Validated Today**
   - Count: Query logs with `created_at >= today's date`
   - Display: Large number with label

2. **Total Stale Tokens Removed**
   - Count: `SELECT COUNT(*) FROM {prefix}pnfpb_ic_subscribed_deviceids_web WHERE token_status = 'invalid'`
   - Display: Large number with label

3. **Cleanup Progress Bar**
   - Show: X% Complete
   - Show: "Processing..." if job is running
   - Show: "Last run: X time ago"

4. **Quick Actions**
   - Link to Token Cleanup Settings
   - Link to Token List (with 'invalid' filter)
   - "Run Cleanup Now" button

5. **Recent Activity**
   - Show: Last 5 validation batches
   - Format: "200 tokens validated - 15 invalid found - 2 hours ago"

---

## Phase 6: AJAX Handlers

### 6.1 Create AJAX Routines
**File**: `admin/ajax_routines/pnfpb_token_cleanup_ajax.php`

**AJAX Actions**:

1. **`pnfpb_manual_token_cleanup`**
   - Permission: Current user is admin
   - Nonce verification: `pnfpb_token_cleanup_nonce`
   - Action: Trigger `execute_cleanup_batch()`
   - Response: JSON with status, batch_size, invalid_count

2. **`pnfpb_get_cleanup_status`**
   - Permission: Current user is admin
   - Return: Progress data for widget/card
   - Response: JSON with progress%, validated_today, total_invalid

3. **`pnfpb_update_cleanup_settings`**
   - Permission: Current user is admin
   - Nonce verification: Required
   - Validate: frequency, batch_limit, time
   - Update options
   - Reschedule Action Scheduler jobs
   - Response: Success/error message

4. **`pnfpb_clear_cleanup_history`**
   - Permission: Current user is admin + confirmation nonce
   - Action: Delete all cleanup logs, reset progress
   - Response: Confirmation message

---

## Phase 7: Hook Registration

### 7.1 Main Plugin File Enhancement
**File**: `pnfpb_push_notification.php`

**Add to constructor/init**:

```php
// Register cleanup job hooks
if (get_option('pnfpb_token_cleanup_enabled')) {
    $frequency = get_option('pnfpb_token_cleanup_frequency', 'daily');
    
    // Clear existing actions
    as_unschedule_all_actions('pnfpb_token_cleanup_batch_job');
    
    // Schedule based on frequency
    $this->schedule_token_cleanup_job($frequency);
}

// Register AJAX handlers
add_action('wp_ajax_pnfpb_manual_token_cleanup', [$this, 'handle_manual_cleanup']);
add_action('wp_ajax_pnfpb_get_cleanup_status', [$this, 'handle_get_status']);
add_action('wp_ajax_pnfpb_update_cleanup_settings', [$this, 'handle_update_settings']);

// Register admin menu
add_action('admin_menu', [$this, 'add_token_cleanup_menu']);

// Add dashboard widget
add_action('wp_dashboard_setup', [$this, 'register_cleanup_dashboard_widget']);
```

---

## Phase 8: Settings Options Storage

### 8.1 Option Keys
```php
// Token Cleanup Configuration
pnfpb_token_cleanup_enabled          // bool
pnfpb_token_cleanup_frequency        // string: daily|weekly|monthly|manual
pnfpb_token_cleanup_batch_limit      // int: 1-500, default 100
pnfpb_token_cleanup_run_time         // string: HH:MM, default 02:00
pnfpb_token_cleanup_day_of_week      // int: 0-6 (0=Monday), default 0
pnfpb_token_cleanup_day_of_month     // int: 1-31, default 1

// Cleanup Progress/Status
pnfpb_token_cleanup_progress         // JSON: {total, validated, invalid, started_at, last_run}
pnfpb_token_cleanup_running          // bool: flag to indicate if job is currently running
pnfpb_tokens_validated_today         // int: count of validations today
pnfpb_tokens_invalid_total           // int: total invalid tokens
```

---

## Phase 9: File Structure Summary

```
pnfpb_push_notification/
├── admin/
│   ├── ajax_routines/
│   │   └── pnfpb_token_cleanup_ajax.php          [NEW]
│   ├── config_tab/
│   │   └── pnfpb_config_token_cleanup.php       [NEW]
│   ├── pnfpb_icfcm_device_tokens_list.php       [MODIFIED]
│   └── pnfpb_admin_token_cleanup_dashboard.php  [NEW]
│
├── public/
│   ├── pnfpb_send_notification_routines/
│   │   ├── database/
│   │   │   └── pnfpb_token_cleanup_migrations.php [NEW]
│   │   ├── pnfpb_token_cleanup/
│   │   │   └── PNFPB_Token_Cleanup_Background_Job.php [NEW]
│   │   ├── pnfpb_token_validation/
│   │   │   └── PNFPB_Token_Validation_Service.php [NEW]
│   │   └── pnfpb_firebase_httpv1_notification/
│   │       └── pnfpb_firebase_httpv1_notification.php [MODIFIED]
│   └── js/
│       └── pnfpb_token_cleanup_admin.js        [NEW]
│
├── inc/
│   ├── pnfpb-admin-callbacks.php                [MODIFIED - add cleanup handlers]
│   └── pnfpb-extracted-admin-functions.php      [MODIFIED - add migration checks]
│
└── pnfpb_push_notification.php                  [MODIFIED - register cleanup system]
```

---

## Phase 10: Implementation Order

### Step 1: Database & Core Services (Week 1)
1. Create migration system
2. Add `token_status` columns
3. Create `PNFPB_Token_Validation_Service` class
4. Create `PNFPB_Token_Cleanup_Background_Job` class

### Step 2: Firebase Integration (Week 2)
1. Enhance Firebase HTTP v1 class with validation
2. Add validation payload sending
3. Integrate stale token detection in push sending

### Step 3: Admin UI (Week 2-3)
1. Modify token list table
2. Create Token Cleanup config tab
3. Create dashboard widget/card
4. Add AJAX handlers

### Step 4: Testing & Optimization (Week 3-4)
1. Test validation on real Firebase accounts
2. Test batch processing with large token sets
3. Test Action Scheduler integration
4. Performance testing and optimization

---

## Phase 11: API Rate Limiting & Performance

### 11.1 Rate Limiting Strategy
- **Firebase FCM Rate Limit**: ~10,000 requests/second per project
- **Our Approach**:
  - Process maximum 500 tokens per job run
  - Add 200ms delay between each token validation (3 tokens/second = 144/minute)
  - Batch cleanup runs only once daily (default)
  - Can be adjusted via batch_limit (100-500)

### 11.2 Database Query Optimization
- Ensure indexes on: `token_status`, `last_verified_at`, `created_at`
- Use prepared statements with placeholders
- Limit query results with LIMIT clause
- Consider pagination for large result sets

### 11.3 Server Load Considerations
- Use `wp_schedule_event()` or Action Scheduler for background execution
- Do NOT block page loads during validation
- Implement progress tracking to avoid duplicate processing
- Log all operations for debugging

---

## Phase 12: Error Handling & Logging

### 12.1 Error Scenarios
1. **Firebase API Errors**
   - Log full response
   - Retry mechanism (exponential backoff)
   - Admin notification if repeated failures

2. **Database Errors**
   - Log database errors
   - Graceful degradation

3. **Network Timeouts**
   - Catch timeout exceptions
   - Reschedule batch for retry
   - Alert admin if too many retries

### 12.2 Logging Levels
- **INFO**: Batch started, batch completed, progress updates
- **WARNING**: API errors, unexpected response formats
- **ERROR**: Database failures, critical errors

### 12.3 Audit Trail
- Track all token status changes
- Log manual cleanup triggers
- Record admin configuration changes

---

## Phase 13: Migration & Rollback

### 13.1 Safe Migration Strategy
1. Run migration during plugin activation/update
2. Add version flag to track migration state
3. Backup database before migration
4. Provide rollback option via admin interface

### 13.2 Rollback Procedure
1. Drop `token_status` columns
2. Clear cleanup jobs
3. Delete cleanup logs
4. Reset all options to defaults

---

## Phase 14: Testing Checklist

### Unit Tests
- [ ] Token validation service methods
- [ ] Stale token detection logic
- [ ] Batch fetching and limiting
- [ ] Status update operations

### Integration Tests
- [ ] Action Scheduler job registration
- [ ] Complete cleanup cycle (fetch → validate → update)
- [ ] Firebase API integration
- [ ] AJAX handlers

### Admin UI Tests
- [ ] Token list filtering by status
- [ ] Config tab form submission
- [ ] Manual cleanup trigger
- [ ] Dashboard widget data updates

### Performance Tests
- [ ] Large token set validation (1000+ tokens)
- [ ] API rate limiting
- [ ] Database query performance
- [ ] Memory usage during batch processing

---

## Phase 15: Documentation Requirements

### Admin Documentation
- How to enable/configure token cleanup
- Understanding token status values
- How to manually trigger cleanup
- Interpreting dashboard metrics
- Troubleshooting common issues

### Developer Documentation
- Class method documentation
- Hook and filter documentation
- Database schema documentation
- API integration guide

---

## Key Considerations & Notes

1. **Non-Destructive**: Tokens are marked invalid, not deleted, allowing admin review
2. **Gradual Processing**: Batch processing prevents server overload
3. **Audit Trail**: All changes are logged for compliance
4. **Flexible Scheduling**: Admins can choose frequency that fits their server
5. **Real-time Detection**: Invalid tokens caught during actual push sending
6. **Progress Tracking**: Transparent status visible in admin panel
7. **Backward Compatibility**: Existing tokens assumed 'valid' until validated
8. **Extensibility**: Can be extended to support other FCM response codes

---

## Success Criteria

✅ Stale tokens identified and marked without affecting user experience
✅ Batch processing completes without server overload
✅ Admins have clear visibility into token validation status
✅ Invalid tokens can be manually reviewed before deletion
✅ Real-time validation integrated into push notification sending
✅ Dashboard shows meaningful metrics
✅ System handles API errors gracefully
✅ Complete audit trail of all validation events

