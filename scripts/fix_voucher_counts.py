#!/usr/bin/env python3
"""
Fix voucher_count and voucher_amount in all Generate Voucher pre-request scripts.

Each folder should explicitly set its own values, not rely on collection defaults.
"""

import json
import re
from pathlib import Path

# Folder-specific configurations
FOLDER_CONFIGS = {
    "01 - Simplest Voucher (₱100)": {"amount": 100, "count": 1},
    "02 - Basic Settings - Bulk (₱1000 for 10 vouchers)": {"amount": 100, "count": 10},
    "03 - Input Fields - Email (₱100 + ₱2.20)": {"amount": 100, "count": 1},
    "03 - Input Fields - Mobile (₱100 + ₱2.30)": {"amount": 100, "count": 1},
    "03 - Input Fields - Name (₱100 + ₱2.40)": {"amount": 100, "count": 1},
    "03 - Input Fields - Location (₱100 + ₱3.00)": {"amount": 100, "count": 1},
    "03 - Input Fields - Signature (₱100 + ₱2.80)": {"amount": 100, "count": 1},
    "03 - Input Fields - Selfie (₱100 + ₱4.00)": {"amount": 100, "count": 1},
    "04 - Input Fields - Basic KYC (₱100 + ₱6.90)": {"amount": 100, "count": 1},
    "04 - Input Fields - Identity Verification (₱100 + ₱7.50)": {"amount": 100, "count": 1},
    "04 - Input Fields - Digital Signature (₱100 + ₱7.30)": {"amount": 100, "count": 1},
    "04 - Input Fields - Full Profile (₱100 + ₱12.20)": {"amount": 100, "count": 1},
    "05 - Feedback - Email (₱100 + ₱1.00)": {"amount": 100, "count": 1},
    "05 - Feedback - Mobile (₱100 + ₱1.80)": {"amount": 100, "count": 1},
    "05 - Feedback - Webhook (₱100 + ₱1.90)": {"amount": 100, "count": 1},
    "05 - Feedback - Email + Mobile + Webhook (₱100 + ₱4.70)": {"amount": 100, "count": 1},
    "06 - Cash Validation - Secret (₱100 + ₱1.20)": {"amount": 100, "count": 1},
    "06 - Cash Validation - Mobile (₱100 + ₱1.30)": {"amount": 100, "count": 1},
    "06 - Cash Validation - Both (₱100 + ₱2.50)": {"amount": 100, "count": 1},
    "07 - Settlement Rail - INSTAPAY / Absorb": {"amount": 100, "count": 1},
    "07 - Settlement Rail - INSTAPAY / Include": {"amount": 100, "count": 1},
    "07 - Settlement Rail - PESONET / Absorb": {"amount": 100, "count": 1},
    "08 - Rider - Message (₱100 + ₱2.00)": {"amount": 100, "count": 1},
    "08 - Rider - Url (₱100 + ₱2.10)": {"amount": 100, "count": 1},
    "08 - Rider - Full (₱100 + ₱4.10)": {"amount": 100, "count": 1},
    "09 - Validation - Location (₱100 + ₱3.00)": {"amount": 100, "count": 1},
    "09 - Validation - Time (₱100 + ₱2.50)": {"amount": 100, "count": 1},
    "11 - Complex Scenario (₱572.50 total)": {"amount": 572.50, "count": 1},
}

def fix_generate_voucher_prerequest(folder):
    """Update the Generate Voucher pre-request script with explicit values."""
    
    folder_name = folder.get('name', '')
    config = FOLDER_CONFIGS.get(folder_name)
    
    if not config:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Generate Voucher':
            for event in request.get('event', []):
                if event.get('listen') == 'prerequest':
                    # Replace with explicit values
                    event['script']['exec'] = [
                        '// Set voucher parameters explicitly for this folder',
                        f'pm.collectionVariables.set(\'voucher_amount\', {config["amount"]});',
                        f'pm.collectionVariables.set(\'voucher_count\', {config["count"]});',
                        f'console.log(\'🔧 Request params:\', {{ amount: {config["amount"]}, count: {config["count"]} }});'
                    ]
                    return True
    return False

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        folder_name = folder.get('name', '')
        if folder_name in FOLDER_CONFIGS:
            if fix_generate_voucher_prerequest(folder):
                config = FOLDER_CONFIGS[folder_name]
                print(f"  ✏️  {folder_name}: amount={config['amount']}, count={config['count']}")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Each folder now explicitly sets its own voucher_amount and voucher_count")

if __name__ == '__main__':
    main()
