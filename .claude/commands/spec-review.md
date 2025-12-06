---
description: Analyze TOON spec changes and plan implementation updates
---

# TOON Specification Compliance Review

You are a **TOON Specification Compliance Analyst**. The upstream TOON specification has been updated. Analyze the changes and produce a structured implementation plan.

## Your Task

1. Analyze the spec diff to identify changes
2. Classify each change by impact
3. Map changes to implementation files
4. Produce a detailed implementation plan
5. Wait for approval before implementing

## Step 1: Get the Diff

Run:
```bash
git diff docs/SPEC.md
```

## Step 2: Classify Changes

For each change, assign a category:

| Category | Definition | Action |
|----------|------------|--------|
| **BREAKING** | New MUST/MUST NOT or semantic change | Code + tests + CHANGELOG |
| **ENHANCEMENT** | New SHOULD/MAY feature | Optional code + CHANGELOG |
| **CLARIFICATION** | Wording improvement, no behavior change | Documentation only |
| **INFORMATIVE** | Non-normative (examples, rationale) | No action |

## Step 3: Map to Code

| Spec Section | Files |
|--------------|-------|
| §2 Data Model | `src/Normalize.php`, `src/Primitives.php` |
| §3 Encoding Normalization | `src/Normalize.php` |
| §4 Decoding Interpretation | `src/Decoder/ValueParser.php` |
| §6 Header Syntax | `src/Decoder/HeaderParser.php`, `src/Encoders.php` |
| §7 Strings/Keys | `src/Primitives.php`, `src/Decoder/ValueParser.php` |
| §9 Arrays | `src/Encoders.php`, `src/Decoder/Parser.php` |
| §11 Delimiters | `src/Decoder/DelimiterParser.php` |
| §13 Conformance | All encoder/decoder files |
| §14 Strict Mode | `src/Decoder/StrictValidator.php` |

## Step 4: Output Format

Structure your response with these exact sections:

### 1. Diff Summary
| Section | Change | Classification |
|---------|--------|----------------|
| §X.Y | Brief description | BREAKING/ENHANCEMENT/CLARIFICATION/INFORMATIVE |

### 2. Impact Analysis
For each BREAKING or ENHANCEMENT:
- **Requirement**: Quote the spec text
- **Current Status**: Compliant / Non-compliant / Missing
- **Files**: List affected files

### 3. Implementation Plan
For each code change:
```
File: src/Example.php
Method: functionName()
Change: Description
Tests: List new test cases
```

### 4. Test Requirements
- Tests to add/update
- Round-trip verification needs

### 5. Documentation Updates
- CHANGELOG.md entry (Keep a Changelog format)
- SPEC-COMPLIANCE.md updates

### 6. Validation Checklist
```
[ ] just test - All tests pass
[ ] just analyse - PHPStan level 9 clean
[ ] Round-trip tests for all delimiters
[ ] CHANGELOG.md updated
```

## Rules

- **Spec is authoritative** - Match spec exactly
- **TDD** - Write tests before implementation
- **Minimal changes** - Only what spec requires
- **Plan mode** - Do not implement until approved

## Begin

Run `git diff docs/SPEC.md` now.
