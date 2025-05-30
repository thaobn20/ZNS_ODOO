#!/bin/bash

# Debug script to find any remaining zns_template_mapping_id references
echo "=== Searching for zns_template_mapping_id references ==="

# Search in all XML files
echo "1. Checking XML files:"
find bom_zns_simple/ -name "*.xml" -exec grep -l "zns_template_mapping_id" {} \;

# Search in all Python files  
echo "2. Checking Python files:"
find bom_zns_simple/ -name "*.py" -exec grep -l "zns_template_mapping_id" {} \;

# Search for specific field references in views
echo "3. Checking for field references in views:"
find bom_zns_simple/views/ -name "*.xml" -exec grep -n "zns_template_mapping_id" {} \;

# Search for any remaining mapping references
echo "4. Checking for template_mapping references:"
find bom_zns_simple/ -name "*.xml" -exec grep -n "template_mapping_id" {} \;

echo "=== Debug complete ==="

# Additional check - look for any view inheritance issues
echo "5. Checking view priorities and inheritance:"
find bom_zns_simple/views/ -name "*sale_order*.xml" -exec echo "File: {}" \; -exec grep -n "priority\|inherit_id" {} \;

echo "=== All checks complete ==="