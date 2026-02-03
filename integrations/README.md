# External Service Integrations

This directory contains code and configurations for integrating redeem-x with external services.

## Directory Structure

```
integrations/
└── pipedream/        # Pipedream SMS gateway workflows
```

## Pipedream

SMS gateway integration using Pipedream as an authentication proxy and workflow orchestrator.

**Location:** `pipedream/`

**Documentation:** See `pipedream/README.md` for detailed architecture, deployment guide, and version history.

**Quick Overview:**
- Receives SMS from Omni Channel (shortcode 22560537)
- Handles AUTHENTICATE command and token storage
- Routes authenticated requests to Laravel `/sms` endpoints
- Two workflow versions: v2.1 (full handling) and v3.0 (simplified proxy)

## Future Integrations

Planned integrations:
- Payment gateway webhooks (NetBank, GCash, PayMaya)
- Email service providers (beyond Laravel mail)
- Third-party KYC providers (HyperVerge webhook handlers)

## Adding New Integrations

When adding a new external service integration:

1. Create subdirectory: `integrations/service-name/`
2. Add README.md documenting:
   - Architecture overview
   - Authentication/credentials
   - Deployment instructions
   - Version history
3. Include code/config files specific to the integration
4. Update this README with a new section
5. Add reference in `docs/architecture/` if significant

## Related Documentation

- **Architecture:** `docs/architecture/`
- **API Documentation:** `docs/api/`
- **Troubleshooting:** `docs/troubleshooting/`
