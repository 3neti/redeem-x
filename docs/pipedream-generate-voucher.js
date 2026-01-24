import axios from "axios";

/**
 * Pipedream SMS-to-Voucher Generation Workflow
 * 
 * This workflow receives SMS commands from Omni Channel (via shortcode 22560537)
 * and generates vouchers using the redeem-x API.
 * 
 * Environment Variables (configured in Pipedream):
 * - REDEEMX_API_URL: Base API URL (default: https://redeem-x.laravel.cloud/api/v1)
 * - REDEEMX_API_TOKEN: Sanctum bearer token from redeem-x
 */

export default defineComponent({
  async run({ steps, $ }) {
    // ===========================
    // Configuration (hardcoded for testing)
    // ===========================
    const REDEEMX_API_URL = process.env.REDEEMX_API_URL || "https://redeem-x.laravel.cloud/api/v1";
    const REDEEMX_API_TOKEN = process.env.REDEEMX_API_TOKEN || "3|TByBdAjcgIosogctuJ24NzzYzSNHcQ8KAIpO7XC6e60487de";
    
    // Hardcoded defaults
    const DEFAULT_COUNT = 1;
    
    // ===========================
    // Extract SMS data from Pipedream trigger
    // ===========================
    // Expected payload from Omni Channel:
    // { body: { sender: "639173011987", sms: "Generate ₱50" } }
    const sender = steps.trigger?.event?.body?.sender || "639173011987"; // Fallback for testing
    const smsText = steps.trigger?.event?.body?.sms || "Generate 50"; // Fallback for testing
    
    console.log("Processing SMS:", { sender, smsText });
    
    // ===========================
    // Parse SMS command
    // ===========================
    // Check if SMS starts with "Generate" (case insensitive)
    const generateRegex = /^generate\s+(\d+)/i;
    const match = smsText.match(generateRegex);
    
    if (!match) {
      console.log("SMS does not match Generate command pattern. Ignoring.");
      $.export("status", "ignored");
      $.export("message", null);
      return {
        status: "ignored",
        message: null,
        reason: "Not a Generate command",
      };
    }
    
    // Extract amount from SMS (first number after "Generate")
    const amount = parseInt(match[1], 10);
    
    // Validate amount
    if (isNaN(amount) || amount <= 0) {
      const errorMessage = "❌ Invalid amount. Please use format: Generate {amount} (e.g., Generate 100)";
      console.error("Invalid amount:", amount);
      $.export("status", "error");
      $.export("message", errorMessage);
      return {
        status: "error",
        message: errorMessage,
        error: "Invalid amount format",
      };
    }
    
    console.log("Parsed command:", { amount });
    
    // ===========================
    // Generate idempotency key
    // ===========================
    const timestamp = Math.floor(Date.now() / 1000);
    const idempotencyKey = `sms-${sender}-${timestamp}`;
    
    console.log("Generated idempotency key:", idempotencyKey);
    
    // ===========================
    // Prepare API request
    // ===========================
    const payload = {
      amount: amount,
      count: DEFAULT_COUNT,
      // Optional: Notify sender when voucher is redeemed
      feedback_mobile: `+${sender}`,
    };
    
    const headers = {
      "Authorization": `Bearer ${REDEEMX_API_TOKEN}`,
      "Content-Type": "application/json",
      "Accept": "application/json",
      "Idempotency-Key": idempotencyKey,
    };
    
    console.log("API Request:", {
      url: `${REDEEMX_API_URL}/vouchers`,
      payload,
      headers: { ...headers, Authorization: "Bearer ***" }, // Hide token in logs
    });
    
    // ===========================
    // Call redeem-x API
    // ===========================
    try {
      const response = await axios.post(
        `${REDEEMX_API_URL}/vouchers`,
        payload,
        { headers }
      );
      
      console.log("API Response Status:", response.status);
      console.log("API Response Data:", JSON.stringify(response.data, null, 2));
      
      // ===========================
      // Handle successful response
      // ===========================
      // API returns nested structure: { data: { vouchers: [...] }, meta: {...} }
      const responseData = response.data.data || response.data;
      
      if (response.status === 201 && responseData.vouchers && responseData.vouchers.length > 0) {
        const voucher = responseData.vouchers[0];
        const code = voucher.code;
        const amount = voucher.amount;
        const currency = voucher.currency || "PHP";
        const redemptionUrl = voucher.redemption_url;
        
        // Format SMS reply message
        const message = `✅ Voucher ${code} generated (₱${amount}). Redeem at: ${redemptionUrl}`;
        
        console.log("Success! Generated voucher:", { code, amount, currency });
        
        // Export for next Pipedream step (SMS sender)
        $.export("status", "success");
        $.export("message", message);
        $.export("voucher", voucher);
        $.export("response", responseData);
        
        return {
          status: "success",
          message,
          voucher,
          full_response: response.data,
        };
      } else {
        throw new Error("Unexpected response format from API");
      }
      
    } catch (error) {
      // ===========================
      // Handle errors
      // ===========================
      console.error("Error generating voucher:", error.message);
      
      let errorMessage = "⚠️ Failed to generate voucher. Please try again later.";
      let errorDetails = {
        error: error.message,
      };
      
      if (error.response) {
        const status = error.response.status;
        const data = error.response.data;
        
        console.error("API Error Response:", {
          status,
          data,
        });
        
        errorDetails = {
          status,
          data,
          error: error.message,
        };
        
        // Handle specific error cases
        switch (status) {
          case 400:
            errorMessage = "⚠️ System error (missing idempotency key). Please contact support.";
            console.error("Missing Idempotency-Key header");
            break;
            
          case 401:
            errorMessage = "⚠️ System error (authentication failed). Please contact support.";
            console.error("Invalid API token");
            break;
            
          case 403:
            errorMessage = "❌ Insufficient wallet balance. Please top up your account.";
            console.error("Insufficient balance");
            break;
            
          case 422:
            errorMessage = "❌ Invalid request. Please check the amount format.";
            console.error("Validation error:", data);
            break;
            
          case 429:
            errorMessage = "⚠️ Too many requests. Please wait a moment and try again.";
            console.error("Rate limit exceeded");
            break;
            
          default:
            errorMessage = `⚠️ System error (${status}). Please contact support.`;
        }
      }
      
      // Export error for next step
      $.export("status", "error");
      $.export("message", errorMessage);
      $.export("error", errorDetails);
      
      return {
        status: "error",
        message: errorMessage,
        error: errorDetails,
      };
    }
  },
});
