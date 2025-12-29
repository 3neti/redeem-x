#!/usr/bin/env python3
"""
Fix settlement rail and fee strategy tests in Get Voucher Details requests.
"""

import json
from pathlib import Path

# Define expected settlement rail per folder (most are null/absorb defaults)
FOLDER_SETTLEMENT = {
    "07 - Settlement Rail - INSTAPAY / Absorb": {"rail": "INSTAPAY", "strategy": "absorb"},
    "07 - Settlement Rail - INSTAPAY / Include": {"rail": "INSTAPAY", "strategy": "include"},
    "07 - Settlement Rail - PESONET / Absorb": {"rail": "PESONET", "strategy": "absorb"},
    "11 - Complex Scenario (₱572.50 total)": {"rail": "INSTAPAY", "strategy": "absorb"},
}

def fix_voucher_details_settlement_tests(folder):
    """Fix the settlement rail tests in Get Voucher Details request."""
    
    folder_name = folder.get('name', '')
    expected_settlement = FOLDER_SETTLEMENT.get(folder_name)
    
    # Only update folders that have specific settlement rail configs
    if expected_settlement is None:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Get Voucher Details':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    script_lines = event['script']['exec']
                    
                    new_lines = []
                    
                    for i, line in enumerate(script_lines):
                        # Replace "Settlement rail default" test
                        if 'pm.test("Settlement rail default"' in line:
                            new_lines.extend([
                                f'pm.test("Settlement rail: {expected_settlement["rail"]}", function () {{',
                                f'    pm.expect(voucher.instructions.cash.settlement_rail).to.equal("{expected_settlement["rail"]}");',
                                '});'
                            ])
                            # Skip the next 2 lines (the old test body and closing)
                            continue
                        elif i > 0 and 'pm.test("Settlement rail default"' in script_lines[i-1]:
                            continue
                        elif i > 1 and 'pm.test("Settlement rail default"' in script_lines[i-2]:
                            continue
                        
                        # Replace "Fee strategy is absorb" test
                        elif 'pm.test("Fee strategy is absorb"' in line:
                            new_lines.extend([
                                f'pm.test("Fee strategy is {expected_settlement["strategy"]}", function () {{',
                                f'    pm.expect(voucher.instructions.cash.fee_strategy).to.equal("{expected_settlement["strategy"]}");',
                                '});'
                            ])
                            # Skip the next 2 lines
                            continue
                        elif i > 0 and 'pm.test("Fee strategy is absorb"' in script_lines[i-1]:
                            continue
                        elif i > 1 and 'pm.test("Fee strategy is absorb"' in script_lines[i-2]:
                            continue
                        else:
                            new_lines.append(line)
                    
                    event['script']['exec'] = new_lines
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
        if folder_name in FOLDER_SETTLEMENT:
            if fix_voucher_details_settlement_tests(folder):
                settlement = FOLDER_SETTLEMENT[folder_name]
                print(f"  ✏️  {folder_name}: {settlement['rail']} / {settlement['strategy']}")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Settlement rail folders now test for their specific rail/strategy")

if __name__ == '__main__':
    main()
