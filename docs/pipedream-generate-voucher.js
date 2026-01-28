import axios from "axios";

/**
 * Pipedream SMS-to-Voucher Generation Workflow
 * 
 * This workflow receives SMS commands from Omni Channel (via shortcode 22560537)
 * and handles both authentication and voucher generation.
 * 
 * @author Lester Hurtado
 * @version 2.1.0
 * @created 2026-01-24
 * @updated 2026-01-24 - Added REDEEM command
 * 
 * Commands:
 * 1. AUTHENTICATE {token} - Store API token for sender's mobile number
 *    Example: "AUTHENTICATE 3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de"
 *    Response: "✅ API token saved successfully for {mobile}. You can now use GENERATE commands."
 * 
 * 2. GENERATE {amount} - Generate voucher using stored token
 *    Example: "Generate 100" (case insensitive)
 *    Response: "✅ Voucher ABC-1234 generated (₱100.00). Redeem at: ..."
 * 
 * 3. REDEEM {code} [bank_spec] - Redeem simple voucher (no authentication)
 *    Examples: 
 *      "ABCD" - Use default/GCash bank account
 *      "ABCD MAYA" - Send to MAYA:{sender_mobile}
 *      "ABCD GCASH:09181111111" - Send to GCASH:09181111111
 *    Response: "✅ Voucher ABCD redeemed (₱100.00). Funds sent to GCASH:639173011987."
 * 
 * 4. BALANCE - Check wallet balance (handled by internal route)
 *    Example: "BALANCE"
 *    Response: "Balance: ₱979,122.40"
 * 
 * 5. BALANCE --system - Check system balance (admin only, handled by internal route)
 *    Example: "BALANCE --system"
 *    Response: "Wallet: ₱979,122.40 | Products: ₱16,723.40\nBank: ₱44.42 (as of 10:39 PM)"
 *    Requires: 'view-balances' permission
 * 
 * Environment Variables (configured in Pipedream):
 * - REDEEMX_API_URL: Base API URL (default: https://redeem-x.laravel.cloud/api/v1)
 * 
 * Data Store Structure:
 * - Store name: "redeem-x" (configured in Pipedream props)
 * - Key: Mobile number (e.g., "639173011987")
 * - Value: { token: string, created_at: ISO datetime, mobile: string }
 * 
 * Architecture:
 * This is a single unified workflow that routes commands to specialized handlers:
 * - AuthenticateHandler: Stores API tokens in Data Store
 * - GenerateHandler: Retrieves tokens and calls redeem-x API
 * - RedeemHandler: Processes simple voucher redemption via API
 * - BALANCE commands: Forwarded to internal Laravel routes (not handled here)
 * - SMS text that doesn't match any command pattern is ignored (no reply)
 * 
 * Note: BALANCE and REGISTER commands are handled by the internal Laravel SMS
 * route (/sms) via the omnichannel package, not by this Pipedream workflow.
 */

// ============================================================================
// CONFIGURATION
// ============================================================================

/**
 * Application configuration constants
 */
const CONFIG = {
  REDEEMX_API_URL: process.env.REDEEMX_API_URL || "https://redeem-x.laravel.cloud/api/v1",
  DEFAULT_COUNT: 1,
  MIN_TOKEN_LENGTH: 10,
};

/**
 * Command patterns for SMS parsing
 */
const COMMAND_PATTERNS = {
  AUTHENTICATE: /^authenticate\s+(.+)/i,
  GENERATE: /^generate\s+(\d+)/i,
  REDEEM: /^([A-Z0-9\-]{4,20})(?:\s+([A-Z]+(?::[0-9]+)?))?$/i,
  // Group 1: Voucher code (4-20 chars, alphanumeric + hyphens)
  // Group 2: Optional bank specification (EMI code or EMI:mobile)
};

/**
 * User-facing response messages
 */
const MESSAGES = {
  AUTHENTICATE: {
    SUCCESS: (mobile) => `✅ API token saved successfully for ${mobile}. You can now use GENERATE commands.`,
    INVALID_TOKEN: "❌ Invalid token format. Please provide a valid API token.",
    STORE_ERROR: "⚠️ Failed to save token. Please try again.",
  },
  GENERATE: {
    SUCCESS: (code, amount, url) => `✅ Voucher ${code} generated (₱${amount}). Redeem at: ${url}`,
    NO_TOKEN: "❌ No API token found for your number. Please send AUTHENTICATE command first.",
    INVALID_AMOUNT: "❌ Invalid amount. Please use format: Generate {amount} (e.g., Generate 100)",
    TOKEN_RETRIEVE_ERROR: "⚠️ Failed to retrieve token. Please try again or re-authenticate.",
    INSUFFICIENT_BALANCE: "❌ Insufficient wallet balance. Please top up your account.",
    VALIDATION_ERROR: "❌ Invalid request. Please check the amount format.",
    RATE_LIMIT: "⚠️ Too many requests. Please wait a moment and try again.",
    SYSTEM_ERROR: (status) => `⚠️ System error (${status}). Please contact support.`,
    GENERIC_ERROR: "⚠️ Failed to generate voucher. Please try again later.",
  },
  REDEEM: {
    SUCCESS: (message) => message,
    INVALID_CODE: "❌ Invalid voucher code. Please check and try again.",
    ALREADY_REDEEMED: "❌ This voucher has already been redeemed.",
    EXPIRED: "❌ This voucher has expired.",
    HAS_REQUIREMENTS: (url) => `❌ This voucher requires additional information. Please redeem via web: ${url}`,
    REDEMPTION_ERROR: "⚠️ Failed to redeem voucher. Please try again or contact support.",
  },
};

// ============================================================================
// COMMAND HANDLERS
// ============================================================================

/**
 * Handles AUTHENTICATE command - stores API token in Data Store
 * 
 * @param {string} sender - Mobile number of SMS sender
 * @param {string} smsText - Full SMS text
 * @param {object} store - Pipedream Data Store instance
 * @param {object} $ - Pipedream export helper
 * @returns {object} Result object with status, message, and action
 */
async function handleAuthenticate(sender, smsText, store, $) {
  const match = smsText.match(COMMAND_PATTERNS.AUTHENTICATE);
  
  if (!match) {
    return null; // Not an authenticate command
  }
  
  const token = match[1].trim();
  
  console.log("[AUTHENTICATE] Command detected", { sender, tokenLength: token.length });
  
  // Validate token format
  if (!token || token.length < CONFIG.MIN_TOKEN_LENGTH) {
    console.error("[AUTHENTICATE] Invalid token format");
    return {
      status: "error",
      message: MESSAGES.AUTHENTICATE.INVALID_TOKEN,
      error: "Invalid token format",
    };
  }
  
  // Store token in Data Store
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
      action: "authenticate",
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

/**
 * Handles GENERATE command - retrieves token and generates voucher
 * 
 * @param {string} sender - Mobile number of SMS sender
 * @param {string} smsText - Full SMS text
 * @param {object} store - Pipedream Data Store instance
 * @param {object} $ - Pipedream export helper
 * @returns {object} Result object with status, message, and voucher data
 */
async function handleGenerate(sender, smsText, store, $) {
  const match = smsText.match(COMMAND_PATTERNS.GENERATE);
  
  if (!match) {
    return null; // Not a generate command
  }
  
  const amount = parseInt(match[1], 10);
  
  console.log("[GENERATE] Command detected", { sender, amount });
  
  // Validate amount
  if (isNaN(amount) || amount <= 0) {
    console.error("[GENERATE] Invalid amount:", amount);
    return {
      status: "error",
      message: MESSAGES.GENERATE.INVALID_AMOUNT,
      error: "Invalid amount format",
    };
  }
  
  // Retrieve token from Data Store
  let tokenData;
  try {
    tokenData = await store.get(sender);
    
    if (!tokenData || !tokenData.token) {
      console.error("[GENERATE] No token found for sender", { sender });
      return {
        status: "error",
        message: MESSAGES.GENERATE.NO_TOKEN,
        error: "Token not found",
      };
    }
    
    console.log("[GENERATE] Token retrieved", { sender, tokenCreatedAt: tokenData.created_at });
  } catch (error) {
    console.error("[GENERATE] Failed to retrieve token", error);
    return {
      status: "error",
      message: MESSAGES.GENERATE.TOKEN_RETRIEVE_ERROR,
      error: error.message,
    };
  }
  
  // Generate voucher via API
  try {
    const result = await callVoucherAPI(sender, amount, tokenData.token);
    console.log("[GENERATE] Voucher created successfully", { code: result.voucher.code });
    return result;
  } catch (error) {
    console.error("[GENERATE] API call failed", error);
    return error; // Error already formatted by callVoucherAPI
  }
}

/**
 * Handles REDEEM command - redeems simple voucher via SMS
 * 
 * @param {string} sender - Mobile number of SMS sender
 * @param {string} smsText - Full SMS text
 * @param {object} store - Pipedream Data Store instance (unused)
 * @param {object} $ - Pipedream export helper
 * @returns {object} Result object with redemption status
 */
async function handleRedeem(sender, smsText, store, $) {
  const match = smsText.match(COMMAND_PATTERNS.REDEEM);
  
  if (!match) {
    return null; // Not a redeem command
  }
  
  const voucherCode = match[1].toUpperCase();
  const bankSpec = match[2] ? match[2].toUpperCase() : null;
  
  console.log("[REDEEM] Command detected", { sender, voucherCode, bankSpec });
  
  // Call SMS redemption endpoint
  try {
    const payload = {
      voucher_code: voucherCode,
      mobile: sender,
      bank_spec: bankSpec,
    };
    
    console.log("[REDEEM] Request", {
      url: `${CONFIG.REDEEMX_API_URL}/redeem/sms`,
      payload,
    });
    
    const response = await axios.post(
      `${CONFIG.REDEEMX_API_URL}/redeem/sms`,
      payload,
      { headers: { "Content-Type": "application/json" } }
    );
    
    console.log("[REDEEM] Response", { status: response.status });
    
    const data = response.data;
    
    if (data.success) {
      console.log("[REDEEM] Redemption successful", { code: voucherCode });
      return {
        status: "success",
        message: data.message,
        voucher: data.data.voucher,
        bank_account: data.data.bank_account,
      };
    } else {
      // Should not reach here for successful responses
      throw new Error("Unexpected response format");
    }
  } catch (error) {
    console.error("[REDEEM] Failed", error);
    
    if (error.response) {
      const status = error.response.status;
      const data = error.response.data;
      
      console.error("[REDEEM] Error response", { status, data });
      
      // Handle specific error types
      if (data.error === "requires_web") {
        return {
          status: "error",
          message: MESSAGES.REDEEM.HAS_REQUIREMENTS(data.redemption_url),
          error: data.error,
        };
      }
      
      // Map status codes to messages
      let message = MESSAGES.REDEEM.REDEMPTION_ERROR;
      switch (status) {
        case 404:
          message = MESSAGES.REDEEM.INVALID_CODE;
          break;
        case 422:
          if (data.message?.includes("already been redeemed")) {
            message = MESSAGES.REDEEM.ALREADY_REDEEMED;
          } else if (data.message?.includes("expired")) {
            message = MESSAGES.REDEEM.EXPIRED;
          } else if (data.message) {
            message = data.message;
          }
          break;
      }
      
      return {
        status: "error",
        message,
        error: { status, data },
      };
    }
    
    return {
      status: "error",
      message: MESSAGES.REDEEM.REDEMPTION_ERROR,
      error: error.message,
    };
  }
}

/**
 * Calls the redeem-x voucher generation API
 * 
 * @param {string} sender - Mobile number of SMS sender
 * @param {number} amount - Voucher amount in PHP
 * @param {string} token - API bearer token
 * @returns {object} Result object with voucher data
 * @throws {object} Formatted error object
 */
async function callVoucherAPI(sender, amount, token) {
  const timestamp = Math.floor(Date.now() / 1000);
  const idempotencyKey = `sms-${sender}-${timestamp}`;
  
  const payload = {
    amount: amount,
    count: CONFIG.DEFAULT_COUNT,
    feedback_mobile: `+${sender}`,
  };
  
  const headers = {
    "Authorization": `Bearer ${token}`,
    "Content-Type": "application/json",
    "Accept": "application/json",
    "Idempotency-Key": idempotencyKey,
  };
  
  console.log("[API] Request", {
    url: `${CONFIG.REDEEMX_API_URL}/vouchers`,
    payload,
    idempotencyKey,
  });
  
  try {
    const response = await axios.post(
      `${CONFIG.REDEEMX_API_URL}/vouchers`,
      payload,
      { headers }
    );
    
    console.log("[API] Response", { status: response.status });
    
    const responseData = response.data.data || response.data;
    
    if (response.status === 201 && responseData.vouchers && responseData.vouchers.length > 0) {
      const voucher = responseData.vouchers[0];
      
      return {
        status: "success",
        message: MESSAGES.GENERATE.SUCCESS(voucher.code, voucher.amount, voucher.redemption_url),
        voucher,
        full_response: response.data,
      };
    } else {
      throw new Error("Unexpected response format from API");
    }
  } catch (error) {
    return formatAPIError(error);
  }
}

/**
 * Formats API errors into user-friendly messages
 * 
 * @param {Error} error - Axios error object
 * @returns {object} Formatted error object
 */
function formatAPIError(error) {
  let errorMessage = MESSAGES.GENERATE.GENERIC_ERROR;
  let errorDetails = { error: error.message };
  
  if (error.response) {
    const status = error.response.status;
    const data = error.response.data;
    
    console.error("[API] Error response", { status, data });
    
    errorDetails = { status, data, error: error.message };
    
    // Map HTTP status codes to user-friendly messages
    switch (status) {
      case 400:
        errorMessage = "⚠️ System error (missing idempotency key). Please contact support.";
        break;
      case 401:
        errorMessage = "⚠️ System error (authentication failed). Please contact support.";
        break;
      case 403:
        errorMessage = MESSAGES.GENERATE.INSUFFICIENT_BALANCE;
        break;
      case 422:
        errorMessage = MESSAGES.GENERATE.VALIDATION_ERROR;
        break;
      case 429:
        errorMessage = MESSAGES.GENERATE.RATE_LIMIT;
        break;
      default:
        errorMessage = MESSAGES.GENERATE.SYSTEM_ERROR(status);
    }
  }
  
  return {
    status: "error",
    message: errorMessage,
    error: errorDetails,
  };
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
    // Extract SMS data from Pipedream trigger
    // Expected payload: { body: { sender: "639173011987", sms: "Generate 100" } }
    const sender = steps.trigger?.event?.body?.sender || "639173011987";
    const smsText = steps.trigger?.event?.body?.sms || "Generate 50";
    
    console.log("[WORKFLOW] Processing SMS", { sender, smsText });
    
    // Route to appropriate command handler
    let result;
    
    // Try AUTHENTICATE handler
    result = await handleAuthenticate(sender, smsText, this.redeemxStore, $);
    if (result) {
      $.export("status", result.status);
      $.export("message", result.message);
      if (result.action) $.export("action", result.action);
      if (result.error) $.export("error", result.error);
      return result;
    }
    
    // Try GENERATE handler
    result = await handleGenerate(sender, smsText, this.redeemxStore, $);
    if (result) {
      $.export("status", result.status);
      $.export("message", result.message);
      if (result.voucher) $.export("voucher", result.voucher);
      if (result.error) $.export("error", result.error);
      return result;
    }
    
    // Try REDEEM handler
    result = await handleRedeem(sender, smsText, this.redeemxStore, $);
    if (result) {
      $.export("status", result.status);
      $.export("message", result.message);
      if (result.voucher) $.export("voucher", result.voucher);
      if (result.bank_account) $.export("bank_account", result.bank_account);
      if (result.error) $.export("error", result.error);
      return result;
    }
    
    // No matching command - ignore SMS
    console.log("[WORKFLOW] SMS does not match any command pattern. Ignoring.");
    $.export("status", "ignored");
    $.export("message", null);
    
    return {
      status: "ignored",
      message: null,
      reason: "Unknown command",
    };
  },
});
