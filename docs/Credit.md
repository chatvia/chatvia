# ChatVia Credit System

The ChatVia application implements a sophisticated credit-based billing system that tracks and manages user consumption of various resources including messages, document pages, and media minutes. This system supports multiple subscription tiers, add-ons, and automatic quota refreshes.

## Table of Contents

- [Quick Start](#quick-start)
- [Developer API](#developer-api)
- [Common Use Cases](#common-use-cases)
- [Integration Examples](#integration-examples)
- [Advanced Usage](#advanced-usage)
- [Error Handling](#error-handling)
- [Testing](#testing)
- [Background Information](#background-information)

## Quick Start

```php
// Get a user's credit system
$creditSystem = $user->creditSystem();

// Check if user can send messages
if ($creditSystem->canSendMessages()) {
    // Send message and consume credit
    $creditSystem->incrementMessagesUsage();
}

// Get available message count
$availableMessages = $user->consumableMessages();

// Check resource limits
$pagesUsed = $user->pagesConsumed();
$pageLimit = $user->pages_limit;
```

## API

### Main User Methods

#### `$user->creditSystem()` → `UserCreditSystem`
Returns the credit system service for the user.

```php
$creditSystem = $user->creditSystem();
```

#### `$user->consumableMessages()` → `int`
Get total available messages (credits + add-ons).

```php
$availableMessages = $user->consumableMessages();
// Returns: 150 (100 from subscription + 50 from add-ons)
```

#### `$user->pagesConsumed()` → `int`
Get total pages consumed from document uploads.

```php
$pagesUsed = $user->pagesConsumed();
// Returns: 45 (pages processed from PDFs/documents)
```

#### `$user->minutesConsumed()` → `int`
Get total minutes consumed from audio/video uploads.

```php
$minutesUsed = $user->minutesConsumed();
// Returns: 120 (minutes processed from audio/video files)
```

### Credit System Service Methods

#### `canSendMessages()` → `bool`
Check if user has available message credits.

```php
if ($user->creditSystem()->canSendMessages()) {
    // Process the message
    dispatch(new SendChatMessageJob($message));
}
```

#### `incrementMessagesUsage($amount = 1)` → `void`
Consume message credits. Call this when processing user messages.

```php
// Consume 1 message credit
$user->creditSystem()->incrementMessagesUsage();

// Consume multiple credits
$user->creditSystem()->incrementMessagesUsage(5);
```

#### `getTotalSentMessages()` → `int`
Get messages sent in current billing period.

```php
$messagesSent = $user->creditSystem()->getTotalSentMessages();
// Returns: 25 (messages sent this month)
```

#### `consumableMessages($refresh = false)` → `int`
Get total available messages with optional cache refresh.

```php
// Get cached value
$available = $user->creditSystem()->consumableMessages();

// Force refresh from database
$available = $user->creditSystem()->consumableMessages(true);
```

#### `addCredit($quotaLimit, $endsAt)` → `Credit`
Add new credit allocation (typically called by billing system).

```php
$quotaLimit = ['messages_limit' => 1000];
$endsAt = now()->addMonth();

$credit = $user->creditSystem()->addCredit($quotaLimit, $endsAt);
```

### Resource Limit Properties

Users have direct access to their resource limits:

```php
$user->agents_knowledge_limit;     // int: Max knowledge-based agents
$user->agents_instruction_limit;   // int: Max instruction-based agents  
$user->pages_limit;               // int: Max document pages
$user->minutes_limit;             // int: Max audio/video minutes
$user->custom_domains_limit;      // int: Max custom domains
$user->members_limit;             // int: Max team members
```

## Common Use Cases

### 1. Chat Message Processing

```php
public function sendMessage(Request $request, Agent $agent)
{
    $user = $request->user();
    
    // Check if user can send messages
    if (!$user->creditSystem()->canSendMessages()) {
        return response()->json([
            'error' => 'No message credits available',
            'available' => $user->consumableMessages()
        ], 403);
    }
    
    // Process the message
    $messageId = Str::uuid();
    $payload = new ChatInputPayload($request->message, $request->user()->id);
    
    // Dispatch job that will consume credits
    dispatch(new StoreChatMessageJob($agent, 'user', $messageId, $payload));
    
    return response()->json(['message_id' => $messageId]);
}
```

### 2. File Upload Validation

```php
public function uploadDocument(Request $request, Agent $agent)
{
    $user = $request->user();
    $file = $request->file('document');
    
    // Estimate pages (rough calculation)
    $estimatedPages = ceil($file->getSize() / 1024 / 2); // ~2KB per page
    
    // Check if user has enough page quota
    $pagesUsed = $user->pagesConsumed();
    $pageLimit = $user->pages_limit;
    
    if (($pagesUsed + $estimatedPages) > $pageLimit) {
        return response()->json([
            'error' => 'Document would exceed page limit',
            'pages_used' => $pagesUsed,
            'pages_limit' => $pageLimit,
            'estimated_pages' => $estimatedPages
        ], 403);
    }
    
    // Process upload
    $agentFile = $agent->files()->create([
        'filename' => $file->getClientOriginalName(),
        'estimated_pages' => $estimatedPages,
        'status' => 'processing'
    ]);
    
    dispatch(new ProcessDocumentJob($agentFile, $file));
    
    return response()->json(['file_id' => $agentFile->id]);
}
```

### 3. Agent Creation Limits

```php
public function createAgent(Request $request)
{
    $user = $request->user();
    $agentType = $request->input('type'); // 'knowledge' or 'instruction'
    
    // Check agent limits
    $currentCount = $user->agents()->where('type', $agentType)->count();
    $limit = $agentType === 'knowledge' 
        ? $user->agents_knowledge_limit 
        : $user->agents_instruction_limit;
    
    if ($currentCount >= $limit) {
        return response()->json([
            'error' => "Maximum {$agentType} agents reached",
            'current' => $currentCount,
            'limit' => $limit
        ], 403);
    }
    
    // Create agent
    $agent = $user->agents()->create($request->validated());
    
    return response()->json($agent);
}
```

### 4. Usage Dashboard

```php
public function getUserUsage(User $user)
{
    $creditSystem = $user->creditSystem();
    
    return [
        'messages' => [
            'sent' => $creditSystem->getTotalSentMessages(),
            'available' => $user->consumableMessages(),
            'percentage_used' => $this->calculatePercentage(
                $creditSystem->getTotalSentMessages(),
                $user->consumableMessages()
            )
        ],
        'pages' => [
            'consumed' => $user->pagesConsumed(),
            'limit' => $user->pages_limit,
            'percentage_used' => $this->calculatePercentage(
                $user->pagesConsumed(),
                $user->pages_limit
            )
        ],
        'minutes' => [
            'consumed' => $user->minutesConsumed(),
            'limit' => $user->minutes_limit,
            'percentage_used' => $this->calculatePercentage(
                $user->minutesConsumed(),
                $user->minutes_limit
            )
        ],
        'agents' => [
            'knowledge' => [
                'count' => $user->agents()->where('type', 'knowledge')->count(),
                'limit' => $user->agents_knowledge_limit
            ],
            'instruction' => [
                'count' => $user->agents()->where('type', 'instruction')->count(),
                'limit' => $user->agents_instruction_limit
            ]
        ]
    ];
}

private function calculatePercentage($used, $limit)
{
    return $limit > 0 ? round(($used / $limit) * 100, 2) : 0;
}
```

## Integration Examples

### Middleware for Credit Checking

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckMessageCredits
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user->creditSystem()->canSendMessages()) {
            return response()->json([
                'error' => 'Insufficient message credits',
                'available' => $user->consumableMessages()
            ], 403);
        }
        
        return $next($request);
    }
}
```

### API Resource for Usage Data

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserUsageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'subscription' => [
                'plan' => $this->plan,
                'is_subscribed' => $this->subscribed(),
            ],
            'usage' => [
                'messages' => [
                    'available' => $this->consumableMessages(),
                    'sent' => $this->creditSystem()->getTotalSentMessages(),
                ],
                'resources' => [
                    'pages_consumed' => $this->pagesConsumed(),
                    'pages_limit' => $this->pages_limit,
                    'minutes_consumed' => $this->minutesConsumed(),
                    'minutes_limit' => $this->minutes_limit,
                ],
                'agents' => [
                    'knowledge_count' => $this->agents()->where('type', 'knowledge')->count(),
                    'knowledge_limit' => $this->agents_knowledge_limit,
                    'instruction_count' => $this->agents()->where('type', 'instruction')->count(),
                    'instruction_limit' => $this->agents_instruction_limit,
                ]
            ]
        ];
    }
}
```

### Vue.js Component Integration

```javascript
// In your Vue component
export default {
    data() {
        return {
            usage: null,
            loading: false
        }
    },
    
    async created() {
        await this.fetchUsage();
    },
    
    methods: {
        async fetchUsage() {
            this.loading = true;
            try {
                const response = await axios.get('/api/usage');
                this.usage = response.data;
            } catch (error) {
                console.error('Failed to fetch usage:', error);
            } finally {
                this.loading = false;
            }
        },
        
        async sendMessage(message) {
            // Check credits before sending
            if (this.usage.usage.messages.available <= 0) {
                this.$toast.error('No message credits available');
                return;
            }
            
            try {
                await axios.post('/api/chat/send', { message });
                // Refresh usage after sending
                await this.fetchUsage();
            } catch (error) {
                if (error.response?.status === 403) {
                    this.$toast.error('Insufficient credits');
                }
            }
        }
    }
}
```

## Advanced Usage

### Manual Credit Management

```php
// Add promotional credits
$user = User::find(1);
$quotaLimit = ['messages_limit' => 500];
$endsAt = now()->addDays(7); // 7-day promotion

$credit = $user->creditSystem()->addCredit($quotaLimit, $endsAt);

// Add message credit add-on
$user->addons()->create([
    'type' => 'extra_message_credits',
    'amount' => 1000,
    'started_at' => now(),
    'ends_at' => now()->addMonth(),
]);

// Refresh cached values
$user->creditSystem()->refreshAvailableMessages();
```

### Batch Credit Operations

```php
// Update credits for multiple users
$users = User::whereIn('id', [1, 2, 3, 4, 5])->get();
$quotaLimit = ['messages_limit' => 1000];
$endsAt = now()->addMonth();

foreach ($users as $user) {
    $user->creditSystem()->addCredit($quotaLimit, $endsAt);
}
```

### Custom Usage Tracking

```php
// Track custom resource consumption
class CustomResourceTracker
{
    public function trackAPICall(User $user, int $cost = 1)
    {
        // Use message credits for API calls
        if ($user->creditSystem()->canSendMessages()) {
            $user->creditSystem()->incrementMessagesUsage($cost);
            return true;
        }
        
        return false;
    }
    
    public function trackBulkOperation(User $user, int $itemCount)
    {
        $cost = ceil($itemCount / 10); // 1 credit per 10 items
        
        if ($user->consumableMessages() >= $cost) {
            $user->creditSystem()->incrementMessagesUsage($cost);
            return true;
        }
        
        return false;
    }
}
```

## Error Handling

### Custom Exception Classes

```php
<?php

namespace App\Exceptions;

use Exception;

class InsufficientCreditsException extends Exception
{
    public function __construct(
        public string $resourceType = 'messages',
        public int $required = 1,
        public int $available = 0
    ) {
        parent::__construct("Insufficient {$resourceType} credits. Required: {$required}, Available: {$available}");
    }
}

class ResourceLimitExceededException extends Exception
{
    public function __construct(
        public string $resourceType,
        public int $limit,
        public int $current
    ) {
        parent::__construct("{$resourceType} limit exceeded. Limit: {$limit}, Current: {$current}");
    }
}
```

### Usage in Controllers

```php
public function processRequest(Request $request)
{
    $user = $request->user();
    
    try {
        // Check credits
        if (!$user->creditSystem()->canSendMessages()) {
            throw new InsufficientCreditsException(
                'messages',
                1,
                $user->consumableMessages()
            );
        }
        
        // Check page limits
        $pagesNeeded = $request->input('estimated_pages', 0);
        if (($user->pagesConsumed() + $pagesNeeded) > $user->pages_limit) {
            throw new ResourceLimitExceededException(
                'pages',
                $user->pages_limit,
                $user->pagesConsumed()
            );
        }
        
        // Process request...
        
    } catch (InsufficientCreditsException $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'type' => 'insufficient_credits',
            'resource_type' => $e->resourceType,
            'required' => $e->required,
            'available' => $e->available
        ], 403);
        
    } catch (ResourceLimitExceededException $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'type' => 'limit_exceeded',
            'resource_type' => $e->resourceType,
            'limit' => $e->limit,
            'current' => $e->current
        ], 403);
    }
}
```



## Testing

### Basic Credit System Tests

```php
use App\Models\User;
use App\Models\Credit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CreditSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_messages_with_available_credits(): void
    {
        $user = User::factory()->create();
        
        // Give user credits
        $user->credits()->create([
            'messages' => 100,
            'started_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->assertTrue($user->creditSystem()->canSendMessages());
        $this->assertEquals(100, $user->consumableMessages());
    }

    public function test_message_consumption_decreases_available_credits(): void
    {
        $user = User::factory()->create();
        
        $user->credits()->create([
            'messages' => 50,
            'started_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        // Consume 5 messages
        $user->creditSystem()->incrementMessagesUsage(5);
        
        $this->assertEquals(5, $user->creditSystem()->getTotalSentMessages());
        $this->assertEquals(45, $user->creditSystem()->consumableMessages(true));
    }

    public function test_user_cannot_send_messages_without_credits(): void
    {
        $user = User::factory()->create();
        // No credits created
        
        $this->assertFalse($user->creditSystem()->canSendMessages());
        $this->assertEquals(0, $user->consumableMessages());
    }
}
```

### API Endpoint Tests

```php
public function test_chat_endpoint_requires_credits(): void
{
    $user = User::factory()->create();
    $agent = Agent::factory()->for($user)->create();
    
    // No credits
    $response = $this->actingAs($user)
        ->postJson("/api/agents/{$agent->id}/chat", [
            'message' => 'Hello'
        ]);
    
    $response->assertStatus(403)
        ->assertJson(['error' => 'No message credits available']);
}

public function test_file_upload_respects_page_limits(): void
{
    $user = User::factory()->create(['pages_limit' => 10]);
    $agent = Agent::factory()->for($user)->create();
    
    // Create a large file that exceeds limit
    $file = UploadedFile::fake()->create('large.pdf', 5000); // ~25 pages
    
    $response = $this->actingAs($user)
        ->postJson("/api/agents/{$agent->id}/files", [
            'document' => $file
        ]);
    
    $response->assertStatus(403)
        ->assertJsonStructure([
            'error',
            'pages_used',
            'pages_limit',
            'estimated_pages'
        ]);
}
```

## Background Information

### System Architecture

The credit system consists of:

1. **Credits Table**: Time-bound message allocations from subscriptions
2. **Addons Table**: Additional credits purchased separately  
3. **Usage Table**: Tracks consumption against credits
4. **Cache Layer**: High-performance counters for real-time checks
5. **User Properties**: Fixed limits for agents, pages, minutes, etc.

### Data Flow

1. **Subscription Payment** → Stripe webhook → Credit allocation
2. **User Action** → Credit check → Resource consumption → Usage tracking
3. **Background Jobs** → Quota refresh → Cache invalidation

### Key Design Decisions

- **Separate credits and fixed limits**: Messages use time-bound credits, while agents/pages use fixed subscription limits
- **Cache-first approach**: Real-time operations use cache, with periodic database sync
- **Add-on support**: Flexible system for one-time credit purchases
- **Expiration handling**: Credits automatically expire based on subscription cycles

This documentation focuses on the practical API that developers need to integrate credit checking and consumption into their features.
