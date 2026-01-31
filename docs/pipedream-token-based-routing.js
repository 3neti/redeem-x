import axios from "axios";

/**
 * Pipedream SMS Workflow v3.0 - Token-Based Routing
 * 
 * Simplified architecture: Pipedream is a stateless authentication proxy.
 * All business logic moved to Laravel SMS handlers.
 * 
 * @author Lester Hurtado
 * @version 3.0.0
 * @created 2026-01-31
 * 
 * Commands:
 * 1. AUTHENTICATE {token} - Store API token for sender's mobile number
 *    Example: "AUTHENTICATE 3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de"
 *    Response: "✅ API token saved successfully for {mobile}. You can now use SMS commands."
 * 
 * 2. PING - Health check (local, no Laravel)
 *    Response: "Pong! 🏓"
 * 
 * 3. All other commands - Forwarded to Laravel
 *    - With token → POST /sms (authenticated, Bearer token in header)
 *    - No token → POST /sms/public (unauthenticated)
 * 
 * Laravel Handles:
 * - GENERATE/REDEEMABLE {amount} [--flags] - Generate redeemable vouchers
 * - PAYABLE {amount} [--flags] - Generate payable vouchers
 * - SETTLEMENT {amount} {target} [--flags] - Generate settlement vouchers
 * - BALANCE [--system] - Check wallet/system balance
 * - {VOUCHER_CODE} [BANK_SPEC] - Redeem voucher (public or authenticated)
 * - HELP - Show available commands
 * 
 * Flags (Laravel parses using Symfony Console):
 * - --count=N - Number of vouchers
 * - --campaign="Name" - Use campaign template
 * - --rider-message="Text" - Post-redemption message
 * - --prefix=CODE - Voucher code prefix
 * - --mask=****-**** - Voucher code pattern
 * - --ttl=30 - Expiry in days
 * - --settlement-rail=INSTAPAY - Disbursement rail
 * 
 * Environment Variables:
 * - REDEEMX_SMS_URL: Laravel SMS endpoint (default: https://redeem-x.laravel.cloud/sms)
 * 
 * Data Store:
 * - Store name: "redeem-x"
 * - Key: Mobile number (E.164 format, e.g., "639173011987")
 * - Value: { token: string, created_at: ISO datetime, mobile: string }
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

const CONFIG = {
  REDEEMX_SMS_URL: process.env.REDEEMX_SMS_URL || "https://redeem-x.laravel.cloud/sms",
  MIN_TOKEN_LENGTH: 10,
};

const MESSAGES = {
  AUTHENTICATE: {
    SUCCESS: (mobile) => `✅ API token saved successfully for ${mobile}. You can now use SMS commands.`,
    INVALID_TOKEN: "❌ Invalid token format. Please provide a valid API token.",
    STORE_ERROR: "⚠️ Failed to save token. Please try again.",
  },
};

// ============================================================================
// COMMAND HANDLERS
// ============================================================================

/**
 * Handles AUTHENTICATE command - stores API token in Data Store
 */
async function handleAuthenticate(sender, smsText, store) {
  const match = smsText.match(/^authenticate\s+(.+)/i);
  
  if (!match) {
    return null;
  }
  
  const token = match[1].trim();
  
  console.log("[AUTHENTICATE] Command detected", { sender, tokenLength: token.length });
  
  if (!token || token.length < CONFIG.MIN_TOKEN_LENGTH) {
    console.error("[AUTHENTICATE] Invalid token format");
    return {
      status: "error",
      message: MESSAGES.AUTHENTICATE.INVALID_TOKEN,
    };
  }
  
  try {
    await store.set(sender, {
      token: token,
      created_at: new Date().toISOString(),
      mobile: sender,
    });
    
    console.log("[AUTHENTICATE] Token stored successfully", { sender });
    
    return {
      status: "success",
      message: MESSAGES.AUTHENTICATE.SUCCESS(sender),
    };
  } catch (error) {
    console.error("[AUTHENTICATE] Failed to store token", error);
    return {
      status: "error",
      message: MESSAGES.AUTHENTICATE.STORE_ERROR,
      error: error.message,
    };
  }
}

// ============================================================================
// MAIN WORKFLOW
// ============================================================================

export default defineComponent({
  props: {
    redeemxStore: {
      type: "data_store"
    }
  },
  async run({ steps, $ }) {
    const sender = steps.trigger?.event?.body?.sender || "639173011987";
    const smsText = steps.trigger?.event?.body?.sms || "";
    
    console.log("[WORKFLOW] Processing SMS", { sender, smsText });
    
    // Handle AUTHENTICATE locally (no Laravel needed)
    if (smsText.match(/^authenticate\s+/i)) {
      const result = await handleAuthenticate(sender, smsText, this.redeemxStore);
      $.export("status", result.status);
      $.export("message", result.message);
      if (result.error) $.export("error", result.error);
      return result;
    }
    
    // Handle PING locally (health check)
    if (smsText.match(/^ping$/i)) {
      const result = { status: "success", message: "Pong! 🏓" };
      $.export("status", result.status);
      $.export("message", result.message);
      return result;
    }
    
    // Check if mobile has a token
    const tokenData = await this.redeemxStore.get(sender);
    
    let endpoint, headers;
    
    if (!tokenData || !tokenData.token) {
      // No token → Route to public endpoint (unauthenticated)
      console.log("[WORKFLOW] No token found, routing to public endpoint");
      endpoint = `${CONFIG.REDEEMX_SMS_URL}/public`;
      headers = {
        "Content-Type": "application/json",
        "Accept": "application/json",
      };
    } else {
      // Has token → Route to authenticated endpoint
      console.log("[WORKFLOW] Token found, routing to authenticated endpoint");
      endpoint = CONFIG.REDEEMX_SMS_URL;
      headers = {
        "Authorization": `Bearer ${tokenData.token}`,
        "Content-Type": "application/json",
        "Accept": "application/json",
      };
    }
    
    // Forward to Laravel
    try {
      const payload = {
        from: sender,
        to: "2929",
        message: smsText,
      };
      
      console.log("[WORKFLOW] Forwarding to Laravel", { endpoint, payload });
      
      const response = await axios.post(endpoint, payload, { headers });
      
      console.log("[WORKFLOW] Response from Laravel", { status: response.status });
      
      const result = {
        status: "success",
        message: response.data.message || "Command processed",
        data: response.data,
      };
      
      $.export("status", result.status);
      $.export("message", result.message);
      $.export("data", result.data);
      
      return result;
    } catch (error) {
      console.error("[WORKFLOW] Laravel request failed", error);
      
      let errorMessage = "⚠️ Command failed. Please try again.";
      
      if (error.response) {
        const status = error.response.status;
        const data = error.response.data;
        
        console.error("[WORKFLOW] Error response", { status, data });
        
        // Use Laravel's error message if available
        if (data && data.message) {
          errorMessage = data.message;
        } else if (status === 401) {
          errorMessage = "⚠️ Authentication expired. Please AUTHENTICATE again.";
        } else if (status === 403) {
          errorMessage = "⚠️ Insufficient permissions or balance.";
        } else if (status === 422) {
          errorMessage = "⚠️ Invalid command format.";
        }
      }
      
      const result = {
        status: "error",
        message: errorMessage,
        error: error.message,
      };
      
      $.export("status", result.status);
      $.export("message", result.message);
      $.export("error", result.error);
      
      return result;
    }
  },
});
