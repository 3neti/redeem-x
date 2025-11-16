# QuestPay™ Implementation Plan for redeem-x

## Executive Summary

**QuestPay™** is a redemption-driven reality race where players complete real-world challenges, redeem voucher codes to verify progress, and compete for clues, codes, and cash. This document outlines the features and enhancements needed in the redeem-x application to support QuestPay gameplay.

**Core Concept**: *"Redeem. Race. Repeat."*
- Players quest → System verifies → Game pays

## Current redeem-x Capabilities (Assets)

The redeem-x platform already has several features that align with QuestPay requirements:

✅ **Voucher Generation & Redemption Engine** (core x-Change functionality)
✅ **Campaign System** - Reusable voucher templates with `VoucherInstructionsData`
✅ **Rich Redemption Flows** - Splash pages, landing pages, input collection
✅ **Input Field Collection** - Photos, location (GPS), signatures, text responses, KYC data
✅ **Validation Rules** - Custom validation logic for inputs
✅ **Multi-Channel Notifications** - Email, SMS (EngageSpark), webhooks
✅ **Payment Gateway Integration** - Omnipay (NetBank) for disbursements
✅ **Top-Up/Direct Checkout** - Users can fund wallets via NetBank Direct Checkout
✅ **User Authentication** - WorkOS integration
✅ **Vue 3 + Inertia.js Frontend** - Modern TypeScript-based UI
✅ **Laravel Wayfinder** - Type-safe route generation

## Gap Analysis: Features Needed for QuestPay

### 🎮 Game Management Layer (NEW)

#### 1. **Game/Episode Management**
**Status**: Does not exist

**Required Features**:
- Create and configure game episodes/seasons
- Define episode themes, duration, and challenge count
- Set start/end times for competitions
- Episode status management (draft, active, completed)
- Configure scoring rules per episode
- Prize pool management

**Database Schema**:
```php
// app/Models/Game.php
Game {
  id, name, slug, description, theme
  status (draft, registration, active, paused, completed, cancelled)
  start_at, end_at
  max_contestants, min_contestants
  scoring_rules (json)
  prize_pool (decimal)
  settings (json) // format, rules, sponsor info
}

// app/Models/Episode.php  
Episode {
  id, game_id, episode_number
  name, description, theme
  start_at, end_at
  challenge_count
  status (upcoming, active, completed)
  settings (json)
}
```

**UI Components**:
- Admin dashboard for game creation
- Episode configuration wizard
- Game calendar/timeline view
- Status management controls

---

#### 2. **Challenge Management System**
**Status**: Does not exist

**Required Features**:
- Create challenge templates (extends Campaign concept)
- Define challenge types (purchase, location, task, puzzle, sponsor activation)
- Set difficulty tiers (Tier 1-4)
- Configure time limits and scoring
- Assign challenges to episodes/stages
- Challenge sequencing and dependencies
- Location-based challenge requirements
- Merchant/sponsor integration per challenge

**Database Schema**:
```php
// app/Models/Challenge.php
Challenge {
  id, episode_id, sequence_order
  name, description, theme
  type (purchase, donation, location, task, puzzle, sponsor_activation)
  difficulty (1-4)
  points_base, points_speed_bonus
  time_limit_minutes
  location_lat, location_lng, location_radius
  merchant_info (json)
  sponsor_id
  validation_rules (json)
  prerequisites (json) // e.g., requires completion of challenge IDs
  settings (json)
}

// Relationship to Campaign
Challenge belongsTo Campaign // Inherits voucher instructions
```

**Implementation Strategy**:
- Extend existing Campaign model to support Challenge metadata
- Challenges inherit `VoucherInstructionsData` from associated Campaign
- Add challenge-specific fields: type, difficulty, points, time_limit, location
- Challenge UI extends Campaign UI with gaming elements

**UI Components**:
- Challenge builder (extends Campaign form)
- Challenge sequencing drag-and-drop interface
- Map-based location picker
- Challenge preview/test mode
- Challenge library/templates

---

#### 3. **Contestant Registration & Management**
**Status**: Partially exists (User model via WorkOS)

**Required Features**:
- Public registration forms for QuestPay episodes
- Contestant selection/approval workflow
- Team management (for team-based formats)
- Contestant profiles with stats
- Onboarding flow with rules acceptance
- Emergency contact collection
- Status tracking (registered, selected, active, eliminated, winner)

**Database Schema**:
```php
// app/Models/Contestant.php
Contestant {
  id, user_id, game_id
  registration_number
  status (registered, waitlist, selected, active, eliminated, winner)
  team_id (nullable)
  registered_at, selected_at, started_at
  emergency_contact (json)
  consent_data (json)
  stats (json) // total_points, challenges_completed, rank
}

// app/Models/Team.php (for team-based formats)
Team {
  id, game_id, name, color
  captain_contestant_id
  members (relationship)
}
```

**UI Components**:
- Public registration page (RedeemWidget-style for embedding)
- Admin contestant management dashboard
- Contestant profile pages
- Team roster views

---

#### 4. **Real-Time Leaderboard System**
**Status**: Does not exist

**Required Features**:
- Live scoring calculation
- Real-time rank updates
- Point accumulation tracking
- Speed bonuses, penalties, special bonuses
- Historical snapshots (track rank changes over time)
- Multiple leaderboard views (overall, episode, challenge)
- Broadcasting-ready leaderboard API/widget

**Database Schema**:
```php
// app/Models/Leaderboard.php (aggregate view)
// Calculated from ContestantProgress records

// app/Models/ContestantProgress.php
ContestantProgress {
  id, contestant_id, game_id, episode_id
  total_points
  challenges_completed
  current_rank, previous_rank
  time_elapsed_seconds
  last_activity_at
  status (active, paused, eliminated)
}

// app/Models/Score.php
Score {
  id, contestant_id, challenge_id, redemption_id
  points_base, points_speed_bonus, points_penalties, points_total
  completion_time_seconds
  awarded_at
}
```

**Implementation**:
- WebSocket integration (Laravel Reverb or Pusher) for real-time updates
- Leaderboard calculation service
- Event-driven scoring pipeline
- Caching layer (Redis) for high-frequency reads

**UI Components**:
- Live leaderboard display (TV-ready graphics)
- Contestant personal dashboard
- Public leaderboard page/widget
- Mobile leaderboard view

---

#### 5. **Challenge Redemption & Verification Flow**
**Status**: Partially exists (voucher redemption flow)

**Gap**: Need to integrate game logic into redemption pipeline

**Required Enhancements**:
- Post-redemption game logic hooks
- Auto-verification based on challenge rules
- Manual verification queue for complex challenges
- Real-time scoring upon redemption
- Next voucher generation/reveal logic
- Landing page customization per challenge
- Sponsor activation integration

**Implementation Strategy**:
- Extend existing voucher redemption pipeline
- Add `ChallengeVerification` pipeline stage
- Hook into existing webhook system
- Add `ChallengeController` to process game-specific logic

**New Pipeline Stages**:
```php
// config/voucher-pipeline.php
'post-redemption' => [
    // ... existing stages
    \App\Voucher\Pipeline\VerifyChallenge::class,
    \App\Voucher\Pipeline\CalculateScore::class,
    \App\Voucher\Pipeline\UpdateLeaderboard::class,
    \App\Voucher\Pipeline\GenerateNextVoucher::class,
    \App\Voucher\Pipeline\NotifyGameEngine::class,
],
```

**Database Schema**:
```php
// app/Models/ChallengeRedemption.php
ChallengeRedemption {
  id, challenge_id, contestant_id, voucher_id
  redemption_id // links to x-Change redemption
  status (pending, verified, rejected, manual_review)
  verification_data (json) // collected inputs, GPS, photos
  verified_at, verified_by_user_id
  score_awarded
  time_to_complete_seconds
}
```

**UI Components**:
- Admin verification dashboard
- Redemption detail viewer
- Manual verification interface
- Contestant redemption history

---

#### 6. **Sponsor Activation & Landing Pages**
**Status**: Partially exists (landing page redirects)

**Gap**: Need dynamic, challenge-specific landing pages with interactive elements

**Required Features**:
- Custom landing page templates per challenge
- Splash screen ad placement configuration
- Interactive elements (trivia, puzzles, captcha)
- Sponsor content embedding (videos, app download CTAs)
- Progressive disclosure (show next voucher after interactions)
- Analytics tracking (time on page, clicks, conversions)

**Database Schema**:
```php
// app/Models/LandingPage.php
LandingPage {
  id, challenge_id
  template_name
  splash_screen_enabled, splash_screen_sponsor_id
  content_blocks (json) // configurable sections
  interactive_elements (json) // trivia questions, puzzles
  next_voucher_reveal_logic (json) // conditions for showing next code
  analytics_tracking (json)
}

// app/Models/Sponsor.php
Sponsor {
  id, name, logo_url, description
  contact_info (json)
  activation_budget
  challenges (relationship)
}

// app/Models/SponsorActivation.php
SponsorActivation {
  id, sponsor_id, challenge_id, contestant_id
  type (splash_screen, landing_page, interactive_element)
  impression_at, click_at, conversion_at
  time_on_page_seconds
  metadata (json)
}
```

**Implementation**:
- Vue components for dynamic landing pages
- Template system for landing page layouts
- Analytics tracking via frontend events
- Sponsor dashboard for activation metrics

**UI Components**:
- Landing page builder (block-based editor)
- Sponsor activation templates
- Analytics dashboard for sponsors
- Preview/test mode for landing pages

---

#### 7. **Game Engine & Logic Services**
**Status**: Does not exist

**Required Services**:
- Challenge deployment automation
- Contestant state machine
- Scoring calculation engine
- Time tracking & penalties
- Rule enforcement
- Event orchestration
- Game status monitoring

**Service Architecture**:
```php
// app/Services/GameEngine/
├── ChallengeDeploymentService.php
├── ScoringService.php
├── LeaderboardService.php
├── VerificationService.php
├── ContestantProgressService.php
└── EventOrchestrator.php

// Example: ScoringService.php
class ScoringService {
    public function calculateScore(
        ChallengeRedemption $redemption,
        Challenge $challenge
    ): Score {
        $basePoints = $challenge->points_base;
        $speedBonus = $this->calculateSpeedBonus($redemption, $challenge);
        $penalties = $this->calculatePenalties($redemption);
        
        return Score::create([
            'contestant_id' => $redemption->contestant_id,
            'challenge_id' => $challenge->id,
            'redemption_id' => $redemption->id,
            'points_base' => $basePoints,
            'points_speed_bonus' => $speedBonus,
            'points_penalties' => $penalties,
            'points_total' => $basePoints + $speedBonus - $penalties,
            'completion_time_seconds' => $redemption->time_to_complete_seconds,
        ]);
    }
}
```

**Events System**:
```php
// app/Events/
├── ChallengeCompleted.php
├── ScoreAwarded.php
├── LeaderboardUpdated.php
├── NextVoucherGenerated.php
├── ContestantEliminated.php
└── GameFinished.php
```

---

#### 8. **Real-Time Communication Layer**
**Status**: Does not exist

**Required Features**:
- WebSocket server for live updates
- Contestant app real-time notifications
- Viewer dashboard live updates
- Production dashboard monitoring
- SMS notifications via EngageSpark (already integrated)

**Implementation Options**:
1. **Laravel Reverb** (official Laravel WebSocket server) - Recommended
2. **Pusher** (commercial service)
3. **Socket.io** (custom Node.js server)

**Channels**:
- `game.{gameId}` - Game-wide events
- `contestant.{contestantId}` - Personal updates
- `leaderboard.{gameId}` - Rank changes
- `challenge.{challengeId}` - Challenge-specific events

**Integration**:
```php
// Broadcasting events
event(new LeaderboardUpdated($game, $leaderboard));

// Frontend listening (Vue)
Echo.channel(`game.${gameId}`)
    .listen('LeaderboardUpdated', (e) => {
        // Update UI
    });
```

**UI Components**:
- Real-time leaderboard widget
- Live notification toasts
- Contestant activity feed
- Production monitoring dashboard

---

#### 9. **Admin Production Dashboard**
**Status**: Partially exists (admin UI)

**Gap**: Need game-specific production controls

**Required Features**:
- Live game monitoring (all contestants on map)
- Real-time transaction feed
- Challenge deployment controls
- Emergency pause/resume
- Manual verification queue
- Contestant communication tools
- Analytics and reporting
- System health monitoring

**UI Sections**:
- Live map with contestant GPS locations
- Challenge status grid
- Verification queue
- Leaderboard view
- Transaction log
- Alerts and notifications
- Emergency controls (big red button)

**Implementation**:
- Admin dashboard route group
- Protected by admin middleware
- Real-time data via WebSockets
- Maps integration (Google Maps, Mapbox)

---

#### 10. **Contestant Mobile App/PWA**
**Status**: Partially exists (responsive web UI)

**Gap**: Need contestant-specific interface

**Required Features**:
- Challenge instructions viewer
- Code scanner (QR code)
- GPS-based navigation to challenge locations
- Redemption form (already exists via voucher flow)
- Personal dashboard (rank, points, progress)
- Notifications
- Support/help contact
- Rules reference

**Implementation Strategy**:
- Progressive Web App (PWA) built with Vue 3
- Installable on mobile devices
- Offline support for viewing instructions
- Camera access for QR scanning and photo uploads
- Geolocation API for navigation

**Routes**:
- `/contestant/dashboard`
- `/contestant/challenges`
- `/contestant/leaderboard`
- `/contestant/profile`
- `/contestant/support`

---

#### 11. **Public Viewer Experience**
**Status**: Does not exist

**Required Features**:
- Public leaderboard page
- Live transaction feed (anonymized)
- Prediction games
- Side quests for viewers
- Social media integration
- Contestant profiles (public)
- Challenge progress visualization

**UI Components**:
- Public leaderboard page
- Live activity feed
- Prediction game interface
- Contestant bio cards
- Share buttons and social widgets

**Routes**:
- `/watch/{gameSlug}`
- `/leaderboard/{gameSlug}`
- `/contestants/{contestantId}`
- `/predict/{gameSlug}`

---

#### 12. **Analytics & Reporting**
**Status**: Basic logging exists

**Gap**: Game-specific analytics and sponsor metrics

**Required Features**:
- Contestant performance reports
- Challenge completion analytics
- Sponsor ROI metrics
  - Impressions, clicks, time-on-page
  - Foot traffic validation (GPS check-ins)
  - Lead generation (contact info collected)
  - UGC (user-generated content) count
- Transaction volume reporting
- Audience engagement metrics
- Social media reach tracking

**Database Schema**:
```php
// app/Models/Analytics/ContestantActivity.php
ContestantActivity {
  id, contestant_id, challenge_id
  activity_type (redemption, location_checkin, photo_upload, etc.)
  occurred_at
  metadata (json)
}

// app/Models/Analytics/SponsorMetrics.php
// Aggregated views for sponsor reporting
```

**UI Components**:
- Admin analytics dashboard
- Sponsor-specific reports
- Exportable CSV/PDF reports
- Real-time metrics widgets

---

### 📱 Enhanced Redemption Features

#### 13. **Progressive Voucher Unlocking**
**Status**: Partially exists (voucher generation)

**Gap**: Need logic to reveal next voucher only after conditions are met

**Implementation**:
- Landing page conditional logic
- "Reveal Next Code" button after verification
- Email/SMS delivery of next code as backup
- Multi-stage challenges requiring sequential vouchers

**Example Flow**:
1. Contestant redeems voucher A
2. Landing page shows sponsor activation
3. Contestant completes interactive task (trivia)
4. System verifies challenge completion
5. Landing page reveals voucher B with next instructions

---

#### 14. **Location-Based Validation**
**Status**: GPS collection exists

**Gap**: Need automatic validation against target locations

**Implementation**:
```php
// app/Services/LocationValidationService.php
class LocationValidationService {
    public function validateLocation(
        float $lat,
        float $lng,
        Challenge $challenge
    ): bool {
        $distance = $this->calculateDistance(
            $lat, $lng,
            $challenge->location_lat,
            $challenge->location_lng
        );
        
        return $distance <= $challenge->location_radius;
    }
}
```

**Integration**:
- Real-time GPS validation during redemption
- Visual feedback ("You are X meters away")
- Map display showing target location
- Navigation links (Google Maps, Waze)

---

#### 15. **Multi-Media Proof Collection**
**Status**: Photo upload exists

**Gap**: Need better media handling and preview

**Enhancements**:
- Video upload support
- Multiple photo uploads per challenge
- Image preview and crop
- Receipt OCR (optional, future enhancement)
- Social media link validation (e.g., verify TikTok/IG post)

---

#### 16. **Time-Based Challenges & Penalties**
**Status**: Does not exist

**Required Features**:
- Challenge timer starts when voucher is received
- Real-time countdown display
- Auto-penalties for late completion
- Time-out handling (challenge expires)
- Speed bonus calculation

**Implementation**:
```php
// In Challenge model
public function isExpired(ChallengeRedemption $redemption): bool {
    $deadline = $redemption->created_at->addMinutes($this->time_limit_minutes);
    return now()->isAfter($deadline);
}

public function calculateSpeedBonus(ChallengeRedemption $redemption): int {
    $completionPercent = $redemption->time_to_complete_seconds / 
                        ($this->time_limit_minutes * 60);
    
    if ($completionPercent <= 0.25) { // Completed in first 25% of time
        return 50; // Top speed bonus
    } elseif ($completionPercent <= 0.50) {
        return 25; // Good speed
    }
    
    return 0; // No bonus
}
```

---

### 🎯 Sponsor & Monetization Features

#### 17. **Sponsor Management System**
**Status**: Does not exist

**Required Features**:
- Sponsor onboarding and profiles
- Budget tracking
- Activation assignment to challenges
- Metrics dashboard for sponsors
- Invoice generation

**Database Schema** (see Sponsor/SponsorActivation models above)

**UI Components**:
- Sponsor portal
- Activation performance dashboard
- Media kit downloads (UGC collected)
- ROI calculator

---

#### 18. **Voucher Generation Fees**
**Status**: Does not exist in redeem-x

**Gap**: Monetization layer for voucher creation

**Implementation**:
- Per-voucher generation fee configuration
- Billing tied to Sponsor or Game budget
- Volume pricing tiers
- Integration with existing payment gateway

**Example Pricing**:
- Simple voucher (SMS only): ₱5
- Rich voucher (splash + landing page): ₱20
- Interactive voucher (with sponsor activation): ₱50

**Database Schema**:
```php
// app/Models/VoucherBilling.php
VoucherBilling {
  id, game_id, sponsor_id
  voucher_count
  unit_price
  total_amount
  billing_period_start, billing_period_end
  status (pending, invoiced, paid)
}
```

---

### 🛠️ Technical Infrastructure Enhancements

#### 19. **Performance & Scaling**
**Status**: Standard Laravel setup

**Required Enhancements**:
- Redis caching for leaderboards
- Database query optimization (indexes)
- Queue workers for async processing
- CDN for media assets (S3 + CloudFront)
- Horizontal scaling for WebSocket server

**Configuration**:
```env
REDIS_CLIENT=phpredis
QUEUE_CONNECTION=redis
BROADCAST_DRIVER=reverb
FILESYSTEM_DISK=s3
```

---

#### 20. **Monitoring & Observability**
**Status**: Basic logging

**Required Enhancements**:
- Application monitoring (Laravel Telescope in dev)
- Error tracking (Sentry)
- Uptime monitoring
- Performance metrics
- Alerting for game-critical failures

**Key Metrics to Track**:
- Redemption processing time
- WebSocket connection count
- Leaderboard refresh latency
- Challenge completion rate
- System health (API response times)

---

#### 21. **Testing Infrastructure**
**Status**: Pest PHP tests exist

**Required Test Coverage**:
- Game engine logic unit tests
- Challenge verification tests
- Scoring calculation tests
- Leaderboard ranking tests
- End-to-end gameplay simulation
- Load testing for concurrent contestants

**Test Commands**:
```bash
php artisan test --filter=GameEngine
php artisan test --filter=Challenge
php artisan test --filter=Leaderboard
```

---

## Implementation Phases

### Phase 0: Foundation (Weeks 1-2)
**Goal**: Database schema and core models

**Tasks**:
- [ ] Create migrations for Game, Episode, Challenge, Contestant, Team models
- [ ] Create migrations for ChallengeRedemption, Score, ContestantProgress, Leaderboard models
- [ ] Create migrations for Sponsor, SponsorActivation, LandingPage models
- [ ] Create migrations for Analytics models
- [ ] Create model factories for testing
- [ ] Set up relationships between models
- [ ] Create seeders for demo data

**Deliverables**:
- Complete database schema
- Model classes with relationships
- Factory classes for all models
- Seeded demo game data

---

### Phase 1: Game Management Core (Weeks 3-5)
**Goal**: Admin can create games, episodes, and challenges

**Tasks**:
- [ ] Game CRUD (Create, Read, Update, Delete)
- [ ] Episode CRUD
- [ ] Challenge builder (extends Campaign UI)
- [ ] Challenge-to-Campaign association
- [ ] Admin dashboard layout
- [ ] Game configuration wizard
- [ ] Challenge sequencing interface
- [ ] Location picker (map integration)

**Deliverables**:
- Admin game management UI
- Challenge creation workflow
- Demo game with 5 challenges created

---

### Phase 2: Contestant Experience (Weeks 6-8)
**Goal**: Public registration and contestant dashboard

**Tasks**:
- [ ] Public registration form
- [ ] Registration workflow (approval/selection)
- [ ] Contestant authentication flow
- [ ] Contestant dashboard UI (Vue PWA)
- [ ] Challenge viewer
- [ ] Personal stats display
- [ ] QR code scanner integration

**Deliverables**:
- Public registration page
- Contestant mobile PWA
- Functional contestant dashboard

---

### Phase 3: Challenge Redemption & Verification (Weeks 9-11)
**Goal**: Integrate game logic into voucher redemption

**Tasks**:
- [ ] Extend voucher pipeline with game stages
- [ ] `VerifyChallenge` pipeline stage
- [ ] `CalculateScore` pipeline stage
- [ ] `UpdateLeaderboard` pipeline stage
- [ ] `GenerateNextVoucher` pipeline stage
- [ ] Manual verification queue UI
- [ ] Location validation service
- [ ] Time-based challenge logic
- [ ] Next voucher reveal on landing page

**Deliverables**:
- Functional challenge redemption flow
- Scoring system working
- Manual verification dashboard
- Next voucher auto-generation

---

### Phase 4: Leaderboard & Real-Time Updates (Weeks 12-14)
**Goal**: Live leaderboard with WebSocket updates

**Tasks**:
- [ ] Install and configure Laravel Reverb
- [ ] Leaderboard calculation service
- [ ] ContestantProgress tracking
- [ ] WebSocket event broadcasting
- [ ] Real-time leaderboard UI (Vue component)
- [ ] Contestant personal dashboard updates
- [ ] Public leaderboard page
- [ ] Redis caching layer

**Deliverables**:
- Real-time leaderboard working
- WebSocket server deployed
- Cached leaderboard queries
- TV-ready leaderboard widget

---

### Phase 5: Sponsor Activations & Landing Pages (Weeks 15-17)
**Goal**: Dynamic landing pages with sponsor content

**Tasks**:
- [ ] Landing page template system
- [ ] Splash screen configuration
- [ ] Interactive elements (trivia, puzzles)
- [ ] Progressive disclosure logic
- [ ] Sponsor analytics tracking
- [ ] Sponsor dashboard UI
- [ ] UGC collection tracking
- [ ] ROI metrics display

**Deliverables**:
- Customizable landing pages
- Sponsor activation templates
- Analytics dashboard
- Demo sponsor integration

---

### Phase 6: Production Dashboard (Weeks 18-20)
**Goal**: Admin real-time monitoring and control

**Tasks**:
- [ ] Live map with contestant GPS
- [ ] Real-time transaction feed
- [ ] Challenge deployment controls
- [ ] Emergency pause/resume functionality
- [ ] System health monitoring
- [ ] Contestant communication tools
- [ ] Analytics reports
- [ ] Alert system

**Deliverables**:
- Production control dashboard
- Live monitoring tools
- Emergency controls functional
- Admin communication system

---

### Phase 7: Public Viewer Experience (Weeks 21-22)
**Goal**: Public can watch and engage

**Tasks**:
- [ ] Public leaderboard page
- [ ] Live activity feed (anonymized)
- [ ] Contestant profile pages
- [ ] Social media integration
- [ ] Prediction games (optional)
- [ ] Side quests (optional)

**Deliverables**:
- Public-facing watch page
- Viewer engagement features
- Social sharing functionality

---

### Phase 8: Testing & Polish (Weeks 23-24)
**Goal**: End-to-end testing and bug fixes

**Tasks**:
- [ ] Complete unit test coverage
- [ ] Integration testing (full redemption flow)
- [ ] Load testing (simulate 50 concurrent contestants)
- [ ] Manual QA of all user journeys
- [ ] Security audit
- [ ] Performance optimization
- [ ] Documentation updates
- [ ] Demo video creation

**Deliverables**:
- Test suite with >80% coverage
- Load testing report
- Security audit report
- Production-ready release

---

### Phase 9: Pilot Episode Preparation (Weeks 25-26)
**Goal**: Launch first pilot game

**Tasks**:
- [ ] Create pilot game configuration
- [ ] Design 5 pilot challenges
- [ ] Configure landing pages and sponsor content
- [ ] Recruit 10-15 test contestants
- [ ] Run dry-run simulation
- [ ] Train production team
- [ ] Set up monitoring and alerts
- [ ] Launch pilot episode

**Deliverables**:
- First live QuestPay episode
- Performance metrics captured
- Feedback collection
- Iteration plan

---

## Technical Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    QuestPay™ System Architecture                 │
└─────────────────────────────────────────────────────────────────┘

┌──────────────────┐      ┌──────────────────┐      ┌──────────────────┐
│  Contestant PWA  │      │  Admin Dashboard │      │  Viewer Website  │
│   (Vue 3 + TS)   │      │   (Vue 3 + TS)   │      │   (Vue 3 + TS)   │
└────────┬─────────┘      └────────┬─────────┘      └────────┬─────────┘
         │                         │                         │
         └─────────────────────────┼─────────────────────────┘
                                   │
                                   ▼
                    ┌──────────────────────────┐
                    │   Laravel 12 Backend     │
                    │   (Inertia.js Bridge)    │
                    └──────────┬───────────────┘
                               │
         ┏━━━━━━━━━━━━━━━━━━━━┻━━━━━━━━━━━━━━━━━━━━━━┓
         ▼                     ▼                      ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────┐
│  Game Engine    │  │  x-Change Core  │  │  Real-Time Layer    │
│  Services       │  │  (Voucher)      │  │  (Reverb/Pusher)    │
├─────────────────┤  ├─────────────────┤  ├─────────────────────┤
│ • Challenge     │  │ • Voucher CRUD  │  │ • WebSocket Server  │
│   Deployment    │  │ • Redemption    │  │ • Broadcasting      │
│ • Scoring       │  │ • Validation    │  │ • Live Leaderboard  │
│ • Leaderboard   │  │ • Notification  │  │ • Contestant Events │
│ • Verification  │  │ • Disbursement  │  │ • Viewer Feed       │
│ • Progress      │  │ • Pipeline      │  │                     │
└────────┬────────┘  └────────┬────────┘  └──────────┬──────────┘
         │                    │                       │
         └────────────────────┼───────────────────────┘
                              ▼
                   ┌────────────────────┐
                   │   Database Layer   │
                   ├────────────────────┤
                   │ • Games/Episodes   │
                   │ • Challenges       │
                   │ • Contestants      │
                   │ • Redemptions      │
                   │ • Scores           │
                   │ • Leaderboard      │
                   │ • Sponsors         │
                   │ • Analytics        │
                   └────────┬───────────┘
                            │
         ┏━━━━━━━━━━━━━━━━━┻━━━━━━━━━━━━━━━━━┓
         ▼                  ▼                  ▼
┌─────────────┐   ┌─────────────┐   ┌─────────────────┐
│   Redis     │   │   Queue     │   │  File Storage   │
│  (Cache)    │   │  (Jobs)     │   │  (S3/Local)     │
├─────────────┤   ├─────────────┤   ├─────────────────┤
│ • Leaderbd  │   │ • Vouchers  │   │ • Photos        │
│ • Session   │   │ • Webhooks  │   │ • Videos        │
│ • Locks     │   │ • Scoring   │   │ • Receipts      │
└─────────────┘   └─────────────┘   └─────────────────┘

         ┌───────────────────────────────────┐
         │    External Integrations          │
         ├───────────────────────────────────┤
         │ • SMS (EngageSpark)              │
         │ • Email (SMTP)                    │
         │ • Payment (NetBank/Omnipay)       │
         │ • Maps (Google Maps API)          │
         │ • Auth (WorkOS)                   │
         │ • Monitoring (Sentry/Telescope)   │
         └───────────────────────────────────┘
```

---

## Database Schema Summary

### Core Game Tables
- `games` - Game/season configuration
- `episodes` - Episodes within a game
- `challenges` - Challenge definitions (extends campaigns)
- `contestants` - Contestant registrations
- `teams` - Team definitions (optional)

### Gameplay Tracking
- `challenge_redemptions` - Links challenges to voucher redemptions
- `scores` - Individual score records
- `contestant_progress` - Aggregated contestant state
- `leaderboard` (computed view)

### Sponsorship
- `sponsors` - Sponsor profiles
- `sponsor_activations` - Sponsor engagement tracking
- `landing_pages` - Challenge landing page configs

### Analytics
- `contestant_activity` - Granular activity log
- `sponsor_metrics` (computed aggregates)

### Existing Tables (Reused)
- `users` - Authentication (WorkOS)
- `campaigns` - Voucher templates (extended for challenges)
- `vouchers` - x-Change vouchers
- `top_ups` - Wallet top-ups

---

## Key Configuration Files

```
config/
├── questpay.php                 # NEW: QuestPay-specific settings
├── voucher-pipeline.php         # EXTENDED: Add game pipeline stages
├── broadcasting.php             # CONFIGURED: Laravel Reverb
└── services.php                 # ADD: Google Maps API key

resources/js/
├── pages/
│   ├── Games/                   # NEW: Game management pages
│   ├── Challenges/              # NEW: Challenge builder
│   ├── Contestants/             # NEW: Contestant pages
│   ├── Leaderboard/             # NEW: Leaderboard views
│   └── Watch/                   # NEW: Public viewer pages
└── components/
    ├── QuestPay/                # NEW: Game-specific components
    │   ├── LeaderboardWidget.vue
    │   ├── ChallengeCard.vue
    │   ├── ContestantDashboard.vue
    │   └── LiveFeed.vue
    └── ui/                      # EXISTING: Reusable UI components

routes/
├── web.php                      # EXTENDED: Add game routes
├── games.php                    # NEW: Game-specific routes
├── contestant.php               # NEW: Contestant routes
└── api.php                      # EXTENDED: Game APIs
```

---

## Environment Variables

```env
# QuestPay Configuration
QUESTPAY_ENABLED=true
QUESTPAY_DEFAULT_EPISODE_DURATION=180  # minutes

# Real-Time Broadcasting
BROADCAST_DRIVER=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Maps Integration
GOOGLE_MAPS_API_KEY=your-api-key

# SMS (Already configured)
ENGAGESPARK_API_KEY=your-key

# Monitoring (Optional)
SENTRY_DSN=your-sentry-dsn
```

---

## API Endpoints (New)

### Game Management (Admin)
```
POST   /api/games                # Create game
GET    /api/games/{id}           # Get game details
PATCH  /api/games/{id}           # Update game
DELETE /api/games/{id}           # Delete game
POST   /api/games/{id}/start     # Start game
POST   /api/games/{id}/pause     # Pause game
POST   /api/games/{id}/end       # End game

POST   /api/episodes             # Create episode
GET    /api/episodes/{id}        # Get episode
PATCH  /api/episodes/{id}        # Update episode

POST   /api/challenges           # Create challenge
GET    /api/challenges/{id}      # Get challenge
PATCH  /api/challenges/{id}      # Update challenge
POST   /api/challenges/{id}/deploy  # Deploy challenge
```

### Contestant APIs
```
POST   /api/contestants/register       # Public registration
GET    /api/contestants/{id}/progress  # Get progress
GET    /api/contestants/{id}/challenges # Get assigned challenges
GET    /api/contestants/{id}/score     # Get score breakdown
```

### Leaderboard & Public APIs
```
GET    /api/games/{id}/leaderboard     # Get current leaderboard
GET    /api/games/{id}/activity        # Get activity feed
GET    /api/games/{id}/stats           # Get game statistics
```

### Real-Time WebSocket Channels
```
game.{gameId}                    # Game-wide events
contestant.{contestantId}        # Contestant personal
leaderboard.{gameId}             # Leaderboard updates
challenge.{challengeId}          # Challenge-specific
production.{gameId}              # Admin monitoring
```

---

## Artisan Commands (New)

```bash
# Game Management
php artisan questpay:create-game "Metro Manila Sprint"
php artisan questpay:start-game {gameId}
php artisan questpay:end-game {gameId}

# Challenge Deployment
php artisan questpay:deploy-challenge {challengeId}
php artisan questpay:deploy-episode {episodeId}  # Deploy all challenges in episode

# Testing & Simulation
php artisan questpay:simulate-contestant {gameId} [--count=10]
php artisan questpay:test-redemption {challengeId} {contestantId}

# Leaderboard Management
php artisan questpay:recalculate-leaderboard {gameId}
php artisan questpay:export-leaderboard {gameId} [--format=csv]

# Maintenance
php artisan questpay:cleanup-old-games [--days=30]
php artisan questpay:generate-reports {gameId}
```

---

## Next Steps

1. **Review and Approve Plan** - Stakeholder sign-off on feature scope
2. **Set Up Development Environment** - Install Laravel Reverb, configure services
3. **Phase 0 Kickoff** - Start database schema implementation
4. **Design UI/UX Mockups** - Design game management and contestant interfaces
5. **Assign Development Team** - Allocate resources per phase
6. **Establish Timeline** - Target pilot launch date (26 weeks from start)

---

## Resources & References

### Internal Documentation
- `WARP.md` - redeem-x development guide
- `OMNIPAY_INTEGRATION_PLAN.md` - Payment gateway architecture
- `NOTIFICATION_TEMPLATES.md` - Template system documentation

### External QuestPay Documentation
- `/Users/rli/Documents/questpay-docs/` - Complete QuestPay concept docs
- Key files: `concept.md`, `mechanics.md`, `technology.md`, `sample-episode.md`

### Technology Stack
- Laravel 12 - https://laravel.com/docs/12.x
- Laravel Reverb - https://reverb.laravel.com
- Vue 3 - https://vuejs.org
- Inertia.js - https://inertiajs.com
- Laravel Wayfinder - https://wayfinder.dev
- Pest PHP - https://pestphp.com

---

## Risk Assessment

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Real-time scaling issues | High | Medium | Load testing, Redis caching, CDN |
| GPS accuracy problems | Medium | High | Use radius-based validation, manual override |
| Webhook delivery failures | High | Medium | Queue retry logic, fallback polling |
| Contestant cheating | Medium | Medium | Multi-factor verification, manual review queue |
| Performance during live episode | High | Low | Pre-launch load testing, redundancy |
| Sponsor integration complexity | Medium | Medium | Template system, reusable components |

---

## Success Metrics

### Technical KPIs
- Redemption processing time: < 3 seconds (target: < 2s)
- WebSocket latency: < 500ms
- Leaderboard refresh rate: < 1 second
- System uptime: 99.9% during live episodes
- Concurrent contestants supported: 50+ (target: 100+)

### Business KPIs
- Contestant completion rate: > 80%
- Sponsor activation engagement: > 60%
- Viewer retention: > 70% through episode
- Social media impressions: 10K+ per episode
- Cost per contestant: < ₱500 (voucher + infrastructure costs)

---

## Appendix: Example User Flows

### Flow 1: Contestant Journey (End-to-End)

1. **Discovery** - Contestant sees Facebook ad for "Metro Manila Sprint"
2. **Registration** - Clicks ad → fills registration form → receives confirmation email
3. **Selection** - Admin reviews and selects contestant → SMS sent: "You're in!"
4. **Onboarding** - Email with rules, instructions, and first voucher code
5. **Challenge 1** - Clicks voucher link → splash page → redemption wizard (upload selfie + location) → verified → landing page shows trivia question
6. **Sponsor Activation** - Answers trivia correctly → UnionBank ad displayed → next voucher code revealed
7. **Challenge 2** - Navigates to location → redeems code → uploads receipt photo → verified → landing page with Jollibee ad → next code
8. **Repeat** - Continues through 5-8 challenges
9. **Finale** - Top 3 receive final voucher → report to finale location → complete obstacle course → winner announced
10. **Prize** - Winner receives ₱50,000 via NetBank disbursement

---

### Flow 2: Admin Production Flow

1. **Pre-Production** - Admin creates game "Metro Manila Sprint" → adds 5 episodes → designs 8 challenges per episode → assigns sponsors
2. **Challenge Setup** - For each challenge: associates campaign template → sets location → configures landing page → sets time limit
3. **Registration** - Admin reviews contestant applications → selects 10 contestants → system sends onboarding emails with first voucher
4. **Live Monitoring** - Admin opens production dashboard → sees live map with contestant locations → monitors transaction feed → watches leaderboard update
5. **Manual Override** - Contestant photo unclear → admin reviews in verification queue → manually approves
6. **Emergency Pause** - Technical issue detected → admin pauses game → sends SMS to contestants → resumes after fix
7. **End Game** - Episode complete → admin ends game → system calculates final rankings → triggers prize disbursement
8. **Post-Production** - Admin exports analytics report → generates sponsor metrics → shares with stakeholders

---

### Flow 3: Sponsor Integration Flow

1. **Onboarding** - Sponsor (e.g., UnionBank) signs contract → admin creates sponsor profile
2. **Budget Allocation** - Sponsor allocates ₱200K budget for episode sponsorship
3. **Challenge Assignment** - Admin assigns sponsor to 3 challenges (splash screens + landing pages)
4. **Content Upload** - Sponsor provides logo, ad creative, promotional video
5. **Landing Page Config** - Admin configures landing page: UnionBank video → trivia question → app download CTA
6. **Live Episode** - 10 contestants complete sponsored challenges → 50+ impressions generated
7. **Real-Time Metrics** - Sponsor views dashboard: 30 seconds avg time on page, 5 app downloads, 8 social shares
8. **UGC Collection** - Admin packages 20 contestant photos/videos featuring sponsor
9. **Post-Episode Report** - Sponsor receives PDF report: impressions, engagement, ROI metrics, UGC media kit
10. **Renewal** - Sponsor reviews metrics → signs up for next episode

---

## Conclusion

This implementation plan outlines a comprehensive approach to building QuestPay™ functionality into the redeem-x platform. By leveraging existing voucher infrastructure and adding game-specific layers, the platform can support reality-based competitive gameplay with minimal disruption to core x-Change functionality.

**Timeline**: 26 weeks (6 months)  
**Team**: 2-3 full-stack developers + 1 DevOps  
**Budget Estimate**: $150K-$250K (development + infrastructure for pilot)

The phased approach allows for iterative development, testing, and refinement while delivering value at each milestone. The first pilot episode (Phase 9) will validate the concept and provide data for optimization before full-scale production.
