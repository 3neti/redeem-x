# Form Flow Manager - TODO

## High Priority

### Configurable Step Ordering
**Status:** Planned  
**Priority:** High  
**Complexity:** Medium

**Problem:**
Currently, step order in YAML is fixed and determines data availability. For KYC auto-population to work, KYC must come before BIO. But users might want different orderings for different flows.

**Current Behavior:**
- Steps execute in YAML definition order
- Variables are named `$step{N}_{fieldname}` where N is the actual step index
- BIO step references `$step1_full_name` assuming KYC is step 1
- If user adds a step before KYC, the index changes and references break

**Proposed Solution:**

Option 1: Named Step References
```yaml
steps:
  kyc:
    handler: "kyc"
    name: "kyc_verification"  # Named identifier
    
  bio:
    handler: "form"
    depends_on: ["kyc_verification"]  # Dependency declaration
    config:
      variables:
        $name: "$kyc_verification.full_name"  # Reference by name, not index
```

Option 2: Priority-Based Ordering
```yaml
steps:
  kyc:
    handler: "kyc"
    priority: 10  # Higher priority = earlier in flow
    
  bio:
    handler: "form"
    priority: 20  # Runs after KYC (10 < 20)
    after: ["kyc"]  # Explicit dependency
```

Option 3: Semantic Variables
```yaml
steps:
  kyc:
    handler: "kyc"
    exports:  # Explicitly export variables
      kyc_name: "full_name"
      kyc_email: "email"
      kyc_birth: "birth_date"
      
  bio:
    handler: "form"
    imports: ["kyc"]  # Import from specific steps
    config:
      variables:
        $name: "$kyc.kyc_name"  # Semantic reference
```

**Benefits:**
- Order-independent variable references
- Clear dependency tracking
- Easier to customize flows
- More maintainable YAML configs
- Better error messages for missing dependencies

**Implementation Tasks:**
1. Add step metadata (name, priority, depends_on)
2. Build dependency graph before execution
3. Topological sort for execution order
4. Update variable resolution to use step names
5. Add validation for circular dependencies
6. Update documentation and examples
7. Migrate existing YAML configs

**Breaking Change:** Yes (requires YAML format update)  
**Mitigation:** Support both formats during transition period

---

## Medium Priority

### Auto-Discovery of KYC Handler
**Problem:** KYC handler package might not be installed  
**Solution:** Graceful fallback when handler not found

### Variable Template Syntax
**Problem:** Mix of `{{ }}` (YAML) and `$var` (FormHandler) is confusing  
**Solution:** Unify to single template syntax

### Conditional Step Groups
**Problem:** Can't conditionally enable multiple related steps  
**Solution:** Add step grouping with group-level conditions

---

## Low Priority

### Step Branching
**Idea:** Allow different paths based on previous answers  
**Example:** If age < 18, skip certain steps

### Async Step Handlers
**Idea:** Support long-running handlers (email verification, etc.)  
**Example:** Send code, wait for user to enter it

### Step Progress Indicators
**Idea:** Show "Step 2 of 5" in UI  
**Challenge:** Dynamic steps make count unpredictable

---

## Completed

✅ YAML driver implementation (v1.1.0)  
✅ Template processor with filters and conditionals  
✅ Auto-sync functionality  
✅ KYC auto-population via collected_data  

---

**Last Updated:** 2025-12-15  
**Contributors:** WARP AI Assistant
