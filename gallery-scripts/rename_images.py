#!/usr/bin/env python3
"""
Rename image files based on deepest folder names, handling duplicates with counters.
Recursively searches all subfolders. Renames ALL files with the folder prefix to ensure uniqueness.
Creates a mapping file.
"""

import os
import json
from collections import defaultdict

# Configuration
BASE_PATH = "/Users/elizabethdrew/Documents/Omnitone/Omnitone/voicebox-website"
IMAGE_EXTENSIONS = {'.jpg', '.jpeg', '.png', '.gif', '.webp'}

def get_folder_prefix(folder_path):
    """Get the deepest folder name to use as prefix (lowercase, no spaces)."""
    folder_name = os.path.basename(folder_path)
    # Replace spaces with underscores, convert to lowercase
    prefix = folder_name.lower().replace(' ', '_')
    return prefix

def walk_and_rename():
    """Recursively walk all subfolders and rename files."""
    mapping = {}
    rename_count = 0
    skipped_count = 0
    
    # Walk through all folders
    for root, dirs, files in os.walk(BASE_PATH):
        # Skip the base directory itself
        if root == BASE_PATH:
            continue
        
        prefix = get_folder_prefix(root)
        
        # Track counter for this prefix
        counter = 0
        
        for filename in sorted(os.listdir(root)):
            filepath = os.path.join(root, filename)
            
            # Skip directories and non-image files
            if not os.path.isfile(filepath) or os.path.splitext(filename)[1].lower() not in IMAGE_EXTENSIONS:
                continue
            
            counter += 1
            
            # Get extension
            name_part, ext = os.path.splitext(filename)
            
            # Create new name: prefix_001.jpg (zero-padded counter)
            new_filename = f"{prefix}_{counter:03d}{ext}"
            new_filepath = os.path.join(root, new_filename)
            
            # Avoid overwriting existing files
            collision_counter = counter
            while os.path.exists(new_filepath) and new_filename != filename:
                collision_counter += 1
                new_filename = f"{prefix}_{collision_counter:03d}{ext}"
                new_filepath = os.path.join(root, new_filename)
            
            # Only rename if name is different
            if new_filename != filename:
                try:
                    os.rename(filepath, new_filepath)
                    mapping[filename] = new_filename
                    print(f"✓ Renamed: {filename} → {new_filename}")
                    rename_count += 1
                except Exception as e:
                    print(f"✗ Error renaming {filename}: {e}")
            else:
                skipped_count += 1
    
    # Save mapping file
    mapping_file = os.path.join(BASE_PATH, "rename_mapping.json")
    with open(mapping_file, 'w') as f:
        json.dump(mapping, f, indent=2)
    
    print(f"\n{'='*60}")
    print(f"Renaming complete!")
    print(f"  Renamed: {rename_count} files")
    print(f"  Kept unchanged: {skipped_count} files")
    print(f"  Mapping saved to: {mapping_file}")
    print(f"{'='*60}")
    
    return mapping

if __name__ == "__main__":
    print("Starting image rename process (recursive, with folder prefixes)...\n")
    mapping = walk_and_rename()
