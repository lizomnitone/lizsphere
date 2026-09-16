#!/usr/bin/env python3
"""
Update categorization.json with renamed filenames
Uses the rename_mapping.json to update old filenames to new ones
"""

import os
import json

BASE_PATH = "/Users/elizabethdrew/Documents/Omnitone/Omnitone/voicebox-webite/gallery-scripts"

# Load the rename mapping
mapping_file = os.path.join(BASE_PATH, "rename_mapping.json")
with open(mapping_file, 'r') as f:
    rename_mapping = json.load(f)

# Load the categorization file
cat_file = os.path.join(BASE_PATH, "categorization.json")
with open(cat_file, 'r') as f:
    categorization = json.load(f)

print(f"Loaded {len(rename_mapping)} file renames")
print(f"Loaded categorization for {len(categorization)} folders")

# Update each folder's categorizations with new filenames
updated_count = 0
for folder, items in categorization.items():
    new_items = {}
    
    for old_filename, category in items.items():
        # Check if this file was renamed
        if old_filename in rename_mapping:
            new_filename = rename_mapping[old_filename]
            new_items[new_filename] = category
            updated_count += 1
            # print(f"  {folder}: {old_filename} → {new_filename}")
        else:
            # Keep unchanged
            new_items[old_filename] = category
    
    categorization[folder] = new_items

# Save updated categorization
with open(cat_file, 'w') as f:
    json.dump(categorization, f, indent=2, ensure_ascii=False)

print(f"\nDone! Updated {updated_count} filenames in categorization.json")
print(f"Saved to: {cat_file}")
