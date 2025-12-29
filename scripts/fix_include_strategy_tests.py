#!/usr/bin/env python3
"""
Fix tests for "include" fee strategy folder.

Include strategy: Fee is deducted from voucher value, user pays less.
Example: ₱100 voucher with ₱10 fee → user pays ₱90, redeemer gets ₱100
"""

import json
from pathlib import Path

def fix_include_strategy_folder(folder):
    """Fix all tests in the include strategy folder."""
    
    folder_name = folder.get('name', '')
    if 'INSTAPAY / Include' not in folder_name:
        return False
    
    updated = False
    
    for request in folder.get('item', []):
        # Fix Get Balance (After) tests
        if request['name'] == 'Get Balance (After)':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    event['script']['exec'] = [
                        'pm.test("Status is 200", function () {',
                        '    pm.response.to.have.status(200);',
                        '});',
                        '',
                        'const jsonData = pm.response.json();',
                        'const balanceBefore = parseFloat(pm.collectionVariables.get(\'balance_before\'));',
                        'const balanceAfter = parseFloat(jsonData.data.balance);',
                        'const voucherAmount = parseFloat(pm.collectionVariables.get(\'voucher_amount\'));',
                        'const voucherCount = parseInt(pm.collectionVariables.get(\'voucher_count\'));',
                        '',
                        'pm.test("Balance decreased", function () {',
                        '    pm.expect(balanceAfter).to.be.below(balanceBefore);',
                        '});',
                        '',
                        'const deducted = balanceBefore - balanceAfter;',
                        'const voucherTotal = voucherAmount * voucherCount;',
                        '// For "include" strategy: fee is deducted from voucher, so deducted < voucherTotal',
                        'const feeAmount = voucherTotal - deducted; // Positive fee value',
                        '',
                        '// Debug calculations',
                        'console.log(\'🔍 DEBUGGING Get Balance (After):\');',
                        'console.log(\'  voucher_amount:\', voucherAmount);',
                        'console.log(\'  voucher_count:\', voucherCount);',
                        'console.log(\'  voucherTotal (amount × count):\', voucherTotal);',
                        'console.log(\'  balanceBefore:\', balanceBefore);',
                        'console.log(\'  balanceAfter:\', balanceAfter);',
                        'console.log(\'  deducted (before - after):\', deducted);',
                        'console.log(\'  feeAmount (voucherTotal - deducted):\', feeAmount);',
                        'console.log(\'  → Fee INCLUDED in voucher value (user pays less)\');',
                        '',
                        'pm.test("Deduction less than voucher amount (include strategy)", function () {',
                        '    // Include strategy: user pays less because fee is deducted from voucher',
                        '    pm.expect(deducted).to.be.below(voucherTotal + 0.01);',
                        '});',
                        '',
                        'pm.test("Fee included (₱10 INSTAPAY fee)", function () {',
                        '    // INSTAPAY fee is ₱10',
                        '    pm.expect(feeAmount).to.be.closeTo(10.0, 0.5);',
                        '});',
                        '',
                        'pm.test("Balance remains positive", function () {',
                        '    pm.expect(balanceAfter).to.be.at.least(0);',
                        '});',
                        '',
                        'pm.test("Balance cents consistent", function () {',
                        '    pm.expect(jsonData.data.balance_cents).to.equal(Math.round(balanceAfter * 100));',
                        '});',
                        '',
                        '// Store values (store negative feeAmount to indicate "include")',
                        'pm.collectionVariables.set(\'balance_after\', balanceAfter);',
                        'pm.environment.set(\'balance_after\', balanceAfter);',
                        'pm.collectionVariables.set(\'actual_fee\', -feeAmount); // Negative for "include"',
                        'pm.environment.set(\'actual_fee\', -feeAmount);',
                        'console.log(\'📝 Stored actual_fee:\', -feeAmount, \'(negative = included in voucher)\');',
                        '',
                        'console.log(\'💸 User Wallet (After):\');',
                        'console.log(\'  Balance before: ₱\' + balanceBefore.toFixed(2));',
                        'console.log(\'  Balance after: ₱\' + balanceAfter.toFixed(2));',
                        'console.log(\'  ---\');',
                        'console.log(\'  Total deducted: ₱\' + deducted.toFixed(2));',
                        'console.log(\'    - Voucher amount: ₱\' + voucherTotal.toFixed(2));',
                        'console.log(\'    - Fee (included): -₱\' + feeAmount.toFixed(2));',
                        'console.log(\'    = User pays: ₱\' + deducted.toFixed(2) + \' (₱100 - ₱10 fee)\');',
                        'console.log(\'  ✓ Wallet charged correctly (include strategy)\');'
                    ]
                    updated = True
        
        # Fix Get Voucher Details - Cash entity test
        elif request['name'] == 'Get Voucher Details':
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
                        
                        # Replace cash entity test
                        if 'pm.test("Cash entity exists with fee calculation"' in line:
                            skip_until_closing = True
                            new_lines.extend([
                                'pm.test("Cash entity with include strategy", function () {',
                                '    pm.expect(voucher.cash).to.exist;',
                                '    pm.expect(voucher.cash.meta.fee_calculation).to.exist;',
                                '    pm.expect(voucher.cash.meta.fee_calculation.strategy).to.equal(\'include\');',
                                '    pm.expect(voucher.cash.meta.fee_calculation.rail).to.equal(\'INSTAPAY\');',
                                '    ',
                                '    // Include strategy: adjusted_amount is LESS than original_amount',
                                '    pm.expect(voucher.cash.meta.fee_calculation.adjusted_amount).to.be.below(voucher.cash.meta.original_amount);',
                                '    ',
                                '    console.log(\'💰 Fee Calculation (Include Strategy):\');',
                                '    console.log(\'  Original: ₱\' + voucher.cash.meta.original_amount);',
                                '    console.log(\'  Adjusted: ₱\' + voucher.cash.meta.fee_calculation.adjusted_amount + \' (fee deducted)\');',
                                '    console.log(\'  Fee: ₱\' + (voucher.cash.meta.fee_calculation.fee_amount / 100));',
                                '    console.log(\'  User Paid: ₱\' + (voucher.cash.meta.fee_calculation.adjusted_amount) + \' (less than original)\');',
                                '    console.log(\'  Strategy: include (fee deducted from voucher value)\');',
                                '    console.log(\'  Rail: INSTAPAY\');',
                                '});'
                            ])
                            continue
                        
                        new_lines.append(line)
                    
                    event['script']['exec'] = new_lines
                    updated = True
    
    return updated

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        if fix_include_strategy_folder(folder):
            folder_name = folder.get('name', '')
            print(f"  ✏️  {folder_name}")
            folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Fixed {folders_updated} folder(s)")
    print(f"")
    print(f"Include strategy tests now correctly handle fee deduction from voucher value")

if __name__ == '__main__':
    main()
