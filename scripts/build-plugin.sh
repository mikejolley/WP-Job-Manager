#!/usr/bin/env bash

# Step 1: Create a new folder called `build`
mkdir -p build/wp-job-manager

# Step 2: Copy the current folder to the build folder, excluding undesired directories
rsync -av --progress . build/wp-job-manager --exclude build --exclude node_modules --exclude vendor --exclude .git --exclude .github --exclude .psalm --exclude tests --exclude .husky --exclude docs

# Navigate to build directory
cd build/wp-job-manager

# Step 3: Build assets
npm run build:assets

# Step 4: Run composer install without development dependencies
composer install --no-dev

# Step 5: Zip the entire contents of the build folder into `wp-job-manager.zip` excluding the files from the exclude.lst file
# Navigate one level up, so the zip command includes the build directory content
cd ..
zip -r wp-job-manager.zip wp-job-manager -x@../scripts/exclude.lst

# Step 6: Remove the contents of the build folder except the new zip file
rm -rf wp-job-manager

echo "Build process complete. The wp-job-manager.zip file is ready in the /build folder."
