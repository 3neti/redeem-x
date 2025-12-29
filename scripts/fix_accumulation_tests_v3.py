#!/usr/bin/env python3
"""
Fix Postman test assertions to be accumulation-tolerant (v3 - clean version).

This completely rewrites the test section to avoid duplicates and ensure
all tests work with accumulated data.
"""

import json
from pathlib import Path

def rebuild_system_balances_after_tests(folder):
    """Completely rebuild the test script with clean, accumulation-tolerant tests."""
    
    for request in folder.get('item', []):
        if request['name'] == 'Get System Balances (After)':
            for event in request.get('event', []):
                if event.get('listen') == 'test':
                    # Completely replace with new test script
                    event['script']['exec'] = [
                        'pm.test("Status is 200", function () {',
                        '    pm.response.to.have.status(200);',
                        '});',
                        '',
                        'const jsonData = pm.response.json();',
                        'const systemBefore = parseFloat(pm.collectionVariables.get(\'system_balance_before\'));',
                        'const productsBefore = parseFloat(pm.collectionVariables.get(\'products_balance_before\'));',
                        'const systemAfter = parseFloat(jsonData.data.system.balance);',
                        'const productsAfter = parseFloat(jsonData.data.totals.products);',
                        'const actualFee = parseFloat(pm.collectionVariables.get(\'actual_fee\'));',
                        '',
                        'const systemIncrease = systemAfter - systemBefore;',
                        'const productsIncrease = productsAfter - productsBefore;',
                        '',
                        '// Additional variables for tests',
                        'const voucherAmount = parseFloat(pm.collectionVariables.get(\'voucher_amount\'));',
                        'const voucherCount = parseInt(pm.collectionVariables.get(\'voucher_count\'));',
                        'const voucherTotal = voucherAmount * voucherCount;',
                        'const afterProducts = jsonData.data.products;',
                        '',
                        '// Comprehensive debugging',
                        'console.log(\'🔍 DEBUGGING Get System Balances (After):\');',
                        'console.log(\'  productsBefore:\', productsBefore);',
                        'console.log(\'  productsAfter:\', productsAfter);',
                        'console.log(\'  productsIncrease:\', productsIncrease);',
                        'console.log(\'  voucherTotal:\', voucherTotal);',
                        'console.log(\'  actualFee (from variable):\', actualFee);',
                        '',
                        'pm.test("System wallet unchanged (closed system)", function () {',
                        '    pm.expect(systemIncrease).to.be.closeTo(0, 0.01);',
                        '});',
                        '',
                        'pm.test("cash.amount product exists and has balance", function() {',
                        '    const cashProduct = afterProducts.find(p => p.index === \'cash.amount\');',
                        '    pm.expect(cashProduct, \'cash.amount product should exist\').to.exist;',
                        '    pm.expect(cashProduct.balance, \'cash.amount balance should be positive\').to.be.above(0);',
                        '});',
                        '',
                        'pm.test("Products balance increased by escrow + fees", function() {',
                        '    const expectedIncrease = voucherTotal + actualFee;',
                        '    pm.expect(productsIncrease, \'Products should increase by escrow + fees\').to.be.closeTo(expectedIncrease, 0.5);',
                        '});',
                        '',
                        'pm.test("No negative product balances", function () {',
                        '    jsonData.data.products.forEach(product => {',
                        '        pm.expect(product.balance).to.be.at.least(0);',
                        '    });',
                        '});',
                        '',
                        'pm.test("Totals recalculated correctly", function () {',
                        '    const productsSum = jsonData.data.products.reduce((sum, p) => sum + p.balance, 0);',
                        '    pm.expect(jsonData.data.totals.products).to.equal(productsSum);',
                        '});',
                        '',
                        '// Store final values',
                        'pm.collectionVariables.set(\'system_balance_after\', systemAfter);',
                        'pm.environment.set(\'system_balance_after\', systemAfter);',
                        'pm.collectionVariables.set(\'products_balance_after\', productsAfter);',
                        'pm.environment.set(\'products_balance_after\', productsAfter);',
                        '',
                        'console.log(\'🏛️ System Balances (After):\');',
                        'console.log(\'  System:\');',
                        'console.log(\'    Before: ₱\' + systemBefore.toFixed(2));',
                        'console.log(\'    After: ₱\' + systemAfter.toFixed(2));',
                        'console.log(\'    Change: ₱\' + systemIncrease.toFixed(2) + \' ✓\');',
                        'console.log(\'  Products:\');',
                        'console.log(\'    Before: ₱\' + productsBefore.toFixed(2));',
                        'console.log(\'    After: ₱\' + productsAfter.toFixed(2));',
                        'console.log(\'    Change: +₱\' + productsIncrease.toFixed(2) + \' ✓\');',
                        'console.log(\'  ---\');',
                        'console.log(\'  🔄 Money Flow:\');',
                        'console.log(\'    User → Products (escrow + fees): ₱\' + (voucherTotal + actualFee).toFixed(2));',
                        'console.log(\'    System unchanged: ₱\' + systemIncrease.toFixed(2));',
                        'console.log(\'  ✅ Balances verified\');'
                    ]
                    break

def main():
    collection_path = Path(__file__).parent.parent / 'docs' / 'postman' / 'redeem-x-e2e-generation-billing.postman_collection.json'
    
    print(f"📖 Reading collection: {collection_path}")
    with open(collection_path, 'r', encoding='utf-8') as f:
        collection = json.load(f)
    
    folders_updated = 0
    for folder in collection.get('item', []):
        folder_name = folder.get('name', '')
        if folder_name.startswith(('01 ', '02 ', '03 ', '04 ', '05 ', '06 ', '07 ', 
                                    '08 ', '09 ', '10 ', '11 ', '12 ', '13 ', '14 ',
                                    '15 ', '16 ', '17 ', '18 ', '19 ', '20 ', '21 ',
                                    '22 ', '23 ', '24 ', '25 ', '26 ', '27 ')):
            print(f"  ✏️  Rebuilding: {folder_name}")
            rebuild_system_balances_after_tests(folder)
            folders_updated += 1
    
    print(f"💾 Writing updated collection...")
    with open(collection_path, 'w', encoding='utf-8') as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
    
    print(f"✅ Rebuilt {folders_updated} folders")
    print(f"")
    print(f"Test logic (accumulation-tolerant):")
    print(f"  • System wallet unchanged (±0)")
    print(f"  • cash.amount product exists + positive balance")
    print(f"  • Products increased by ~voucherTotal (closeTo with 0.5 tolerance)")
    print(f"  • No negative balances")
    print(f"  • Totals calculated correctly")

if __name__ == '__main__':
    main()
