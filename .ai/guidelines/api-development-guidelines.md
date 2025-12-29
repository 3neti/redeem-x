# API Development Guidelines for AI Assistants

This document provides guidelines for AI assistants working on API endpoints in this project.

## API Documentation Synchronization

**CRITICAL**: When creating or updating API endpoints, you MUST:

1. **Update Scramble/OpenAPI annotations** on controllers
   - Add `@tags` for grouping
   - Add `@summary` for endpoint description
   - Add `@response` for response examples
   - Document all parameters with `@param`

2. **Verify Scramble auto-documentation**
   - Check that endpoint appears in `/docs/api`
   - Ensure request/response schemas are correct
   - Validate that authentication requirements are documented

3. **Update BANK_INTEGRATION_GUIDE.md** (if endpoint is for bank integration)
   - Add endpoint to relevant section
   - Include request/response examples
   - Document error codes
   - Add rate limiting information

## Testing Requirements

**MANDATORY**: Every new or updated endpoint MUST include:

### 1. Pest PHP Tests

Create feature tests in `tests/Feature/` covering:

```php
// Example structure
it('returns 200 for valid request', function () {
    // Arrange
    $user = User::factory()->create();
    
    // Act
    $response = $this->actingAs($user)
        ->postJson('/api/v1/endpoint', ['data' => 'value']);
    
    // Assert
    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

it('returns 401 for unauthenticated request', function () {
    $response = $this->postJson('/api/v1/endpoint');
    $response->assertStatus(401);
});

it('returns 422 for invalid data', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)
        ->postJson('/api/v1/endpoint', ['invalid' => 'data']);
    
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['required_field']);
});
```

**Test Coverage Required**:
- ✅ Happy path (200/201 responses)
- ✅ Authentication (401)
- ✅ Authorization (403)
- ✅ Validation errors (422)
- ✅ Not found (404)
- ✅ Rate limiting (429)
- ✅ Idempotency (if applicable)

### 2. Postman Collection

**Suggest** creating/updating Postman collection:

1. **Collection structure**:
   ```
   Redeem-X API
   ├── Authentication
   ├── Vouchers
   │   ├── Generate Vouchers
   │   ├── List Vouchers
   │   └── Redeem Voucher
   └── Reports
       ├── Disbursements
       └── Failed Disbursements
   ```

2. **Request template**:
   - Set environment variables ({{baseUrl}}, {{apiToken}})
   - Include all required headers
   - Add example request body
   - Document expected response
   - Add tests (status code, response schema)

3. **Pre-request scripts** (if needed):
   ```javascript
   // Generate idempotency key
   pm.environment.set("idempotencyKey", pm.variables.replaceIn('{{$guid}}'));
   
   // Generate request signature
   const crypto = require('crypto-js');
   const timestamp = Math.floor(Date.now() / 1000);
   const signingString = `${pm.request.method}\n${pm.request.url.getPath()}\n${timestamp}\n${pm.request.body.raw}`;
   const signature = crypto.HmacSHA256(signingString, pm.environment.get("apiSecret")).toString();
   
   pm.request.headers.add({key: 'X-Timestamp', value: timestamp.toString()});
   pm.request.headers.add({key: 'X-Signature', value: signature});
   ```

4. **Tests tab**:
   ```javascript
   pm.test("Status code is 200", function () {
       pm.response.to.have.status(200);
   });
   
   pm.test("Response has correct structure", function () {
       const jsonData = pm.response.json();
       pm.expect(jsonData).to.have.property('data');
       pm.expect(jsonData.data).to.be.an('array');
   });
   ```

## When to Suggest Tests

**ALWAYS suggest** Pest tests and Postman collection when:
- Creating a new API endpoint
- Modifying existing endpoint behavior
- Adding new request parameters
- Changing response structure
- Adding authentication/authorization
- Implementing security features

**Example suggestion format**:

```markdown
I've created the endpoint. Here's what you should add:

**Pest Tests** (tests/Feature/Api/YourEndpointTest.php):
- [ ] Test successful request
- [ ] Test authentication required
- [ ] Test validation errors
- [ ] Test rate limiting

**Postman Collection**:
- [ ] Add request to "Endpoint Group" folder
- [ ] Set {{baseUrl}} and {{apiToken}} variables
- [ ] Add test for status code 200
- [ ] Document expected response format

Would you like me to create these tests now?
```

## API Endpoint Checklist

Before considering an endpoint "complete", verify:

- [ ] Controller action implemented
- [ ] Route registered in `routes/api.php`
- [ ] Request validation (FormRequest class)
- [ ] Authorization check (Gate/Policy)
- [ ] Idempotency support (if creating/modifying data)
- [ ] Rate limiting configured
- [ ] Scramble annotations added
- [ ] Response format consistent (use ApiResponse helper if available)
- [ ] Error handling (try-catch for external services)
- [ ] Pest tests written and passing
- [ ] Postman request created
- [ ] Documentation updated

## Security Considerations

When creating API endpoints:

1. **Authentication**: Always require authentication unless public endpoint
2. **Authorization**: Check user permissions before allowing action
3. **Validation**: Use FormRequest with strict validation rules
4. **Rate Limiting**: Apply appropriate rate limits (especially for expensive operations)
5. **Idempotency**: Support idempotency keys for non-idempotent operations
6. **Error Messages**: Don't leak sensitive information in error messages
7. **Logging**: Log security-relevant events (failed auth, validation errors)

## Response Format Standards

Use consistent JSON response format:

**Success Response**:
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "pagination": { ... }
  }
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "error_code",
  "message": "Human-readable message",
  "errors": {
    "field": ["Validation error"]
  }
}
```

## OpenAPI/Scramble Annotations

Example controller with proper annotations:

```php
/**
 * @tags Vouchers
 * @summary Generate new vouchers
 * @description Creates one or more vouchers with specified parameters
 * 
 * @param GenerateVouchersRequest $request
 * @return JsonResponse
 * 
 * @response 201 {
 *   "success": true,
 *   "data": {
 *     "vouchers": [
 *       {"code": "BANK-1234", "amount": 500.00}
 *     ]
 *   }
 * }
 * @response 422 {
 *   "error": "validation_failed",
 *   "errors": {"count": ["The count field is required"]}
 * }
 */
public function store(GenerateVouchersRequest $request): JsonResponse
{
    // Implementation
}
```

## References

- Pest PHP: https://pestphp.com/docs/
- Postman: https://learning.postman.com/docs/
- Scramble: https://scramble.dedoc.co/
- Laravel API Resources: https://laravel.com/docs/eloquent-resources
