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

For local development without a public URL, use long polling:

```php
// In a custom command or tinker
$driver = app(TelegramDriver::class);
$offset = 0;

while (true) {
    $updates = $driver->getUpdates($offset);
    
    foreach ($updates as $update) {
        $kernel = app(MessagingKernel::class);
        $normalized = $driver->parseUpdate($update);
        $response = $kernel->handle($normalized);
        $driver->sendMessage($normalized->chatId, $response);
        $offset = $update['update_id'] + 1;
    }
    
    sleep(1);
}
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

```bash
# Run package tests
cd packages/messaging-bot
composer test

# Or from root
php artisan test packages/messaging-bot/tests
```

## License

Proprietary - All rights reserved.
