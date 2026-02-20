# AI Session Safety Protocol

This document defines mandatory safety checks for AI-assisted development sessions to prevent accidental data loss and maintain code integrity.

## Pre-Session Checklist

**Every AI session MUST start with these checks:**

### 1. Check Git Status
```bash
git status
```

**Expected:** `nothing to commit, working tree clean`

**If uncommitted changes exist:**
- ❌ **DO NOT** proceed with new work
- ✅ **ALERT** the user immediately
- ✅ **ASK** what to do with uncommitted changes:
  - **Commit** - If changes are complete
  - **Stash** - If changes are in-progress but need to save
  - **Discard** - If changes are unwanted/experimental

### 2. Verify Branch
```bash
git branch --show-current
```

**Expected:** Usually `main` at session start

### 3. Sync with Remote
```bash
git pull origin main
```

**Expected:** `Already up to date` or successful merge

### 4. Check for Duplicate/Unexpected Directories
```bash
# Example: Check for accidental duplicates
ls -la resources/js/components/ | grep -E "pwa/pwa|duplicates"
```

## During Session Workflow

### Feature Development
```bash
# 1. Create feature branch
git checkout -b feature/descriptive-name

# 2. Make changes with surgical precision
# ... edit files ...

# 3. Build and test
npm run build
# run relevant tests

# 4. Commit with descriptive message
git add <files>
git commit -m "feat: descriptive message

- Bullet point 1
- Bullet point 2

Co-Authored-By: Warp <agent@warp.dev>"

# 5. Push feature branch
git push -u origin feature/descriptive-name
```

### Merging to Main
```bash
# Only after user approval
git checkout main
git merge feature/descriptive-name
git push origin main
git branch -d feature/descriptive-name
git push origin --delete feature/descriptive-name
```

## Post-Session Checklist

**Before ending session:**

### 1. Verify Clean State
```bash
git status
```

**Expected:** `nothing to commit, working tree clean`

**If uncommitted changes:**
- ✅ **ALERT** user about uncommitted changes
- ✅ **ASK** if they want to commit, stash, or continue in next session

### 2. Confirm Current Branch
```bash
git branch --show-current
```

**Expected:** `main` (all feature branches should be deleted)

### 3. Document Active Work
If work is incomplete:
- Create/update TODO list
- Update implementation plan status
- Note what's in-progress in session summary

## Handling Multiple Sessions

### ⚠️ Warning Signs of Session Conflicts
- Uncommitted changes at session start
- Unexpected file modifications
- Duplicate directories (e.g., `pwa/pwa/`)
- Modified files across unrelated domains

### Recovery Procedure
```bash
# 1. Assess uncommitted changes
git status
git diff

# 2. If changes are valuable
git stash push -m "Session conflict - review needed"

# 3. If changes are unwanted
git reset --hard HEAD

# 4. Clean untracked files/directories
git clean -fd

# 5. Verify restoration
git status  # Should be clean
```

## Red Flags to Alert User About

**Immediately alert user if:**
1. ❌ Uncommitted changes exist at session start
2. ❌ Working on `main` branch directly (should use feature branches)
3. ❌ Duplicate directories detected
4. ❌ Modified files in unrelated areas (suggests session conflict)
5. ❌ Git reports diverged branches
6. ❌ Build failures after pulling latest changes

## Communication Protocol

### Session Start Template
```
🔍 **Pre-Session Safety Check**

✅ Git status: clean
✅ Current branch: main
✅ Synced with origin/main
✅ No unexpected directories

Ready to proceed with feature development.
```

### Session Start with Issues Template
```
⚠️ **Pre-Session Safety Alert**

❌ Uncommitted changes detected:
- Modified: file1.vue, file2.ts
- Untracked: directory/

**Action required:** What would you like to do?
1. Review and commit these changes
2. Stash for later (git stash push -m "...")
3. Discard changes (git reset --hard HEAD)

I cannot proceed with new work until this is resolved.
```

### Session End Template
```
✅ **Session Complete**

Final state:
- Current branch: main
- Git status: clean
- All feature branches: merged and deleted
- Build status: passing

Safe to start new session.
```

## Best Practices

### DO ✅
- Always create feature branches for new work
- Commit frequently with descriptive messages
- Build and test before committing
- Delete feature branches after merging
- Sync with remote before starting new work
- Alert user about any unexpected state

### DON'T ❌
- Never commit directly to `main` (except hotfixes with justification)
- Never proceed with uncommitted changes from another session
- Never assume uncommitted changes are intentional
- Never merge without user approval
- Never skip the pre-session safety check
- Never leave session with uncommitted changes without alerting user

## Example Session Flow

```bash
# ========================================
# SESSION START
# ========================================

# Safety check
git status                          # ✅ Clean
git branch --show-current           # ✅ main
git pull origin main                # ✅ Up to date

# ========================================
# FEATURE WORK
# ========================================

# Create branch
git checkout -b feature/new-ui-component

# Make changes, build, test
# ...

# Commit
git add resources/js/components/NewComponent.vue
git commit -m "feat: add new UI component"

# Push
git push -u origin feature/new-ui-component

# ========================================
# MERGE (after user approval)
# ========================================

git checkout main
git merge feature/new-ui-component
git push origin main
git branch -d feature/new-ui-component
git push origin --delete feature/new-ui-component

# ========================================
# SESSION END
# ========================================

# Final check
git status                          # ✅ Clean
git branch --show-current           # ✅ main

# Session complete ✅
```

## Recovery from 2026-02-20 Incident

**What happened:**
- Uncommitted changes from another session removed Rider card feature
- Created duplicate `pwa/pwa/` directory
- Modified multiple unrelated files
- Changes conflicted with recently merged work

**Recovery:**
```bash
git reset --hard HEAD               # Restored to last commit
rm -rf resources/js/components/pwa/pwa/  # Removed duplicate
git status                          # Verified clean
```

**Lesson learned:**
- Always run `git status` at session start
- Alert user immediately if uncommitted changes exist
- Never assume uncommitted changes are safe to overwrite

## Integration with WARP.md

This protocol complements the Git Workflow section in `WARP.md`:
- `WARP.md` defines the development workflow
- This document adds AI session safety checks
- Both must be followed for safe AI-assisted development

---

**Last Updated:** 2026-02-20  
**Incident Reference:** Rider card restoration (85eb3d44)
