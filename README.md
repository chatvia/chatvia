# ChatVia RAG Application

A Laravel-based RAG (Retrieval-Augmented Generation) application with Vue.js frontend using Inertia.js.

## Testing Pending Plan Changes with Stripe CLI

This section covers how to test the pending plan change functionality using the Stripe CLI to simulate webhook events.

### Prerequisites

1. **Install Stripe CLI**
   ```bash
   # macOS
   brew install stripe/stripe-cli/stripe
   
   # Or download from https://github.com/stripe/stripe-cli/releases
   ```

2. **Login to Stripe**
   ```bash
   stripe login
   ```

3. **Set up webhook forwarding**
   ```bash
   # Forward webhooks to your local Laravel app
   stripe listen --forward-to localhost:8000/stripe/webhook
   ```

### Step-by-Step Testing Process

#### Step 1: Create a Test Customer and Subscription

```bash
# Create a test customer
stripe customers create \
  --email="test@example.com" \
  --name="Test User"

# Create a subscription with metadata (replace customer and price IDs)
stripe subscriptions create \
  --customer="cus_XXXXXXXXXXXXXX" \
  --items='[{"price": "price_starter_monthly"}]' \
  --metadata='{"pending_change": "true", "from_package": "starter", "to_package": "pro", "new_price_id": "price_pro_monthly", "is_yearly": "false", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}'
```

#### Step 2: Simulate Subscription Update Event

```bash
# Trigger a subscription update event to test the webhook
stripe events resend evt_XXXXXXXXXXXXXX

# Or create a mock event
stripe trigger customer.subscription.updated
```

#### Step 3: Simulate Invoice Payment Success (Next Billing Cycle)

```bash
# Method 1: Trigger invoice payment success
stripe trigger invoice.payment_succeeded

# Method 2: Create and pay an invoice manually
stripe invoices create --customer="cus_XXXXXXXXXXXXXX" --auto_advance=true
```

#### Step 4: Manual Subscription Update (for specific testing)

```bash
# Update subscription metadata to simulate pending change
stripe subscriptions update sub_XXXXXXXXXXXXXX \
  --metadata='{"pending_change": "true", "from_package": "starter", "to_package": "pro", "new_price_id": "price_pro_monthly", "is_yearly": "false", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}'

# Then trigger the subscription update event
stripe subscriptions update sub_XXXXXXXXXXXXXX \
  --metadata='{"trigger": "test"}'
```

### Complete Test Scenario

Here's a complete scenario to test the pending plan change flow:

```bash
# 1. Create test customer
CUSTOMER_ID=$(stripe customers create --email="test@example.com" --name="Test User" --format=json | jq -r '.id')
echo "Created customer: $CUSTOMER_ID"

# 2. Create subscription with pending change
SUBSCRIPTION_ID=$(stripe subscriptions create \
  --customer="$CUSTOMER_ID" \
  --items='[{"price": "price_starter_monthly"}]' \
  --metadata='{"pending_change": "true", "from_package": "starter", "to_package": "pro", "new_price_id": "price_pro_monthly", "is_yearly": "false", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}' \
  --format=json | jq -r '.id')
echo "Created subscription: $SUBSCRIPTION_ID"

# 3. Update the subscription to trigger webhook
stripe subscriptions update $SUBSCRIPTION_ID \
  --metadata='{"pending_change": "true", "from_package": "starter", "to_package": "pro", "new_price_id": "price_pro_monthly", "is_yearly": "false", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'", "trigger_test": "'$(date +%s)'"}'

# 4. Check your Laravel logs to see the webhook processing
tail -f storage/logs/laravel.log | grep "pending plan change"
```

### Testing Different Scenarios

#### Test Scenario 1: Monthly to Yearly Change
```bash
stripe subscriptions update $SUBSCRIPTION_ID \
  --metadata='{"pending_change": "true", "from_package": "starter", "to_package": "starter", "new_price_id": "price_starter_yearly", "is_yearly": "true", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}'
```

#### Test Scenario 2: Plan Upgrade
```bash
stripe subscriptions update $SUBSCRIPTION_ID \
  --metadata='{"pending_change": "true", "from_package": "starter", "to_package": "pro", "new_price_id": "price_pro_monthly", "is_yearly": "false", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}'
```

#### Test Scenario 3: Plan Downgrade
```bash
stripe subscriptions update $SUBSCRIPTION_ID \
  --metadata='{"pending_change": "true", "from_package": "pro", "to_package": "starter", "new_price_id": "price_starter_monthly", "is_yearly": "false", "scheduled_at": "'$(date -u +"%Y-%m-%dT%H:%M:%SZ")'"}'
```

### Webhook Events to Monitor

Your webhook handler will process these events:

1. **customer.subscription.updated**
   - Triggered when subscription metadata changes
   - This is where pending changes are detected and applied

2. **invoice.payment_succeeded**
   - Triggered when billing cycle payments complete
   - Alternative trigger point for applying pending changes

### Debugging Tips

1. **Check webhook delivery**
   ```bash
   # List recent webhook endpoints
   stripe webhook_endpoints list
   
   # Check webhook delivery attempts
   stripe events list --type=customer.subscription.updated
   ```

2. **Monitor Laravel logs**
   ```bash
   # Watch for webhook processing
   tail -f storage/logs/laravel.log | grep -E "(pending plan change|Applied pending|Failed to apply)"
   ```

3. **Check database changes**
   ```sql
   -- Check user plan updates
   SELECT id, email, plan, agents_knowledge_limit, agents_instruction_limit, updated_at 
   FROM users 
   WHERE stripe_id = 'cus_XXXXXXXXXXXXXX';
   
   -- Check credit additions
   SELECT * FROM credits WHERE user_id = X ORDER BY created_at DESC;
   ```

### Environment Setup

Make sure your `.env` file has the correct Stripe configuration:

```env
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

### Price IDs Configuration

Update your `config/billing.php` with the correct Stripe price IDs:

```php
'packages' => [
    'starter' => [
        'stripe_monthly_price_id' => 'price_starter_monthly',
        'stripe_yearly_price_id' => 'price_starter_yearly',
        // ...
    ],
    'pro' => [
        'stripe_monthly_price_id' => 'price_pro_monthly',
        'stripe_yearly_price_id' => 'price_pro_yearly',
        // ...
    ],
],
```

### Cleanup After Testing

```bash
# Cancel test subscription
stripe subscriptions cancel $SUBSCRIPTION_ID

# Delete test customer
stripe customers delete $CUSTOMER_ID
```
