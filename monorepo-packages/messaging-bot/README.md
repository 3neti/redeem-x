# Messaging Bot Package

Multi-channel messaging bot for PayCode voucher operations. Currently supports Telegram, with WhatsApp and Viber planned for future phases.

## Features

- **Multi-step conversation flows** - Stateful conversations for complex operations
- **Platform-agnostic core** - Normalized DTOs work across all messaging platforms
- **Pluggable drivers** - Easy to add new messaging platforms
- **Admin authorization** - Restrict commands to configured admin chat IDs
- **Laravel Cache integration** - Conversation state persists across messages

## Installation

The package is included in the mono-repo. Add it to your host app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/messaging-bot"
        }
    ],
    "require": {
        "lbhurtado/messaging-bot": "@dev"
    }
}
```

Then run:

```bash
composer update lbhurtado/messaging-bot
```

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=messaging-bot-config
```

### Environment Variables

```bash
# Telegram Bot
TELEGRAM_BOT_TOKEN=your_bot_token_here
TELEGRAM_WEBHOOK_SECRET=your_secret_here
TELEGRAM_ADMIN_CHAT_IDS=123456789,987654321

# General
MESSAGING_BOT_CONVERSATION_TTL=1800
```

## Setting Up Telegram

### 1. Create a Bot

1. Open Telegram and search for `@BotFather`
2. Send `/newbot` and follow the prompts
3. Copy the bot token to `TELEGRAM_BOT_TOKEN`

### 2. Set Webhook

Set your webhook URL (replace with your domain):

```bash
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://yourdomain.com/messaging/telegram/webhook", "secret_token": "your_secret"}'
```

Or use the driver directly:

```php
use LBHurtado\MessagingBot\Drivers\Telegram\TelegramDriver;

$driver = app(TelegramDriver::class);
$driver->setWebhook('https://yourdomain.com/messaging/telegram/webhook');
```

### 3. Local Development

For local development, use the test command to simulate messages:

```bash
# Simulate a message
php artisan test:messaging "/redeem"

# Continue the conversation
php artisan test:messaging "VOUCHER-CODE"

# Simulate contact sharing (phone number)
php artisan test:messaging "" --contact

# Test deep link (as if user clicked t.me/bot?start=redeem_CODE)
php artisan test:messaging "/start redeem_ABCD"

# Use different chat ID (for separate conversation state)
php artisan test:messaging "/redeem" --chat-id=99999

# Specify mobile number for contact sharing
php artisan test:messaging "" --contact --mobile=09181234567
```

Alternatively, use long polling for real Telegram interaction:

```bash
php artisan messaging:poll
```

## Available Commands

### Public Commands

| Command | Description |
|---------|-------------|
| `/start` | Welcome message |
| `/help` | Show available commands |
| `/balance` | Check wallet balance (requires linked account) |
| `/redeem` | Start voucher redemption flow |
| `/cancel` | Cancel current operation |

### Admin Commands

| Command | Description |
|---------|-------------|
| `/generate` | Generate multiple vouchers |
| `/disburse` | Quick single-voucher creation |

## Deep Links

Telegram deep links allow users to start redemption with a single click:

```
https://t.me/your_bot?start=redeem_VOUCHER-CODE
https://t.me/your_bot?start=disburse_VOUCHER-CODE
```

**Format:** `start=action_param` (underscore separator)

**Examples:**
```
https://t.me/xchange_paycode_bot?start=redeem_3LGB-GX8S
https://t.me/xchange_paycode_bot?start=disburse_ABCD-1234
```

When a user clicks a deep link:
1. Telegram opens and starts the bot
2. Bot automatically validates the voucher code
3. User sees amount and is prompted to share phone (or confirm if returning user)

## Redemption UX Flow

### First-Time User (3 steps)
```
Click: t.me/bot?start=redeem_ABCD
→ ✅ ₱100.00 found!
  We need your mobile number to send the funds.
  [📱 Share Phone Number]

*taps share*
→ 📋 You will receive:
  ₱100.00 → GCash:09173011987
  [✅ Accept] [✏️ Change Account]

*taps accept*
→ 🎉 Done!
```

### Returning User (2 steps)
Phone number is cached for 30 days after successful redemption:
```
Click: t.me/bot?start=redeem_EFGH
→ ✅ ₱100.00 found!
  Send to GCash:09173011987?
  [✅ Accept] [📱 Different Number]

*taps accept*
→ 🎉 Done!
```

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│  Drivers (Platform-Specific)                            │
│  TelegramDriver │ WhatsAppDriver │ ViberDriver          │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│  Core Engine                                            │
│  MessagingKernel → IntentRouter → Handler/Flow          │
│  ConversationStore (Laravel Cache)                      │
└────────────────────┬────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────┐
│  Domain Actions                                         │
│  GenerateVouchers │ RedeemViaSms │ ProcessRedemption    │
└─────────────────────────────────────────────────────────┘
```

## Adding a New Handler

Create a handler extending `BaseMessagingHandler`:

```php
namespace LBHurtado\MessagingBot\Handlers;

use LBHurtado\MessagingBot\Data\NormalizedResponse;
use LBHurtado\MessagingBot\Data\NormalizedUpdate;

class MyHandler extends BaseMessagingHandler
{
    protected function process(NormalizedUpdate $update): NormalizedResponse
    {
        return NormalizedResponse::text('Hello from MyHandler!');
    }
    
    // Optional: require authentication
    public function requiresAuth(): bool
    {
        return true;
    }
}
```

Register in config:

```php
// config/messaging-bot.php
'handlers' => [
    'my_intent' => \LBHurtado\MessagingBot\Handlers\MyHandler::class,
],
```

## Adding a New Flow

Create a flow extending `BaseFlow`:

```php
namespace LBHurtado\MessagingBot\Flows;

class MyFlow extends BaseFlow
{
    public function initialStep(): string
    {
        return 'step1';
    }
    
    public function steps(): array
    {
        return ['step1', 'step2', 'finalize'];
    }
    
    protected function promptStep1(ConversationState $state): NormalizedResponse
    {
        return NormalizedResponse::text('Enter something:');
    }
    
    protected function handleStep1(NormalizedUpdate $update, ConversationState $state, string $input): array
    {
        $newState = $state->with('value', $input)->advanceTo('step2');
        
        return [
            'response' => $this->promptStep2($newState),
            'state' => $newState,
        ];
    }
    
    // ... more steps
}
```

## Webhook Endpoints

| Platform | Endpoint |
|----------|----------|
| Telegram | `POST /messaging/telegram/webhook` |
| WhatsApp | `POST /messaging/whatsapp/webhook` (planned) |
| Viber | `POST /messaging/viber/webhook` (planned) |

## Testing

### Unit Tests
```bash
# Run package tests
cd packages/messaging-bot
composer test

# Or from root
php artisan test packages/messaging-bot/tests
```

### Manual Testing
```bash
# Test the full redemption flow
php artisan cache:clear
php artisan test:messaging "/start redeem_VOUCHER-CODE"
php artisan test:messaging "" --contact
php artisan test:messaging "accept"

# Test returning user (with cached phone)
php artisan tinker --execute="Cache::put('messaging:phone:12345', '+639173011987', now()->addDays(30));"
php artisan test:messaging "/start redeem_ANOTHER-CODE"
php artisan test:messaging "accept"
```

## License

Proprietary - All rights reserved.
