#!/usr/bin/env python3
"""
Fix cash entity fee calculation tests to use the correct rail/strategy per folder.
"""

import json
from pathlib import Path

# Define expected rail/strategy per folder
FOLDER_RAIL_STRATEGY = {
    "07 - Settlement Rail - INSTAPAY / Absorb": {"rail": "INSTAPAY", "strategy": "absorb"},
    "07 - Settlement Rail - INSTAPAY / Include": {"rail": "INSTAPAY", "strategy": "include"},
    "07 - Settlement Rail - PESONET / Absorb": {"rail": "PESONET", "strategy": "absorb"},
    "11 - Complex Scenario (₱572.50 total)": {"rail": "INSTAPAY", "strategy": "absorb"},
}

def fix_cash_entity_test(folder):
    """Fix the cash entity test to use correct rail/strategy."""
    
    folder_name = folder.get('name', '')
    expected = FOLDER_RAIL_STRATEGY.get(folder_name)
    
    if expected is None:
        return False
    
    for request in folder.get('item', []):
        if request['name'] == 'Get Voucher Details':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    script_lines = event['script']['exec']
                    new_lines = []
                    skip_until_closing = False
                    
                    for i, line in enumerate(script_lines):
                        if skip_until_closing:
                            if line.strip() == '});':
                                skip_until_closing = False
                            continue
                        
                        # Find and replace cash entity test
                        if ('pm.test("Cash entity exists with fee calculation"' in line or 
                            'pm.test("Cash entity with include strategy"' in line):
                            skip_until_closing = True
                            
                            # Generate appropriate test based on strategy
                            if expected["strategy"] == "include":
                                new_lines.extend([
                                    f'pm.test("Cash entity: {expected["rail"]} / {expected["strategy"]}", function () {{',
                                    '    pm.expect(voucher.cash).to.exist;',
                                    '    pm.expect(voucher.cash.meta.fee_calculation).to.exist;',
                                    f'    pm.expect(voucher.cash.meta.fee_calculation.strategy).to.equal("{expected["strategy"]}");',
                                    f'    pm.expect(voucher.cash.meta.fee_calculation.rail).to.equal("{expected["rail"]}");',
                                    '    ',
                                    '    // Include strategy: adjusted_amount is LESS than original_amount',
                                    '    pm.expect(voucher.cash.meta.fee_calculation.adjusted_amount).to.be.below(voucher.cash.meta.original_amount);',
                                    '    ',
                                    f'    console.log("💰 Fee Calculation: {expected["rail"]} / {expected["strategy"]}");',
                                    '    console.log("  Original: ₱" + voucher.cash.meta.original_amount);',
                                    '    console.log("  Adjusted: ₱" + voucher.cash.meta.fee_calculation.adjusted_amount);',
                                    '    console.log("  Fee: ₱" + (voucher.cash.meta.fee_calculation.fee_amount / 100));',
                                    '});'
                                ])
                            else:  # absorb
                                new_lines.extend([
                                    f'pm.test("Cash entity: {expected["rail"]} / {expected["strategy"]}", function () {{',
                                    '    pm.expect(voucher.cash).to.exist;',
                                    '    pm.expect(voucher.cash.meta.fee_calculation).to.exist;',
                                    f'    pm.expect(voucher.cash.meta.fee_calculation.strategy).to.equal("{expected["strategy"]}");',
                                    f'    pm.expect(voucher.cash.meta.fee_calculation.rail).to.equal("{expected["rail"]}");',
                                    '    ',
                                    f'    console.log("💰 Fee Calculation: {expected["rail"]} / {expected["strategy"]}");',
                                    '    console.log("  Original: ₱" + voucher.cash.meta.original_amount);',
                                    '    console.log("  Adjusted: ₱" + voucher.cash.meta.fee_calculation.adjusted_amount);',
                                    '    console.log("  Fee: ₱" + (voucher.cash.meta.fee_calculation.fee_amount / 100));',
                                    '    console.log("  Total Cost: ₱" + (voucher.cash.meta.fee_calculation.total_cost / 100));',
                                    '});'
                                ])
                            continue
                        
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
        if folder_name in FOLDER_RAIL_STRATEGY:
            if fix_cash_entity_test(folder):
                config = FOLDER_RAIL_STRATEGY[folder_name]
                print(f"  ✏️  {folder_name}: {config['rail']} / {config['strategy']}")
                folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folders")
    print(f"")
    print(f"Cash entity tests now check for the correct rail/strategy per folder")

if __name__ == '__main__':
    main()
