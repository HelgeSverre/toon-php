# Released 3.3 profile: known fixture differences

The default codec preserves release 3.2.1, commit `55ca6ba93846bde75eeb04dac70a99fe0bb435dd`. Its captured results differ from 29 of the 389 pinned official 3.3 fixture expectations (comparing decoded objects using PHP associative arrays). These are compatibility snapshots, not claims of full conformance. Optional key folding and path expansion have no public options in this package.

| Fixture | Upstream expectation | Options |
| --- | --- | --- |
| `decode/path-expansion.json#0` | expands dotted key to nested object in safe mode | `{"expandPaths": "safe"}` |
| `decode/path-expansion.json#1` | expands dotted key with inline array | `{"expandPaths": "safe"}` |
| `decode/path-expansion.json#2` | expands dotted key with tabular array | `{"expandPaths": "safe"}` |
| `decode/path-expansion.json#4` | expands and deep-merges preserving document-order insertion | `{"expandPaths": "safe"}` |
| `decode/path-expansion.json#5` | throws on expansion conflict (object vs primitive) when strict=true | `{"expandPaths": "safe", "strict": true}` |
| `decode/path-expansion.json#6` | throws on expansion conflict (object vs array) when strict=true | `{"expandPaths": "safe", "strict": true}` |
| `decode/path-expansion.json#7` | applies LWW when strict=false (primitive overwrites expanded object) | `{"expandPaths": "safe", "strict": false}` |
| `decode/path-expansion.json#8` | applies LWW when strict=false (expanded object overwrites primitive) | `{"expandPaths": "safe", "strict": false}` |
| `decode/path-expansion.json#9` | preserves quoted dotted key as literal when expandPaths=safe | `{"expandPaths": "safe"}` |
| `decode/path-expansion.json#11` | expands keys creating empty nested objects | `{"expandPaths": "safe"}` |
| `encode/arrays-nested.json#9` | encodes empty root-level array | `{}` |
| `encode/arrays-nested.json#10` | encodes complex nested structure | `{}` |
| `encode/arrays-objects.json#4` | uses list format for objects containing arrays of arrays | `{}` |
| `encode/arrays-objects.json#6` | uses list format for nested object arrays with mismatched keys | `{}` |
| `encode/arrays-objects.json#9` | encodes objects with empty arrays in list format | `{}` |
| `encode/arrays-objects.json#12` | places empty arrays on hyphen line when first | `{}` |
| `encode/arrays-objects.json#13` | encodes empty object list items as bare hyphen | `{}` |
| `encode/arrays-objects.json#16` | uses expanded list for arrays containing empty objects | `{}` |
| `encode/arrays-primitive.json#3` | encodes empty arrays | `{}` |
| `encode/arrays-primitive.json#5` | encodes empty string keys for empty arrays | `{}` |
| `encode/key-folding.json#0` | encodes folded chain to primitive (safe mode) | `{"keyFolding": "safe"}` |
| `encode/key-folding.json#1` | encodes folded chain with inline array | `{"keyFolding": "safe"}` |
| `encode/key-folding.json#2` | encodes folded chain with tabular array | `{"keyFolding": "safe"}` |
| `encode/key-folding.json#5` | encodes partial folding with flattenDepth=2 | `{"keyFolding": "safe", "flattenDepth": 2}` |
| `encode/key-folding.json#6` | encodes full chain with flattenDepth=Infinity (default) | `{"keyFolding": "safe"}` |
| `encode/key-folding.json#9` | encodes folded chain ending with empty object | `{"keyFolding": "safe"}` |
| `encode/key-folding.json#10` | stops folding at array boundary (not single-key object) | `{"keyFolding": "safe"}` |
| `encode/key-folding.json#11` | encodes folded chains preserving sibling field order | `{"keyFolding": "safe"}` |
| `encode/objects.json#27` | encodes empty nested object | `{}` |
