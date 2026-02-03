# QuestPay™ Executive Summary

## What is QuestPay™?

**QuestPay™** is a redemption-driven reality race where players complete real-world challenges, redeem voucher codes to verify progress, and compete for clues, codes, and cash prizes.

**Tagline**: *"Redeem. Race. Repeat."*

**Core Principle**: Players quest → System verifies → Game pays

## Why Build This on redeem-x?

The redeem-x platform (powered by x-Change voucher technology) is **uniquely positioned** to support QuestPay because:

1. **80% of the core functionality already exists**:
   - Voucher generation and redemption engine ✅
   - Rich redemption flows (splash pages, landing pages, input collection) ✅
   - Multi-channel delivery (SMS, email, webhooks) ✅
   - Payment gateway integration (NetBank/Omnipay) ✅
   - Photo/location/signature collection ✅

2. **QuestPay is the perfect showcase for x-Change capabilities**:
   - Demonstrates voucher technology at scale
   - Normalizes redemption-based behavior
   - Creates high-frequency voucher usage
   - Provides measurable ROI for partners
   - Positions x-Change as **gaming-capable infrastructure**

3. **Strategic business opportunity**:
   - New revenue stream (voucher generation fees, sponsor activations)
   - Market education for voucher adoption
   - B2B applications (corporate team building, brand activations)
   - Franchise licensing potential

## What Needs to Be Built?

### The 20% Gap: Game Management Layer

While voucher infrastructure exists, we need to add **game-specific features**:

#### Core Components (NEW)
1. **Game/Episode Management** - Season/episode configuration, status tracking
2. **Challenge System** - Extends existing Campaign model with gaming elements
3. **Contestant Management** - Registration, selection, profiles, teams
4. **Real-Time Leaderboard** - Live scoring, rank updates via WebSockets
5. **Verification Flow** - Auto/manual challenge verification pipeline
6. **Sponsor Activations** - Dynamic landing pages with interactive elements
7. **Production Dashboard** - Admin control panel with live monitoring
8. **Contestant PWA** - Mobile app for players
9. **Public Viewer Experience** - Watch page for audience engagement
10. **Analytics & Reporting** - Sponsor ROI metrics, performance reports

#### Technical Infrastructure
- **Laravel Reverb** (WebSocket server) for real-time updates
- **Redis caching** for leaderboard performance
- **Google Maps API** for location validation
- **Event-driven architecture** for game logic

## Implementation Timeline

**Total Duration**: 26 weeks (6 months)

### Phase Breakdown
- **Phase 0**: Database schema (2 weeks)
- **Phase 1**: Game management UI (3 weeks)
- **Phase 2**: Contestant experience (3 weeks)
- **Phase 3**: Challenge redemption integration (3 weeks)
- **Phase 4**: Real-time leaderboard (3 weeks)
- **Phase 5**: Sponsor activations (3 weeks)
- **Phase 6**: Production dashboard (3 weeks)
- **Phase 7**: Public viewer experience (2 weeks)
- **Phase 8**: Testing & polish (2 weeks)
- **Phase 9**: Pilot episode launch (2 weeks)

## Investment Required

**Development**: $150K-$250K
- 2-3 full-stack developers × 6 months
- DevOps/infrastructure setup
- Third-party services (WebSocket hosting, Maps API)

**Infrastructure**: $10K-$30K annually
- Cloud hosting (AWS/GCP)
- Redis, S3, CDN
- Monitoring tools

**Pilot Episode**: $50K-$100K
- Production costs (not included in platform development)
- Prize pool
- Marketing

## Revenue Potential

### Direct Revenue
- **Voucher generation fees**: ₱5-₱50 per voucher (depending on richness)
- **Sponsor activations**: ₱50K-₱200K per challenge
- **Corporate packages**: ₱100K-₱1M per team-building event
- **Franchise licensing**: $200K platform license + $50K annual maintenance

### Indirect Benefits
- **Market education** for x-Change voucher adoption
- **B2B pipeline** for voucher-based solutions
- **Brand positioning** as gaming infrastructure provider
- **Data insights** on redemption behavior

## Success Metrics

### Technical KPIs
- Redemption processing: < 3 seconds
- WebSocket latency: < 500ms
- System uptime: 99.9% during live episodes
- Support 50+ concurrent contestants (target: 100+)

### Business KPIs
- Contestant completion rate: > 80%
- Sponsor engagement: > 60%
- Viewer retention: > 70%
- Social impressions: 10K+ per episode
- Cost per contestant: < ₱500

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Real-time scaling issues | Load testing, Redis caching, CDN |
| GPS accuracy problems | Radius-based validation, manual override |
| Webhook delivery failures | Queue retry logic, fallback polling |
| Contestant cheating | Multi-factor verification, manual review |
| Performance during live episode | Pre-launch load testing, redundancy |

## Strategic Context: The Redemption Switch

QuestPay is a key driver of **digital wallet transformation** from destination wallets to the **origin of digital value**.

### For Wallet Partners (Maya, Vybe, iCash, BDO Pay, etc.)
- Creates **redemption velocity** through competitive gameplay
- Demonstrates **voucher portability** (redeem anywhere)
- Shifts perception: "To *give* money, use [Partner]"
- **Non-regulated revenue** from voucher activity vs. float/MDR

### Market Education
- High-frequency redemption events normalize voucher usage
- Public witnesses frictionless value transfer
- Contestants become voucher power users and advocates
- Builds consumer confidence in voucher-based transactions

## Example User Journey (Simplified)

**Contestant Flow**:
1. Sees Facebook ad → registers → selected by admin
2. Receives first voucher code via SMS
3. Clicks link → splash page → redemption wizard (upload selfie + location)
4. Verified → landing page shows sponsor ad + trivia question
5. Answers correctly → next voucher code revealed
6. Navigates to next location → repeats process
7. Completes 5-8 challenges → reaches finale
8. Winner receives ₱50,000 prize via NetBank disbursement

**Key Innovation**: Every redemption triggers:
- Automatic verification via GPS, photos, timestamps
- Real-time scoring and leaderboard update
- Sponsor impression tracking
- Next voucher generation
- Live broadcast data feed

## Why Now?

### Market Readiness
- 70M+ mobile wallet users in Philippines
- High social media engagement
- Proven reality TV viewership
- Growing fintech ecosystem

### Technical Readiness
- redeem-x platform mature and stable
- Laravel Reverb (WebSocket) officially released
- Payment gateway integration proven
- Top-up system already working

### Competitive Advantage
- **First-mover** in redemption-driven reality competition
- **Proven infrastructure** (x-Change in production)
- **Franchise-ready** format
- **Multi-revenue streams** beyond media rights

## Next Steps

1. ✅ **Review this plan** - Stakeholder approval
2. **Design UI/UX mockups** - Game management and contestant interfaces
3. **Phase 0 kickoff** - Database schema implementation
4. **Set up development environment** - Laravel Reverb, services configuration
5. **Allocate development team** - 2-3 developers for 6 months
6. **Target pilot date** - 6 months from start

## Conclusion

QuestPay™ represents a **strategic opportunity** to:
- **Showcase x-Change capabilities** at scale
- **Create new revenue streams** for redeem-x
- **Position 3neti R&D** as gaming infrastructure provider
- **Drive voucher adoption** through market education
- **Build franchise-ready IP** with global potential

The platform development leverages **80% existing infrastructure**, requiring only a **20% game management layer** to enable this transformative application.

**Investment**: $150K-$250K development + infrastructure  
**Timeline**: 26 weeks to pilot episode  
**ROI Potential**: High (direct revenue + market positioning + franchise licensing)

---

**For detailed technical specifications, see**: [`QUESTPAY_IMPLEMENTATION_PLAN.md`](./QUESTPAY_IMPLEMENTATION_PLAN.md)
