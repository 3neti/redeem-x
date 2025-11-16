# QuestPay™ Feature Matrix

Quick reference showing what exists vs. what needs to be built.

## ✅ Existing Features (80% Complete)

| Feature | Status | Location | Notes |
|---------|--------|----------|-------|
| **Voucher Generation** | ✅ Ready | x-Change Core | Generate codes with QR, expiration, metadata |
| **Voucher Redemption** | ✅ Ready | x-Change Core | Web-based redemption with validation |
| **Campaign System** | ✅ Ready | `app/Models/Campaign.php` | Reusable voucher templates with `VoucherInstructionsData` |
| **Splash Pages** | ✅ Ready | Redemption flow | Custom messaging on code entry |
| **Landing Pages** | ✅ Ready | Redemption flow | Redirect after redemption |
| **Input Collection** | ✅ Ready | Redemption wizard | Photos, location, signatures, text, KYC |
| **Photo Upload** | ✅ Ready | Input fields | Image capture and storage |
| **GPS Location** | ✅ Ready | Input fields | Lat/lng collection with consent |
| **Digital Signature** | ✅ Ready | Input fields | Signature pad capture |
| **Text Input** | ✅ Ready | Input fields | Survey-like responses |
| **Validation Rules** | ✅ Ready | Campaign config | Custom validation per field |
| **SMS Notifications** | ✅ Ready | EngageSpark | Code delivery and alerts |
| **Email Notifications** | ✅ Ready | SMTP | Code delivery and updates |
| **Webhooks** | ✅ Ready | x-Change Core | Post-redemption callbacks |
| **Payment Disbursement** | ✅ Ready | Omnipay/NetBank | Send money to mobile numbers |
| **Top-Up/Wallet Funding** | ✅ Ready | Direct Checkout | Users fund wallets via NetBank |
| **User Authentication** | ✅ Ready | WorkOS | Secure login and session management |
| **Responsive UI** | ✅ Ready | Vue 3 + Tailwind | Mobile-friendly interface |
| **Type-Safe Routes** | ✅ Ready | Laravel Wayfinder | Auto-generated TypeScript routes |

---

## 🔨 Features to Build (20% Gap)

### Core Game Engine

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Game/Episode Models** | 🔴 Critical | 2 days | Database migration |
| **Challenge Model** | 🔴 Critical | 2 days | Campaign model (extends) |
| **Contestant Model** | 🔴 Critical | 2 days | User model (relationship) |
| **Score Model** | 🔴 Critical | 1 day | Challenge, Contestant |
| **Leaderboard Service** | 🔴 Critical | 3 days | Score model, Redis |
| **Verification Pipeline** | 🔴 Critical | 3 days | Existing voucher pipeline |
| **Game Engine Services** | 🔴 Critical | 5 days | All models |

### Admin Interfaces

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Game Management UI** | 🔴 Critical | 5 days | Game/Episode models |
| **Challenge Builder** | 🔴 Critical | 5 days | Challenge model, Campaign UI |
| **Contestant Dashboard** | 🟡 High | 3 days | Contestant model |
| **Verification Queue** | 🟡 High | 3 days | ChallengeRedemption model |
| **Production Dashboard** | 🟡 High | 5 days | Real-time layer, Maps API |
| **Analytics Reports** | 🟢 Medium | 3 days | Analytics models |

### Contestant Experience

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Registration Form** | 🔴 Critical | 2 days | Contestant model |
| **Contestant PWA** | 🔴 Critical | 5 days | Vue PWA setup |
| **Challenge Viewer** | 🔴 Critical | 3 days | Challenge model |
| **Personal Dashboard** | 🟡 High | 3 days | ContestantProgress model |
| **QR Scanner** | 🟢 Medium | 2 days | Camera API, Vue component |
| **Navigation** | 🟢 Medium | 2 days | Google Maps API |

### Real-Time Features

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Laravel Reverb Setup** | 🔴 Critical | 1 day | Laravel package |
| **WebSocket Broadcasting** | 🔴 Critical | 3 days | Reverb, Events |
| **Live Leaderboard Widget** | 🔴 Critical | 3 days | Leaderboard service, Reverb |
| **Real-Time Scoring** | 🔴 Critical | 2 days | Score model, Broadcasting |
| **Live Activity Feed** | 🟡 High | 2 days | Broadcasting |

### Sponsor Features

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Sponsor Model** | 🟡 High | 1 day | Database migration |
| **Landing Page Builder** | 🟡 High | 5 days | LandingPage model, Vue components |
| **Splash Screen Config** | 🟡 High | 2 days | Challenge model |
| **Interactive Elements** | 🟢 Medium | 3 days | Vue components (trivia, puzzles) |
| **Sponsor Analytics** | 🟢 Medium | 3 days | SponsorActivation tracking |
| **Sponsor Dashboard** | 🟢 Medium | 3 days | Analytics models |

### Public Viewer

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Public Leaderboard** | 🟡 High | 2 days | Leaderboard service |
| **Watch Page** | 🟢 Medium | 3 days | Game model, Broadcasting |
| **Activity Feed** | 🟢 Medium | 2 days | Broadcasting |
| **Contestant Profiles** | 🟢 Medium | 2 days | Contestant model |
| **Social Sharing** | 🟢 Low | 1 day | Meta tags, Share API |

### Advanced Features

| Feature | Priority | Effort | Dependencies |
|---------|----------|--------|--------------|
| **Location Validation** | 🟡 High | 2 days | GPS, Challenge model |
| **Time-Based Penalties** | 🟡 High | 2 days | Scoring service |
| **Speed Bonuses** | 🟡 High | 2 days | Scoring service |
| **Progressive Unlocking** | 🟡 High | 3 days | Landing page logic |
| **Video Upload** | 🟢 Medium | 2 days | Storage, Input fields |
| **Receipt OCR** | 🔵 Low | 5 days | ML service, Third-party API |

---

## Effort Summary

### Total Development Days
- **Critical (🔴)**: ~70 days
- **High (🟡)**: ~45 days
- **Medium (🟢)**: ~35 days
- **Low (🔵)**: ~5 days

**Total**: ~155 developer-days

### Team Configuration
- **2 developers × 13 weeks** = 130 days (Critical + High priority)
- **3 developers × 9 weeks** = 135 days (All features)

**Recommendation**: 3 developers for 20 weeks (includes buffer)

---

## Priority Definitions

- 🔴 **Critical**: Required for MVP/pilot episode
- 🟡 **High**: Strongly recommended for pilot
- 🟢 **Medium**: Nice-to-have for pilot, required for full season
- 🔵 **Low**: Future enhancement

---

## Phase-to-Feature Mapping

### Phase 0: Foundation (Weeks 1-2)
- Game, Episode, Challenge, Contestant, Team models
- ChallengeRedemption, Score, ContestantProgress models
- Sponsor, SponsorActivation, LandingPage models
- Model factories and seeders

### Phase 1: Game Management (Weeks 3-5)
- Game CRUD UI
- Episode CRUD UI
- Challenge Builder (extends Campaign UI)
- Location picker

### Phase 2: Contestant Experience (Weeks 6-8)
- Registration form
- Contestant PWA
- Challenge viewer
- Personal dashboard

### Phase 3: Redemption Integration (Weeks 9-11)
- Verification pipeline stages
- Location validation
- Time-based logic
- Manual verification queue

### Phase 4: Real-Time (Weeks 12-14)
- Laravel Reverb setup
- Leaderboard service
- WebSocket broadcasting
- Live leaderboard UI

### Phase 5: Sponsor Features (Weeks 15-17)
- Landing page builder
- Splash screen config
- Interactive elements
- Sponsor analytics

### Phase 6: Production Tools (Weeks 18-20)
- Production dashboard
- Live map
- Emergency controls
- System monitoring

### Phase 7: Public Viewer (Weeks 21-22)
- Public leaderboard
- Watch page
- Activity feed

### Phase 8: Testing (Weeks 23-24)
- Unit tests
- Integration tests
- Load testing
- QA

### Phase 9: Pilot Launch (Weeks 25-26)
- Pilot game setup
- Dry-run simulation
- Team training
- Launch

---

## Technology Stack

### Existing
- Laravel 12 ✅
- Vue 3 + TypeScript ✅
- Inertia.js ✅
- Tailwind CSS v4 ✅
- WorkOS Authentication ✅
- Laravel Wayfinder ✅
- EngageSpark SMS ✅
- NetBank/Omnipay Payment ✅
- Pest PHP Testing ✅

### New Additions
- **Laravel Reverb** 🆕 - WebSocket server
- **Redis** 🆕 - Caching and queues (or upgrade usage)
- **Google Maps API** 🆕 - Location services
- **Sentry** 🆕 - Error tracking (optional)
- **S3/CloudFront** 🆕 - Media CDN (optional)

---

## Database Schema Summary

### New Tables (14)
1. `games` - Game/season configuration
2. `episodes` - Episodes within games
3. `challenges` - Challenge definitions
4. `contestants` - Contestant registrations
5. `teams` - Team definitions (optional)
6. `challenge_redemptions` - Links challenges to redemptions
7. `scores` - Individual score records
8. `contestant_progress` - Aggregated contestant state
9. `sponsors` - Sponsor profiles
10. `sponsor_activations` - Sponsor engagement tracking
11. `landing_pages` - Landing page configs
12. `contestant_activity` - Activity log
13. `voucher_billing` - Billing records
14. `leaderboard_snapshots` - Historical leaderboard states

### Existing Tables (Extended)
- `campaigns` - Add relationship to challenges
- `users` - Add relationship to contestants
- `vouchers` - Add relationship to challenge_redemptions

---

## API Endpoints Summary

### Game Management (Admin)
- `POST /api/games` - Create game
- `GET /api/games/{id}` - Get game
- `PATCH /api/games/{id}` - Update game
- `POST /api/games/{id}/start` - Start game

### Contestant
- `POST /api/contestants/register` - Public registration
- `GET /api/contestants/{id}/progress` - Get progress
- `GET /api/contestants/{id}/challenges` - Get challenges

### Leaderboard
- `GET /api/games/{id}/leaderboard` - Current standings
- `GET /api/games/{id}/activity` - Activity feed

### WebSocket Channels
- `game.{gameId}` - Game-wide events
- `contestant.{contestantId}` - Personal updates
- `leaderboard.{gameId}` - Rank changes

---

## Integration Points

### Existing Systems
- **Voucher Pipeline** → Add game stages
- **Campaign System** → Extend for challenges
- **User Model** → Relate to contestants
- **SMS/Email** → Use for game notifications
- **Payment Gateway** → Use for prize disbursement

### External Services
- **EngageSpark** → SMS delivery (existing)
- **NetBank** → Disbursements (existing)
- **Google Maps** → Location validation (new)
- **Laravel Reverb** → Real-time updates (new)

---

## Testing Strategy

### Unit Tests (30%)
- Game engine logic
- Scoring calculations
- Validation rules
- Time-based penalties

### Integration Tests (50%)
- Full redemption flow
- Challenge verification
- Leaderboard updates
- Webhook processing

### E2E Tests (10%)
- Complete contestant journey
- Admin workflows
- Public viewer experience

### Load Tests (10%)
- 50 concurrent contestants
- 1000 viewer connections
- Real-time leaderboard updates

---

## Success Criteria

### MVP/Pilot Ready
- ✅ 10 contestants can complete 5 challenges
- ✅ Real-time leaderboard updates within 1 second
- ✅ GPS validation works within 50m radius
- ✅ Automatic scoring and verification
- ✅ Admin can monitor all contestants live
- ✅ Prize disbursement works end-to-end

### Production Ready
- ✅ 50+ concurrent contestants supported
- ✅ System uptime > 99.9%
- ✅ Redemption processing < 3 seconds
- ✅ Load tested at 2x expected capacity
- ✅ Sponsor analytics dashboard functional
- ✅ Public viewer experience polished

---

## Key Decisions

### Architecture
- ✅ **Extend vs. Separate**: Extend redeem-x (not separate app)
- ✅ **WebSocket Provider**: Laravel Reverb (official)
- ✅ **Real-Time Strategy**: Event broadcasting (not polling)
- ✅ **Cache Layer**: Redis for leaderboard
- ✅ **File Storage**: S3 for photos/videos

### Design Patterns
- ✅ **Challenge-Campaign Relationship**: Challenges belong to Campaigns
- ✅ **Redemption Integration**: Pipeline stages (not separate flow)
- ✅ **Scoring**: Event-driven (not scheduled)
- ✅ **Verification**: Auto + manual queue fallback

---

## Risk Register

| Risk | Impact | Likelihood | Mitigation | Owner |
|------|--------|------------|------------|-------|
| Real-time scaling | High | Medium | Load test, CDN, Redis | DevOps |
| GPS accuracy | Medium | High | Radius validation, manual override | Backend |
| Webhook failures | High | Medium | Queue retry, polling fallback | Backend |
| Contestant cheating | Medium | Medium | Multi-factor verification | Product |
| Live episode crash | Critical | Low | Redundancy, monitoring | DevOps |

---

**For detailed implementation steps, see**: [`QUESTPAY_IMPLEMENTATION_PLAN.md`](./QUESTPAY_IMPLEMENTATION_PLAN.md)

**For executive overview, see**: [`QUESTPAY_EXECUTIVE_SUMMARY.md`](./QUESTPAY_EXECUTIVE_SUMMARY.md)
